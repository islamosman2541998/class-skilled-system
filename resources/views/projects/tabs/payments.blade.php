{{-- Payment schedule of a project (spec §8.8). --}}
@php
    $installments = $project->installments()->with('stage')->get();
    $stages = $project->stages()->get();
    $difference = \App\Services\InstallmentSchedule::difference($project);
    $canEdit = auth()->user()->can('projects.update');
@endphp

<x-panel :title="__('projects.installments.title')" class="panel-flush">
    @if ($canEdit)
        <x-slot:tools>
            <button type="button" class="btn btn-primary btn-sm" data-installment-new><x-icon name="plus" :size="16" /> {{ __('projects.installments.new') }}</button>
        </x-slot:tools>
    @endif

    <div class="p-3 pb-0">
        @if (bccomp($difference, '0', 2) !== 0)
            <div class="alert alert-warning mb-3">{{ __('projects.installments.mismatch', ['amount' => money($difference)]) }}</div>
        @elseif ($installments->isNotEmpty())
            <p class="text-success small mb-3">{{ __('projects.installments.matches') }}</p>
        @endif
    </div>

    @if ($installments->isEmpty())
        <x-empty-state :message="__('projects.installments.empty')" icon="calendar-dollar" />
    @else
        <div class="table-responsive">
            <table class="table q-view mb-0">
                <thead>
                    <tr>
                        <th scope="col">{{ __('projects.installments.label_ar') }}</th>
                        <th scope="col" class="num">{{ __('projects.installments.percent') }}</th>
                        <th scope="col" class="num">{{ __('projects.installments.amount') }}</th>
                        <th scope="col">{{ __('projects.installments.due') }}</th>
                        <th scope="col">{{ __('projects.status') }}</th>
                        @if ($canEdit)<th scope="col"><span class="visually-hidden">{{ __('table.actions') }}</span></th>@endif
                    </tr>
                </thead>
                <tbody>
                    @foreach ($installments as $row)
                        <tr>
                            <td>{{ $row->label() }}</td>
                            <td class="num"><bdi dir="ltr">{{ $row->percent !== null ? rtrim(rtrim($row->percent, '0'), '.').'%' : '' }}</bdi></td>
                            <td class="num"><bdi dir="ltr">{{ money($row->amount) }}</bdi></td>
                            <td>
                                @if ($row->due_type === \App\Enums\InstallmentDueType::Stage)
                                    {{ __('projects.installments.stage') }}: {{ $row->stage?->name }}
                                @else
                                    <bdi dir="ltr">{{ $row->due_date ? fmt_date($row->due_date) : '' }}</bdi>
                                @endif
                            </td>
                            <td>
                                <x-status-badge :color="$row->status->color()">{{ $row->status->label() }}</x-status-badge>
                                @includeIf('projects.partials.installment-invoice-action', ['row' => $row, 'project' => $project])
                            </td>
                            @if ($canEdit)
                                <td class="dt-actions">
                                    @unless ($row->status->isLocked())
                                        <div class="dt-row-actions">
                                            <button type="button" class="btn-icon" aria-label="{{ __('projects.installments.edit') }}" data-installment-edit="{{ json_encode([
                                                'id' => $row->id, 'label_ar' => $row->label_ar, 'label_en' => $row->label_en, 'percent' => $row->percent,
                                                'amount' => $row->amount, 'due_type' => $row->due_type->value, 'due_date' => $row->due_date?->format('Y-m-d'), 'stage_id' => $row->stage_id,
                                            ]) }}"><x-icon name="pencil" /></button>
                                            <form method="POST" action="{{ route('projects.installments.destroy', [$project, $row]) }}" data-confirm="{{ __('projects.installments.delete') }}" data-confirm-danger>
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn-icon btn-icon-danger" aria-label="{{ __('table.delete') }}"><x-icon name="trash" /></button>
                                            </form>
                                        </div>
                                    @endunless
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="q-grand">
                        <th scope="row">{{ __('projects.installments.total') }}</th>
                        <td></td>
                        <td class="num"><bdi dir="ltr">{{ money($installments->sum('amount')) }}</bdi></td>
                        <td colspan="{{ $canEdit ? 3 : 2 }}"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    @endif
</x-panel>

@if ($canEdit)
    @push('modals')
        <div class="modal fade" id="installment-modal" tabindex="-1" aria-labelledby="installment-modal-title" aria-hidden="true">
            <div class="modal-dialog">
                <form class="modal-content" method="POST" novalidate
                    data-store-url="{{ route('projects.installments.store', $project) }}" data-update-url="{{ route('projects.installments.update', [$project, '__ID__']) }}"
                    data-title-new="{{ __('projects.installments.new') }}" data-title-edit="{{ __('projects.installments.edit') }}">
                    @csrf
                    <input type="hidden" name="_method" value="POST">
                    <div class="modal-header">
                        <h2 class="modal-title" id="installment-modal-title">{{ __('projects.installments.new') }}</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('app.cancel') }}"></button>
                    </div>
                    <div class="modal-body">
                        <x-field name="label_ar" :label="__('projects.installments.label_ar')" for="in-label-ar" required>
                            <input id="in-label-ar" name="label_ar" class="form-control" maxlength="190" required dir="rtl">
                        </x-field>
                        <x-field name="label_en" :label="__('projects.installments.label_en')" for="in-label-en">
                            <input id="in-label-en" name="label_en" class="form-control" maxlength="190" dir="ltr">
                        </x-field>
                        <p class="form-text">{{ __('projects.installments.percent_or_amount') }}</p>
                        <div class="form-grid">
                            <x-field name="percent" :label="__('projects.installments.percent')" for="in-percent">
                                <input id="in-percent" name="percent" class="form-control text-end" dir="ltr" inputmode="decimal">
                            </x-field>
                            <x-field name="amount" :label="__('projects.installments.amount')" for="in-amount">
                                <input id="in-amount" name="amount" class="form-control text-end" dir="ltr" inputmode="decimal">
                            </x-field>
                            <x-field name="due_type" :label="__('projects.installments.due_type')" for="in-due-type" required>
                                <select id="in-due-type" name="due_type" class="form-select">
                                    @foreach (\App\Enums\InstallmentDueType::cases() as $type)
                                        <option value="{{ $type->value }}">{{ $type->label() }}</option>
                                    @endforeach
                                </select>
                            </x-field>
                            <x-field name="due_date" :label="__('projects.installments.due_date')" for="in-due-date" data-due="date">
                                <input id="in-due-date" name="due_date" type="date" class="form-control" dir="ltr">
                            </x-field>
                            <x-field name="stage_id" :label="__('projects.installments.stage')" for="in-stage" data-due="stage">
                                <select id="in-stage" name="stage_id" class="form-select">
                                    <option value=""></option>
                                    @foreach ($stages as $stage)
                                        <option value="{{ $stage->id }}">{{ $stage->name }}</option>
                                    @endforeach
                                </select>
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
            const modalElement = document.getElementById('installment-modal');
            const form = modalElement.querySelector('form');
            const dueType = form.elements.namedItem('due_type');
            const syncDue = () => form.querySelectorAll('[data-due]').forEach((field) => { field.hidden = field.dataset.due !== dueType.value; });
            const percent = form.elements.namedItem('percent');
            const amount = form.elements.namedItem('amount');

            percent.addEventListener('input', () => { if (percent.value) amount.value = ''; });
            amount.addEventListener('input', () => { if (amount.value) percent.value = ''; });
            dueType.addEventListener('change', syncDue);

            const open = (row) => {
                form.reset();
                form.action = row ? form.dataset.updateUrl.replace('__ID__', row.id) : form.dataset.storeUrl;
                form.querySelector('[name="_method"]').value = row ? 'PUT' : 'POST';
                modalElement.querySelector('.modal-title').textContent = row ? form.dataset.titleEdit : form.dataset.titleNew;
                if (row) {
                    ['label_ar', 'label_en', 'due_type', 'due_date', 'stage_id'].forEach((name) => { form.elements.namedItem(name).value = row[name] ?? ''; });
                    percent.value = row.percent ?? '';
                    amount.value = row.percent ? '' : (row.amount ?? '');
                }
                syncDue();
                window.bootstrap.Modal.getOrCreateInstance(modalElement).show();
            };

            document.addEventListener('click', (event) => {
                const edit = event.target.closest('[data-installment-edit]');
                if (edit) open(JSON.parse(edit.dataset.installmentEdit));
                if (event.target.closest('[data-installment-new]')) open(null);
            });
        </script>
    @endpush
@endif
