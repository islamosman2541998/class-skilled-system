@php
    $flash = array_filter([
        'success' => session('success'),
        'error' => session('error'),
        'warning' => session('warning'),
        'info' => session('info'),
    ]);
@endphp
@if ($flash)
    <script type="application/json" id="flash-data">@json($flash)</script>
@endif
