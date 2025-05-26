<?php

namespace CoderBeams\POTW\Cron;


class PotwAlert
{
    public static function runJob()
    {
        $watchRepo = \XF::app()->repository('CoderBeams\POTW:Watch');
//        $alertRepo = \XF::app()->repository('XF:Alert');

        // Fetch all watches
        $watches = $watchRepo->findAll();
        if (!$watches->count()) {
            return false; // No watches to process
        }
        foreach ($watches as $watch) {
            $timeLapse = $watch->time_lapse; // 'day' or 'week'
            // Check if there are any new posts since last alert
            if (($timeLapse == 'day')) {
                $alertRepo = \XF::app()->repository('XF:UserAlert');
                // Create the alert
                $template = 'potw_day';
                $title = \XF::phrase('cb_potw_day_alert');

                $extra = [
                    'title' => $title,
                    'link' => \XF::app()->router('public')->buildLink('potw'),
                ];

                $alertRepo->alert(
                    $watch->User, // User being alerted
                    $watch->user_id, // Sender (0 indicates system)
                    '', // Alert title/subject
                    'user', // Content type for Post of the Week watch alert
                    $watch->user_id, // Content ID (user)
                    $template, // Template to use
                    $extra, // Extra data for the template (e.g., link to POTW)
                    [] // Extra data (can be left empty)
                );
            }
        }

        return true;
    }

    public static function runJobWeekly()
    {
        $watchRepo = \XF::app()->repository('CoderBeams\POTW:Watch');
//        $alertRepo = \XF::app()->repository('XF:Alert');

        // Fetch all watches
        $watches = $watchRepo->findAll();
        if (!$watches->count()) {
            return false; // No watches to process
        }
        foreach ($watches as $watch) {
            $userId = $watch->user_id;
            $timeLapse = $watch->time_lapse; // 'day' or 'week'
            // Check if there are any new posts since last alert
            if (($timeLapse == 'week')) {
                $alertRepo = \XF::app()->repository('XF:UserAlert');
                // Create the alert
                $template = 'potw_week';
                $title = \XF::phrase('cb_potw_week_alert');

                $extra = [
                    'title' => $title,
                    'link' => \XF::app()->router('public')->buildLink('potw'),
                ];

                $alertRepo->alert(
                    $watch->User, // User being alerted
                    $watch->user_id, // Sender (0 indicates system)
                    '', // Alert title/subject
                    'user', // Content type for Post of the Week watch alert
                    $watch->user_id, // Content ID (user)
                    $template, // Template to use
                    $extra, // Extra data for the template (e.g., link to POTW)
                    [] // Extra data (can be left empty)
                );
            }
        }

        return true;
    }

}
