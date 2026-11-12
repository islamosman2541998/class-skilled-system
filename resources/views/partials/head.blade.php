<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@hasSection('title')@yield('title') · @endif{{ __('app.name') }}</title>
@foreach (\App\Support\Theme::fonts() as $font)
    @vite("resources/css/font-{$font}.css")
@endforeach
@vite($dir === 'rtl' ? 'resources/scss/app-rtl.scss' : 'resources/scss/app-ltr.scss')
@include('partials.theme')
<script>window.I18N = @json(__('js'));</script>
@vite('resources/js/app.js')
