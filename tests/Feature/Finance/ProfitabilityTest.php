<?php

use App\Livewire\Tables\ProjectsTable;
use App\Models\Invoice;
use App\Models\Project;
use Database\Seeders\DocumentSequenceSeeder;
use Database\Seeders\LookupSeeder;
use Database\Seeders\RolesSeeder;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed([LookupSeeder::class, DocumentSequenceSeeder::class, RolesSeeder::class]);
    $this->project = Project::factory()->create(['invoiced_total' => '21000.00', 'invoiced_net' => '20000.00', 'collected_total' => '10500.00', 'cost_total' => '15000.00']);
});

/** Keys of the columns the table offers (shown, hidden and exported alike). */
function projectColumnKeys(): array
{
    $table = Livewire::test(ProjectsTable::class)->instance();

    return collect((fn () => $this->columns())->call($table))->pluck('key')->all();
}

it('profit is invoiced net minus cost and margin its share', function () {
    expect($this->project->profit())->toBe('5000.00')
        ->and($this->project->margin())->toBe('25.00');

    $loss = Project::factory()->create(['invoiced_net' => '1000.00', 'cost_total' => '1500.00']);
    expect($loss->profit())->toBe('-500.00')
        ->and($loss->margin())->toBe('-50.00');
});

it('margin is empty without invoices', function () {
    $project = Project::factory()->create(['invoiced_net' => '0.00', 'cost_total' => '300.00']);

    expect($project->profit())->toBe('-300.00')
        ->and($project->margin())->toBeNull();
});

it('shows profit and margin only with finance.profitability and cost only with finance.costs', function () {
    $plain = scopedUser(['projects.view']);
    $full = scopedUser(['projects.view', 'finance.costs', 'finance.profitability']);

    actingAs($plain)->get(route('projects.show', $this->project))->assertOk()
        ->assertDontSee(__('projects.profit'))->assertDontSee(__('projects.margin'))->assertDontSee(__('projects.cost'));
    actingAs($full)->get(route('projects.show', $this->project))->assertOk()
        ->assertSee(__('projects.profit'))->assertSee('5,000.00')->assertSee('25%');
});

it('adds money columns to the projects table by permission, export included', function () {
    actingAs(scopedUser(['projects.view', 'projects.export']));
    $keys = projectColumnKeys();
    expect($keys)->toContain('invoiced_total')->not->toContain('cost_total')->not->toContain('profit');

    actingAs(scopedUser(['projects.view', 'projects.export', 'finance.costs', 'finance.profitability']));
    $keys = projectColumnKeys();
    expect($keys)->toContain('cost_total')->toContain('profit')->toContain('margin');
});

it('client page shows invoiced and outstanding', function () {
    Invoice::factory()->for($this->project)->issued()->create(['total' => '1000.00', 'paid_amount' => '400.00', 'status' => 'partially_paid']);
    Invoice::factory()->for($this->project)->create(['total' => '9999.00']);

    actingAs(scopedUser(['clients.view', 'invoices.view']))->get(route('clients.show', $this->project->client_id))->assertOk()
        ->assertSee(__('clients.figures.outstanding'))->assertSee('600.00')->assertSee('1,000.00');

    actingAs(scopedUser(['clients.view']))->get(route('clients.show', $this->project->client_id))->assertOk()
        ->assertDontSee(__('clients.figures.outstanding'));
});
