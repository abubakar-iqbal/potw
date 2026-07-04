<?php

namespace CoderBeams\POTW;

use XF\AddOn\AbstractSetup;
use XF\AddOn\StepRunnerInstallTrait;
use XF\AddOn\StepRunnerUninstallTrait;
use XF\AddOn\StepRunnerUpgradeTrait;
use XF\Db\Schema\Alter;
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
        if ($this->schemaManager()->tableExists('xf_potw_watch')) {
            return;
        }

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
     * Install Step 2: Create the xf_cb_potw_winner table for tracking POTW wins
     */
    public function installStep2(array $stepParams = [])
    {
        $sm = $this->schemaManager();

        if ($sm->tableExists('xf_cb_potw_winner')) {
            return;
        }

        $sm->createTable('xf_cb_potw_winner', function (Create $table) {
            $table->addColumn('potw_winner_id', 'int')->autoIncrement();
            $table->addColumn('user_id', 'int')->comment('User who won the POTW');
            $table->addColumn('post_id', 'int')->comment('The winning post');
            $table->addColumn('time_lapse', 'enum', ['values' => ['day', 'week']])->setDefault('week');
            $table->addColumn('period', 'varchar', 10)->comment('ISO year-week identifier, e.g. 2026-26');
            $table->addColumn('won_date', 'int')->setDefault(0);
            $table->addUniqueKey(['time_lapse', 'period'], 'time_lapse_period');
            $table->addKey('user_id');
        });
    }

    /**
     * Install Step 3: Add the cached POTW win counter to the user table
     */
    public function installStep3(array $stepParams = [])
    {
        $sm = $this->schemaManager();

        if ($sm->columnExists('xf_user', 'cb_potw_count')) {
            return;
        }

        $sm->alterTable('xf_user', function (Alter $table) {
            $table->addColumn('cb_potw_count', 'int')->setDefault(0)->comment('Number of POTW wins');
        });
    }

    /**
     * Install Step 4: Index supporting the POTW queries. post_date leads:
     * the queries always bound a date window, while the reaction threshold
     * is typically low (default 1) and filters almost nothing on its own.
     */
    public function installStep4(array $stepParams = [])
    {
        if ($this->potwIndexExists('cb_potw_date_reaction')) {
            return;
        }

        $this->schemaManager()->alterTable('xf_post', function (Alter $table) {
            $table->addKey(['post_date', 'reaction_score'], 'cb_potw_date_reaction');
        });

        // earlier 1.0.9 builds created the index with the columns reversed
        if ($this->potwIndexExists('cb_potw_reaction_date')) {
            $this->schemaManager()->alterTable('xf_post', function (Alter $table) {
                $table->dropIndexes('cb_potw_reaction_date');
            });
        }
    }

    /**
     * Install Step 5: Create the xf_cb_potw_promoted table for manually
     * promoted posts (pinned on the POTW page until the week ends)
     */
    public function installStep5(array $stepParams = [])
    {
        $sm = $this->schemaManager();

        if ($sm->tableExists('xf_cb_potw_promoted')) {
            return;
        }

        $sm->createTable('xf_cb_potw_promoted', function (Create $table) {
            $table->addColumn('post_id', 'int')->comment('The promoted post');
            $table->addColumn('promoted_by', 'int')->comment('User who promoted the post');
            $table->addColumn('promote_date', 'int')->setDefault(0);
            $table->addColumn('expiry_date', 'int')->setDefault(0)->comment('End of the week the post was promoted in');
            $table->addPrimaryKey('post_id');
            $table->addKey('expiry_date');
        });
    }

    /**
     * Upgrade to 1.0.8: add winner tracking
     */
    public function upgrade18Step1(array $stepParams = [])
    {
        $this->installStep2();
    }

    public function upgrade18Step2(array $stepParams = [])
    {
        $this->installStep3();
    }

    /**
     * Upgrade to 1.0.9: add the xf_post index for POTW queries
     */
    public function upgrade19Step1(array $stepParams = [])
    {
        $this->installStep4();
    }

    /**
     * Upgrade to 1.0.10: add promoted-post tracking
     */
    public function upgrade20Step1(array $stepParams = [])
    {
        $this->installStep5();
    }

    /**
     * Uninstall Step 1: Drop the table when uninstalling the add-on
     */
    public function uninstallStep1(array $stepParams = [])
    {
        $this->schemaManager()->dropTable('xf_potw_watch');
    }

    public function uninstallStep2(array $stepParams = [])
    {
        $this->schemaManager()->dropTable('xf_cb_potw_winner');
    }

    public function uninstallStep3(array $stepParams = [])
    {
        $sm = $this->schemaManager();

        if ($sm->columnExists('xf_user', 'cb_potw_count')) {
            $sm->alterTable('xf_user', function (Alter $table) {
                $table->dropColumns('cb_potw_count');
            });
        }
    }

    public function uninstallStep5(array $stepParams = [])
    {
        $this->schemaManager()->dropTable('xf_cb_potw_promoted');
    }

    public function uninstallStep4(array $stepParams = [])
    {
        foreach (['cb_potw_date_reaction', 'cb_potw_reaction_date'] as $index) {
            if ($this->potwIndexExists($index)) {
                $this->schemaManager()->alterTable('xf_post', function (Alter $table) use ($index) {
                    $table->dropIndexes($index);
                });
            }
        }
    }

    protected function potwIndexExists(string $name): bool
    {
        return (bool)$this->db()->fetchOne(
            'SHOW INDEX FROM xf_post WHERE Key_name = ?',
            $name
        );
    }
}
