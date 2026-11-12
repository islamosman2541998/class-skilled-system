<div class="dropdown">
    <button type="button" class="btn btn-outline btn-sm" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
        <x-icon name="columns-3" :size="16" />
        {{ __('table.columns') }}
    </button>
    <div class="dropdown-menu dropdown-menu-end dt-menu" wire:ignore.self>
        @foreach ($columns as $column)
            <label class="dropdown-item dt-menu-check" wire:key="col-{{ $column->key }}">
                <input type="checkbox" class="form-check-input" wire:click="toggleColumn('{{ $column->key }}')" @checked(! in_array($column->key, $hiddenKeys, true))>
                <span>{{ $column->label() }}</span>
            </label>
        @endforeach
    </div>
</div>
