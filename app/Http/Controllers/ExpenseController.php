<?php

namespace App\Http\Controllers;

use App\Actions\AttachUploads;
use App\Actions\Finance\SaveExpense;
use App\Enums\LookupType;
use App\Http\Requests\QuickExpenseRequest;
use App\Http\Requests\SaveExpenseRequest;
use App\Http\Requests\StoreAttachmentsRequest;
use App\Models\Expense;
use App\Models\Lookup;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Expenses (spec §8.12) and the phone quick entry.
 */
class ExpenseController extends Controller
{
    public function index(): View
    {
        Gate::authorize('expenses.view');

        return view('expenses.index');
    }

    public function create(Request $request): View
    {
        Gate::authorize('expenses.create');

        $project = $request->integer('project') ? Project::query()->visibleTo($request->user())->find($request->integer('project')) : null;

        return $this->form($request, new Expense(['project_id' => $project?->id, 'expense_date' => today(), 'paid_by' => $request->user()->id]));
    }

    public function store(SaveExpenseRequest $request, SaveExpense $save): RedirectResponse
    {
        $expense = $save->handle($request->validated(), $request->user());

        return redirect()->route('expenses.show', $expense)->with('success', __('app.saved'));
    }

    public function show(Request $request, Expense $expense): View
    {
        Gate::authorize('expenses.view');
        $this->ensureVisible($request, $expense);

        return view('expenses.show', ['expense' => $expense->load(['project', 'category', 'paymentMethod', 'payer', 'creator'])]);
    }

    public function edit(Request $request, Expense $expense): View
    {
        Gate::authorize('expenses.update');
        $this->ensureVisible($request, $expense);

        return $this->form($request, $expense);
    }

    public function update(SaveExpenseRequest $request, Expense $expense, SaveExpense $save): RedirectResponse
    {
        $this->ensureVisible($request, $expense);

        $save->handle($request->validated(), $request->user(), $expense);

        return redirect()->route('expenses.show', $expense)->with('success', __('app.saved'));
    }

    /** Phone first: photo, amount, category, project; saves and opens the same page again. */
    public function quick(Request $request): View
    {
        Gate::authorize('expenses.create');

        return view('expenses.quick', [
            'projects' => Project::query()->visibleTo($request->user())->whereNotIn('status', ['cancelled', 'delivered'])->latest('id')->get(['id', 'number', 'title_ar', 'title_en']),
            'categories' => Lookup::options(LookupType::ExpenseCategory),
        ]);
    }

    public function storeQuick(QuickExpenseRequest $request, SaveExpense $save): RedirectResponse
    {
        $data = $request->validated();
        $expense = $save->handle([
            ...$data,
            'vat_mode' => $request->boolean('includes_vat') ? 'included' : 'none',
            'expense_date' => today(),
            'paid_by' => $request->user()->id,
        ], $request->user());

        return redirect()->route('expenses.quick')->with('success', __('finance.expenses.quick_saved', ['number' => $expense->number]));
    }

    public function attach(StoreAttachmentsRequest $request, Expense $expense, AttachUploads $attach): RedirectResponse
    {
        Gate::authorize('expenses.update');
        $this->ensureVisible($request, $expense);

        $attach->handle($expense, $request->validated('uploads'), $request->user(), $request->meta());

        return back()->with('success', __('uploads.attached'));
    }

    private function form(Request $request, Expense $expense): View
    {
        return view('expenses.form', [
            'expense' => $expense,
            'projects' => Project::query()->visibleTo($request->user())->latest('id')->get(['id', 'number', 'title_ar', 'title_en']),
            'categories' => Lookup::options(LookupType::ExpenseCategory),
            'methods' => Lookup::options(LookupType::PaymentMethod),
            'users' => User::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    private function ensureVisible(Request $request, Expense $expense): void
    {
        abort_unless($expense->isVisibleTo($request->user()), 404);
    }
}
