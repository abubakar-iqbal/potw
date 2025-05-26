<?php

namespace CoderBeams\POTW;

use XF\AddOn\AbstractSetup;
use XF\AddOn\StepRunnerInstallTrait;
use XF\AddOn\StepRunnerUninstallTrait;
use XF\AddOn\StepRunnerUpgradeTrait;
use XF\Db\Schema\Create;

class Setup extends AbstractSetup
{
    use StepRunnerInstallTrait;
    use StepRunnerUpgradeTrait;
    use StepRunnerUninstallTrait;

    /**
     * Upgrade Step 1: This will run if upgrading the add-on, making sure that necessary changes to the table are applied.
     */
    public function upgrade16Step1(array $stepParams = [])
    {
        $schemaManager = $this->schemaManager();

        // Check if the table exists before trying to create it
        if (!$schemaManager->tableExists('xf_potw_watch')) {
            // If the table doesn't exist, create it
            $this->installStep1();  // Call the installStep1 to create the table
        }

    }

    /**
     * Install Step 1: Create the xf_potw_watch table for tracking watched users
     */
    public function installStep1(array $stepParams = [])
    {
        $this->schemaManager()->createTable('xf_potw_watch', function (Create $table) {
            $table->addColumn('user_id', 'int')->comment('User who is watching the posts');
            $table->addColumn('time_lapse', 'enum', ['values' => ['day', 'week']])->comment(
                'Time period for watching (Post of the Day or Week)'
            );
            $table->addColumn('watch_date', 'int')->setDefault(0)->comment('Timestamp when the user started watching');
            $table->addPrimaryKey(['user_id', 'time_lapse']);
            $table->addKey('time_lapse');  // Add index for time_lapse
        });
    }

    /**
     * Uninstall Step 1: Drop the table when uninstalling the add-on
     */
    public function uninstallStep1(array $stepParams = [])
    {
        $this->schemaManager()->dropTable('xf_potw_watch');
    }
}
