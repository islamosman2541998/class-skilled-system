@extends('layouts.app')

@php
    $editing = $variation->exists;
    $title = $editing ? __('projects.variations.edit').' '.$variation->number : __('projects.variations.new');
@endphp

@section('title', $title)

@section('content')
    <x-page-header :title="$title" :breadcrumbs="[__('projects.projects') => route('projects.index'), $project->number => route('projects.show', [$project, 'tab' => 'variations'])]" />

    <form method="POST" action="{{ $editing ? route('projects.variations.update', [$project, $variation]) : route('projects.variations.store', $project) }}" data-dirty-check novalidate data-variation-form>
        @csrf
        @if ($editing)
            @method('PUT')
        @endif

        <x-panel :title="$project->title">
            <x-field name="description" :label="__('projects.variations.description')" required>
                <textarea id="description" name="description" @class(['form-control', 'is-invalid' => $errors->has('description')]) rows="3" required>{{ old('description', $variation->description) }}</textarea>
            </x-field>
        </x-panel>

        <x-panel :title="__('projects.variations.items')">
            <p class="form-text mt-0">{{ __('projects.variations.items_hint') }}</p>
            <div class="repeater" data-rows>
                @foreach ($items as $i => $item)
                    @include('variations.partials.item-row', ['i' => $i, 'item' => $item])
                @endforeach
            </div>
            <template data-row-template>
                @include('variations.partials.item-row', ['i' => '__I__', 'item' => ['quantity' => '1', 'unit_price' => '0']])
            </template>
            @error('items')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            <div class="payment-template-footer">
                <button type="button" class="btn btn-outline btn-sm" data-row-add><x-icon name="plus" :size="16" /> {{ __('projects.variations.add_item') }}</button>
                <span class="ms-auto fw-semibold">{{ __('projects.variations.subtotal') }}: <bdi dir="ltr" data-subtotal></bdi></span>
            </div>
        </x-panel>

        <x-sticky-actions>
            <button type="submit" class="btn btn-primary">{{ __('app.save') }}</button>
            <a href="{{ route('projects.show', [$project, 'tab' => 'variations']) }}" class="btn btn-outline">{{ __('app.cancel') }}</a>
        </x-sticky-actions>
    </form>

    @push('scripts')
        <script type="module">
            const form = document.querySelector('[data-variation-form]');
            const rows = form.querySelector('[data-rows]');
            let next = 10000;
            const number = (value) => parseFloat(String(value).replace(/,/g, '')) || 0;

            // Display only; the server computes the stored totals.
            const recalc = () => {
                let subtotal = 0;
                rows.querySelectorAll('[data-repeater-row]').forEach((row) => {
                    const line = Math.round(number(row.querySelector('[data-qty]').value) * number(row.querySelector('[data-price]').value) * 100) / 100;
                    row.querySelector('[data-line]').textContent = line.toFixed(2);
                    subtotal += line;
                });
                form.querySelector('[data-subtotal]').textContent = subtotal.toFixed(2);
            };

            form.addEventListener('input', recalc);
            form.addEventListener('click', (event) => {
                if (event.target.closest('[data-row-add]')) {
                    rows.insertAdjacentHTML('beforeend', form.querySelector('template[data-row-template]').innerHTML.replaceAll('__I__', String(next++)));
                }
                if (event.target.closest('[data-row-remove]') && rows.children.length > 1) {
                    event.target.closest('[data-repeater-row]').remove();
                }
                recalc();
            });
            recalc();
        </script>
    @endpush
@endsection
