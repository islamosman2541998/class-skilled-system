{{-- Add / edit one list row without leaving the page (spec §5.3). Filled by resources/js/ui/ajax-form.js. --}}
<div class="modal fade" id="lookup-modal" tabindex="-1" aria-labelledby="lookup-modal-title" aria-hidden="true">
    <div class="modal-dialog">
        <form class="modal-content" method="POST" novalidate data-ajax data-ajax-refresh
            data-store-url="{{ route('lookups.store', $type->value) }}"
            data-update-url="{{ route('lookups.update', [$type->value, '__ID__']) }}"
            data-title-new="{{ __('settings.new_row') }}" data-title-edit="{{ __('settings.edit_row') }}">
            @csrf
            <input type="hidden" name="_method" value="POST">

            <div class="modal-header">
                <h2 class="modal-title" id="lookup-modal-title">{{ __('settings.new_row') }}</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('app.cancel') }}"></button>
            </div>

            <div class="modal-body">
                @if ($type->parentType())
                    <x-field name="parent_id" :label="$type->parentType()->label()" for="lookup-parent" required>
                        <select id="lookup-parent" name="parent_id" class="form-select" required>
                            <option value=""></option>
                            @foreach ($parents as $parent)
                                <option value="{{ $parent->id }}">{{ $parent->name }}</option>
                            @endforeach
                        </select>
                    </x-field>
                @endif

                <x-field name="name_ar" :label="__('settings.name_ar')" for="lookup-name-ar" required>
                    <input id="lookup-name-ar" name="name_ar" class="form-control" required maxlength="120" dir="rtl">
                </x-field>

                <x-field name="name_en" :label="__('settings.name_en')" for="lookup-name-en" :hint="__('settings.name_en_hint')">
                    <input id="lookup-name-en" name="name_en" class="form-control" maxlength="120" dir="ltr">
                </x-field>

                @if ($type->hasColor())
                    <x-field name="color" :label="__('settings.color')" for="lookup-color">
                        <select id="lookup-color" name="color" class="form-select">
                            <option value=""></option>
                            @foreach (\App\Models\Lookup::COLORS as $color)
                                <option value="{{ $color }}">{{ __('settings.colors.'.$color) }}</option>
                            @endforeach
                        </select>
                    </x-field>
                @endif

                <input type="hidden" name="is_active" value="0">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch" name="is_active" value="1" id="lookup-active" checked>
                    <label class="form-check-label" for="lookup-active">{{ __('users.active') }}</label>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline" data-bs-dismiss="modal">{{ __('app.cancel') }}</button>
                <button type="submit" class="btn btn-primary">{{ __('app.save') }}</button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
    <script type="module">
        const modalElement = document.getElementById('lookup-modal');
        const form = modalElement.querySelector('form');
        const open = (row) => {
            form.reset();
            form.querySelectorAll('[data-error-for]').forEach((node) => { node.textContent = ''; });
            form.querySelectorAll('.is-invalid').forEach((node) => node.classList.remove('is-invalid'));

            form.action = row ? form.dataset.updateUrl.replace('__ID__', row.id) : form.dataset.storeUrl;
            form.querySelector('[name="_method"]').value = row ? 'PUT' : 'POST';
            modalElement.querySelector('.modal-title').textContent = row ? form.dataset.titleEdit : form.dataset.titleNew;

            if (row) {
                ['name_ar', 'name_en', 'parent_id', 'color'].forEach((name) => {
                    const input = form.elements.namedItem(name);
                    if (input) input.value = row[name] ?? '';
                });
                form.querySelector('#lookup-active').checked = !!row.is_active;
            }

            window.bootstrap.Modal.getOrCreateInstance(modalElement).show();
        };

        document.addEventListener('click', (event) => {
            const edit = event.target.closest('[data-lookup-edit]');

            if (edit) {
                open(JSON.parse(edit.dataset.lookupEdit));
            } else if (event.target.closest('[data-lookup-new]')) {
                open(null);
            }
        });

        modalElement.addEventListener('shown.bs.modal', () => form.querySelector('#lookup-name-ar').focus());
    </script>
@endpush
