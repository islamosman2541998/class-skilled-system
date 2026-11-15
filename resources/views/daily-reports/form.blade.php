{{-- Daily report (spec §8.13, §4.4): phone first, one screen, save bar at the bottom. --}}
@extends('layouts.app')

@php
    $editing = $report->exists;
    $title = $editing ? __('site.reports.edit') : __('site.reports.new');
    $selected = (int) old('project_id', $report->project_id);
    $values = old('stages', $progress);
@endphp

@section('title', $title)

@section('content')
    <x-page-header :title="$title" :breadcrumbs="auth()->user()->can('daily_reports.view') ? [__('site.reports.reports') => route('daily-reports.index')] : []" />

    <form method="POST" action="{{ $editing ? route('daily-reports.update', $report) : route('daily-reports.store') }}" class="field-form" data-dirty-check novalidate data-report-form>
        @csrf
        @if ($editing)
            @method('PUT')
        @endif

        <x-panel>
            <x-field name="project_id" :label="__('site.reports.project')" required>
                <select id="project_id" name="project_id" class="form-select form-select-lg" required data-report-project>
                    <option value=""></option>
                    @foreach ($projects as $project)
                        <option value="{{ $project->id }}" @selected($selected === $project->id)>{{ $project->number }} · {{ $project->title }}</option>
                    @endforeach
                </select>
            </x-field>
            <x-field name="report_date" :label="__('site.reports.date')" required>
                <input id="report_date" name="report_date" type="date" max="{{ today()->toDateString() }}" value="{{ old('report_date', $report->report_date?->format('Y-m-d')) }}" @class(['form-control form-control-lg', 'is-invalid' => $errors->has('report_date')]) dir="ltr" required>
            </x-field>
            <x-field name="work_done" :label="__('site.reports.work_done')" required>
                <textarea id="work_done" name="work_done" rows="4" @class(['form-control form-control-lg', 'is-invalid' => $errors->has('work_done')]) maxlength="5000" required>{{ old('work_done', $report->work_done) }}</textarea>
            </x-field>
        </x-panel>

        <x-panel :title="__('site.reports.stages')">
            @error('stages')<div class="invalid-feedback d-block mb-2">{{ $message }}</div>@enderror
            <p class="text-muted mb-2" data-no-project @if ($selected) hidden @endif>{{ __('site.reports.choose_project') }}</p>
            @foreach ($stagesByProject as $projectId => $stages)
                <div class="report-stages" data-project-stages="{{ $projectId }}" @if ($selected !== (int) $projectId) hidden @endif>
                    @forelse ($stages as $stage)
                        <div class="report-stage">
                            <label class="report-stage-name" for="stage-{{ $stage->id }}">
                                {{ $stage->name }}
                                <span class="text-muted">{{ __('site.reports.current_progress', ['progress' => rtrim(rtrim((string) $stage->progress, '0'), '.') ?: '0']) }}</span>
                            </label>
                            <div class="input-group report-stage-input">
                                <input id="stage-{{ $stage->id }}" name="stages[{{ $stage->id }}]" value="{{ $values[$stage->id] ?? '' }}" class="form-control form-control-lg text-end" dir="ltr" inputmode="decimal" placeholder="{{ rtrim(rtrim((string) $stage->progress, '0'), '.') ?: '0' }}" @disabled($selected !== (int) $projectId)>
                                <span class="input-group-text">%</span>
                            </div>
                        </div>
                    @empty
                        <p class="text-muted mb-0">{{ __('site.reports.no_stages') }}</p>
                    @endforelse
                </div>
            @endforeach
            <p class="form-text mb-0">{{ __('site.reports.stages_hint') }}</p>
        </x-panel>

        <x-panel>
            <x-field name="labour_count" :label="__('site.reports.labour_count')">
                <input id="labour_count" name="labour_count" type="number" min="0" max="9999" value="{{ old('labour_count', $report->labour_count) }}" @class(['form-control form-control-lg', 'is-invalid' => $errors->has('labour_count')]) dir="ltr" inputmode="numeric">
            </x-field>
            <x-field name="obstacles" :label="__('site.reports.obstacles')">
                <textarea id="obstacles" name="obstacles" rows="2" class="form-control form-control-lg" maxlength="5000">{{ old('obstacles', $report->obstacles) }}</textarea>
            </x-field>
            <x-field name="notes" :label="__('site.reports.notes')">
                <textarea id="notes" name="notes" rows="2" class="form-control form-control-lg" maxlength="5000">{{ old('notes', $report->notes) }}</textarea>
            </x-field>
            @unless ($editing)
                <x-field name="uploads" :label="__('site.reports.media')" for="uploads-input">
                    <x-uploader name="uploads" accept="image,video" :camera="true" />
                </x-field>
            @endunless
        </x-panel>

        <x-sticky-actions class="sticky-actions-mobile">
            <button type="submit" class="btn btn-primary btn-lg flex-fill">{{ __('app.save') }}</button>
        </x-sticky-actions>
    </form>

    @push('scripts')
        <script>
            // Shows the stages of the chosen project; hidden stages are disabled so they are not sent.
            (() => {
                const form = document.querySelector('[data-report-form]');
                const select = form.querySelector('[data-report-project]');
                const show = () => {
                    form.querySelectorAll('[data-project-stages]').forEach((group) => {
                        const active = group.dataset.projectStages === select.value;
                        group.hidden = !active;
                        group.querySelectorAll('input').forEach((input) => { input.disabled = !active; });
                    });
                    form.querySelector('[data-no-project]').hidden = select.value !== '';
                };
                select.addEventListener('change', show);
            })();
        </script>
    @endpush
@endsection
