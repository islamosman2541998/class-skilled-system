@extends('layouts.app')

@section('title', __('price_items.price_items'))

@section('content')
    <x-page-header :title="__('price_items.price_items')">
        <x-slot:actions>
            @can('price_items.create')
                <button type="button" class="btn btn-outline" data-bs-toggle="modal" data-bs-target="#price-import"><x-icon name="file-import" :size="16" /> {{ __('price_items.import') }}</button>
                <button type="button" class="btn btn-primary" data-price-new><x-icon name="plus" :size="16" /> {{ __('price_items.new_item') }}</button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    @if (session('import_errors'))
        <div class="alert alert-warning">
            <ul class="mb-0 ps-3">
                @foreach (session('import_errors') as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <livewire:tables.price-items-table />

    @can('price_items.create')
        @push('modals')
            <div class="modal fade" id="price-modal" tabindex="-1" aria-labelledby="price-modal-title" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <form class="modal-content" method="POST" novalidate data-ajax data-ajax-refresh
                        data-store-url="{{ route('price-items.store') }}" data-update-url="{{ route('price-items.update', '__ID__') }}"
                        data-title-new="{{ __('price_items.new_item') }}" data-title-edit="{{ __('price_items.edit_item') }}">
                        @csrf
                        <input type="hidden" name="_method" value="POST">
                        <div class="modal-header">
                            <h2 class="modal-title" id="price-modal-title">{{ __('price_items.new_item') }}</h2>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('app.cancel') }}"></button>
                        </div>
                        <div class="modal-body">
                            <x-field name="description_ar" :label="__('price_items.description_ar')" for="pi-description-ar" required>
                                <textarea id="pi-description-ar" name="description_ar" class="form-control" rows="2" required dir="rtl"></textarea>
                            </x-field>
                            <x-field name="description_en" :label="__('price_items.description_en')" for="pi-description-en" :hint="__('settings.name_en_hint')">
                                <textarea id="pi-description-en" name="description_en" class="form-control" rows="2" dir="ltr"></textarea>
                            </x-field>
                            <div class="form-grid">
                                <x-field name="service_id" :label="__('price_items.service')" for="pi-service">
                                    <select id="pi-service" name="service_id" class="form-select">
                                        <option value=""></option>
                                        @foreach ($services as $service)
                                            <option value="{{ $service->id }}">{{ $service->name }}</option>
                                        @endforeach
                                    </select>
                                </x-field>
                                <x-field name="unit_id" :label="__('price_items.unit')" for="pi-unit" required>
                                    <select id="pi-unit" name="unit_id" class="form-select" required>
                                        <option value=""></option>
                                        @foreach ($units as $unit)
                                            <option value="{{ $unit->id }}">{{ $unit->name }}</option>
                                        @endforeach
                                    </select>
                                </x-field>
                                <x-field name="default_price" :label="__('price_items.price')" for="pi-price" required>
                                    <input id="pi-price" name="default_price" class="form-control text-end" dir="ltr" inputmode="decimal" required>
                                </x-field>
                            </div>
                            <input type="hidden" name="is_active" value="0">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" name="is_active" value="1" id="pi-active" checked>
                                <label class="form-check-label" for="pi-active">{{ __('users.active') }}</label>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline" data-bs-dismiss="modal">{{ __('app.cancel') }}</button>
                            <button type="submit" class="btn btn-primary">{{ __('app.save') }}</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="modal fade" id="price-import" tabindex="-1" aria-labelledby="price-import-title" aria-hidden="true">
                <div class="modal-dialog">
                    <form class="modal-content" method="POST" action="{{ route('price-items.import') }}" enctype="multipart/form-data">
                        @csrf
                        <div class="modal-header">
                            <h2 class="modal-title" id="price-import-title">{{ __('price_items.import') }}</h2>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('app.cancel') }}"></button>
                        </div>
                        <div class="modal-body">
                            <p class="form-text mt-0">{{ __('price_items.import_hint') }}</p>
                            <x-field name="file" :label="__('price_items.import_file')" for="pi-file" required>
                                <input id="pi-file" type="file" name="file" class="form-control" accept=".xlsx,.csv" required>
                            </x-field>
                            <a href="{{ route('price-items.import-template') }}">{{ __('price_items.download_template') }}</a>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline" data-bs-dismiss="modal">{{ __('app.cancel') }}</button>
                            <button type="submit" class="btn btn-primary">{{ __('price_items.import') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        @endpush

        @push('scripts')
            <script type="module">
                const modalElement = document.getElementById('price-modal');
                const form = modalElement.querySelector('form');
                const open = (row) => {
                    form.reset();
                    form.querySelectorAll('[data-error-for]').forEach((node) => { node.textContent = ''; });
                    form.querySelectorAll('.is-invalid').forEach((node) => node.classList.remove('is-invalid'));
                    form.action = row ? form.dataset.updateUrl.replace('__ID__', row.id) : form.dataset.storeUrl;
                    form.querySelector('[name="_method"]').value = row ? 'PUT' : 'POST';
                    modalElement.querySelector('.modal-title').textContent = row ? form.dataset.titleEdit : form.dataset.titleNew;

                    if (row) {
                        ['description_ar', 'description_en', 'service_id', 'unit_id', 'default_price'].forEach((name) => {
                            form.elements.namedItem(name).value = row[name] ?? '';
                        });
                        form.querySelector('#pi-active').checked = !!row.is_active;
                    }

                    window.bootstrap.Modal.getOrCreateInstance(modalElement).show();
                };

                document.addEventListener('click', (event) => {
                    const edit = event.target.closest('[data-price-edit]');

                    if (edit) {
                        open(JSON.parse(edit.dataset.priceEdit));
                    } else if (event.target.closest('[data-price-new]')) {
                        open(null);
                    }
                });

                modalElement.addEventListener('shown.bs.modal', () => form.querySelector('#pi-description-ar').focus());
            </script>
        @endpush
    @endcan
@endsection
