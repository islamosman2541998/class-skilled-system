<?php

namespace App\Services;

use App\Actions\Leads\ReceiveWebsiteLead;
use App\Http\Requests\Api\WebsiteLeadRequest;
use App\Services\Uploads\UploadValidator;
use App\Settings\IntegrationSettings;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

/**
 * "Fetch requests" (spec §10): pulls quote requests the website could not deliver, creates the
 * missing leads, then tells the website which ones arrived. Never throws; the outcome is stored
 * in the integration settings.
 */
class WebsiteSync
{
    public function __construct(
        private readonly IntegrationSettings $settings,
        private readonly ReceiveWebsiteLead $receive,
        private readonly UploadValidator $uploads,
    ) {}

    public function fetch(): int
    {
        $base = rtrim((string) $this->settings->website_url, '/');
        $count = 0;

        try {
            abort_if($base === '' || blank($this->settings->outgoing_key), 422, __('leads.website.not_configured'));

            $response = $this->client()->get($base.'/api/system/quote-requests/unsynced')->throw();
            $received = [];

            foreach ((array) $response->json('data', []) as $payload) {
                $validator = Validator::make((array) $payload, WebsiteLeadRequest::payloadRules());

                if ($validator->fails()) {
                    continue;
                }

                $files = $this->download((array) ($payload['files'] ?? []), $base);

                try {
                    $result = $this->receive->handle($validator->validated(), $files);
                    $received[] = $result['lead']->external_id;
                    $count += $result['created'] ? 1 : 0;
                } finally {
                    foreach ($files as $file) {
                        @unlink($file['path']);
                    }
                }
            }

            if ($received !== []) {
                $this->client()->post($base.'/api/system/quote-requests/synced', ['external_ids' => $received])->throw();
            }

            $this->record('ok', $count);
        } catch (Throwable $e) {
            Log::warning('Website sync failed: '.$e->getMessage());
            $this->record(mb_substr($e->getMessage() ?: get_class($e), 0, 190), $count);
        }

        return $count;
    }

    private function client(): PendingRequest
    {
        return Http::withHeaders(['X-Api-Key' => (string) $this->settings->outgoing_key, 'Accept' => 'application/json'])->timeout(20);
    }

    /**
     * Downloads attachment URLs that belong to the website's own host, within the size limit.
     *
     * @param  list<mixed>  $urls
     * @return list<array{path: string, name: string}>
     */
    private function download(array $urls, string $base): array
    {
        $host = parse_url($base, PHP_URL_HOST);
        $files = [];

        foreach (array_slice($urls, 0, 20) as $url) {
            if (! is_string($url) || parse_url($url, PHP_URL_SCHEME) !== 'https' || parse_url($url, PHP_URL_HOST) !== $host) {
                continue;
            }

            $path = tempnam(sys_get_temp_dir(), 'web');
            $response = $this->client()->sink($path)->get($url);

            if (! $response->successful() || filesize($path) > $this->uploads->largestBytes()) {
                @unlink($path);

                continue;
            }

            $files[] = ['path' => $path, 'name' => basename((string) parse_url($url, PHP_URL_PATH)) ?: 'file'];
        }

        return $files;
    }

    private function record(string $status, int $count): void
    {
        $this->settings->last_sync_at = now()->toIso8601String();
        $this->settings->last_sync_status = $status;
        $this->settings->last_sync_count = $count;
        $this->settings->save();
    }
}
