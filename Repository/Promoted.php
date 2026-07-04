<?php

namespace CoderBeams\POTW\Repository;

use XF\Mvc\Entity\Repository;

class Promoted extends Repository
{
    /**
     * Promote a post to the POTW page until the end of the current week.
     * Re-promoting an expired promotion refreshes its window.
     *
     * @return \CoderBeams\POTW\Entity\Promoted
     */
    public function promote(\XF\Entity\Post $post, \XF\Entity\User $byUser)
    {
        $promoted = $this->em->find('CoderBeams\POTW:Promoted', $post->post_id);
        if (!$promoted) {
            $promoted = $this->em->create('CoderBeams\POTW:Promoted');
            $promoted->post_id = $post->post_id;
        }

        $promoted->promoted_by = $byUser->user_id;
        $promoted->promote_date = \XF::$time;
        $promoted->expiry_date = $this->getCurrentWeekExpiry($byUser);
        $promoted->save();

        return $promoted;
    }

    /**
     * End of the current ISO week: next Monday 00:00 in the promoter's
     * timezone, so a promotion always survives the rest of the week
     */
    protected function getCurrentWeekExpiry(\XF\Entity\User $user): int
    {
        $dt = new \DateTime('now', new \DateTimeZone($user->timezone));
        $dt->setISODate((int)$dt->format('o'), (int)$dt->format('W') + 1);
        $dt->setTime(0, 0, 0);

        return $dt->getTimestamp();
    }

    public function demote(int $postId)
    {
        $promoted = $this->em->find('CoderBeams\POTW:Promoted', $postId);
        if ($promoted) {
            $promoted->delete();
        }
    }

    /**
     * Currently promoted posts the visitor may see, newest promotion first
     *
     * @return \XF\Entity\Post[]
     */
    public function getActivePromotedPosts(\XF\Entity\User $visitor): array
    {
        $promotions = $this->finder('CoderBeams\POTW:Promoted')
            ->where('expiry_date', '>', \XF::$time)
            ->order('promote_date', 'DESC')
            ->fetch();

        if (!$promotions->count()) {
            return [];
        }

        $postIds = $promotions->pluckNamed('post_id');

        $posts = $this->finder('XF:Post')
            ->whereIds($postIds)
            ->with([
                'Thread.Forum.Node.Permissions|' . $visitor->permission_combination_id,
                'User',
            ])
            ->with('full')
            ->where('message_state', 'visible')
            ->where('Thread.discussion_state', 'visible')
            ->fetch();

        $result = [];
        foreach ($postIds as $postId) {
            if (isset($posts[$postId])) {
                $result[] = $posts[$postId];
            }
        }

        return $result;
    }
}
