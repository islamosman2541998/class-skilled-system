@props(['title' => null])
<section {{ $attributes->class(['panel']) }}>
    @if ($title || isset($tools))
        <header class="panel-header">
            <h2 class="panel-title">{{ $title }}</h2>
            @isset($tools)
                <div class="d-flex align-items-center gap-2">{{ $tools }}</div>
            @endisset
        </header>
    @endif
    <div class="panel-body">{{ $slot }}</div>
</section>
