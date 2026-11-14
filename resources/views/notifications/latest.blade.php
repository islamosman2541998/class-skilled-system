{{-- Fragment loaded into the topbar dropdown when it opens. --}}
@forelse ($notifications as $notification)
    <form method="POST" action="{{ route('notifications.read', $notification->id) }}">
        @csrf
        <button type="submit" @class(['notification-item', 'is-unread' => $notification->read_at === null])>
            <span class="notification-title">{{ \App\Notifications\SystemNotification::title($notification->data) }}</span>
            <span class="notification-time"><bdi dir="ltr">{{ fmt_datetime($notification->created_at) }}</bdi></span>
        </button>
    </form>
@empty
    <p class="notification-empty">{{ __('notifications.empty') }}</p>
@endforelse
