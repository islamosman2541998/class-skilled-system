@php
    $unread = auth()->user()->unreadNotifications()->count();
@endphp
<div class="dropdown notifications-bell" data-notifications data-latest-url="{{ route('notifications.latest') }}" data-unread="{{ $unread }}">
    <button type="button" class="btn-icon" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" aria-label="{{ __('notifications.notifications') }}">
        <x-icon name="bell" :size="20" />
        @if ($unread > 0)
            <span class="bell-count"><bdi dir="ltr">{{ $unread > 99 ? '99+' : $unread }}</bdi></span>
        @endif
    </button>
    <div class="dropdown-menu dropdown-menu-end notifications-menu">
        <div class="notifications-menu-header">
            <span>{{ __('notifications.notifications') }}</span>
            @if ($unread > 0)
                <form method="POST" action="{{ route('notifications.read-all') }}">
                    @csrf
                    <button type="submit" class="btn-link-plain small">{{ __('notifications.mark_all_read') }}</button>
                </form>
            @endif
        </div>
        <div class="notifications-menu-body" data-notifications-body>
            <p class="notification-empty">{{ __('notifications.loading') }}</p>
        </div>
        <a class="notifications-menu-footer" href="{{ route('notifications.index') }}">{{ __('notifications.view_all') }}</a>
    </div>
</div>
