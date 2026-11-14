{{-- Handover and warranty (spec §8.18). Maintenance requests of the project are added by Task 8. --}}
@php
    $handover = $project->handover;
    $canEdit = auth()->user()->can('projects.update');
@endphp

<div class="row g-3">
    <div class="col-lg-6">
        <x-panel :title="__('projects.handover.title')">
            @if ($handover)
                <x-slot:tools>
                    @can('projects.print')
                        <div class="dropdown">
                            <button type="button" class="btn btn-outline btn-sm" data-bs-toggle="dropdown" aria-expanded="false"><x-icon name="printer" :size="16" /> {{ __('projects.handover.record') }}</button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                @foreach (['ar', 'en', 'both'] as $lang)
                                    <li><a class="dropdown-item" href="{{ route('projects.handover.pdf', [$project, 'lang' => $lang]) }}" target="_blank" rel="noopener">{{ __('quotations.print_'.$lang) }}</a></li>
                                @endforeach
                            </ul>
                        </div>
                    @endcan
                </x-slot:tools>
                <dl class="detail-list">
                    <div><dt>{{ __('projects.handover.handover_date') }}</dt><dd><bdi dir="ltr">{{ fmt_date($handover->handover_date) }}</bdi></dd></div>
                    <div><dt>{{ __('projects.handover.warranty_months') }}</dt><dd><bdi dir="ltr">{{ $handover->warranty_months }}</bdi></dd></div>
                    <div>
                        <dt>{{ __('projects.handover.warranty_end') }}</dt>
                        <dd>
                            <bdi dir="ltr">{{ fmt_date($handover->warranty_end) }}</bdi>
                            @if ($handover->isUnderWarranty(today()))
                                <x-status-badge color="success">{{ __('projects.handover.under_warranty') }}</x-status-badge>
                            @else
                                <x-status-badge color="neutral">{{ __('projects.handover.warranty_over') }}</x-status-badge>
                            @endif
                        </dd>
                    </div>
                </dl>
                @if ($handover->notes)
                    <p class="mt-3 mb-0 text-pre">{{ $handover->notes }}</p>
                @endif
            @else
                <x-empty-state :message="__('projects.handover.not_yet')" icon="key" />
            @endif

            @if ($canEdit)
                <form method="POST" action="{{ route('projects.handover.update', $project) }}" class="contact-add" novalidate>
                    @csrf
                    @method('PUT')
                    <div class="form-grid">
                        <x-field name="handover_date" :label="__('projects.handover.handover_date')" required>
                            <input id="handover_date" name="handover_date" value="{{ old('handover_date', $handover?->handover_date?->format('Y-m-d') ?? today()->format('Y-m-d')) }}" @class(['form-control', 'is-invalid' => $errors->has('handover_date')]) data-date dir="ltr" required>
                        </x-field>
                        <x-field name="warranty_months" :label="__('projects.handover.warranty_months')" required>
                            <input id="warranty_months" name="warranty_months" type="number" min="0" max="240" value="{{ old('warranty_months', $handover?->warranty_months ?? 12) }}" @class(['form-control text-end', 'is-invalid' => $errors->has('warranty_months')]) dir="ltr" required>
                        </x-field>
                    </div>
                    <x-field name="notes" :label="__('projects.handover.notes')">
                        <textarea id="notes" name="notes" class="form-control" rows="2">{{ old('notes', $handover?->notes) }}</textarea>
                    </x-field>
                    <p class="form-text">{{ __('projects.handover.delivers_hint') }}</p>
                    <button type="submit" class="btn btn-primary btn-sm">{{ __('projects.handover.save') }}</button>
                </form>
            @endif
        </x-panel>

        @includeIf('projects.partials.maintenance-list', ['project' => $project])
    </div>

    <div class="col-lg-6">
        @if ($handover)
            <x-attachments :model="$handover" :action="route('projects.handover.attachments.store', $project)" />
        @endif
    </div>
</div>
