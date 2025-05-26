<?php

namespace CoderBeams\POTW\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

class Watch extends Entity
{
    /**
     * Define the entity structure
     */
    public static function getStructure(Structure $structure)
    {
        $structure->table = 'xf_potw_watch';  // Table name
        $structure->shortName = 'CoderBeams\POTW:Watch';  // Short name for the entity
        $structure->primaryKey = ['user_id', 'time_lapse'];  // Primary key (composite of user_id and time_lapse)
        $structure->columns = [
            'user_id' => ['type' => self::UINT, 'required' => true],  // User ID (who is watching)
            'time_lapse' => ['type' => self::STR, 'maxLength' => 4, 'required' => true],  // Time lapse (day or week)
            'watch_date' => ['type' => self::UINT, 'default' => 0],  // The date when the user started watching
        ];
        $structure->getters = [
            'watchDate' => true,  // Allows access to watch_date via $entity->watchDate
        ];
        $structure->relations = [
            'User' => [
                'entity' => 'XF:User',
                'type' => self::TO_ONE,
                'conditions' => 'user_id',
                'primary' => true,
                'api' => true,
            ],  // Allows access to watch_date via $entity->watchDate
        ];

        return $structure;
    }


}
