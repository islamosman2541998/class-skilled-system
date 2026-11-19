<?php

use App\Enums\LookupType;
use App\Livewire\Tables\ExpensesTable;
use App\Models\Expense;
use App\Models\Lookup;
use App\Models\Project;
use App\Services\Uploads\TemporaryUpload;
use Database\Seeders\DocumentSequenceSeeder;
use Database\Seeders\LookupSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Carbon::setTestNow('2026-11-16 10:00');
    $this->seed([LookupSeeder::class, DocumentSequenceSeeder::class, RolesSeeder::class]);
    $this->user = scopedUser(['expenses.view', 'expenses.create', 'expenses.update', 'expenses.delete', 'expenses.restore', 'projects.view', 'finance.costs']);
    $this->project = Project::factory()->create();
    $this->category = Lookup::query()->ofType(LookupType::ExpenseCategory)->value('id');
    $this->cash = Lookup::query()->ofType(LookupType::PaymentMethod)->where('code', 'cash')->value('id');
    $this->payload = fn (array $extra = []) => $extra + [
        'project_id' => $this->project->id,
        'category_id' => $this->category,
        'expense_date' => '2026-11-16',
        'amount' => '1000',
        'vat_mode' => 'excluded',
        'payment_method_id' => $this->cash,
        'paid_by' => $this->user->id,
    ];
});

it('records EXP-2026-0001 on a project and raises cost_total by the amount before VAT', function () {
    actingAs($this->user)->post(route('expenses.store'), ($this->payload)())->assertRedirect();

    $expense = Expense::query()->firstOrFail();

    expect($expense->number)->toBe('EXP-2026-0001')
        ->and($expense->amount)->toBe('1000.00')
        ->and($expense->vat_amount)->toBe('50.00')
        ->and($expense->total)->toBe('1050.00')
        ->and($expense->vat_rate)->toBe('5.00')
        ->and($this->project->fresh()->cost_total)->toBe('1000.00');
});

it('computes VAT from an amount that includes it, or none', function () {
    actingAs($this->user)->post(route('expenses.store'), ($this->payload)(['amount' => '1050', 'vat_mode' => 'included']));
    actingAs($this->user)->post(route('expenses.store'), ($this->payload)(['amount' => '300', 'vat_mode' => 'none']));

    [$included, $none] = Expense::query()->orderBy('id')->get()->all();

    expect([$included->amount, $included->vat_amount, $included->total])->toBe(['1000.00', '50.00', '1050.00'])
        ->and([$none->amount, $none->vat_amount, $none->total, $none->vat_rate])->toBe(['300.00', '0.00', '300.00', '0.00'])
        ->and($this->project->fresh()->cost_total)->toBe('1300.00');
});

it('moving an expense to another project updates both', function () {
    actingAs($this->user)->post(route('expenses.store'), ($this->payload)());
    $other = Project::factory()->create();
    $expense = Expense::query()->firstOrFail();

    actingAs($this->user)->put(route('expenses.update', $expense), ($this->payload)(['project_id' => $other->id]))->assertRedirect();

    expect($this->project->fresh()->cost_total)->toBe('0.00')
        ->and($other->fresh()->cost_total)->toBe('1000.00');
});

it('general expense has no project and is hidden from engineers', function () {
    actingAs($this->user)->post(route('expenses.store'), ($this->payload)(['project_id' => null]))->assertRedirect();
    $engineer = scopedUser(['expenses.view', 'expenses.create', 'projects.view'], 'assigned');

    actingAs($engineer);
    expect(Livewire::test(ExpensesTable::class)->viewData('rows')->total())->toBe(0);
    actingAs($engineer)->get(route('expenses.show', Expense::query()->firstOrFail()))->assertNotFound();
    actingAs($engineer)->post(route('expenses.store'), ($this->payload)(['project_id' => null]))->assertSessionHasErrors('project_id');
});

it('engineer adds a quick expense with a photo to own project only', function () {
    Storage::fake('private');
    $engineer = scopedUser(['expenses.view', 'expenses.create', 'projects.view'], 'assigned');
    $own = Project::factory()->create(['manager_id' => $engineer->id]);
    $file = UploadedFile::fake()->image('receipt.jpg', 800, 600);
    $bytes = file_get_contents($file->getPathname());
    $upload = TemporaryUpload::create($engineer->id, strlen($bytes), 'receipt.jpg');
    file_put_contents($upload->path(), $bytes);
    $upload->saveMeta(['offset' => strlen($bytes)]);

    actingAs($engineer)->get(route('expenses.quick'))->assertOk();
    actingAs($engineer)->post(route('expenses.quick.store'), [
        'project_id' => $own->id, 'category_id' => $this->category, 'amount' => '105', 'includes_vat' => '1', 'uploads' => [$upload->id],
    ])->assertRedirect(route('expenses.quick'));

    $expense = Expense::query()->firstOrFail();
    expect($expense->project_id)->toBe($own->id)
        ->and($expense->amount)->toBe('100.00')
        ->and($expense->paid_by)->toBe($engineer->id)
        ->and($expense->getMedia('attachments'))->toHaveCount(1);

    actingAs($engineer)->post(route('expenses.quick.store'), ['project_id' => $this->project->id, 'category_id' => $this->category, 'amount' => '50'])->assertSessionHasErrors('project_id');
});

it('highlights only the current expense sidebar item', function () {
    actingAs($this->user)->get(route('expenses.index'))
        ->assertOk()
        ->assertSee('href="'.route('expenses.index').'" class="side-link is-active"', false)
        ->assertDontSee('href="'.route('expenses.quick').'" class="side-link is-active"', false);

    actingAs($this->user)->get(route('expenses.quick'))
        ->assertOk()
        ->assertSee('href="'.route('expenses.quick').'" class="side-link is-active"', false)
        ->assertDontSee('href="'.route('expenses.index').'" class="side-link is-active"', false);
});

it('deleting an expense lowers cost_total and restoring raises it', function () {
    actingAs($this->user)->post(route('expenses.store'), ($this->payload)());
    $expense = Expense::query()->firstOrFail();

    actingAs($this->user);
    Livewire::test(ExpensesTable::class)->call('deleteOne', $expense->id);
    expect($this->project->fresh()->cost_total)->toBe('0.00');

    Livewire::test(ExpensesTable::class)->set('trashed', true)->call('restore', $expense->id);
    expect($this->project->fresh()->cost_total)->toBe('1000.00');
});

it('filters by category, project, supplier, entered by, paid by, method and amount', function () {
    $other = Lookup::query()->ofType(LookupType::ExpenseCategory)->where('id', '!=', $this->category)->value('id');
    $payer = scopedUser(['expenses.view']);
    actingAs($this->user)->post(route('expenses.store'), ($this->payload)(['supplier_name' => 'مؤسسة النور للمواد']));
    actingAs($this->user)->post(route('expenses.store'), ($this->payload)(['category_id' => $other, 'amount' => '5000', 'paid_by' => $payer->id, 'project_id' => null]));

    actingAs($this->user);

    expect(Livewire::test(ExpensesTable::class)->set('filters.category_id', (string) $other)->viewData('rows')->total())->toBe(1)
        ->and(Livewire::test(ExpensesTable::class)->set('filters.project_id', (string) $this->project->id)->viewData('rows')->total())->toBe(1)
        ->and(Livewire::test(ExpensesTable::class)->set('search', 'النور')->viewData('rows')->total())->toBe(1)
        ->and(Livewire::test(ExpensesTable::class)->set('filters.created_by', (string) $this->user->id)->viewData('rows')->total())->toBe(2)
        ->and(Livewire::test(ExpensesTable::class)->set('filters.paid_by', (string) $payer->id)->viewData('rows')->total())->toBe(1)
        ->and(Livewire::test(ExpensesTable::class)->set('filters.payment_method_id', (string) $this->cash)->viewData('rows')->total())->toBe(2)
        ->and(Livewire::test(ExpensesTable::class)->set('filters.amount', ['min' => '2000'])->viewData('rows')->total())->toBe(1)
        ->and(Livewire::test(ExpensesTable::class)->viewData('totals')['amount'])->toBe('6,000.00');
});

it('hides the expenses tab and cost figure without finance.costs', function () {
    $viewer = scopedUser(['projects.view', 'expenses.view']);
    $costs = scopedUser(['projects.view', 'expenses.view', 'finance.costs']);

    actingAs($viewer)->get(route('projects.show', [$this->project, 'tab' => 'expenses']))->assertOk()->assertDontSee('tab=expenses', false)->assertDontSee(__('projects.cost'));
    actingAs($costs)->get(route('projects.show', [$this->project, 'tab' => 'expenses']))->assertOk()->assertSee('tab=expenses', false)->assertSee(__('projects.cost'));
});
