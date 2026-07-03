<?php

namespace CoderBeams\POTW\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

class Winner extends Entity
{
    public static function getStructure(Structure $structure)
    {
        $structure->table = 'xf_cb_potw_winner';
        $structure->shortName = 'CoderBeams\POTW:Winner';
        $structure->primaryKey = 'potw_winner_id';
        $structure->columns = [
            'potw_winner_id' => ['type' => self::UINT, 'autoIncrement' => true],
            'user_id' => ['type' => self::UINT, 'required' => true],
            'post_id' => ['type' => self::UINT, 'required' => true],
            'time_lapse' => ['type' => self::STR, 'maxLength' => 4, 'default' => 'week'],
            'period' => ['type' => self::STR, 'maxLength' => 10, 'required' => true],  // ISO year-week, e.g. 2026-26
            'won_date' => ['type' => self::UINT, 'default' => \XF::$time],
        ];
        $structure->relations = [
            'User' => [
                'entity' => 'XF:User',
                'type' => self::TO_ONE,
                'conditions' => 'user_id',
                'primary' => true,
                'api' => true,
            ],
            'Post' => [
                'entity' => 'XF:Post',
                'type' => self::TO_ONE,
                'conditions' => 'post_id',
                'primary' => true,
            ],
        ];

        return $structure;
    }
}
