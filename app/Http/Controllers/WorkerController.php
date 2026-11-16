<?php

namespace App\Http\Controllers;

use App\Enums\LookupType;
use App\Http\Requests\SaveWorkerRequest;
use App\Models\Lookup;
use App\Models\Worker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** Workers of the company or external (spec §8.17). */
class WorkerController extends Controller
{
    public function index(): View
    {
        Gate::authorize('workers.view');

        return view('workers.index');
    }

    public function create(): View
    {
        Gate::authorize('workers.create');

        return $this->form(new Worker(['is_company' => true, 'is_active' => true]));
    }

    public function store(SaveWorkerRequest $request): RedirectResponse
    {
        $worker = new Worker;
        $worker->fill($this->values($request));
        $worker->created_by = $request->user()->id;
        $worker->save();

        return redirect()->route('workers.index')->with('success', __('app.saved'));
    }

    public function edit(Worker $worker): View
    {
        Gate::authorize('workers.update');

        return $this->form($worker);
    }

    public function update(SaveWorkerRequest $request, Worker $worker): RedirectResponse
    {
        $worker->fill($this->values($request))->save();

        return redirect()->route('workers.index')->with('success', __('app.saved'));
    }

    /**
     * @return array<string, mixed>
     */
    private function values(SaveWorkerRequest $request): array
    {
        return [
            ...$request->safe()->except(['is_company', 'is_active']),
            'is_company' => $request->boolean('is_company', true),
            'is_active' => $request->boolean('is_active', true),
        ];
    }

    private function form(Worker $worker): View
    {
        return view('workers.form', ['worker' => $worker, 'trades' => Lookup::options(LookupType::WorkerTrade)]);
    }
}
