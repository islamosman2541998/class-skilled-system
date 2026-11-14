<x-panel :title="__('clients.contact_data')">
    <dl class="detail-list">
        <div><dt>{{ __('app.phone') }}</dt><dd><bdi dir="ltr">{{ $client->phone }}</bdi></dd></div>
        @if ($client->phone2)
            <div><dt>{{ __('clients.phone2') }}</dt><dd><bdi dir="ltr">{{ $client->phone2 }}</bdi></dd></div>
        @endif
        @if ($client->whatsapp)
            <div><dt>{{ __('clients.whatsapp') }}</dt><dd><bdi dir="ltr">{{ $client->whatsapp }}</bdi></dd></div>
        @endif
        @if ($client->email)
            <div><dt>{{ __('app.email') }}</dt><dd><bdi dir="ltr">{{ $client->email }}</bdi></dd></div>
        @endif
        @if ($client->trn)
            <div><dt>{{ __('clients.trn') }}</dt><dd><bdi dir="ltr">{{ $client->trn }}</bdi></dd></div>
        @endif
        @if ($client->emirate)
            <div><dt>{{ __('clients.emirate') }}</dt><dd>{{ $client->emirate->name }}@if ($client->area){{ __('app.comma') }}{{ $client->area->name }}@endif</dd></div>
        @endif
        @if ($client->address)
            <div><dt>{{ __('clients.address') }}</dt><dd>{{ $client->address }}</dd></div>
        @endif
        @if ($client->source)
            <div><dt>{{ __('clients.source') }}</dt><dd>{{ $client->source->name }}</dd></div>
        @endif
        <div><dt>{{ __('table.created_at') }}</dt><dd><bdi dir="ltr">{{ fmt_date($client->created_at) }}</bdi></dd></div>
    </dl>
    @if ($client->notes)
        <p class="mt-3 mb-0 text-pre">{{ $client->notes }}</p>
    @endif
</x-panel>
