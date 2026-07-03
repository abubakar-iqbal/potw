<?php

namespace CoderBeams\POTW\Repository;

use XF\Mvc\Entity\Repository;

class Winner extends Repository
{
    /**
     * Determine the top post of the week that just ended and record it as a win.
     * Safe to call repeatedly: only one winner is recorded per ISO week.
     *
     * @return \CoderBeams\POTW\Entity\Winner|null
     */
    public function recordWeeklyWinner()
    {
        $weekService = new \CoderBeams\POTW\Service\Week($this->app());
        $visitor = \XF::visitor();
        $range = $weekService->getWeekRange(-1, $visitor);

        // Identify the completed ISO week in the same timezone the range was
        // computed in; rendering the timestamp in UTC (server tz) can flip
        // the week number near boundaries and double-record a winner
        $dt = new \DateTime('now', new \DateTimeZone($visitor->timezone));
        $dt->setISODate((int)$dt->format('o'), (int)$dt->format('W') - 1);
        $period = $dt->format('o-W');

        $existing = $this->finder('CoderBeams\POTW:Winner')
            ->where('time_lapse', 'week')
            ->where('period', $period)
            ->fetchOne();
        if ($existing) {
            return $existing;
        }

        $post = $this->getTopPostForRange($range['start'], $range['end']);
        if (!$post || !$post->user_id) {
            return null; // no qualifying post, or a guest post
        }

        $winner = $this->em->create('CoderBeams\POTW:Winner');
        $winner->user_id = $post->user_id;
        $winner->post_id = $post->post_id;
        $winner->time_lapse = 'week';
        $winner->period = $period;
        $winner->won_date = \XF::$time;
        $winner->save();

        $this->rebuildUserPotwCount($post->user_id);

        return $winner;
    }

    /**
     * @return \XF\Entity\Post|null
     */
    protected function getTopPostForRange(int $start, int $end)
    {
        $weekService = new \CoderBeams\POTW\Service\Week($this->app());

        $postFinder = $weekService->applyQualifyingCriteria(
            $this->finder('XF:Post'),
            $start,
            $end,
            $weekService->getQualifyingConfigFromOptions()
        )
            ->order('reaction_score', 'DESC')
            ->order('post_date', 'ASC'); // ties go to the earlier post

        return $postFinder->fetchOne();
    }

    /**
     * Recalculate the cached win counter on the user record
     */
    public function rebuildUserPotwCount(int $userId): int
    {
        $count = $this->finder('CoderBeams\POTW:Winner')
            ->where('user_id', $userId)
            ->total();

        $this->db()->update('xf_user', ['cb_potw_count' => $count], 'user_id = ?', $userId);

        return $count;
    }
}
