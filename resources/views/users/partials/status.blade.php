@if ($row->is_active)
    <x-status-badge color="success">{{ __('users.active') }}</x-status-badge>
@else
    <x-status-badge color="neutral">{{ __('users.inactive') }}</x-status-badge>
@endif
