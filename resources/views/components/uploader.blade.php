{{-- One upload control for the whole system (spec §5.4). Emits hidden "{name}[]" inputs holding transfer ids. --}}
@props(['name' => 'uploads', 'multiple' => true, 'accept' => 'image,video,file', 'camera' => false])
@php
    $validator = app(\App\Services\Uploads\UploadValidator::class);
    $settings = app(\App\Settings\UploadSettings::class);
    $kinds = collect(explode(',', $accept))->map(fn ($kind) => \App\Enums\UploadKind::from(trim($kind)))->all();
    $config = [
        'process' => route('uploads.store'),
        'base' => url('/uploads'),
        'accept' => $validator->acceptList($kinds),
        'maxBytes' => $validator->largestBytes(),
        'chunkBytes' => $settings->chunk_size_mb * 1048576,
        'maxDimension' => $settings->image_max_dimension,
        'camera' => (bool) $camera,
    ];
@endphp
<div {{ $attributes->class(['uploader']) }}>
    <input type="file" id="{{ $name }}-input" data-uploader="{{ json_encode($config) }}" name="{{ $name }}[]" @if ($multiple) multiple @endif>
</div>

@pushOnce('scripts')
    @vite('resources/js/uploader.js')
@endPushOnce
