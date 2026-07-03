<?php

namespace CoderBeams\POTW\Api\Controller;

use XF\Api\Controller\AbstractController;
use XF\Mvc\ParameterBag;

class Potw extends AbstractController
{
    protected function preDispatchController($action, ParameterBag $params)
    {
        $this->assertApiScopeByRequestMethod('thread');
    }

    public function actionGet(ParameterBag $params)
    {
        $visitor = \XF::visitor();
        $options = $this->options();

        $page = $this->filterPage($params->page);
        $perPage = 20;

        $config = [
            'page' => $page,
            'perPage' => $perPage,
            'timeLapse' => $options->cb_time_lapse ?? 'week',
            'nodeIds' => $options->cb_potw_applicable_forum ?? [],
            'minimumReaction' => $options->cb_potw_reaction_limit ?? 1,
            'postsInWeeks' => $options->cb_limit_post_per_week ?? 3,
            'lastWeeks' => $options->cb_posts_weeks ?? 1,
        ];

        /** @var \CoderBeams\POTW\Service\Week $weekService */
        $weekService = $this->service('CoderBeams\POTW:Week');
        [$allPosts, $weekendArray] = $weekService->processWeeklyPosts($visitor, $config);

        $allPosts = array_slice($allPosts, ($page - 1) * $perPage, $perPage);

        return $this->apiResult(['post' => $allPosts]);
    }
}
