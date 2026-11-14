{{-- Stages of a project (spec §8.7): list with progress, late badge, photos; timeline; add / edit modal. --}}
@php
    $stages = $project->stages()->get();
    $canEdit = auth()->user()->can('projects.update');
@endphp

<x-panel :title="__('projects.stages')">
    @if ($canEdit)
        <x-slot:tools>
            <button type="button" class="btn btn-primary btn-sm" data-stage-new><x-icon name="plus" :size="16" /> {{ __('projects.new_stage') }}</button>
        </x-slot:tools>
    @endif

    @if ($stages->isEmpty())
        <x-empty-state :message="__('projects.no_stages')" icon="list-check" />
    @else
        <ul class="stage-list" @if ($canEdit) data-stage-list data-reorder-url="{{ route('projects.stages.reorder', $project) }}" @endif>
            @foreach ($stages as $stage)
                <li class="stage-row" data-id="{{ $stage->id }}">
                    @if ($canEdit)
                        <span class="btn-icon sort-handle" aria-hidden="true"><x-icon name="grip-vertical" :size="16" /></span>
                    @endif
                    <div class="stage-main">
                        <div class="stage-head">
                            <span class="fw-semibold">{{ $stage->name }}</span>
                            <x-status-badge :color="$stage->status->color()">{{ $stage->status->label() }}</x-status-badge>
                            @if ($stage->isLate())
                                <x-status-badge color="danger">{{ __('projects.stage_late') }}</x-status-badge>
                            @endif
                            <span class="text-muted small">{{ __('projects.weight') }} <bdi dir="ltr">{{ rtrim(rtrim($stage->weight, '0'), '.') }}</bdi></span>
                        </div>
                        <div class="progress-line"><span style="inline-size: {{ min(100, (float) $stage->progress) }}%"></span></div>
                        <div class="text-muted small">
                            <bdi dir="ltr">{{ rtrim(rtrim($stage->progress, '0'), '.') ?: '0' }}%</bdi>
                            @if ($stage->planned_start || $stage->planned_end)
                                · {{ __('projects.planned') }}: <bdi dir="ltr">{{ $stage->planned_start ? fmt_date($stage->planned_start) : '' }} – {{ $stage->planned_end ? fmt_date($stage->planned_end) : '' }}</bdi>
                            @endif
                            @if ($stage->actual_start)
                                · {{ __('projects.actual') }}: <bdi dir="ltr">{{ fmt_date($stage->actual_start) }} – {{ $stage->actual_end ? fmt_date($stage->actual_end) : '' }}</bdi>
                            @endif
                        </div>
                        @if ($stage->notes)
                            <p class="mb-0 small text-pre">{{ $stage->notes }}</p>
                        @endif
                    </div>
                    @if ($canEdit)
                        <div class="d-flex gap-1">
                            <button type="button" class="btn-icon" aria-label="{{ __('projects.edit_stage') }}"
                                data-stage-edit="{{ json_encode([
                                    'id' => $stage->id, 'name_ar' => $stage->name_ar, 'name_en' => $stage->name_en, 'weight' => $stage->weight,
                                    'planned_start' => $stage->planned_start?->format('Y-m-d'), 'planned_end' => $stage->planned_end?->format('Y-m-d'),
                                    'actual_start' => $stage->actual_start?->format('Y-m-d'), 'actual_end' => $stage->actual_end?->format('Y-m-d'),
                                    'status' => $stage->status->value, 'progress' => $stage->progress, 'notes' => $stage->notes,
                                ]) }}"><x-icon name="pencil" /></button>
                            <form method="POST" action="{{ route('projects.stages.destroy', [$project, $stage]) }}" data-confirm="{{ __('projects.delete_stage') }}" data-confirm-danger>
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn-icon btn-icon-danger" aria-label="{{ __('table.delete') }}"><x-icon name="trash" /></button>
                            </form>
                        </div>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</x-panel>

@if ($stages->isNotEmpty())
    @include('projects.partials.timeline', ['stages' => $stages, 'project' => $project])
@endif

@if ($canEdit)
    @push('modals')
        <div class="modal fade" id="stage-modal" tabindex="-1" aria-labelledby="stage-modal-title" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <form class="modal-content" method="POST" novalidate
                    data-store-url="{{ route('projects.stages.store', $project) }}" data-update-url="{{ route('projects.stages.update', [$project, '__ID__']) }}"
                    data-title-new="{{ __('projects.new_stage') }}" data-title-edit="{{ __('projects.edit_stage') }}">
                    @csrf
                    <input type="hidden" name="_method" value="POST">
                    <div class="modal-header">
                        <h2 class="modal-title" id="stage-modal-title">{{ __('projects.new_stage') }}</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('app.cancel') }}"></button>
                    </div>
                    <div class="modal-body">
                        <div class="form-grid">
                            <x-field name="name_ar" :label="__('projects.stage_name_ar')" for="st-name-ar" required>
                                <input id="st-name-ar" name="name_ar" class="form-control" maxlength="190" required dir="rtl">
                            </x-field>
                            <x-field name="name_en" :label="__('projects.stage_name_en')" for="st-name-en">
                                <input id="st-name-en" name="name_en" class="form-control" maxlength="190" dir="ltr">
                            </x-field>
                            <x-field name="weight" :label="__('projects.weight')" for="st-weight" required>
                                <input id="st-weight" name="weight" class="form-control text-end" dir="ltr" inputmode="decimal" required>
                            </x-field>
                            <x-field name="status" :label="__('projects.status')" for="st-status">
                                <select id="st-status" name="status" class="form-select">
                                    @foreach (\App\Enums\StageStatus::cases() as $status)
                                        <option value="{{ $status->value }}">{{ $status->label() }}</option>
                                    @endforeach
                                </select>
                            </x-field>
                            <x-field name="progress" :label="__('projects.progress')" for="st-progress">
                                <input id="st-progress" name="progress" type="number" min="0" max="100" step="1" class="form-control text-end" dir="ltr">
                            </x-field>
                            <x-field name="planned_start" :label="__('projects.planned_start')" for="st-ps">
                                <input id="st-ps" name="planned_start" type="date" class="form-control" dir="ltr">
                            </x-field>
                            <x-field name="planned_end" :label="__('projects.planned_end')" for="st-pe">
                                <input id="st-pe" name="planned_end" type="date" class="form-control" dir="ltr">
                            </x-field>
                            <x-field name="actual_start" :label="__('projects.actual_start')" for="st-as">
                                <input id="st-as" name="actual_start" type="date" class="form-control" dir="ltr">
                            </x-field>
                            <x-field name="actual_end" :label="__('projects.actual_end')" for="st-ae">
                                <input id="st-ae" name="actual_end" type="date" class="form-control" dir="ltr">
                            </x-field>
                        </div>
                        <x-field name="notes" :label="__('projects.notes')" for="st-notes">
                            <textarea id="st-notes" name="notes" class="form-control" rows="2"></textarea>
                        </x-field>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline" data-bs-dismiss="modal">{{ __('app.cancel') }}</button>
                        <button type="submit" class="btn btn-primary">{{ __('app.save') }}</button>
                    </div>
                </form>
            </div>
        </div>
    @endpush

    @push('scripts')
        @vite('resources/js/project-stages.js')
    @endpush
@endif
