<x-status-badge :color="$row->status->color()">{{ $row->status->label() }}</x-status-badge>
@if ($row->isOverdue())
    <x-status-badge color="danger">{{ __('finance.invoices.overdue') }}</x-status-badge>
@endif
