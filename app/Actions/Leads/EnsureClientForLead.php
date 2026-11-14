<?php

namespace App\Actions\Leads;

use App\Enums\ClientType;
use App\Models\Client;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * On the first conversion of a lead (visit or quotation) the client must exist (spec §8.2):
 * the linked client, else a client with the same phone, else a new one from the contact data.
 */
class EnsureClientForLead
{
    public function handle(Lead $lead, User $by): Client
    {
        if ($lead->client_id !== null && ($client = Client::query()->find($lead->client_id)) !== null) {
            return $client;
        }

        return DB::transaction(function () use ($lead, $by) {
            $client = Client::withSamePhone($lead->contact_phone)->first() ?? Client::query()->create([
                'type' => ClientType::Individual,
                'name_ar' => (string) $lead->contact_name,
                'phone' => (string) $lead->contact_phone,
                'email' => $lead->contact_email,
                'emirate_id' => $lead->emirate_id,
                'area_id' => $lead->area_id,
                'source_id' => $lead->source_id,
                'created_by' => $by->id,
            ]);

            $lead->client_id = $client->id;
            $lead->save();

            return $client;
        });
    }
}
