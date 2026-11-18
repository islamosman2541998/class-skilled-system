<?php

namespace App\Http\Controllers;

use App\Settings\CompanySettings;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BrandingImageController extends Controller
{
    public function __invoke(string $filename): StreamedResponse
    {
        $path = 'branding/'.$filename;
        $company = app(CompanySettings::class);
        $publicImages = [$company->logo_path, $company->login_logo_path];
        $privateImages = [$company->stamp_path, $company->signature_path];

        abort_unless(
            in_array($path, $publicImages, true)
                || (auth()->user()?->can('settings.manage') && in_array($path, $privateImages, true)),
            Response::HTTP_NOT_FOUND,
        );

        $disk = Storage::disk('public');

        abort_unless($disk->exists($path), Response::HTTP_NOT_FOUND);

        return $disk->response($path, null, ['Cache-Control' => 'public, max-age=31536000, immutable']);
    }
}
