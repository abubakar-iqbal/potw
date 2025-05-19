<?php

namespace CoderBeams\POTW\Alert;

use XF\Alert\AbstractHandler;
use XF\Mvc\Entity\Entity;

class PotwAlert extends AbstractHandler
{
    /**
     * This method is used to check whether the user can view the content of the alert.
     */
    public function canViewContent(Entity $entity, &$error = null)
    {
        // Allow all users to view the alert
        return true;
    }

    /**
     * This method is used to specify any additional data to be loaded with the entity.
     * In this case, we are fetching the associated User (the one being watched).
     */
    public function getEntityWith()
    {
        return ['User'];
    }

    /**
     * This method defines the content type for this alert.
     */
    public function getContentType()
    {
        return 'potw_watch';  // Unique identifier for this alert type
    }

    /**
     * This method gets the relevant data for the alert.
     */
    public function getContentData()
    {
        return [
            'user_id' => $this->alert->user_id,
            'time_lapse' => $this->alert->extra_data['time_lapse'], // 'day' or 'week'
            'watch_date' => $this->alert->extra_data['watch_date'],
        ];
    }

    /**
     * Define the phrase used for the alert.
     */
    public function getAlertPhrase()
    {
        return 'cb_potw_watch_alert_message';  // Define this phrase in your phrases XML
    }

    /**
     * Optional method to define the URL for the alert. This is where users will be directed when they click on the alert.
     */
    public function getAlertUrl()
    {
        return \XF::app()->router()->buildLink('potw');
    }
}
