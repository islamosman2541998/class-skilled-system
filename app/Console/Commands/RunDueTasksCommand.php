<?php

namespace App\Console\Commands;

use App\Services\DueTasks;
use Illuminate\Console\Command;

/** For hosts that have cron: `* * * * * php artisan app:due-tasks`. */
class RunDueTasksCommand extends Command
{
    protected $signature = 'app:due-tasks';

    protected $description = 'Run the periodic tasks that are due';

    public function handle(DueTasks $tasks): int
    {
        $tasks->runDue();

        return self::SUCCESS;
    }
}
