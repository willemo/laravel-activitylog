<?php

use Illuminate\Auth\AuthManager;
use Illuminate\Contracts\Config\Repository;

return [

    /*
     * If set to false, no activities will be saved to the database.
     */
    'enabled' => env('ACTIVITY_LOGGER_ENABLED', true),

    /*
     * When the clean-command is executed, all recording activities older than
     * the number of days specified here will be deleted.
     */
    'delete_records_older_than_days' => 365,

    /*
     * If no log name is passed to the activity() helper
     * we use this default log name.
     */
    'default_log_name' => 'default',

    /*
     * You can specify an auth driver here that gets user models.
     * If this is null we'll use the default Laravel auth driver.
     */
    'default_auth_driver' => null,

    /*
     * You can specify the code that resolves the causer of the activity.
     * By default it will use the auth driver to get the logged in user.
     */
    'caused_by_resolver' => function (AuthManager $auth, Repository $config) {
        $authDriver = $config['laravel-activitylog']['default_auth_driver'] ?? $auth->getDefaultDriver();

        return $auth->guard($authDriver)->user();
    },

    /*
     * If set to true, the subject returns soft deleted models.
     */
     'subject_returns_soft_deleted_models' => false,

    /*
     * This model will be used to log activity. The only requirement is that
     * it should be or extend the Spatie\Activitylog\Models\Activity model.
     */
    'activity_model' => \Spatie\Activitylog\Models\Activity::class,
];
