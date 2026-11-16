<?php

use App\Enums\LookupType;
use App\Enums\SupplierType;
use App\Livewire\Tables\SuppliersTable;
use App\Models\Lookup;
use App\Models\Supplier;
use Database\Seeders\DocumentSequenceSeeder;
use Database\Seeders\LookupSeeder;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed([LookupSeeder::class, DocumentSequenceSeeder::class]);
    $this->user = scopedUser(['suppliers.view', 'suppliers.create', 'suppliers.update', 'suppliers.delete', 'suppliers.restore']);
});

function supplierPayload(array $overrides = []): array
{
    return array_merge([
        'type' => 'supplier',
        'name_ar' => 'مؤسسة الخليج لمواد البناء',
        'phone' => '0501234567',
    ], $overrides);
}

it('creates a supplier and a subcontractor with Arabic name only and shows English fallback', function () {
    $specialty = Lookup::query()->ofType(LookupType::SupplierSpecialty)->value('id');

    actingAs($this->user)->post(route('suppliers.store'), supplierPayload(['specialty_id' => $specialty]))->assertRedirect();
    actingAs($this->user)->post(route('suppliers.store'), supplierPayload(['type' => 'subcontractor', 'name_ar' => 'مقاولات الريم للجبس', 'bank_iban' => 'AE07 0331 2345 6789 0123 456']))->assertRedirect();

    $supplier = Supplier::query()->where('type', 'supplier')->firstOrFail();
    $sub = Supplier::query()->where('type', 'subcontractor')->firstOrFail();

    expect($supplier->specialty_id)->toBe($specialty)
        ->and($supplier->is_active)->toBeTrue()
        ->and($sub->type)->toBe(SupplierType::Subcontractor)
        ->and($sub->bank_iban)->toBe('AE070331234567890123456');

    app()->setLocale('en');
    expect($supplier->name)->toBe('مؤسسة الخليج لمواد البناء');

    actingAs($this->user)->get(route('suppliers.show', $supplier))->assertOk()->assertSee('مؤسسة الخليج لمواد البناء');
});

it('validates TRN 15 digits and IBAN format when given', function () {
    actingAs($this->user)->post(route('suppliers.store'), supplierPayload(['trn' => '12345', 'bank_iban' => 'XX12']))
        ->assertSessionHasErrors(['trn', 'bank_iban']);
    actingAs($this->user)->post(route('suppliers.store'), supplierPayload(['name_ar' => '', 'type' => 'other']))
        ->assertSessionHasErrors(['name_ar', 'type']);

    expect(Supplier::query()->count())->toBe(0);
});

it('filters by type, specialty and active, and searches name, phone and TRN', function () {
    $specialty = Lookup::query()->ofType(LookupType::SupplierSpecialty)->value('id');
    Supplier::factory()->create(['name_ar' => 'مؤسسة الخليج', 'specialty_id' => $specialty, 'trn' => '100123456700003']);
    Supplier::factory()->create(['type' => 'subcontractor', 'name_ar' => 'مقاولات الريم', 'phone' => '0559998877']);
    Supplier::factory()->create(['name_ar' => 'مورد موقوف', 'is_active' => false]);
    actingAs($this->user);

    expect(Livewire::test(SuppliersTable::class)->set('filters.type', 'subcontractor')->viewData('rows')->total())->toBe(1)
        ->and(Livewire::test(SuppliersTable::class)->set('filters.specialty_id', (string) $specialty)->viewData('rows')->total())->toBe(1)
        ->and(Livewire::test(SuppliersTable::class)->set('filters.is_active', '0')->viewData('rows')->total())->toBe(1)
        ->and(Livewire::test(SuppliersTable::class)->set('search', 'الريم')->viewData('rows')->total())->toBe(1)
        ->and(Livewire::test(SuppliersTable::class)->set('search', '0559998877')->viewData('rows')->total())->toBe(1)
        ->and(Livewire::test(SuppliersTable::class)->set('search', '100123456700003')->viewData('rows')->total())->toBe(1);
});

it('soft deletes a supplier without records and restores it from the trash', function () {
    $supplier = Supplier::factory()->create();
    actingAs($this->user);

    Livewire::test(SuppliersTable::class)->call('deleteOne', $supplier->id);
    expect(Supplier::query()->count())->toBe(0)->and(Supplier::withTrashed()->count())->toBe(1);

    Livewire::test(SuppliersTable::class)->set('trashed', true)->call('restore', $supplier->id);
    expect(Supplier::query()->count())->toBe(1);
});

it('needs suppliers permissions (403)', function () {
    $supplier = Supplier::factory()->create();
    $viewer = scopedUser(['suppliers.view']);

    actingAs(scopedUser())->get(route('suppliers.index'))->assertForbidden();
    actingAs($viewer)->get(route('suppliers.index'))->assertOk();
    actingAs($viewer)->get(route('suppliers.create'))->assertForbidden();
    actingAs($viewer)->post(route('suppliers.store'), supplierPayload())->assertForbidden();
    actingAs($viewer)->put(route('suppliers.update', $supplier), supplierPayload())->assertForbidden();
});
