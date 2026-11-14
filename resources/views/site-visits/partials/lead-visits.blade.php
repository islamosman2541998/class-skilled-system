@can('site_visits.view')
    @php
        $visits = $lead->siteVisits()->visibleTo(auth()->user())->with('engineer')->latest('scheduled_at')->get();
    @endphp
    @if ($visits->isNotEmpty())
        <x-panel :title="__('visits.visits')">
            <ul class="contact-list">
                @foreach ($visits as $visit)
                    <li>
                        <div>
                            <a href="{{ route('site-visits.show', $visit) }}" class="fw-semibold"><bdi dir="ltr">{{ $visit->number }}</bdi></a>
                            <div class="text-muted small"><bdi dir="ltr">{{ fmt_datetime($visit->scheduled_at) }}</bdi> · {{ $visit->engineer->name }}</div>
                        </div>
                        <x-status-badge :color="$visit->status->color()">{{ $visit->status->label() }}</x-status-badge>
                    </li>
                @endforeach
            </ul>
        </x-panel>
    @endif
@endcan
