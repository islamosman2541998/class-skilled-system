{{-- Record tabs (spec §4.3). tabs: [key => label]; each tab links to ?tab=key so the page stays server-rendered. --}}
@props(['tabs', 'active'])
<nav {{ $attributes->class(['record-tabs']) }} aria-label="{{ __('app.sections') }}">
    @foreach ($tabs as $key => $label)
        <a href="{{ request()->fullUrlWithQuery(['tab' => $key]) }}" @class(['record-tab', 'is-active' => $key === $active]) @if ($key === $active) aria-current="page" @endif>{{ $label }}</a>
    @endforeach
</nav>
