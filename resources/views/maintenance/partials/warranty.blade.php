<x-status-badge :color="$row->in_warranty ? 'success' : 'neutral'">{{ $row->in_warranty ? __('maintenance.in_warranty') : __('maintenance.out_of_warranty') }}</x-status-badge>
