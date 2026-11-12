<?php

namespace App\Livewire\Tables;

use App\Livewire\DataTable;
use App\Models\User;
use App\Tables\Column;
use App\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Locked;
use Spatie\Activitylog\Models\Activity;

/**
 * The activity log: read-only. With subjectType and subjectId it shows the history of one record.
 */
class ActivityTable extends DataTable
{
    #[Locked]
    public string $subjectType = '';

    #[Locked]
    public int|string|null $subjectId = null;

    protected bool $deletable = false;

    public function tableKey(): string
    {
        return $this->subjectType === '' ? 'activity' : 'activity.record';
    }

    protected function permissionPrefix(): ?string
    {
        return 'activity';
    }

    protected function query(): Builder
    {
        return Activity::query()
            ->with('causer')
            ->when($this->subjectType !== '', fn (Builder $query) => $query
                ->where('subject_type', $this->subjectType)
                ->where('subject_id', $this->subjectId));
    }

    protected function columns(): array
    {
        $columns = [
            Column::make('created_at', 'activity.when')->sortable()->format(fn (Activity $row) => fmt_datetime($row->created_at))->onCard(),
            Column::make('causer', 'activity.user')->format(fn (Activity $row) => $row->causer instanceof User ? $row->causer->name : __('activity.system'))->onCard(),
        ];

        if ($this->subjectType === '') {
            $columns[] = Column::make('log_name', 'activity.module')->sortable()->format(fn (Activity $row) => self::moduleLabel((string) $row->log_name))->onCard();
            $columns[] = Column::make('subject_id', 'activity.record')->format(fn (Activity $row) => $row->subject_id ? '#'.$row->subject_id : '');
        }

        $columns[] = Column::make('event', 'activity.event')->sortable()->format(fn (Activity $row) => self::eventLabel((string) $row->event))->onCard();
        $columns[] = Column::make('changes', 'activity.changes')->view('activity.partials.changes')
            ->exportAs(fn (Activity $row) => json_encode($row->properties, JSON_UNESCAPED_UNICODE));

        return $columns;
    }

    protected function filterDefinitions(): array
    {
        $filters = [
            SelectFilter::make('causer_id', 'activity.user')
                ->options(fn () => User::query()->orderBy('name')->pluck('name', 'id'))
                ->query(fn (Builder $query, string $value) => $query->where('causer_type', (new User)->getMorphClass())->where('causer_id', $value)),
            SelectFilter::make('event', 'activity.event')
                ->options(fn () => Activity::query()->distinct()->orderBy('event')->pluck('event')->filter()
                    ->mapWithKeys(fn (string $event) => [$event => self::eventLabel($event)])),
        ];

        if ($this->subjectType === '') {
            $filters[] = SelectFilter::make('log_name', 'activity.module')
                ->options(fn () => Activity::query()->distinct()->orderBy('log_name')->pluck('log_name')->filter()
                    ->mapWithKeys(fn (string $module) => [$module => self::moduleLabel($module)]));
        }

        return $filters;
    }

    public static function eventLabel(string $event): string
    {
        $key = 'activity.events.'.$event;

        return __($key) === $key ? $event : __($key);
    }

    public static function moduleLabel(string $module): string
    {
        $key = 'activity.modules.'.$module;

        return __($key) === $key ? $module : __($key);
    }
}
