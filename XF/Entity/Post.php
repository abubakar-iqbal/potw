<?php
namespace CoderBeams\POTW\XF\Entity;

use XF\Mvc\Entity\Structure;

class Post extends XFCP_Post
{
	protected function setupApiResultData(
		\XF\Api\Result\EntityResult $result, $verbosity = self::VERBOSITY_NORMAL, array $options = []
	)
	{
		parent::setupApiResultData($result,$verbosity,$options);
        $result->thread_title=$this->Thread->title;
		$result->view_url =$this->app()->router('public')->buildLink('canonical:threads/post', $this->Thread, ['post_id' => $this->post_id]);
    }

    /**
     * @param \XF\Phrase|string|null $error
     */
    public function canPromoteToPotw(&$error = null): bool
    {
        $visitor = \XF::visitor();

        if (!$visitor->user_id || !$visitor->hasPermission('cb_potw', 'promote')) {
            return false;
        }

        if ($this->message_state !== 'visible'
            || !$this->Thread
            || $this->Thread->discussion_state !== 'visible'
        ) {
            return false;
        }

        return true;
    }

    public function isPotwPromoted(): bool
    {
        $promoted = $this->PotwPromoted;

        return $promoted ? $promoted->isActive() : false;
    }

    public static function getStructure(Structure $structure)
    {
        $structure = parent::getStructure($structure);

        $structure->relations['PotwPromoted'] = [
            'entity' => 'CoderBeams\POTW:Promoted',
            'type' => self::TO_ONE,
            'conditions' => 'post_id',
            'primary' => true,
        ];

        return $structure;
    }
}