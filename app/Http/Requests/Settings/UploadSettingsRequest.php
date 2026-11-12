<?php

namespace App\Http\Requests\Settings;

use Illuminate\Validation\Rule;

class UploadSettingsRequest extends SettingsRequest
{
    /** Extensions that can run on a server or in a browser are never accepted as uploads. */
    public const FORBIDDEN = [
        'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'phar', 'cgi', 'pl', 'py', 'rb', 'sh', 'bash', 'bat', 'cmd', 'com',
        'exe', 'msi', 'dll', 'js', 'mjs', 'jsp', 'asp', 'aspx', 'htm', 'html', 'xhtml', 'svg', 'htaccess', 'jar', 'vbs', 'ps1',
    ];

    protected function prepareForValidation(): void
    {
        $this->merge([
            'image_types' => $this->splitList($this->input('image_types')),
            'video_types' => $this->splitList($this->input('video_types')),
            'file_types' => $this->splitList($this->input('file_types')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $extension = ['string', 'regex:/^[a-z0-9]{1,8}$/', Rule::notIn(self::FORBIDDEN)];

        return [
            'image_max_mb' => ['required', 'integer', 'between:1,1024'],
            'video_max_mb' => ['required', 'integer', 'between:1,1024'],
            'file_max_mb' => ['required', 'integer', 'between:1,1024'],
            'image_types' => ['required', 'array', 'min:1'],
            'image_types.*' => [...$extension, Rule::in(['jpg', 'jpeg', 'png', 'webp', 'gif'])],
            'video_types' => ['required', 'array', 'min:1'],
            'video_types.*' => [...$extension, Rule::in(['mp4', 'webm', 'mov'])],
            'file_types' => ['required', 'array', 'min:1'],
            'file_types.*' => $extension,
            'image_max_dimension' => ['required', 'integer', 'between:500,8000'],
            'chunk_size_mb' => ['required', 'integer', 'between:1,10'],
        ];
    }

    public function values(): array
    {
        $values = $this->validated();

        foreach (['image_max_mb', 'video_max_mb', 'file_max_mb', 'image_max_dimension', 'chunk_size_mb'] as $key) {
            $values[$key] = (int) $values[$key];
        }

        return $values;
    }
}
