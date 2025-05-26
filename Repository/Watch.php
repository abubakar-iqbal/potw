<?php

namespace CoderBeams\POTW\Repository;

use XF\Mvc\Entity\Repository;

class Watch extends Repository
{
    /**
     * Add a user to the watch list for a given time lapse (day/week)
     *
     * @param \XF\Entity\User $user
     * @param string $timeLapse
     * @return void
     */
    public function addWatch(\XF\Entity\User $user, $timeLapse)
    {
        // Check if user is already watching
        $existingWatch = $this->getWatchByUser($user->user_id, $timeLapse);
        if ($existingWatch) {
            return; // User is already watching
        }

        // Create a new Watch entity
        $watch = $this->em()->create('CoderBeams\POTW:Watch');
        $watch->user_id = $user->user_id;
        $watch->time_lapse = $timeLapse;
        $watch->watch_date = time(); // Current timestamp
        $watch->save();
    }

    /**
     * Check if a user is already watching a given time lapse
     *
     * @param int $userId
     * @param string $timeLapse
     * @return \CoderBeams\POTW\Entity\Watch|null
     */
    public function getWatchByUser($userId, $timeLapse)
    {
        return $this->finder('CoderBeams\POTW:Watch')
            ->where('user_id', $userId)
            ->where('time_lapse', $timeLapse)
            ->fetchOne();
    }

    /**
     * Remove a user from the watch list for a given time lapse
     *
     * @param int $userId
     * @param string $timeLapse
     * @return void
     */
    public function removeWatch($userId, $timeLapse)
    {
        // Find the watch record for the user
        $watch = $this->finder('CoderBeams\POTW:Watch')
            ->where('user_id', $userId)
            ->where('time_lapse', $timeLapse)
            ->fetchOne();

        if ($watch) {
            // Delete the watch record
            $watch->delete();
        }
    }

    /**
     * Get all users watching the posts for a specific time lapse
     *
     * @param string $timeLapse
     * @return \XF\Mvc\Entity\ArrayCollection
     */
    public function getWatchedUsers($timeLapse)
    {
        return $this->finder('CoderBeams\POTW:Watch')
            ->where('time_lapse', $timeLapse)
            ->with('User')
            ->fetch();
    }

    public function findAll()
    {
        return $this->finder('CoderBeams\POTW:Watch')->with('User')->fetch();
    }

    public function hasWatchedPOTW($userId, $timeLapse)
    {
        $watch = $this->finder('CoderBeams\POTW:Watch')
            ->where('user_id', $userId)
            ->where('time_lapse', $timeLapse)
            ->fetchOne();

        return $watch ? true : false;  // Returns true if the user has watched, false otherwise
    }
}
