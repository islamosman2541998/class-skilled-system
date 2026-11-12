@props(['title', 'breadcrumbs' => []])
{{-- breadcrumbs: [label => url|null]; the current page is the title and is not repeated. --}}
<div class="page-header">
    <div>
        @if ($breadcrumbs)
            <nav aria-label="{{ __('app.breadcrumb') }}">
                <ol class="breadcrumb">
                    @foreach ($breadcrumbs as $label => $url)
                        <li class="breadcrumb-item">
                            @if ($url)
                                <a href="{{ $url }}">{{ $label }}</a>
                            @else
                                {{ $label }}
                            @endif
                        </li>
                    @endforeach
                </ol>
            </nav>
        @endif
        <h1 class="page-title">{{ $title }}</h1>
    </div>
    @isset($actions)
        <div class="page-actions">{{ $actions }}</div>
    @endisset
</div>
