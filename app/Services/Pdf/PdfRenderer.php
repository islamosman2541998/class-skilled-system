<?php

namespace App\Services\Pdf;

use Illuminate\Support\Facades\File;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\Mpdf;

/**
 * Renders Blade views to PDF with mPDF: Arabic shaping, right-to-left layout, no server tools
 * (spec §3.3, §5.10). Language "ar" and "both" are laid out right to left.
 *
 * Font: Tajawal from resources/fonts/pdf (the Cairo files are not shipped, so Cairo falls back to
 * Tajawal — user decision 2026-11-14). Without those files mPDF's bundled XB Riyaz is used.
 */
class PdfRenderer
{
    public const LANGUAGES = ['ar', 'en', 'both'];

    /**
     * @param  array<string, mixed>  $data
     */
    public function render(string $view, array $data, string $language): string
    {
        $language = in_array($language, self::LANGUAGES, true) ? $language : 'ar';
        $html = view($view, [...$data, 'language' => $language])->render();

        $mpdf = new Mpdf($this->config($language));
        $mpdf->SetDirectionality($language === 'en' ? 'ltr' : 'rtl');
        $mpdf->WriteHTML($html);

        return $mpdf->Output('', 'S');
    }

    /**
     * @return array<string, mixed>
     */
    private function config(string $language): array
    {
        $tempDir = storage_path('app/mpdf');
        File::ensureDirectoryExists($tempDir);

        $fontDir = resource_path('fonts/pdf');
        $hasTajawal = is_file($fontDir.'/Tajawal-Regular.ttf') && is_file($fontDir.'/Tajawal-Bold.ttf');

        $defaults = (new ConfigVariables)->getDefaults();
        $fonts = (new FontVariables)->getDefaults();

        $config = [
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_top' => 14,
            'margin_bottom' => 16,
            'margin_left' => 12,
            'margin_right' => 12,
            'tempDir' => $tempDir,
            'default_font' => $hasTajawal ? 'tajawal' : 'xbriyaz',
            'autoScriptToLang' => true,
            'autoLangToFont' => false,
            'useSubstitutions' => true,
        ];

        if ($hasTajawal) {
            $config['fontDir'] = [...$defaults['fontDir'], $fontDir];
            $config['fontdata'] = $fonts['fontdata'] + [
                'tajawal' => [
                    'R' => 'Tajawal-Regular.ttf',
                    'B' => 'Tajawal-Bold.ttf',
                    'useOTL' => 0xFF,
                    'useKashida' => 75,
                ],
            ];
        }

        return $config;
    }
}
