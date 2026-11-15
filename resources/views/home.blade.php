@extends('layouts.app')

@section('title', __('app.home'))

@section('content')
    <x-page-header :title="__('app.home')" />

    @if ($figures !== [] || $chart !== null)
        <form method="GET" action="{{ route('home') }}" class="dash-period" aria-label="{{ __('home.period') }}">
            <label class="form-label mb-0" for="dash-from">{{ __('home.from') }}</label>
            <input id="dash-from" type="date" name="from" value="{{ $period->from->toDateString() }}" class="form-control form-control-sm" dir="ltr">
            <label class="form-label mb-0" for="dash-to">{{ __('home.to') }}</label>
            <input id="dash-to" type="date" name="to" value="{{ $period->to->toDateString() }}" class="form-control form-control-sm" dir="ltr">
            <button type="submit" class="btn btn-primary btn-sm">{{ __('home.apply') }}</button>
            <a href="{{ route('home') }}" class="btn btn-outline btn-sm">{{ __('home.this_month') }}</a>
        </form>

        @foreach ($figures as $group => $items)
            <x-panel :title="__('home.groups.'.$group)">
                <div class="dash-figures">
                    @foreach ($items as $item)
                        @include('home.widgets.figure', ['item' => $item])
                    @endforeach
                </div>
            </x-panel>
        @endforeach

        @if ($chart !== null)
            <x-panel :title="__('home.revenue_vs_cost')">
                <x-bar-chart
                    :caption="__('home.revenue_vs_cost')"
                    :labels="array_column($chart, 'label')"
                    :axis-labels="array_column($chart, 'short')"
                    :series="[
                        ['label' => __('home.revenue'), 'color' => 'var(--c-series-1)', 'values' => array_column($chart, 'revenue')],
                        ['label' => __('home.cost'), 'color' => 'var(--c-series-2)', 'values' => array_column($chart, 'cost')],
                    ]"
                />
            </x-panel>
        @endif
    @endif

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
