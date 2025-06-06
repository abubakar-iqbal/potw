<?php

namespace CoderBeams\POTW\Service;

use XF\Entity\User;
use XF\Finder\Post as PostFinder;
use XF\Service\AbstractService;

class Week extends AbstractService
{
    public function __construct(\XF\App $app)
    {
        parent::__construct($app);
    }

    public function processWeeklyPosts(
        User $visitor,
        array $config
    ): array {
        $allPosts = [];
        $weekendArray = [];

        if ($config['timeLapse'] !== 'week') {
            return [[], []];
        }

        for ($i = 1; $i <= $config['lastWeeks']; $i++) {
            $weekPosts = $this->getPostsForWeek(
                $visitor,
                -$i,
                $config['minimumReaction'],
                $config['postsInWeeks'],
                $config['nodeIds']
            );

            if (!empty($weekPosts)) {
                $weekIdentifier = $this->getWeekIdentifier(-$i, $visitor);

                // Collect all post IDs for this week
                $weekendArray[$weekIdentifier] = array_column($weekPosts, 'post_id');
                // Merge posts for final display
                $allPosts = array_merge($allPosts, $weekPosts);
            }
        }

        return [$allPosts, $weekendArray];
    }

    protected function getPostsForWeek(
        User $visitor,
        int $weekOffset,
        int $minimumReaction,
        int $postsLimit,
        array $nodeIds
    ): array {
        $weekRange = $this->getWeekRange($weekOffset, $visitor);

        $postFinder = $this->app->finder('XF:Post');
        $postFinder
            ->with($this->getPostWithClauses($visitor))
            ->where('Thread.discussion_state', 'visible')
            ->where('message_state', 'visible')
            ->where('reaction_score', '>=', $minimumReaction)
            ->where('post_date', '>=', $weekRange['start'])
            ->where('post_date', '<=', $weekRange['end'])
            ->setDefaultOrder('reaction_score', 'DESC')
            ->limit($postsLimit);

        if (!empty($nodeIds) && !in_array(0, $nodeIds)) {
            $postFinder->where('Thread.Forum.Node.node_id', $nodeIds);
        }

        $posts = $this->withFullViews($postFinder)->fetch();

        return $posts ? $posts->toArray() : [];
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

    protected function withFullViews(PostFinder $finder): PostFinder
    {
        return $finder->with('full');
    }

    public function getWeekIdentifier(int $weekOffset, User $user): string
    {
        $range = $this->getWeekRange($weekOffset, $user);

        return (string)$range['end'];
    }

    public function processDailyPosts(\XF\Entity\User $visitor, array $config): array
    {
        $dayPosts = [];
        $weekendArray = [];

        // Get the start and end of the day (24 hours)
        $dayStart = strtotime('today 00:00:00');
        $dayEnd = time(); // Current time (end of the day)

        // Fetch posts made within the last 24 hours
        $postFinder = $this->finder('XF:Post');
        $postFinder
            ->with('User')
            ->where('post_date', '>=', $dayStart)
            ->where('post_date', '<=', $dayEnd)
            ->where('reaction_score', '>=', $config['minimumReaction'])
            ->setDefaultOrder('reaction_score', 'DESC')
            ->limit($config['perPage']);

        $nodeIds = $config['nodeIds'];
        if (!empty($nodeIds) && !in_array(0, $nodeIds)) {
            $postFinder->where('Thread.Forum.Node.node_id', $nodeIds);
        }
        $posts = $postFinder->fetch();

        // If no posts were found, return empty arrays like processWeeklyPosts
        if ($posts->count() === 0) {
            return [
                'posts' => [],
                'weekendArray' => $weekendArray,
            ];
        }

        // Process posts for display
        foreach ($posts as $post) {
            $dayPosts[] = $post; // Add the post to the list of posts for the day
        }

        return [
            'posts' => $dayPosts,
            'weekendArray' => $weekendArray,
        ];
    }

}
