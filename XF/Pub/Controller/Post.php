<?php

namespace CoderBeams\POTW\XF\Pub\Controller;

use XF\Mvc\ParameterBag;

class Post extends XFCP_Post
{
    public function actionPotwPromote(ParameterBag $params)
    {
        $post = $this->assertViewablePost($params->post_id);

        if (!$post->canPromoteToPotw($error)) {
            return $this->noPermission($error);
        }

        /** @var \CoderBeams\POTW\Repository\Promoted $promotedRepo */
        $promotedRepo = $this->repository('CoderBeams\POTW:Promoted');

        if ($this->isPost()) {
            if ($post->isPotwPromoted()) {
                $promotedRepo->demote($post->post_id);
            } else {
                $promotedRepo->promote($post, \XF::visitor());
            }

            return $this->redirect($this->getDynamicRedirect());
        }

        return $this->view(
            'CoderBeams\POTW:Post\PotwPromote',
            'cb_potw_promote_confirm',
            ['post' => $post]
        );
    }
}
