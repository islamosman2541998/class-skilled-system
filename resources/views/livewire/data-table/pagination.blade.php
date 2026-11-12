@if ($rows->total() > 0)
    <div class="dt-footer">
        <div class="dt-footer-info">
            <span>{{ __('table.showing', ['from' => $rows->firstItem(), 'to' => $rows->lastItem(), 'total' => $rows->total()]) }}</span>
            <select class="form-select form-select-sm dt-per-page" wire:model.live.number="perPage" aria-label="{{ __('table.per_page') }}">
                @foreach (\App\Livewire\DataTable::PAGE_SIZES as $size)
                    <option value="{{ $size }}">{{ $size }}</option>
                @endforeach
            </select>
        </div>

        @if ($rows->hasPages())
            @php
                $current = $rows->currentPage();
                $last = $rows->lastPage();
                $pages = collect(range(1, $last))->filter(fn ($page) => $page === 1 || $page === $last || abs($page - $current) <= 1)->values();
            @endphp
            <nav aria-label="{{ __('table.pages') }}">
                <ul class="pagination pagination-sm mb-0">
                    <li @class(['page-item', 'disabled' => $rows->onFirstPage()])>
                        <button type="button" class="page-link" wire:click="previousPage" @disabled($rows->onFirstPage()) aria-label="{{ __('pagination.previous') }}">
                            <x-icon :name="app()->getLocale() === 'ar' ? 'chevron-right' : 'chevron-left'" :size="14" />
                        </button>
                    </li>
                    @foreach ($pages as $index => $page)
                        @if ($index > 0 && $page - $pages[$index - 1] > 1)
                            <li class="page-item disabled"><span class="page-link">…</span></li>
                        @endif
                        <li @class(['page-item', 'active' => $page === $current]) wire:key="page-{{ $page }}">
                            <button type="button" class="page-link" wire:click="gotoPage({{ $page }})" @if ($page === $current) aria-current="page" @endif>{{ $page }}</button>
                        </li>
                    @endforeach
                    <li @class(['page-item', 'disabled' => ! $rows->hasMorePages()])>
                        <button type="button" class="page-link" wire:click="nextPage" @disabled(! $rows->hasMorePages()) aria-label="{{ __('pagination.next') }}">
                            <x-icon :name="app()->getLocale() === 'ar' ? 'chevron-left' : 'chevron-right'" :size="14" />
                        </button>
                    </li>
                </ul>
            </nav>
        @endif
    </div>
@endif
