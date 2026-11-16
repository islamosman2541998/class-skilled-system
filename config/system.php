<?php

return [

    /*
    | First administrator, created by AdminUserSeeder. Values live in .env only.
    */
    'admin' => [
        'name' => env('ADMIN_NAME'),
        'email' => env('ADMIN_EMAIL'),
        'password' => env('ADMIN_PASSWORD'),
    ],

    /*
    | Daily database backup on the first request of the day (spec §13). Off in the test suite,
    | where every signed-in request would otherwise start a dump.
    */
    'backup_daily' => env('BACKUP_DAILY', true),

];
