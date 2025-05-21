<?php

namespace CoderBeams\POTW\Cron;


class PotwAlert extends AbstractJob
{
    public function runJob()
    {
        $watchRepo = \XF::repository('CoderBeams\POTW:Watch');
        $alertRepo = \XF::repository('XF:Alert');

        // Fetch all watches
        $watches = $watchRepo->findAll();

        foreach ($watches as $watch) {
            $userId = $watch->user_id;
            $timeLapse = $watch->time_lapse; // 'day' or 'week'

            // Check when last alerted to avoid spamming
            $lastAlert = $watch->last_alert_date ?? 0;

            // Check if there are any new posts since last alert
            if ($this->hasNewPosts($timeLapse, $lastAlert)) {
                // Send alert to user (simple notification)
                $alertRepo->alert(
                    $userId,
                    0, // sender system user
                    'potw_watch',
                    [
                        'time_lapse' => $timeLapse,
                        'alert_date' => time(),
                    ]
                );

                // Update last_alert_date to now
                $watch->last_alert_date = time();
                $watch->save();
            }
        }

        return true;
    }

    protected function hasNewPosts(string $timeLapse, int $lastAlert): bool
    {
        $weekService = new \CoderBeams\POTW\Service\Week(\XF::app());

        $config = [
            'minimumReaction' => 1,  // Adjust per your options
            'perPage' => 10,
            'nodeIds' => [],  // or your specific forums
            'postsInWeeks' => 3,
            'lastWeeks' => 1,
        ];

        if ($timeLapse == 'day') {
            $postsData = $weekService->processDailyPosts(\XF::visitor(), $config);
            $posts = $postsData['posts'];
        } else {
            list($posts,) = $weekService->processWeeklyPosts(\XF::visitor(), $config);
        }

        foreach ($posts as $post) {
            if ($post['post_date'] > $lastAlert) {
                return true;  // Found a new post since last alert
            }
        }

        return false;  // No new posts since last alert
    }
}
