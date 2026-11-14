<?php

namespace App\Http\Controllers;

use App\Actions\AttachUploads;
use App\Enums\ClientType;
use App\Enums\LookupType;
use App\Http\Requests\SaveClientRequest;
use App\Http\Requests\StoreAttachmentsRequest;
use App\Models\Client;
use App\Models\Lookup;
use App\Support\Like;
use App\Support\Phone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;

class ClientController extends Controller
{
    /** Tabs of the client page; a tab whose module is not built yet is skipped. */
    private const TABS = [
        'overview' => null,
        'contacts' => null,
        'leads' => 'leads.index',
        'visits' => 'site-visits.index',
        'quotations' => 'quotations.index',
        'projects' => 'projects.index',
        'invoices' => 'invoices.index',
        'attachments' => null,
        'activity' => null,
    ];

    /**
     * Figures on the client page added by modules: key => closure returning the formatted value.
     *
     * @var array<string, \Closure(Client): string>
     */
    public static array $figureProviders = [];

    /**
     * Blade partials (receiving $client) with header buttons added by modules, such as "new request".
     *
     * @var list<string>
     */
    public static array $actionViews = [];

    public function index(): View
    {
        Gate::authorize('clients.view');

        return view('clients.index');
    }

    public function create(): View
    {
        Gate::authorize('clients.create');

        return $this->form(new Client(['type' => ClientType::Individual]));
    }

    public function store(SaveClientRequest $request): RedirectResponse
    {
        $client = $this->save($request, new Client(['created_by' => $request->user()->id]));

        return redirect()->route('clients.show', $client)->with('success', __('app.saved'));
    }

    /** The quick-add modal used inside other forms (spec §5.3). */
    public function quickStore(SaveClientRequest $request): JsonResponse
    {
        $client = $this->save($request, new Client(['created_by' => $request->user()->id]));

        return response()->json(['id' => $client->id, 'name' => $client->name, 'message' => __('app.saved')], 201);
    }

    public function show(Request $request, Client $client): View
    {
        Gate::authorize('clients.view');
        $this->ensureVisible($request, $client);

        $tabs = collect(self::TABS)
            ->filter(fn (?string $route) => $route === null || Route::has($route))
            ->keys()
            ->mapWithKeys(fn (string $tab) => [$tab => __('clients.tabs.'.$tab)])
            ->all();
        $active = array_key_exists((string) $request->query('tab'), $tabs) ? (string) $request->query('tab') : 'overview';

        $client->load(['contacts', 'emirate', 'area', 'source']);

        return view('clients.show', ['client' => $client, 'tabs' => $tabs, 'active' => $active, 'figures' => $this->figures($client)]);
    }

    public function edit(Request $request, Client $client): View
    {
        Gate::authorize('clients.update');
        $this->ensureVisible($request, $client);

        return $this->form($client->load('contacts'));
    }

    public function update(SaveClientRequest $request, Client $client): RedirectResponse
    {
        $this->ensureVisible($request, $client);
        $this->save($request, $client);

        return redirect()->route('clients.show', $client)->with('success', __('app.saved'));
    }

    /** Options for remote client selects in other forms: [{value, text}]. */
    public function options(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('clients.view') || $request->user()->can('leads.create') || $request->user()->can('quotations.create'), 403);

        $term = (string) $request->query('q', '');
        $query = Client::query()->visibleTo($request->user());

        if ($term !== '') {
            $query->where(function ($inner) use ($term) {
                $inner->orWhere(fn ($q) => Like::any($q, ['name_ar', 'name_en', 'phone'], $term));

                foreach (Phone::searchVariants($term) as $digits) {
                    $inner->orWhere(fn ($q) => Like::any($q, ['phone_normalized'], $digits));
                }
            });
        }

        return response()->json($query->orderBy('name_ar')->limit(20)->get()
            ->map(fn (Client $client) => ['value' => $client->id, 'text' => $client->name.' · '.$client->phone])->values());
    }

    /** Clients with the same phone, however it was written (spec §8.1). */
    public function duplicates(Request $request): JsonResponse
    {
        Gate::authorize('clients.view');

        $matches = Client::withSamePhone((string) $request->query('phone'), $request->integer('ignore') ?: null)
            ->visibleTo($request->user())
            ->limit(5)
            ->get();

        return response()->json($matches->map(fn (Client $client) => [
            'id' => $client->id,
            'name' => $client->name,
            'url' => route('clients.show', $client),
        ])->values());
    }

    public function attach(StoreAttachmentsRequest $request, Client $client, AttachUploads $attach): RedirectResponse
    {
        Gate::authorize('clients.update');
        $this->ensureVisible($request, $client);

        $attach->handle($client, $request->validated('uploads'), $request->user(), $request->meta());

        return back()->with('success', __('uploads.attached'));
    }

    private function save(SaveClientRequest $request, Client $client): Client
    {
        return DB::transaction(function () use ($request, $client) {
            $client->fill($request->clientData())->save();

            if ($request->has('contacts') || $request->has('contacts_present')) {
                $client->contacts()->delete();

                foreach ($request->contacts() as $contact) {
                    $client->contacts()->create($contact);
                }
            }

            return $client;
        });
    }

    private function form(Client $client): View
    {
        return view('clients.form', [
            'client' => $client,
            'emirates' => Lookup::options(LookupType::Emirate),
            'areas' => Lookup::query()->ofType(LookupType::Area)->active()->orderBy('sort_order')->get(),
            'sources' => Lookup::options(LookupType::LeadSource),
        ]);
    }

    /**
     * Summary figures shown on the client page. Later stages add projects, invoiced and collected.
     *
     * @return array<string, string>
     */
    private function figures(Client $client): array
    {
        $figures = ['contacts' => (string) $client->contacts->count()];

        // A provider returns an empty string when the user may not see its figure.
        foreach (self::$figureProviders as $key => $provider) {
            if (($value = $provider($client)) !== '') {
                $figures[$key] = $value;
            }
        }

        return $figures;
    }

    private function ensureVisible(Request $request, Client $client): void
    {
        abort_unless($client->isVisibleTo($request->user()), 404);
    }
}
