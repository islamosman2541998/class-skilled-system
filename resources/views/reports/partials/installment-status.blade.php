@php($status = \App\Enums\InstallmentStatus::from((string) $row->getAttribute('status')))
<x-status-badge :color="$status->color()">{{ $status->label() }}</x-status-badge>
