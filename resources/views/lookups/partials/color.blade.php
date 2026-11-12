@if ($row->color)
    <x-status-badge :color="$row->color">{{ $row->name }}</x-status-badge>
@endif
