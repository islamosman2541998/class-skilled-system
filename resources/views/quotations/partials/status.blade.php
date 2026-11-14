@php($status = $row->displayStatus())
<x-status-badge :color="$status->color()">{{ $status->label() }}</x-status-badge>
@if ($row->superseded_at)
    <x-status-badge color="neutral">{{ __('quotations.superseded') }}</x-status-badge>
@endif
