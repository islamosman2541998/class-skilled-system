<?php

namespace App\Console\Commands;

use App\Enums\LookupType;
use App\Models\Lookup;
use App\Support\Like;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

/**
 * Times the standard table queries on the largest list (spec §11: search and filter under 300 ms
 * on 50,000 rows). Prints the median of five runs for search, filter and sort, each with the
 * count and the first page the table runs.
 */
class MeasureTableCommand extends Command
{
    protected $signature = 'app:measure-table {--term=تأجيل : Search term}';

    protected $description = 'Measure search, filter and sort on the 50,000-row lookups list';

    public function handle(): int
    {
        $type = LookupType::RejectionReason;
        $base = fn (): Builder => Lookup::query()->ofType($type);
        $this->line('Rows: '.$base()->count());

        $cases = [
            'search' => fn () => Like::any($base(), ['name_ar', 'name_en'], (string) $this->option('term')),
            'filter' => fn () => $base()->where('is_active', false),
            'sort' => fn () => $base()->orderByDesc('created_at'),
        ];

        foreach ($cases as $name => $query) {
            $times = [];

            for ($run = 0; $run < 5; $run++) {
                $start = hrtime(true);
                $query()->count();
                $query()->limit(25)->get();
                $times[] = (hrtime(true) - $start) / 1_000_000;
            }

            sort($times);
            $this->line(sprintf('%-7s %7.1f ms', $name, $times[2]));
        }

        return self::SUCCESS;
    }
}
