{{--
    Files of one record (spec §5.4): grid with image preview, video player and PDF preview, filtered in
    the browser by title, kind and category. "action" is the record's attach route; without it the grid is read-only.
--}}
@props(['model', 'action' => null])
@php
    $items = $model->getMedia('attachments')->sortByDesc('created_at')->values();
    $posters = $model->getMedia('posters')->keyBy(fn ($poster) => $poster->getCustomProperty('media_id'));
    $uploaders = \App\Models\User::withTrashed()->whereIn('id', $items->map->getCustomProperty('uploaded_by')->filter()->unique())->pluck('name', 'id');
    $categories = \App\Models\Lookup::options(\App\Enums\LookupType::AttachmentCategory);
    $categoryNames = \App\Models\Lookup::withTrashed()->whereIn('id', $items->map->getCustomProperty('category_id')->filter()->unique())->get()->keyBy('id');
    $canUpdate = auth()->user()?->can($model->attachmentPermission().'.update') ?? false;
@endphp

<x-panel :title="__('uploads.attachments')" class="attachments" data-attachments>
    @if ($items->isNotEmpty())
        <div class="attachments-toolbar">
            <input type="search" class="form-control" data-filter="title" placeholder="{{ __('uploads.search') }}" aria-label="{{ __('uploads.search') }}">
            <select class="form-select" data-filter="kind" aria-label="{{ __('uploads.kind') }}">
                <option value="">{{ __('uploads.all_kinds') }}</option>
                @foreach (\App\Enums\UploadKind::cases() as $kind)
                    <option value="{{ $kind->value }}">{{ $kind->label() }}</option>
                @endforeach
            </select>
            @if ($categories->isNotEmpty())
                <select class="form-select" data-filter="category" aria-label="{{ __('uploads.category') }}">
                    <option value="">{{ __('uploads.all_categories') }}</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </select>
            @endif
        </div>

        <ul class="attachments-grid">
            @foreach ($items as $media)
                @php
                    $kind = $media->getCustomProperty('kind', 'file');
                    $hasThumb = $media->hasGeneratedConversion('thumb') || ($posters->get($media->id)?->hasGeneratedConversion('thumb') ?? false);
                    $category = $categoryNames->get($media->getCustomProperty('category_id'));
                @endphp
                <li class="attachment" data-item data-title="{{ mb_strtolower($media->name) }}" data-kind="{{ $kind }}" data-category="{{ $media->getCustomProperty('category_id') }}">
                    <button type="button" class="attachment-preview" data-view
                        data-src="{{ route('media.show', $media) }}" data-kind="{{ $kind }}" data-mime="{{ $media->mime_type }}"
                        data-poster="{{ $kind === 'video' && $hasThumb ? route('media.thumb', $media) : '' }}"
                        data-name="{{ $media->name }}" aria-label="{{ __('uploads.open') }}">
                        @if ($hasThumb)
                            <img src="{{ route('media.thumb', $media) }}" alt="" loading="lazy">
                        @else
                            <x-icon :name="$kind === 'video' ? 'movie' : 'file-text'" :size="32" />
                        @endif
                        @if ($kind === 'video')
                            <span class="attachment-badge"><x-icon name="player-play" :size="14" /></span>
                        @endif
                    </button>
                    <div class="attachment-body">
                        <div class="attachment-title" data-name>{{ $media->name }}</div>
                        <div class="attachment-meta">
                            @if ($category)
                                <span>{{ $category->name }}</span>
                            @endif
                            <span>{{ $uploaders->get($media->getCustomProperty('uploaded_by')) }}</span>
                            <span><bdi dir="ltr">{{ fmt_date($media->created_at) }}</bdi></span>
                            <span><bdi dir="ltr">{{ $media->human_readable_size }}</bdi></span>
                        </div>
                    </div>
                    <div class="dropdown attachment-actions">
                        <button type="button" class="btn btn-icon" data-bs-toggle="dropdown" aria-expanded="false" aria-label="{{ __('table.actions') }}">
                            <x-icon name="dots" />
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="{{ route('media.show', $media) }}?download=1" download>{{ __('uploads.download') }}</a></li>
                            @if ($canUpdate)
                                <li><button type="button" class="dropdown-item" data-rename="{{ route('media.update', $media) }}">{{ __('uploads.rename') }}</button></li>
                                <li><button type="button" class="dropdown-item text-danger" data-delete="{{ route('media.destroy', $media) }}">{{ __('uploads.delete') }}</button></li>
                            @endif
                        </ul>
                    </div>
                </li>
            @endforeach
        </ul>
        <p class="text-muted small d-none" data-no-match>{{ __('table.empty') }}</p>
    @else
        <x-empty-state :message="__('uploads.empty')" icon="paperclip" />
    @endif

    @if ($canUpdate && $action)
        <form method="POST" action="{{ $action }}" class="attachments-form" novalidate>
            @csrf
            <x-field name="uploads" :label="__('uploads.add_files')" for="uploads-input">
                <x-uploader name="uploads" />
            </x-field>
            <div class="form-grid">
                <x-field name="title" :label="__('uploads.title')" for="attachment-title" :hint="__('app.optional')">
                    <input id="attachment-title" name="title" class="form-control" maxlength="190">
                </x-field>
                @if ($categories->isNotEmpty())
                    <x-field name="category_id" :label="__('uploads.category')" for="attachment-category">
                        <select id="attachment-category" name="category_id" class="form-select">
                            <option value=""></option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}">{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </x-field>
                @endif
            </div>
            <button type="submit" class="btn btn-primary">{{ __('uploads.attach') }}</button>
        </form>
    @endif
</x-panel>

@pushOnce('modals')
    <div class="modal fade" id="attachment-viewer" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title" data-viewer-title></h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('app.cancel') }}"></button>
                </div>
                <div class="modal-body attachment-viewer" data-viewer-body></div>
            </div>
        </div>
    </div>
@endPushOnce

@pushOnce('scripts')
    @vite('resources/js/attachments.js')
@endPushOnce
