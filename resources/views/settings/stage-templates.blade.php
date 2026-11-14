@extends('layouts.app')

@section('title', __('settings.groups.stage_templates'))

@section('content')
    <x-page-header :title="__('settings.groups.stage_templates')" :breadcrumbs="[__('settings.settings') => route('settings.index')]" />

    <x-panel>
        <form method="GET" action="{{ route('settings.stage-templates') }}" class="d-flex flex-wrap gap-2 align-items-end">
            <x-field name="service" :label="__('projects.templates.service')" class="mb-0">
                <select id="service" name="service" class="form-select" onchange="this.form.submit()">
                    @foreach ($services as $item)
                        <option value="{{ $item->id }}" @selected($service?->id === $item->id)>{{ $item->name }}</option>
                    @endforeach
                </select>
            </x-field>
        </form>
    </x-panel>

    @if ($service)
        <form method="POST" action="{{ route('settings.stage-templates.update', ['service' => $service->id]) }}" data-dirty-check novalidate data-stage-templates>
            @csrf
            @method('PUT')

            <x-panel :title="$service->name">
                <p class="form-text mt-0">{{ __('projects.templates.hint') }}</p>
                <div class="repeater" data-rows data-sortable>
                    @foreach ($stages as $i => $stage)
                        @include('settings.partials.stage-template-row', ['i' => $i, 'stage' => $stage])
                    @endforeach
                </div>
                <template data-row-template>
                    @include('settings.partials.stage-template-row', ['i' => '__I__', 'stage' => []])
                </template>
                @error('stages')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                <div class="payment-template-footer">
                    <button type="button" class="btn btn-outline btn-sm" data-row-add><x-icon name="plus" :size="16" /> {{ __('projects.templates.add_stage') }}</button>
                    <span class="ms-auto">{{ __('projects.templates.total_weight') }}: <bdi dir="ltr" data-weight-total></bdi></span>
                </div>
            </x-panel>

            <x-sticky-actions>
                <button type="submit" class="btn btn-primary">{{ __('app.save') }}</button>
                <a href="{{ route('settings.index') }}" class="btn btn-outline">{{ __('app.cancel') }}</a>
            </x-sticky-actions>
        </form>

        @push('scripts')
            <script type="module">
                const form = document.querySelector('[data-stage-templates]');
                const rows = form.querySelector('[data-rows]');
                let next = 10000;

                const total = () => {
                    const sum = Array.from(rows.querySelectorAll('[data-weight]')).reduce((carry, input) => carry + (parseFloat(input.value) || 0), 0);
                    form.querySelector('[data-weight-total]').textContent = sum.toFixed(2);
                };

                form.addEventListener('input', total);
                form.addEventListener('click', (event) => {
                    if (event.target.closest('[data-row-add]')) {
                        rows.insertAdjacentHTML('beforeend', form.querySelector('template[data-row-template]').innerHTML.replaceAll('__I__', String(next++)));
                        rows.lastElementChild.querySelector('input').focus();
                    }

                    if (event.target.closest('[data-row-remove]')) {
                        event.target.closest('[data-repeater-row]').remove();
                    }

                    total();
                });

                total();
            </script>
        @endpush
    @endif
@endsection
