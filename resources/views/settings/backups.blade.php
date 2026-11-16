{{-- Backups (spec §13): the daily database backup, files on demand, download and delete. --}}
@extends('layouts.app')

@php
    $age = fn ($backup) => $backup ? fmt_datetime($backup->started_at) : __('settings.backups.never');
    $size = fn (int $bytes) => $bytes >= 1048576 ? number_format($bytes / 1048576, 1).' MB' : number_format(max(1, (int) round($bytes / 1024))).' KB';
@endphp

@section('title', __('settings.backups.backups'))

@section('content')
    <x-page-header :title="__('settings.backups.backups')" :breadcrumbs="[__('settings.settings') => route('settings.index')]">
        <x-slot:actions>
            <form method="POST" action="{{ route('settings.backups.run', 'database') }}">
                @csrf
                <button type="submit" class="btn btn-primary"><x-icon name="database" :size="16" /> {{ __('settings.backups.run_database') }}</button>
            </form>
            <form method="POST" action="{{ route('settings.backups.run', 'files') }}" data-confirm="{{ __('settings.backups.files_confirm') }}">
                @csrf
                <button type="submit" class="btn btn-outline"><x-icon name="files" :size="16" /> {{ __('settings.backups.run_files') }}</button>
            </form>
        </x-slot:actions>
    </x-page-header>

    <div class="row g-3 mb-3">
        <div class="col-md-6">
            <x-panel :title="__('settings.backups.last_database')">
                <p class="mb-1 fw-semibold"><bdi dir="ltr">{{ $age($lastDatabase) }}</bdi></p>
                <p class="text-muted small mb-0">{{ __('settings.backups.daily_hint', ['keep' => app(\App\Settings\SystemSettings::class)->backup_keep]) }}</p>
            </x-panel>
        </div>
        <div class="col-md-6">
            <x-panel :title="__('settings.backups.last_files')">
                <p class="mb-1 fw-semibold"><bdi dir="ltr">{{ $age($lastFiles) }}</bdi></p>
                <p class="text-muted small mb-0">{{ __('settings.backups.files_hint') }}</p>
            </x-panel>
        </div>
    </div>

    <x-panel :title="__('settings.backups.list')" class="panel-flush">
        @if ($backups->isEmpty())
            <x-empty-state :message="__('settings.backups.empty')" icon="database" />
        @else
            <div class="table-responsive">
                <table class="table q-view mb-0">
                    <thead>
                        <tr>
                            <th scope="col">{{ __('settings.backups.date') }}</th>
                            <th scope="col">{{ __('settings.backups.type') }}</th>
                            <th scope="col">{{ __('settings.backups.file') }}</th>
                            <th scope="col" class="num">{{ __('settings.backups.size') }}</th>
                            <th scope="col">{{ __('settings.backups.status') }}</th>
                            <th scope="col"><span class="visually-hidden">{{ __('table.actions') }}</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($backups as $backup)
                            <tr>
                                <td class="text-nowrap"><bdi dir="ltr">{{ fmt_datetime($backup->started_at) }}</bdi></td>
                                <td>{{ $backup->type->label() }}@if ($backup->creator)<span class="text-muted small"> · {{ $backup->creator->name }}</span>@endif</td>
                                <td><bdi dir="ltr">{{ basename($backup->path) }}</bdi></td>
                                <td class="num"><bdi dir="ltr">{{ $backup->status === \App\Enums\BackupStatus::Success ? $size($backup->size) : '' }}</bdi></td>
                                <td>
                                    <x-status-badge :color="$backup->status->color()">{{ $backup->status->label() }}</x-status-badge>
                                    @if ($backup->error)<div class="text-danger small">{{ $backup->error }}</div>@endif
                                </td>
                                <td class="dt-actions">
                                    <div class="dt-row-actions">
                                        @if ($backup->status === \App\Enums\BackupStatus::Success)
                                            <a href="{{ route('settings.backups.download', $backup) }}" class="btn-icon" aria-label="{{ __('settings.backups.download') }}"><x-icon name="download" /></a>
                                        @endif
                                        <form method="POST" action="{{ route('settings.backups.destroy', $backup) }}" data-confirm="{{ __('table.confirm_delete') }}" data-confirm-danger>
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn-icon btn-icon-danger" aria-label="{{ __('table.delete') }}"><x-icon name="trash" /></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-panel>
@endsection
