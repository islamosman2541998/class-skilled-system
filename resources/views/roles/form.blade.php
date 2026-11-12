@extends('layouts.app')

@php
    $editing = $role->exists;
    $title = $editing ? __('users.edit_role') : __('users.new_role');
    $checked = old('permissions', $granted);
@endphp

@section('title', $title)

@section('content')
    <x-page-header :title="$title" :breadcrumbs="[__('users.roles') => route('roles.index')]" />

    <form method="POST" action="{{ $editing ? route('roles.update', $role) : route('roles.store') }}" data-dirty-check novalidate>
        @csrf
        @if ($editing)
            @method('PUT')
        @endif

        <x-panel :title="__('users.role')">
            <div class="form-grid">
                <x-field name="label_ar" :label="__('users.label_ar')" required>
                    <input id="label_ar" name="label_ar" value="{{ old('label_ar', $role->label_ar) }}" @class(['form-control', 'is-invalid' => $errors->has('label_ar')]) required maxlength="80" dir="rtl">
                </x-field>

                <x-field name="label_en" :label="__('users.label_en')" :hint="__('app.optional')">
                    <input id="label_en" name="label_en" value="{{ old('label_en', $role->label_en) }}" @class(['form-control', 'is-invalid' => $errors->has('label_en')]) maxlength="80" dir="ltr">
                </x-field>

                <x-field name="scope" :label="__('users.scope')" required>
                    <select id="scope" name="scope" class="form-select" @disabled($role->is_system)>
                        <option value="all" @selected(old('scope', $role->scope) === 'all')>{{ __('users.scope_all') }}</option>
                        <option value="assigned" @selected(old('scope', $role->scope) === 'assigned')>{{ __('users.scope_assigned') }}</option>
                    </select>
                    @if ($role->is_system)
                        <input type="hidden" name="scope" value="all">
                    @endif
                </x-field>
            </div>
        </x-panel>

        <x-panel :title="__('users.permissions')">
            @if ($role->is_system)
                <p class="text-muted mb-0">{{ __('users.system_role_hint') }}</p>
            @else
                <div class="perm-matrix">
                    @foreach ($modules as $module => $actions)
                        <fieldset class="perm-row" data-perm-row>
                            <legend class="perm-module">
                                <span>{{ __('permissions.modules.'.$module) }}</span>
                                <label class="perm-all">
                                    <input type="checkbox" class="form-check-input" data-perm-all>
                                    {{ __('users.select_all') }}
                                </label>
                            </legend>
                            <div class="perm-actions">
                                @foreach ($actions as $action)
                                    <label class="perm-action">
                                        <input type="checkbox" class="form-check-input" name="permissions[]" value="{{ $module }}.{{ $action }}" @checked(in_array("{$module}.{$action}", $checked, true))>
                                        {{ __('permissions.actions.'.$action) }}
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>
                    @endforeach
                </div>
                @error('permissions.*')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
            @endif
        </x-panel>

        <div class="sticky-actions">
            <button type="submit" class="btn btn-primary">{{ __('app.save') }}</button>
            <a href="{{ route('roles.index') }}" class="btn btn-outline">{{ __('app.cancel') }}</a>
        </div>
    </form>
@endsection

@push('scripts')
    <script type="module">
        // "All" ticks or clears every permission of its row and follows manual changes.
        document.querySelectorAll('[data-perm-row]').forEach((row) => {
            const all = row.querySelector('[data-perm-all]');
            const boxes = [...row.querySelectorAll('input[name="permissions[]"]')];
            const sync = () => { all.checked = boxes.every((box) => box.checked); };

            all.addEventListener('change', () => boxes.forEach((box) => { box.checked = all.checked; }));
            boxes.forEach((box) => box.addEventListener('change', sync));
            sync();
        });
    </script>
@endpush
