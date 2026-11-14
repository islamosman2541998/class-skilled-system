<?php

use App\Actions\Projects\CreateProjectFromQuotation;
use App\Actions\Quotations\ChangeQuotationStatus;
use App\Actions\Quotations\SaveQuotation;
use App\Enums\LookupType;
use App\Models\Client;
use App\Models\Lookup;
use App\Models\Project;
use App\Models\StageTemplate;
use Database\Seeders\DocumentSequenceSeeder;
use Database\Seeders\LookupSeeder;
use Illuminate\Support\Carbon;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Carbon::setTestNow('2026-11-15 10:00');
    $this->seed([LookupSeeder::class, DocumentSequenceSeeder::class]);
    $this->user = scopedUser(['projects.view', 'projects.create', 'quotations.view', 'quotations.approve']);
    $this->service = Lookup::query()->ofType(LookupType::Service)->firstOrFail();
    StageTemplate::query()->create(['service_id' => $this->service->id, 'name_ar' => 'الحفر', 'weight' => '30', 'sort_order' => 1]);
    StageTemplate::query()->create(['service_id' => $this->service->id, 'name_ar' => 'التشطيب', 'weight' => '70', 'sort_order' => 2]);

    $this->quotation = app(SaveQuotation::class)->handle([
        'client_id' => Client::factory()->create()->id,
        'service_id' => $this->service->id,
        'title_ar' => 'فيلا خليفة',
        'location' => 'خليفة أ',
        'issue_date' => '2026-11-15',
        'valid_until' => '2026-12-15',
        'assigned_to' => $this->user->id,
        'discount_type' => null,
        'discount_value' => '0',
        'items' => [
            ['section_ar' => 'العزل', 'description_ar' => 'عزل مائي', 'quantity' => '100', 'unit_price' => '50'],
            ['description_ar' => 'حماية', 'quantity' => '1', 'unit_price' => '1000'],
        ],
        'payments' => [['label_ar' => 'مقدم', 'percent' => '30'], ['label_ar' => 'تسليم', 'percent' => '70']],
    ], $this->user);
});

function confirmFields(int $manager): array
{
    return ['start_date' => '2026-11-20', 'planned_end_date' => '2027-02-20', 'manager_id' => $manager, 'billing_method' => 'installments'];
}

it('converts an accepted quotation with value, items, installments and the service stages', function () {
    app(ChangeQuotationStatus::class)->accept($this->quotation, $this->user);

    actingAs($this->user)->post(route('quotations.convert', $this->quotation), confirmFields($this->user->id))->assertRedirect();

    $project = Project::query()->firstOrFail();

    expect($project->quotation_id)->toBe($this->quotation->id)
        ->and($project->client_id)->toBe($this->quotation->client_id)
        ->and($project->title_ar)->toBe('فيلا خليفة')
        ->and($project->contract_value)->toBe('6300.00')
        ->and($project->original_value)->toBe('6300.00')
        ->and($project->vat_rate)->toBe('5.00')
        ->and($project->items)->toHaveCount(2)
        ->and($project->items[0]->line_total)->toBe('5000.00')
        ->and($project->installments()->pluck('amount')->all())->toBe(['1890.00', '4410.00'])
        ->and($project->stages()->pluck('name_ar')->all())->toBe(['الحفر', 'التشطيب']);
});

it('refuses a draft, sent or rejected quotation', function () {
    actingAs($this->user)->post(route('quotations.convert', $this->quotation), confirmFields($this->user->id))->assertSessionHas('error');

    expect(Project::query()->count())->toBe(0);
});

it('creates one project when converted twice', function () {
    app(ChangeQuotationStatus::class)->accept($this->quotation, $this->user);

    $first = app(CreateProjectFromQuotation::class)->handle($this->quotation->fresh(), confirmFields($this->user->id), $this->user);
    $second = app(CreateProjectFromQuotation::class)->handle($this->quotation->fresh(), confirmFields($this->user->id), $this->user);

    expect($second->id)->toBe($first->id)->and(Project::query()->count())->toBe(1);
});

it('needs projects.create and quotation visibility', function () {
    app(ChangeQuotationStatus::class)->accept($this->quotation, $this->user);

    actingAs(scopedUser(['quotations.view']))->post(route('quotations.convert', $this->quotation), confirmFields($this->user->id))->assertForbidden();
    actingAs(scopedUser(['quotations.view', 'projects.create'], 'assigned'))->post(route('quotations.convert', $this->quotation), confirmFields($this->user->id))->assertNotFound();
});

it('links the project from the quotation page and shows the convert form', function () {
    app(ChangeQuotationStatus::class)->accept($this->quotation, $this->user);

    actingAs($this->user)->get(route('quotations.show', $this->quotation))->assertSee(__('projects.convert'));
    actingAs($this->user)->get(route('projects.create', ['quotation' => $this->quotation->id]))->assertOk()->assertSee('فيلا خليفة');

    actingAs($this->user)->post(route('quotations.convert', $this->quotation), confirmFields($this->user->id));
    $project = Project::query()->firstOrFail();

    actingAs($this->user)->get(route('quotations.show', $this->quotation))->assertSee($project->number)->assertDontSee(__('projects.convert'));
});
