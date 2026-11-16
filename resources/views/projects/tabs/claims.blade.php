{{-- Claims of a project billed by claims (spec §5.9, §8.14): the claims, the setup and the contract items. --}}
@php
    $items = $project->items()->with('unit')->get();
    $units = \App\Models\Lookup::options(\App\Enums\LookupType::Unit);
    $locked = $project->hasLockedClaims();
    $canEdit = auth()->user()->can('projects.update') && ! $locked;
    $sectionOf = fn ($item) => app()->getLocale() === 'en' && filled($item->section_en) ? $item->section_en : $item->section_ar;
    $previous = null;
    $retained = (string) $project->claims()->whereIn('status', \App\Enums\ClaimStatus::countedValues())->sum('retention_amount');
    $retentionInvoice = \App\Models\Invoice::query()->where('project_id', $project->id)->where('retention_release', true)
        ->where('status', '!=', \App\Enums\InvoiceStatus::Cancelled->value)->latest('id')->first();
@endphp

<x-panel :title="__('site.claims.claims')" class="panel-flush">
    <x-slot:tools>
        <span class="text-muted small">{{ __('site.claims.retention_to_date') }}: <bdi dir="ltr">{{ money($retained) }}</bdi></span>
        @if ($retentionInvoice)
            @can('invoices.view')
                <a href="{{ route('invoices.show', $retentionInvoice) }}" class="btn btn-outline btn-sm">{{ __('site.retention.invoice') }}</a>
            @endcan
        @elseif ($project->handover()->exists() && bccomp($retained, '0', 2) > 0)
            @can('invoices.create')
                <form method="POST" action="{{ route('projects.retention-invoice', $project) }}" data-confirm="{{ __('site.retention.confirm', ['amount' => money($retained)]) }}">
                    @csrf
                    <button type="submit" class="btn btn-outline btn-sm">{{ __('site.retention.create') }}</button>
                </form>
            @endcan
        @endif
        @can('claims.create')
            <form method="POST" action="{{ route('claims.store', $project) }}">
                @csrf
                <button type="submit" class="btn btn-primary btn-sm"><x-icon name="plus" :size="16" /> {{ __('site.claims.new') }}</button>
            </form>
        @endcan
    </x-slot:tools>
    <livewire:tables.claims-table :project-id="$project->id" />
</x-panel>

@if ($locked)
    <div class="alert alert-info">{{ __('site.setup.locked') }}</div>
@endif

<x-panel :title="__('site.setup.title')">
    @if ($canEdit)
        <form method="POST" action="{{ route('projects.claim-setup.update', $project) }}" novalidate>
            @csrf
            @method('PUT')
            <div class="form-grid">
                <x-field name="retention_percent" :label="__('site.setup.retention_percent')" :hint="__('site.setup.retention_percent_hint')" required>
                    <input id="retention_percent" name="retention_percent" value="{{ old('retention_percent', $project->retention_percent) }}" @class(['form-control text-end', 'is-invalid' => $errors->has('retention_percent')]) required inputmode="decimal" dir="ltr">
                </x-field>
                <x-field name="contract_discount_percent" :label="__('site.setup.contract_discount_percent')" :hint="__('site.setup.contract_discount_percent_hint')" required>
                    <input id="contract_discount_percent" name="contract_discount_percent" value="{{ old('contract_discount_percent', $project->contract_discount_percent) }}" @class(['form-control text-end', 'is-invalid' => $errors->has('contract_discount_percent')]) required inputmode="decimal" dir="ltr">
                </x-field>
                <x-field name="advance_amount" :label="__('site.setup.advance_amount')" :hint="__('site.setup.advance_amount_hint')" required>
                    <input id="advance_amount" name="advance_amount" value="{{ old('advance_amount', $project->advance_amount) }}" @class(['form-control text-end', 'is-invalid' => $errors->has('advance_amount')]) required inputmode="decimal" dir="ltr">
                </x-field>
            </div>
            <div class="d-flex justify-content-end mt-3">
                <button type="submit" class="btn btn-primary">{{ __('app.save') }}</button>
            </div>
        </form>
    @else
        <dl class="detail-list mb-0">
            <div><dt>{{ __('site.setup.retention_percent') }}</dt><dd><bdi dir="ltr">{{ rtrim(rtrim($project->retention_percent, '0'), '.') }}%</bdi></dd></div>
            <div><dt>{{ __('site.setup.contract_discount_percent') }}</dt><dd><bdi dir="ltr">{{ rtrim(rtrim($project->contract_discount_percent, '0'), '.') }}%</bdi></dd></div>
            <div><dt>{{ __('site.setup.advance_amount') }}</dt><dd><bdi dir="ltr">{{ money($project->advance_amount) }}</bdi></dd></div>
        </dl>
    @endif
</x-panel>

<x-panel :title="__('site.items.title')" class="panel-flush">
    @if ($canEdit)
        <x-slot:tools>
            <button type="button" class="btn btn-outline btn-sm" data-item-new><x-icon name="plus" :size="16" /> {{ __('site.items.new') }}</button>
        </x-slot:tools>
    @endif

    @if ($items->isEmpty())
        <x-empty-state :message="__('site.items.empty')" icon="list-check" />
    @else
        <div class="table-responsive">
            <table class="table q-view mb-0">
                <thead>
                    <tr>
                        <th scope="col">{{ __('site.items.description') }}</th>
                        <th scope="col">{{ __('site.items.unit') }}</th>
                        <th scope="col" class="num">{{ __('site.items.quantity') }}</th>
                        <th scope="col" class="num">{{ __('site.items.unit_price') }}</th>
                        <th scope="col" class="num">{{ __('site.items.line_total') }}</th>
                        @if ($canEdit)<th scope="col"><span class="visually-hidden">{{ __('table.actions') }}</span></th>@endif
                    </tr>
                </thead>
                <tbody>
                    @foreach ($items as $item)
                        @php($section = $sectionOf($item))
                        @if (filled($section) && $section !== $previous)
                            <tr class="q-section-row"><th scope="rowgroup" colspan="{{ $canEdit ? 6 : 5 }}">{{ $section }}</th></tr>
                        @endif
                        @php($previous = $section)
                        <tr>
                            <td>{{ app()->getLocale() === 'en' && filled($item->description_en) ? $item->description_en : $item->description_ar }}</td>
                            <td>{{ $item->unit?->name }}</td>
                            <td class="num"><bdi dir="ltr">{{ rtrim(rtrim($item->quantity, '0'), '.') }}</bdi></td>
                            <td class="num"><bdi dir="ltr">{{ money($item->unit_price) }}</bdi></td>
                            <td class="num"><bdi dir="ltr">{{ money($item->line_total) }}</bdi></td>
                            @if ($canEdit)
                                <td class="dt-actions">
                                    <div class="dt-row-actions">
                                        <button type="button" class="btn-icon" aria-label="{{ __('site.items.edit') }}" data-item-edit="{{ json_encode([
                                            'id' => $item->id, 'section_ar' => $item->section_ar, 'section_en' => $item->section_en, 'description_ar' => $item->description_ar,
                                            'description_en' => $item->description_en, 'unit_id' => $item->unit_id, 'quantity' => rtrim(rtrim($item->quantity, '0'), '.'), 'unit_price' => $item->unit_price,
                                        ]) }}"><x-icon name="pencil" /></button>
                                        <form method="POST" action="{{ route('projects.items.destroy', [$project, $item]) }}" data-confirm="{{ __('site.items.delete') }}" data-confirm-danger>
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn-icon btn-icon-danger" aria-label="{{ __('table.delete') }}"><x-icon name="trash" /></button>
                                        </form>
                                    </div>
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="q-grand">
                        <th scope="row" colspan="4">{{ __('site.items.total') }}</th>
                        <td class="num"><bdi dir="ltr">{{ money($items->reduce(fn (string $sum, $item) => bcadd($sum, (string) $item->line_total, 2), '0')) }}</bdi></td>
                        @if ($canEdit)<td></td>@endif
                    </tr>
                </tfoot>
            </table>
        </div>
    @endif
</x-panel>

@if ($canEdit)
    @push('modals')
        <div class="modal fade" id="item-modal" tabindex="-1" aria-labelledby="item-modal-title" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <form class="modal-content" method="POST" novalidate
                    data-store-url="{{ route('projects.items.store', $project) }}" data-update-url="{{ route('projects.items.update', [$project, '__ID__']) }}"
                    data-title-new="{{ __('site.items.new') }}" data-title-edit="{{ __('site.items.edit') }}">
                    @csrf
                    <input type="hidden" name="_method" value="POST">
                    <div class="modal-header">
                        <h2 class="modal-title" id="item-modal-title">{{ __('site.items.new') }}</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('app.cancel') }}"></button>
                    </div>
                    <div class="modal-body">
                        <div class="form-grid">
                            <x-field name="section_ar" :label="__('site.items.section_ar')" for="it-section-ar">
                                <input id="it-section-ar" name="section_ar" class="form-control" maxlength="190" dir="rtl">
                            </x-field>
                            <x-field name="section_en" :label="__('site.items.section_en')" for="it-section-en">
                                <input id="it-section-en" name="section_en" class="form-control" maxlength="190" dir="ltr">
                            </x-field>
                            <x-field name="description_ar" :label="__('site.items.description_ar')" for="it-description-ar" required>
                                <textarea id="it-description-ar" name="description_ar" class="form-control" rows="2" maxlength="2000" required dir="rtl"></textarea>
                            </x-field>
                            <x-field name="description_en" :label="__('site.items.description_en')" for="it-description-en">
                                <textarea id="it-description-en" name="description_en" class="form-control" rows="2" maxlength="2000" dir="ltr"></textarea>
                            </x-field>
                            <x-field name="unit_id" :label="__('site.items.unit')" for="it-unit">
                                <select id="it-unit" name="unit_id" class="form-select">
                                    <option value=""></option>
                                    @foreach ($units as $unit)
                                        <option value="{{ $unit->id }}">{{ $unit->name }}</option>
                                    @endforeach
                                </select>
                            </x-field>
                            <x-field name="quantity" :label="__('site.items.quantity')" for="it-quantity" required>
                                <input id="it-quantity" name="quantity" class="form-control text-end" required inputmode="decimal" dir="ltr">
                            </x-field>
                            <x-field name="unit_price" :label="__('site.items.unit_price')" for="it-unit-price" required>
                                <input id="it-unit-price" name="unit_price" class="form-control text-end" required inputmode="decimal" dir="ltr">
                            </x-field>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline" data-bs-dismiss="modal">{{ __('app.cancel') }}</button>
                        <button type="submit" class="btn btn-primary">{{ __('app.save') }}</button>
                    </div>
                </form>
            </div>
        </div>
    @endpush

    @push('scripts')
        <script type="module">
            const modalElement = document.getElementById('item-modal');
            const form = modalElement.querySelector('form');
            const fields = ['section_ar', 'section_en', 'description_ar', 'description_en', 'unit_id', 'quantity', 'unit_price'];

            const open = (row) => {
                form.reset();
                form.action = row ? form.dataset.updateUrl.replace('__ID__', row.id) : form.dataset.storeUrl;
                form.querySelector('[name="_method"]').value = row ? 'PUT' : 'POST';
                modalElement.querySelector('.modal-title').textContent = row ? form.dataset.titleEdit : form.dataset.titleNew;
                if (row) fields.forEach((name) => { form.elements.namedItem(name).value = row[name] ?? ''; });
                window.bootstrap.Modal.getOrCreateInstance(modalElement).show();
            };

            document.addEventListener('click', (event) => {
                const edit = event.target.closest('[data-item-edit]');
                if (edit) open(JSON.parse(edit.dataset.itemEdit));
                if (event.target.closest('[data-item-new]')) open(null);
            });
        </script>
    @endpush
@endif
