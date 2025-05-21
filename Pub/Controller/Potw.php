<?php

namespace CoderBeams\POTW\Pub\Controller;

use XF\Mvc\ParameterBag;
use XF\Pub\Controller\AbstractController;

class Potw extends AbstractController
{
    public function actionIndex(ParameterBag $params)
    {
        $visitor = \XF::visitor();
        $options = $this->options();

        // Get the config from the options
        $config = $this->getConfigFromOptions($options, $params);

        // Determine timeLapse based on the selected time period
        $timeLapse = $options->cb_time_lapse ?? 'week';  // Default to 'week' if not set
        if (isset($params->timeLapse) && $params->timeLapse === 'day') {
            $timeLapse = 'day';  // Set to 'day' if selected
        }

        $weekService = new \CoderBeams\POTW\Service\Week($this->app);

        // Handle the timeLapse logic
        if ($timeLapse == 'day') {
            // Post of the Day - Fetch data for the last day
            $dayPosts = $weekService->processDailyPosts($visitor, $config);
            $allPosts = $dayPosts['posts']; // The fetched posts for the day
            $weekendArray = $dayPosts['weekendArray']; // Any additional metadata you need
        } else {
            // Post of the Week - Fetch data for the last week
            list($allPosts, $weekendArray) = $weekService->processWeeklyPosts($visitor, $config);
        }

        // Check if the visitor has already watched the posts for the selected timeLapse ('day' or 'week')
        $hasAlreadyWatched = $this->repository('CoderBeams\POTW:Watch')->hasWatchedPOTW($visitor->user_id, $timeLapse);

        // Return the response with the necessary data, including the 'hasAlreadyWatched' parameter
        return $this->buildViewResponse(
            $allPosts,
            $weekendArray,
            $config,
            $timeLapse,
            $hasAlreadyWatched
        );
    }


    protected function getConfigFromOptions($options, ParameterBag $params): array
    {
        return [
            'page' => $this->filterPage($params->page),
            'perPage' => $options->cb_potw_per_page,
            'timeLapse' => $options->cb_time_lapse, // Admin-selected time frame (day/week)
            'nodeIds' => $options->cb_potw_applicable_forum,
            'minimumReaction' => $options->cb_potw_reaction_limit,
            'postsInWeeks' => $options->cb_limit_post_per_week,
            'lastWeeks' => $options->cb_posts_weeks,
        ];
    }

    protected function buildViewResponse(
        array $allPosts,
        array $weekendArray,
        array $config,
        $timeLapse,
        $hasAlreadyWatch
    ) {
        // Paginate the posts
        $paginatedPosts = array_slice(
            $allPosts,
            ($config['page'] - 1) * $config['perPage'],
            $config['perPage']
        );

        // Prepare view parameters
        $viewParams = [
            'allPosts' => $paginatedPosts,
            'perPage' => $config['perPage'],
            'page' => $config['page'],
            'timeLapse' => $timeLapse,
            'total' => count($allPosts),
            'weekendArray' => $weekendArray,
            'hasAlreadyWatch' => $hasAlreadyWatch,
            'showExpandedTitle' => '',
        ];

        // Return the view
        return $this->view('', 'cb_potw_index', $viewParams);
    }

    public function actionWatchConfirm()
    {
        $visitor = \XF::visitor();
        $timeLapse = $this->filter('timeLapse', 'str');

        // Ensure the timeLapse is valid
        if (!in_array($timeLapse, ['day', 'week'])) {
            return $this->error(\XF::phrase('invalid_time_lapse'));
        }

        // Fetch watch preference (already watched)
        $potwWatchRepo = $this->repository('CoderBeams\POTW:Watch');

        $isWatched = $potwWatchRepo->getWatchByUser($visitor->user_id, $timeLapse);
//        if ($existingWatch) {
//            return $this->error(\XF::phrase('you_are_already_watching_this'));
//        }

        // Display confirmation overlay
        $viewParams = [
            'timeLapse' => $timeLapse,
            'isWatched' => $isWatched,
        ];

        return $this->view('CoderBeams\POTW:WatchConfirm', 'potw_watch_confirm', $viewParams);
    }

    public function actionWatch(ParameterBag $params)
    {
        $visitor = \XF::visitor();
        $timeLapse = $this->filter('time_lapse', 'str');


        // Validate time lapse
        if (!in_array($timeLapse, ['day', 'week'])) {
            return $this->error(\XF::phrase('invalid_time_lapse'));
        }
        // If 'stop' is passed, unwatch the POTW
        if ($this->filter('stop', 'bool')) {
            // Handle unwatch logic
            $this->repository('CoderBeams\POTW:Watch')->removeWatch($visitor->user_id, $timeLapse);

            return $this->redirect($this->buildLink('potw'));  // Redirect to POTW page
        }
        // Initialize the Watch repository
        $potwWatchRepo = $this->repository('CoderBeams\POTW:Watch');

        // Check if the user is already watching the posts
        $existingWatch = $potwWatchRepo->getWatchByUser($visitor->user_id, $timeLapse);
        if ($existingWatch) {
            return $this->error(
                \XF::phrase('you_are_already_watching_this')
            );  // If user is already watching, show an error
        }

        // Create a new Watch entity to store the user's preference
        $watchEntity = $this->em()->create('CoderBeams\POTW:Watch');
        $watchEntity->user_id = $visitor->user_id;
        $watchEntity->time_lapse = $timeLapse;
        $watchEntity->watch_date = time();  // Store the current timestamp when the user opts to watch

        // Save the entity
        $watchEntity->save();
//
//        // Correct way to trigger an alert
//        /** @var \XF\Repository\Alert $alertRepo */
//        $alertRepo = $this->repository('XF:Alert');
//
//        $alert = $alertRepo->createAlert(
//            $visitor->user_id,           // The user who is watching
//            $visitor->user_id,           // The recipient of the alert (same user in this case)
//            'potw_watch',                // The custom content type for Post of the Week Watch
//            [
//                'time_lapse' => $timeLapse, // 'day' or 'week'
//                'watch_date' => time(),    // Timestamp when the user opted to watch
//            ]
//        );
//
//        $alert->send();  // Send the alert to the user

        // Redirect the user back to the page (or wherever appropriate)
        return $this->redirect($this->buildLink('potw'));
    }


}
