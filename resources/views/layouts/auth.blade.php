@php
    $company = app(\App\Settings\CompanySettings::class);
    $companyName = app()->getLocale() === 'ar' ? $company->name_ar : ($company->name_en ?: $company->name_ar);
    $logo = $company->login_logo_path ?: $company->logo_path;
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ $dir }}">
<head>
    @include('partials.head')
</head>
<body>
    <main class="auth-page">
        <div class="auth-card">
            <div @class(['auth-brand', 'has-logo' => $logo])>
                @if ($logo)
                    <img src="{{ route('branding.show', ['filename' => basename($logo)]) }}" alt="{{ $companyName }}">
                @else
                    <span class="brand-mark" aria-hidden="true">{{ __('app.brand_mark') }}</span>
                @endif
            </div>

            @yield('content')
        </div>
    </main>
    @include('partials.flash')
</body>
</html>
