{{-- Attendance sheet (spec §8.17, §4.4): one project and day, every active worker on one screen, save at the bottom. --}}
@extends('layouts.app')

@section('title', __('resources.attendance.sheet'))

@section('content')
    <x-page-header :title="__('resources.attendance.sheet')" :breadcrumbs="auth()->user()->can('attendance.view') ? [__('resources.attendance.attendance') => route('attendance.index')] : []" />

    <form method="GET" action="{{ route('attendance.sheet') }}" class="field-form" data-sheet-pick>
        <x-panel>
            <div class="form-grid">
                <x-field name="project" :label="__('resources.attendance.project')" required>
                    <select id="project" name="project" class="form-select form-select-lg" onchange="this.form.submit()" required>
                        <option value=""></option>
                        @foreach ($projects as $option)
                            <option value="{{ $option->id }}" @selected($project?->id === $option->id)>{{ $option->number }} · {{ $option->title }}</option>
                        @endforeach
                    </select>
                </x-field>
                <x-field name="date" :label="__('resources.attendance.date')" required>
                    <input id="date" name="date" type="date" max="{{ today()->toDateString() }}" value="{{ $date->toDateString() }}" class="form-control form-control-lg" onchange="this.form.submit()" dir="ltr" required>
                </x-field>
            </div>
        </x-panel>
    </form>

    @if ($project)
        <form method="POST" action="{{ route('attendance.store') }}" class="field-form" data-dirty-check novalidate>
            @csrf
            <input type="hidden" name="project_id" value="{{ $project->id }}">
            <input type="hidden" name="work_date" value="{{ $date->toDateString() }}">

            <x-panel :title="__('resources.attendance.workers')">
                @error('rows')<div class="alert alert-danger">{{ $message }}</div>@enderror
                @forelse ($workers as $worker)
                    @php
                        $row = $rows->get($worker->id);
                        $elsewhere = $row !== null && $row->project_id !== $project->id;
                        $status = old("rows.{$worker->id}.status", $elsewhere ? '' : $row?->status->value);
                        $hours = old("rows.{$worker->id}.overtime_hours", $elsewhere ? '' : ($row ? rtrim(rtrim((string) $row->overtime_hours, '0'), '.') : ''));
                    @endphp
                    <div class="attendance-row">
                        <div class="attendance-name">
                            <span class="fw-semibold">{{ $worker->name }}</span>
                            <span class="text-muted small">{{ $worker->trade?->name }}</span>
                            @if ($elsewhere)
                                <span class="text-muted small">{{ __('resources.attendance.on_other_project', ['project' => $row->project->number]) }}</span>
                            @endif
                            @error("rows.{$worker->id}")<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        @unless ($elsewhere)
                            <div class="btn-group attendance-status-options" role="group" aria-label="{{ __('resources.attendance.status') }} {{ $worker->name }}">
                                @foreach (['present', 'half', 'absent', ''] as $value)
                                    <input type="radio" class="btn-check" name="rows[{{ $worker->id }}][status]" id="st-{{ $worker->id }}-{{ $value ?: 'none' }}" value="{{ $value }}" @checked((string) $status === $value)>
                                    <label class="btn btn-outline" for="st-{{ $worker->id }}-{{ $value ?: 'none' }}">{{ $value === '' ? __('resources.attendance.none') : __('resources.attendance.statuses.'.$value) }}</label>
                                @endforeach
                            </div>
                            <input name="rows[{{ $worker->id }}][overtime_hours]" value="{{ $hours }}" class="form-control attendance-overtime text-end" dir="ltr" inputmode="decimal" placeholder="{{ __('resources.attendance.overtime') }}" aria-label="{{ __('resources.attendance.overtime') }} {{ $worker->name }}">
                        @endunless
                    </div>
                @empty
                    <x-empty-state :message="__('resources.attendance.no_workers')" icon="users-group" />
                @endforelse
            </x-panel>

            <x-sticky-actions class="sticky-actions-mobile">
                <button type="submit" class="btn btn-primary btn-lg flex-fill">{{ __('app.save') }}</button>
            </x-sticky-actions>
        </form>
    @else
        <p class="text-muted">{{ __('resources.attendance.choose_project') }}</p>
    @endif
@endsection
