<?php

namespace CoderBeams\POTW\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

class Promoted extends Entity
{
    public function isActive(): bool
    {
        return $this->expiry_date > \XF::$time;
    }

    public static function getStructure(Structure $structure)
    {
        $structure->table = 'xf_cb_potw_promoted';
        $structure->shortName = 'CoderBeams\POTW:Promoted';
        $structure->primaryKey = 'post_id';
        $structure->columns = [
            'post_id' => ['type' => self::UINT, 'required' => true],
            'promoted_by' => ['type' => self::UINT, 'required' => true],
            'promote_date' => ['type' => self::UINT, 'default' => 0],
            'expiry_date' => ['type' => self::UINT, 'default' => 0],
        ];
        $structure->relations = [
            'Post' => [
                'entity' => 'XF:Post',
                'type' => self::TO_ONE,
                'conditions' => 'post_id',
                'primary' => true,
            ],
            'PromotedBy' => [
                'entity' => 'XF:User',
                'type' => self::TO_ONE,
                'conditions' => [['user_id', '=', '$promoted_by']],
                'primary' => true,
            ],
        ];

        return $structure;
    }
}
