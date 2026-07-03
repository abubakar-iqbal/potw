<?php

namespace CoderBeams\POTW\XF\Entity;

use XF\Mvc\Entity\Structure;

class User extends XFCP_User
{
    public static function getStructure(Structure $structure)
    {
        $structure = parent::getStructure($structure);

        $structure->columns['cb_potw_count'] = ['type' => self::UINT, 'default' => 0, 'api' => true];

        return $structure;
    }
}
