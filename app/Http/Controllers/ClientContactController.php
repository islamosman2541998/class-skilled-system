<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveClientContactRequest;
use App\Models\Client;
use App\Models\ClientContact;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ClientContactController extends Controller
{
    public function store(SaveClientContactRequest $request, Client $client): RedirectResponse
    {
        abort_unless($client->isVisibleTo($request->user()), 404);

        $client->contacts()->create($request->validated());

        return redirect()->route('clients.show', [$client, 'tab' => 'contacts'])->with('success', __('app.saved'));
    }

    public function update(SaveClientContactRequest $request, Client $client, ClientContact $contact): RedirectResponse
    {
        $this->ensureOwned($request, $client, $contact);

        $contact->update($request->validated());

        return redirect()->route('clients.show', [$client, 'tab' => 'contacts'])->with('success', __('app.saved'));
    }

    public function destroy(Request $request, Client $client, ClientContact $contact): RedirectResponse
    {
        Gate::authorize('clients.update');
        $this->ensureOwned($request, $client, $contact);

        $contact->delete();

        return redirect()->route('clients.show', [$client, 'tab' => 'contacts'])->with('success', __('table.deleted', ['count' => 1]));
    }

    private function ensureOwned(Request $request, Client $client, ClientContact $contact): void
    {
        abort_unless($contact->client_id === $client->id && $client->isVisibleTo($request->user()), 404);
    }
}
