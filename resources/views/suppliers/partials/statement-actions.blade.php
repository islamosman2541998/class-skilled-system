{{-- Supplier statement: the report and its PDF (spec §9.2). --}}
@if (\App\Support\ReportRegistry::allows(auth()->user(), 'supplier-statement'))
    <a href="{{ route('reports.show', ['supplier-statement', 'f' => ['supplier_id' => $supplier->id]]) }}" class="btn btn-outline"><x-icon name="file-text" :size="16" /> {{ __('resources.statement.statement') }}</a>
    <div class="dropdown">
        <button type="button" class="btn btn-outline" data-bs-toggle="dropdown" aria-expanded="false"><x-icon name="printer" :size="16" /> {{ __('quotations.print') }} <x-icon name="chevron-down" :size="14" /></button>
        <ul class="dropdown-menu dropdown-menu-end">
            @foreach (['ar', 'en', 'both'] as $lang)
                <li><a class="dropdown-item" href="{{ route('suppliers.statement.pdf', [$supplier, 'lang' => $lang]) }}" target="_blank" rel="noopener">{{ __('quotations.print_'.$lang) }}</a></li>
            @endforeach
        </ul>
    </div>
@endif
