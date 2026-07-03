<?php

namespace CoderBeams\POTW\Cron;

class PotwAlert
{
    public static function runJob()
    {
        // Only alert if a qualifying post exists in the last 24 hours
        $end = \XF::$time;
        $start = $end - 86400;

        if (!self::hasQualifyingPosts($start, $end)) {
            return false;
        }

        return self::alertWatchers('day', 'potw_day', \XF::phrase('cb_potw_day_alert'));
    }

    public static function runJobWeekly()
    {
        // Only alert if a qualifying post exists in the week that just ended
        $weekService = new \CoderBeams\POTW\Service\Week(\XF::app());
        $range = $weekService->getWeekRange(-1, \XF::visitor());

        if (!self::hasQualifyingPosts($range['start'], $range['end'])) {
            return false;
        }

        return self::alertWatchers('week', 'potw_week', \XF::phrase('cb_potw_week_alert'));
    }

    /**
     * Same criteria as the POTW page, so watchers are only alerted when
     * the page will actually show something.
     */
    protected static function hasQualifyingPosts(int $start, int $end): bool
    {
        $app = \XF::app();
        $weekService = new \CoderBeams\POTW\Service\Week($app);

        $finder = $weekService->applyQualifyingCriteria(
            $app->finder('XF:Post'),
            $start,
            $end,
            $weekService->getQualifyingConfigFromOptions()
        );

        return $finder->total() > 0;
    }

    protected static function alertWatchers(string $timeLapse, string $template, \XF\Phrase $title): bool
    {
        $watchRepo = \XF::app()->repository('CoderBeams\POTW:Watch');
        $alertRepo = \XF::app()->repository('XF:UserAlert');

        $watches = $watchRepo->getWatchedUsers($timeLapse);
        if (!$watches->count()) {
            return false;
        }

        $extra = [
            'title' => $title->render(),
            'link' => \XF::app()->router('public')->buildLink('potw'),
        ];

        foreach ($watches as $watch) {
            if (!$watch->User) {
                continue;
            }

            $alertRepo->alert(
                $watch->User, // User being alerted
                $watch->user_id,
                '',
                'user', // Content type for Post of the Week watch alert
                $watch->user_id,
                $template,
                $extra
            );
        }

        return true;
    }
}
