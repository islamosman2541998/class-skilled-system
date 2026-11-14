<?php

namespace App\Actions\Leads;

use App\Enums\DocumentType;
use App\Enums\LookupType;
use App\Enums\NotificationType;
use App\Exceptions\InvalidUploadException;
use App\Models\Lead;
use App\Models\Lookup;
use App\Services\Notifier;
use App\Services\NumberGenerator;
use App\Services\Uploads\UploadValidator;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Turns a website quote request into a lead (spec §10). The website's request number is the
 * dedupe key: a repeat (a retry after a timeout) returns the existing lead and changes nothing.
 */
class ReceiveWebsiteLead
{
    public function __construct(
        private readonly NumberGenerator $numbers,
        private readonly UploadValidator $validator,
        private readonly Notifier $notifier,
    ) {}

    /**
     * @param  array<string, mixed>  $data  validated payload
     * @param  list<array{path: string, name: string}>  $files  local files to attach
     * @return array{lead: Lead, created: bool}
     *
     * @throws ValidationException
     */
    public function handle(array $data, array $files = []): array
    {
        if (($existing = Lead::withTrashed()->where('external_id', $data['external_id'])->first()) !== null) {
            return ['lead' => $existing, 'created' => false];
        }

        $kinds = [];
        foreach ($files as $index => $file) {
            try {
                $kinds[$index] = $this->validator->kind($file['path'], $file['name']);
            } catch (InvalidUploadException $e) {
                throw ValidationException::withMessages(["files.{$index}" => $e->getMessage()]);
            }
        }

        try {
            $lead = DB::transaction(function () use ($data, $files, $kinds) {
                $lead = new Lead($this->attributes($data));
                $lead->external_id = $data['external_id'];
                $lead->number = $this->numbers->next(DocumentType::Lead);
                $lead->status_id = Lead::statusIdFor('new');
                $lead->save();

                foreach ($files as $index => $file) {
                    $lead->addMedia($file['path'])
                        ->preservingOriginal()
                        ->usingName(mb_substr((string) preg_replace('/\.[^.]+$/u', '', $file['name']), 0, 190) ?: $file['name'])
                        ->usingFileName(Str::uuid().'.'.UploadValidator::extension($file['name']))
                        ->withCustomProperties(['kind' => $kinds[$index]->value, 'category_id' => null, 'uploaded_by' => null])
                        ->toMediaCollection('attachments', 'private');
                }

                return $lead;
            });
        } catch (UniqueConstraintViolationException) {
            // The same request arrived twice at the same moment; the first one won.
            return ['lead' => Lead::withTrashed()->where('external_id', $data['external_id'])->firstOrFail(), 'created' => false];
        }

        $this->notifier->toRoles(NotificationType::LeadFromWebsite, $lead, ['number' => $lead->number], route('leads.show', $lead));

        return ['lead' => $lead, 'created' => true];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        $service = $this->lookupId(LookupType::Service, $data['service'] ?? null);
        $emirate = $this->lookupId(LookupType::Emirate, $data['emirate'] ?? null);

        // Values the system cannot match are kept in the description, so nothing sent is lost.
        $unmatched = array_filter([
            $service === null && filled($data['service'] ?? null) ? __('leads.service').': '.$data['service'] : null,
            $emirate === null && filled($data['emirate'] ?? null) ? __('leads.emirate').': '.$data['emirate'] : null,
            filled($data['preferred_contact'] ?? null) ? __('leads.website.preferred_contact').': '.$data['preferred_contact'] : null,
        ]);

        return [
            'contact_name' => $data['name'],
            'contact_phone' => $data['phone'],
            'contact_email' => $data['email'] ?? null,
            'service_id' => $service,
            'emirate_id' => $emirate,
            'facility_type' => $data['facility_type'] ?? null,
            'project_state' => $data['project_state'] ?? null,
            'area_text' => $data['area_text'] ?? null,
            'area_m2' => $data['area_m2'] ?? null,
            'expected_start' => $data['start_when'] ?? null,
            'description' => trim(implode("\n", array_filter([$data['description'] ?? null, ...$unmatched]))) ?: null,
            'source_id' => Lookup::query()->ofType(LookupType::LeadSource)->where('code', 'website')->value('id'),
        ];
    }

    private function lookupId(LookupType $type, ?string $name): ?int
    {
        if (blank($name)) {
            return null;
        }

        $name = mb_strtolower(trim($name));

        return Lookup::query()->ofType($type)->get(['id', 'name_ar', 'name_en'])
            ->first(fn (Lookup $lookup) => mb_strtolower($lookup->name_ar) === $name || mb_strtolower((string) $lookup->name_en) === $name)
            ?->id;
    }
}
