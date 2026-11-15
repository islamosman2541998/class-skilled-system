{{-- The client statement and its PDF on the client page (spec §9.2). --}}
@if (\App\Support\ReportRegistry::allows(auth()->user(), 'statement'))
    <a href="{{ route('reports.show', ['statement', 'f' => ['client_id' => $client->id]]) }}" class="btn btn-outline"><x-icon name="chart-bar" :size="16" /> {{ __('reports.statement') }}</a>
    <div class="dropdown">
        <button type="button" class="btn btn-outline" data-bs-toggle="dropdown" aria-expanded="false"><x-icon name="printer" :size="16" /> {{ __('reports.statement_print') }} <x-icon name="chevron-down" :size="14" /></button>
        <ul class="dropdown-menu dropdown-menu-end">
            @foreach (['ar', 'en', 'both'] as $lang)
                <li><a class="dropdown-item" href="{{ route('clients.statement.pdf', [$client, 'lang' => $lang]) }}" target="_blank" rel="noopener">{{ __('quotations.print_'.$lang) }}</a></li>
            @endforeach
        </ul>
    </div>
@endif
