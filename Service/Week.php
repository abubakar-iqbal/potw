<?php

namespace CoderBeams\POTW\Service;

use XF\Entity\User;
use XF\Finder\Post as PostFinder;
use XF\Service\AbstractService;

class Week extends AbstractService
{
    const CACHE_TTL_WEEK = 3600; // weekly results barely change; re-check hourly

    public function __construct(\XF\App $app)
    {
        parent::__construct($app);
    }

    public function processWeeklyPosts(
        User $visitor,
        array $config
    ): array {
        if ($config['timeLapse'] !== 'week') {
            return [[], []];
        }

        // fill option-derived criteria the caller didn't set (e.g. excludeThreadIds)
        $config += $this->getQualifyingConfigFromOptions();

        $lastWeeks = max(1, (int)$config['lastWeeks']);

        $weeks = [];
        for ($i = 1; $i <= $lastWeeks; $i++) {
            $weeks[$i] = $this->getWeekRange(-$i, $visitor);
        }

        $idsByWeek = $this->getWeeklyPostIdsCached($weeks, $config);

        $allIds = [];
        foreach ($idsByWeek as $ids) {
            $allIds = array_merge($allIds, $ids);
        }
        if (!$allIds) {
            return [[], []];
        }

        // Single hydration query for all weeks, with the visitor's permission data
        $posts = $this->finder('XF:Post')
            ->with($this->getPostWithClauses($visitor))
            ->with('full')
            ->whereIds($allIds)
            ->fetch();

        $allPosts = [];
        $weekendArray = [];

        foreach ($idsByWeek as $weekIdentifier => $ids) {
            $weekPosts = [];
            foreach ($ids as $postId) {
                if (isset($posts[$postId])) {
                    $weekPosts[] = $posts[$postId];
                }
            }

            if ($weekPosts) {
                $weekendArray[$weekIdentifier] = array_column($weekPosts, 'post_id');
                $allPosts = array_merge($allPosts, $weekPosts);
            }
        }

        return [$allPosts, $weekendArray];
    }

    /**
     * Winning post IDs per week, newest week first, from the simple cache
     * when fresh. IDs are permission-independent (content visibility only);
     * the visitor's permission data is joined at hydration time.
     *
     * @return array<string, int[]> week identifier => post IDs
     */
    protected function getWeeklyPostIdsCached(array $weeks, array $config): array
    {
        $simpleCache = $this->app->simpleCache();

        // Day-level key: week boundaries carry a time-of-day component that
        // drifts every request and would otherwise never produce a cache hit
        $keyParts = [];
        foreach ($weeks as $range) {
            $keyParts[] = date('Y-m-d', $range['start']) . '/' . date('Y-m-d', $range['end']);
        }
        $cacheKey = md5(json_encode([
            $keyParts,
            $config['nodeIds'],
            $config['minimumReaction'],
            $config['postsInWeeks'],
            $config['excludeThreadIds'] ?? [],
        ]));

        // Multiple slots: visitors in different timezones produce different
        // week boundaries (and so different keys); a single slot would be
        // evicted on every alternation
        $cached = $simpleCache->getValue('CoderBeams/POTW', 'weeklyPostIds');
        if (!is_array($cached)) {
            $cached = [];
        }

        if (
            isset($cached[$cacheKey])
            && ($cached[$cacheKey]['expires'] ?? 0) > \XF::$time
        ) {
            return $cached[$cacheKey]['ids'];
        }

        $idsByWeek = $this->fetchWeeklyPostIds($weeks, $config);

        foreach ($cached as $key => $entry) {
            if (($entry['expires'] ?? 0) <= \XF::$time) {
                unset($cached[$key]);
            }
        }
        $cached[$cacheKey] = [
            'expires' => \XF::$time + self::CACHE_TTL_WEEK,
            'ids' => $idsByWeek,
        ];
        if (count($cached) > 5) {
            $cached = array_slice($cached, -5, null, true);
        }

        $simpleCache->setValue('CoderBeams/POTW', 'weeklyPostIds', $cached);

        return $idsByWeek;
    }

    /**
     * The single definition of what makes a post eligible for POTW:
     * visible content, minimum reaction score, date window, forum filter.
     * Used by the page, the widget, the alert cron and winner recording.
     * fetchWeeklyPostIdsOptimized() mirrors these rules in raw SQL - keep
     * both in sync when eligibility changes.
     */
    public function applyQualifyingCriteria(
        PostFinder $finder,
        int $start,
        int $end,
        array $config
    ): PostFinder {
        $finder
            ->where('Thread.discussion_state', 'visible')
            ->where('message_state', 'visible')
            ->where('reaction_score', '>=', $config['minimumReaction'])
            ->where('post_date', '>=', $start)
            ->where('post_date', '<=', $end);

        $nodeIds = $config['nodeIds'];
        if (!empty($nodeIds) && !in_array(0, $nodeIds)) {
            $finder->where('Thread.node_id', $nodeIds);
        }

        $excludeThreadIds = $config['excludeThreadIds'] ?? [];
        if (!empty($excludeThreadIds)) {
            $finder->where('thread_id', '!=', $excludeThreadIds);
        }

        return $finder;
    }

    /**
     * Standard config shape for the qualifying criteria, from the addon options
     */
    public function getQualifyingConfigFromOptions(): array
    {
        $options = $this->app->options();

        return [
            'minimumReaction' => $options->cb_potw_reaction_limit ?? 1,
            'nodeIds' => $options->cb_potw_applicable_forum ?? [],
            'excludeThreadIds' => $this->getExcludedThreadIds(),
        ];
    }

    /**
     * Thread IDs excluded from POTW, parsed from the comma-separated option
     *
     * @return int[]
     */
    public function getExcludedThreadIds(): array
    {
        $raw = (string)($this->app->options()->cb_potw_exclude_thread_ids ?? '');
        if ($raw === '') {
            return [];
        }

        $ids = [];
        foreach (preg_split('/[\s,]+/', $raw, -1, PREG_SPLIT_NO_EMPTY) as $part) {
            $id = (int)$part;
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_unique($ids);
    }

    /**
     * @return array<string, int[]>
     */
    protected function fetchWeeklyPostIds(array $weeks, array $config): array
    {
        try {
            return $this->fetchWeeklyPostIdsOptimized($weeks, $config);
        } catch (\XF\Db\Exception $e) {
            // window functions unavailable (e.g. MySQL 5.7) - one query per week
            return $this->fetchWeeklyPostIdsLegacy($weeks, $config);
        }
    }

    /**
     * Top posts for every week in one query, bucketed by 7-day windows from
     * the oldest week's start. Each window only counts its first 6 days to
     * mirror the Sunday-Saturday ranges produced by getWeekRange().
     *
     * @return array<string, int[]>
     */
    protected function fetchWeeklyPostIdsOptimized(array $weeks, array $config): array
    {
        $db = $this->db();
        $lastWeeks = count($weeks);

        $anchor = $weeks[$lastWeeks]['start']; // oldest week
        $newestEnd = $weeks[1]['end'];

        $nodeIds = $config['nodeIds'];
        $nodeCondition = '';
        if (!empty($nodeIds) && !in_array(0, $nodeIds)) {
            $nodeCondition = 'AND thread.node_id IN (' . $db->quote(array_map('intval', $nodeIds)) . ')';
        }

        $excludeThreadIds = $config['excludeThreadIds'] ?? [];
        if (!empty($excludeThreadIds)) {
            $nodeCondition .= ' AND post.thread_id NOT IN ('
                . $db->quote(array_map('intval', $excludeThreadIds)) . ')';
        }

        $results = $db->fetchAll("
            SELECT post_id, bucket
            FROM (
                SELECT post.post_id,
                    FLOOR((post.post_date - ?) / 604800) AS bucket,
                    ROW_NUMBER() OVER (
                        PARTITION BY FLOOR((post.post_date - ?) / 604800)
                        ORDER BY post.reaction_score DESC, post.post_date ASC
                    ) AS rn
                FROM xf_post AS post
                INNER JOIN xf_thread AS thread ON (thread.thread_id = post.thread_id)
                WHERE post.post_date >= ?
                    AND post.post_date <= ?
                    AND MOD(post.post_date - ?, 604800) <= 518400
                    AND post.message_state = 'visible'
                    AND thread.discussion_state = 'visible'
                    AND post.reaction_score >= ?
                    {$nodeCondition}
            ) AS ranked
            WHERE rn <= ?
            ORDER BY bucket DESC, rn ASC
        ", [
            $anchor, $anchor,
            $anchor, $newestEnd, $anchor,
            $config['minimumReaction'],
            $config['postsInWeeks'],
        ]);

        // bucket 0 = oldest week; newest week first in the returned map
        $idsByWeek = [];
        for ($i = 1; $i <= $lastWeeks; $i++) {
            $idsByWeek[$this->getRangeIdentifier($weeks[$i])] = [];
        }
        foreach ($results as $row) {
            $weekIndex = $lastWeeks - (int)$row['bucket'];
            if (isset($weeks[$weekIndex])) {
                $idsByWeek[$this->getRangeIdentifier($weeks[$weekIndex])][] = (int)$row['post_id'];
            }
        }

        return array_filter($idsByWeek);
    }

    /**
     * @return array<string, int[]>
     */
    protected function fetchWeeklyPostIdsLegacy(array $weeks, array $config): array
    {
        $idsByWeek = [];

        foreach ($weeks as $range) {
            $finder = $this->applyQualifyingCriteria(
                $this->finder('XF:Post'),
                $range['start'],
                $range['end'],
                $config
            )
                ->order('reaction_score', 'DESC')
                ->order('post_date', 'ASC')
                ->limit($config['postsInWeeks']);

            $ids = [];
            foreach ($finder->fetchColumns('post_id') as $row) {
                $ids[] = (int)$row['post_id'];
            }
            if ($ids) {
                $idsByWeek[$this->getRangeIdentifier($range)] = $ids;
            }
        }

        return $idsByWeek;
    }

    public function getWeekRange(int $weekOffset, User $user): array
    {
        $dt = new \DateTime("now", new \DateTimeZone($user->timezone));
        $dt->setISODate($dt->format('o'), $dt->format('W') + $weekOffset);
        $lastSunday = strtotime("-1 day", $dt->getTimestamp());

        return [
            'start' => $lastSunday,
            'end' => $dt->modify('+5 days')->getTimestamp(),
        ];
    }

    protected function getPostWithClauses(User $visitor): array
    {
        return [
            'Thread.Forum.Node.Permissions|'.$visitor->permission_combination_id,
            'User',
        ];
    }

    protected function getRangeIdentifier(array $range): string
    {
        return (string)$range['end'];
    }

    public function getWeekIdentifier(int $weekOffset, User $user): string
    {
        return $this->getRangeIdentifier($this->getWeekRange($weekOffset, $user));
    }

    public function processDailyPosts(\XF\Entity\User $visitor, array $config): array
    {
        // fill option-derived criteria the caller didn't set (e.g. excludeThreadIds)
        $config += $this->getQualifyingConfigFromOptions();

        // Rolling 24-hour window, matching the daily alert cron's check -
        // a calendar-day window is empty right after midnight, exactly when
        // the cron-alerted users arrive
        $dayEnd = \XF::$time;
        $dayStart = $dayEnd - 86400;

        $postFinder = $this->applyQualifyingCriteria(
            $this->finder('XF:Post'),
            $dayStart,
            $dayEnd,
            $config
        )
            ->with($this->getPostWithClauses($visitor))
            ->with('full')
            ->setDefaultOrder('reaction_score', 'DESC')
            ->limit($config['perPage']);

        $dayPosts = [];
        foreach ($postFinder->fetch() as $post) {
            $dayPosts[] = $post;
        }

        return [
            'posts' => $dayPosts,
            'weekendArray' => [],
        ];
    }
}
