@extends('layouts.app')

@section('title', __('settings.groups.translations'))

@section('content')
    <x-page-header :title="__('settings.groups.translations')" :breadcrumbs="[__('settings.settings') => route('settings.index')]" />

    <x-panel class="panel-flush" data-translations
        data-update-url="{{ route('translations.update') }}" data-reset-url="{{ route('translations.reset') }}">
        <div class="translations-toolbar">
            <input type="search" class="form-control" data-t-search placeholder="{{ __('table.search') }}" aria-label="{{ __('table.search') }}">
            <select class="form-select" data-t-group aria-label="{{ __('settings.translation_group') }}">
                <option value="">{{ __('settings.all_groups') }}</option>
                @foreach ($groups as $group)
                    <option value="{{ $group }}">{{ __('settings.translation_groups.'.$group) }}</option>
                @endforeach
            </select>
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" role="switch" id="t-overridden" data-t-overridden>
                <label class="form-check-label" for="t-overridden">{{ __('settings.overridden_only') }}</label>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover translations-table">
                <thead>
                    <tr>
                        <th scope="col">{{ __('settings.translation_key') }}</th>
                        <th scope="col">{{ __('app.arabic') }}</th>
                        <th scope="col">{{ __('app.english') }}</th>
                        <th scope="col" class="dt-actions"><span class="visually-hidden">{{ __('table.actions') }}</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr data-row data-group="{{ $row['group'] }}" data-key="{{ $row['key'] }}" data-overridden="{{ $row['overridden'] ? '1' : '0' }}"
                            data-search="{{ mb_strtolower($row['group'].'.'.$row['key'].' '.$row['ar'].' '.$row['en']) }}">
                            <td class="t-key"><bdi dir="ltr">{{ $row['group'] }}.{{ $row['key'] }}</bdi>
                                <span @class(['badge text-bg-light t-flag', 'd-none' => ! $row['overridden']])>{{ __('settings.overridden') }}</span>
                            </td>
                            <td dir="rtl" data-ar>{{ $row['ar'] }}</td>
                            <td dir="ltr" data-en>{{ $row['en'] }}</td>
                            <td class="dt-actions">
                                <button type="button" class="btn-icon" data-t-edit aria-label="{{ __('settings.edit_translation') }}"><x-icon name="pencil" /></button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="text-muted p-3 m-0 d-none" data-t-empty>{{ __('table.empty') }}</p>
    </x-panel>

    @push('modals')
        <div class="modal fade" id="translation-modal" tabindex="-1" aria-labelledby="translation-modal-title" aria-hidden="true">
            <div class="modal-dialog">
                <form class="modal-content" novalidate data-t-form>
                    <div class="modal-header">
                        <h2 class="modal-title" id="translation-modal-title">{{ __('settings.edit_translation') }}</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('app.cancel') }}"></button>
                    </div>
                    <div class="modal-body">
                        <p class="form-text mt-0"><bdi dir="ltr" data-t-label></bdi></p>
                        <x-field name="ar" :label="__('app.arabic')" for="t-ar" required>
                            <textarea id="t-ar" name="ar" class="form-control" rows="3" dir="rtl" required maxlength="2000"></textarea>
                        </x-field>
                        <x-field name="en" :label="__('app.english')" for="t-en" required>
                            <textarea id="t-en" name="en" class="form-control" rows="3" dir="ltr" required maxlength="2000"></textarea>
                        </x-field>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline me-auto" data-t-reset>{{ __('settings.reset_translation') }}</button>
                        <button type="button" class="btn btn-outline" data-bs-dismiss="modal">{{ __('app.cancel') }}</button>
                        <button type="submit" class="btn btn-primary">{{ __('app.save') }}</button>
                    </div>
                </form>
            </div>
        </div>
    @endpush

    @push('scripts')
        @vite('resources/js/translations.js')
    @endpush
@endsection
