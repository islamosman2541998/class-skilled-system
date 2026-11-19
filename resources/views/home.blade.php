@extends('layouts.app')

@section('title', __('app.home'))

@section('content')
    <x-page-header :title="__('app.home')">
        @if ($chartOptions !== [])
            <x-slot:actions>
                <button type="button" class="btn btn-outline" data-bs-toggle="modal" data-bs-target="#dashboard-widgets">
                    <x-icon name="adjustments-horizontal" :size="18" /> {{ __('home.customize_charts') }}
                </button>
            </x-slot:actions>
        @endif
    </x-page-header>

    @if ($figures !== [] || $chartOptions !== [])
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

        @if ($charts !== [])
            <div class="dashboard-section-heading">
                <div>
                    <h2>{{ __('home.analytics') }}</h2>
                    <p>{{ __('home.analytics_hint') }}</p>
                </div>
            </div>
            <div class="dashboard-charts">
                @foreach ($charts as $widget)
                    <x-panel :title="$widget['title']" @class(['dashboard-chart-card', 'is-wide' => $widget['type'] === 'bar'])>
                        <p class="dashboard-chart-description">{{ $widget['description'] }}</p>

                        @if ($widget['type'] === 'bar')
                            <x-bar-chart
                                :caption="$widget['title']"
                                :labels="array_column($widget['data'], 'label')"
                                :axis-labels="array_column($widget['data'], 'short')"
                                :series="[
                                    ['label' => __('home.revenue'), 'color' => 'var(--c-series-1)', 'values' => array_column($widget['data'], 'revenue')],
                                    ['label' => __('home.cost'), 'color' => 'var(--c-series-2)', 'values' => array_column($widget['data'], 'cost')],
                                ]"
                            />
                        @elseif ($widget['type'] === 'donut')
                            <x-donut-chart :caption="$widget['title']" :items="$widget['data']" :center-label="__('home.projects_total')" />
                        @else
                            <x-horizontal-bar-chart :caption="$widget['title']" :items="$widget['data']" />
                        @endif
                    </x-panel>
                @endforeach
            </div>
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

    @if ($chartOptions !== [])
        @push('modals')
            <div class="modal fade" id="dashboard-widgets" tabindex="-1" aria-labelledby="dashboard-widgets-title" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <form class="modal-content" method="POST" action="{{ route('dashboard.widgets.update') }}">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="from" value="{{ $period->from->toDateString() }}">
                        <input type="hidden" name="to" value="{{ $period->to->toDateString() }}">
                        <div class="modal-header">
                            <div>
                                <h2 class="modal-title" id="dashboard-widgets-title">{{ __('home.customize_charts') }}</h2>
                                <p class="text-muted small mb-0 mt-1">{{ __('home.customize_charts_hint') }}</p>
                            </div>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('app.cancel') }}"></button>
                        </div>
                        <div class="modal-body dashboard-widget-options">
                            @foreach ($chartOptions as $option)
                                <label class="dashboard-widget-option" for="widget-{{ $option['key'] }}">
                                    <span>
                                        <strong>{{ $option['title'] }}</strong>
                                        <small>{{ $option['description'] }}</small>
                                    </span>
                                    <span class="form-check form-switch m-0">
                                        <input class="form-check-input" type="checkbox" role="switch" name="visible_widgets[]" value="{{ $option['key'] }}" id="widget-{{ $option['key'] }}" @checked(! in_array($option['key'], $hiddenCharts, true))>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline" data-bs-dismiss="modal">{{ __('app.cancel') }}</button>
                            <button type="submit" class="btn btn-primary">{{ __('home.save_charts') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        @endpush
    @endif
@endsection
