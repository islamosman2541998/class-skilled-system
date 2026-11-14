<?php

namespace App\Livewire\Tables;

use App\Enums\NotificationType;
use App\Livewire\DataTable;
use App\Notifications\SystemNotification;
use App\Tables\Column;
use App\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Notifications\DatabaseNotification;

/**
 * The signed-in user's notifications. Read-only: no delete, no export.
 */
class NotificationsTable extends DataTable
{
    protected bool $deletable = false;

    public function tableKey(): string
    {
        return 'notifications';
    }

    protected function query(): Builder
    {
        $user = auth()->user();

        return DatabaseNotification::query()
            ->where('notifiable_type', $user->getMorphClass())
            ->where('notifiable_id', $user->getKey());
    }

    protected function columns(): array
    {
        return [
            Column::make('title', 'notifications.title')->view('notifications.partials.title')
                ->exportAs(fn (DatabaseNotification $row) => SystemNotification::title($row->data))->onCard(),
            Column::make('type', 'notifications.type')->sortable()
                ->format(fn (DatabaseNotification $row) => NotificationType::tryFrom((string) $row->type)?->label() ?? (string) $row->type),
            Column::make('created_at', 'notifications.date')->sortable()->format(fn (DatabaseNotification $row) => fmt_datetime($row->created_at))->onCard(),
            Column::make('read_at', 'notifications.state')->sortable()
                ->format(fn (DatabaseNotification $row) => $row->read_at ? __('notifications.read') : __('notifications.unread'))->onCard(),
        ];
    }

    protected function filterDefinitions(): array
    {
        return [
            SelectFilter::make('type', 'notifications.type')->options(fn () => collect(NotificationType::cases())
                ->mapWithKeys(fn (NotificationType $type) => [$type->value => $type->label()])),
            SelectFilter::make('read', 'notifications.state')
                ->options(['0' => __('notifications.unread'), '1' => __('notifications.read')])
                ->query(fn (Builder $query, string $value) => $value === '1' ? $query->whereNotNull('read_at') : $query->whereNull('read_at')),
        ];
    }
}
