<?php

namespace CoderBeams\POTW\Cron;

class PotwWinner
{
    /**
     * Record the winner of the week that just ended. Separate from the
     * alert cron so disabling alerts does not stop win tracking.
     */
    public static function recordWeekly()
    {
        \XF::app()->repository('CoderBeams\POTW:Winner')->recordWeeklyWinner();

        return true;
    }
}
