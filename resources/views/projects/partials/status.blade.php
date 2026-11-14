<x-status-badge :color="$row->status->color()">{{ $row->status->label() }}</x-status-badge>
@if ($row->isLate())
    <x-status-badge color="danger">{{ __('projects.late') }}</x-status-badge>
@endif
