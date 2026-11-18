@php
    $company = app(\App\Settings\CompanySettings::class);
    $companyName = app()->getLocale() === 'ar' ? $company->name_ar : ($company->name_en ?: $company->name_ar);
@endphp
<aside class="app-sidebar" id="app-sidebar">
    <a @class(['sidebar-brand', 'has-logo' => $company->logo_path]) href="{{ route('home') }}">
        @if ($company->logo_path)
            <img src="{{ route('branding.show', ['filename' => basename($company->logo_path)]) }}" alt="{{ $companyName }}">
        @else
            <span class="brand-mark" aria-hidden="true">{{ __('app.brand_mark') }}</span>
            <span class="brand-name">{{ $companyName }}</span>
        @endif
    </a>

    <nav class="sidebar-nav" aria-label="{{ __('app.menu') }}">
        @foreach (\App\Support\Menu::items(auth()->user()) as $group)
            <div class="nav-group">
                @if ($group['key'] !== 'main')
                    <button type="button" class="nav-heading" data-bs-toggle="collapse" data-bs-target="#nav-{{ $group['key'] }}" aria-expanded="true" aria-controls="nav-{{ $group['key'] }}">
                        {{ __($group['label']) }}
                        <x-icon name="chevron-down" :size="14" />
                    </button>
                @endif
                <div class="collapse show" id="nav-{{ $group['key'] }}">
                    @foreach ($group['items'] as $item)
                        <a href="{{ $item['url'] }}" @class(['side-link', 'is-active' => $item['active']]) @if ($item['active']) aria-current="page" @endif title="{{ __($item['label']) }}">
                            <x-icon :name="$item['icon']" />
                            <span>{{ __($item['label']) }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        @endforeach
    </nav>
</aside>
<div class="sidebar-backdrop"></div>
