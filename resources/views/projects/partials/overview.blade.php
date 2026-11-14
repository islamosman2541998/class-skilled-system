<div class="row g-3">
    <div class="col-lg-7">
        <x-panel :title="__('projects.basic')">
            <dl class="detail-list">
                <div><dt>{{ __('projects.number') }}</dt><dd><bdi dir="ltr">{{ $project->number }}</bdi></dd></div>
                <div><dt>{{ __('projects.client') }}</dt><dd><a href="{{ route('clients.show', $project->client) }}">{{ $project->client->name }}</a></dd></div>
                @if ($project->quotation)
                    <div><dt>{{ __('projects.quotation') }}</dt><dd><a href="{{ route('quotations.show', $project->quotation) }}"><bdi dir="ltr">{{ $project->quotation->number }}</bdi></a></dd></div>
                @endif
                @if ($project->service)
                    <div><dt>{{ __('projects.service') }}</dt><dd>{{ $project->service->name }}</dd></div>
                @endif
                @if ($project->location || $project->emirate)
                    <div><dt>{{ __('projects.location') }}</dt><dd>{{ $project->location }} {{ $project->emirate?->name }}</dd></div>
                @endif
                <div><dt>{{ __('projects.billing_method') }}</dt><dd>{{ $project->billing_method->label() }}</dd></div>
                <div><dt>{{ __('projects.original_value') }}</dt><dd><bdi dir="ltr">{{ money($project->original_value) }}</bdi></dd></div>
                <div><dt>{{ __('projects.variations_total') }}</dt><dd><bdi dir="ltr">{{ money($project->variations_total) }}</bdi></dd></div>
                <div><dt>{{ __('projects.vat_rate') }}</dt><dd><bdi dir="ltr">{{ rtrim(rtrim($project->vat_rate, '0'), '.') }}%</bdi></dd></div>
                <div><dt>{{ __('projects.start_date') }}</dt><dd><bdi dir="ltr">{{ $project->start_date ? fmt_date($project->start_date) : '' }}</bdi></dd></div>
                <div><dt>{{ __('projects.planned_end_date') }}</dt><dd><bdi dir="ltr">{{ $project->planned_end_date ? fmt_date($project->planned_end_date) : '' }}</bdi></dd></div>
                @if ($project->actual_end_date)
                    <div><dt>{{ __('projects.actual_end_date') }}</dt><dd><bdi dir="ltr">{{ fmt_date($project->actual_end_date) }}</bdi></dd></div>
                @endif
            </dl>
            @if ($project->description)
                <p class="mt-3 mb-0 text-pre">{{ $project->description }}</p>
            @endif
        </x-panel>
    </div>
    <div class="col-lg-5">
        <x-panel :title="__('projects.members')">
            <ul class="contact-list">
                <li><span>{{ $project->manager->name }}</span><span class="text-muted small">{{ __('projects.manager') }}</span></li>
                @foreach ($project->members as $member)
                    <li><span>{{ $member->name }}</span></li>
                @endforeach
            </ul>
        </x-panel>
    </div>
</div>
