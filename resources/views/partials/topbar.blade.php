@php
    $user = auth()->user();
    $otherLocale = app()->getLocale() === 'ar' ? 'en' : 'ar';
    $initials = mb_strtoupper(mb_substr($user->name, 0, 1));
@endphp
<header class="app-topbar">
    <button type="button" class="btn-icon" data-sidebar-toggle aria-label="{{ __('app.menu') }}" aria-controls="app-sidebar">
        <x-icon name="menu-2" :size="20" />
    </button>

    <div class="topbar-search">
        @includeIf('partials.search-trigger')
    </div>

    <div class="topbar-actions">
        @includeIf('partials.quick-add')

        <form method="POST" action="{{ route('locale.switch', $otherLocale) }}">
            @csrf
            <button type="submit" class="btn-plain" lang="{{ $otherLocale }}">{{ $otherLocale === 'ar' ? __('app.arabic') : __('app.english') }}</button>
        </form>

        @includeIf('partials.notifications-bell')

        <div class="dropdown">
            <button type="button" class="user-chip" data-bs-toggle="dropdown" aria-expanded="false">
                <span class="avatar" aria-hidden="true">{{ $initials }}</span>
                <span class="user-name">{{ $user->name }}</span>
                <x-icon name="chevron-down" :size="14" />
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="{{ route('profile.edit') }}">{{ __('app.profile') }}</a></li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="dropdown-item">{{ __('app.logout') }}</button>
                    </form>
                </li>
            </ul>
        </div>
    </div>
</header>
