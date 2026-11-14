@extends('layouts.app')

@section('title', __('app.home'))

@section('content')
    <x-page-header :title="__('app.home')" />

    <div class="row g-3">
        <div class="col-lg-6">
            <x-panel :title="__('home.unread_notifications')">
                <x-slot:tools>
                    <a href="{{ route('notifications.index') }}" class="btn btn-outline btn-sm">{{ __('notifications.view_all') }}</a>
                </x-slot:tools>

                @forelse ($notifications as $notification)
                    <form method="POST" action="{{ route('notifications.read', $notification->id) }}">
                        @csrf
                        <button type="submit" class="notification-item is-unread">
                            <span class="notification-title">{{ \App\Notifications\SystemNotification::title($notification->data) }}</span>
                            <span class="notification-time"><bdi dir="ltr">{{ fmt_datetime($notification->created_at) }}</bdi></span>
                        </button>
                    </form>
                @empty
                    <x-empty-state :message="__('notifications.empty')" icon="bell" />
                @endforelse
            </x-panel>
        </div>

        <div class="col-lg-6">
            <x-panel :title="__('home.my_activity')">
                @if ($activities->isEmpty())
                    <x-empty-state :message="__('home.no_activity')" icon="history" />
                @else
                    <ul class="activity-list">
                        @foreach ($activities as $activity)
                            <li>
                                <span class="activity-event">{{ \App\Livewire\Tables\ActivityTable::eventLabel((string) $activity->event) }}</span>
                                <span class="activity-module">{{ \App\Livewire\Tables\ActivityTable::moduleLabel((string) $activity->log_name) }}</span>
                                <span class="activity-time"><bdi dir="ltr">{{ fmt_datetime($activity->created_at) }}</bdi></span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-panel>
        </div>
    </div>
@endsection
