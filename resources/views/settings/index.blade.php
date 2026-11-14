@extends('layouts.app')

@section('title', __('settings.settings'))

@php
    $icons = ['company' => 'building', 'appearance' => 'palette', 'finance' => 'coins', 'uploads' => 'cloud-upload', 'system' => 'adjustments'];
    $extra = [
        ['route' => 'settings.integration', 'label' => 'settings.groups.integration', 'icon' => 'plug-connected', 'permission' => 'settings.manage'],
        ['route' => 'settings.stage-templates', 'label' => 'settings.groups.stage_templates', 'icon' => 'list-check', 'permission' => 'settings.manage'],
        ['route' => 'settings.templates', 'label' => 'settings.groups.templates', 'icon' => 'file-text', 'permission' => 'settings.manage'],
        ['route' => 'settings.numbering', 'label' => 'settings.groups.numbering', 'icon' => 'hash', 'permission' => 'settings.manage'],
        ['route' => 'settings.notifications', 'label' => 'settings.groups.notifications', 'icon' => 'bell', 'permission' => 'settings.manage'],
        ['route' => 'translations.index', 'label' => 'settings.groups.translations', 'icon' => 'language', 'permission' => 'translations.manage'],
        ['route' => 'users.index', 'label' => 'users.users', 'icon' => 'users', 'permission' => 'users.view'],
        ['route' => 'roles.index', 'label' => 'users.roles', 'icon' => 'shield-lock', 'permission' => 'roles.view'],
        ['route' => 'activity.index', 'label' => 'settings.groups.activity', 'icon' => 'history', 'permission' => 'activity.view'],
        ['route' => 'design.index', 'label' => 'design.title', 'icon' => 'layout', 'permission' => 'settings.manage'],
    ];
@endphp

@section('content')
    <x-page-header :title="__('settings.settings')" />

    <div class="tile-grid">
        @foreach ($groups as $group)
            <a class="tile" href="{{ route('settings.edit', $group) }}">
                <x-icon :name="$icons[$group]" :size="20" />
                <span class="tile-title">{{ __('settings.groups.'.$group) }}</span>
                <span class="tile-text">{{ __('settings.group_hints.'.$group) }}</span>
            </a>
        @endforeach

        @foreach ($extra as $item)
            @if (Route::has($item['route']) && auth()->user()->can($item['permission']))
                <a class="tile" href="{{ route($item['route']) }}">
                    <x-icon :name="$item['icon']" :size="20" />
                    <span class="tile-title">{{ __($item['label']) }}</span>
                </a>
            @endif
        @endforeach
    </div>

    @can('lookups.view')
        <h2 class="section-title">{{ __('settings.lists_title') }}</h2>
        <div class="tile-grid tile-grid-compact">
            @foreach ($lists as $list)
                <a class="tile tile-row" href="{{ route('lookups.index', $list->value) }}">
                    <x-icon name="list-details" :size="18" />
                    <span class="tile-title">{{ $list->label() }}</span>
                </a>
            @endforeach
        </div>
    @endcan
@endsection
