<?php

use App\Enums\DocumentType;
use App\Models\DocumentSequence;
use App\Services\NumberGenerator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\actingAs;

beforeEach(fn () => Carbon::setTestNow('2026-11-12 10:00:00'));

function generator(): NumberGenerator
{
    return app(NumberGenerator::class);
}

function sequence(DocumentType $type, array $values): void
{
    DocumentSequence::ensureAll();
    DocumentSequence::where('type', $type->value)->update($values);
}

function numberingPayload(array $overrides = []): array
{
    DocumentSequence::ensureAll();

    $rows = [];

    foreach (DocumentSequence::all() as $row) {
        $rows[$row->type->value] = [
            'prefix' => $row->prefix, 'padding' => $row->padding, 'next_number' => $row->next_number,
            'include_year' => $row->include_year ? '1' : '0', 'reset_yearly' => $row->reset_yearly ? '1' : '0',
        ];
    }

    foreach ($overrides as $type => $values) {
        $rows[$type] = $values + $rows[$type];
    }

    return ['sequences' => $rows];
}

it('issues sequential numbers', function () {
    expect(generator()->next(DocumentType::Quotation))->toBe('QT-2026-0001')
        ->and(generator()->next(DocumentType::Quotation))->toBe('QT-2026-0002')
        ->and(generator()->next(DocumentType::Invoice))->toBe('INV-2026-0001');
});

it('restarts on a new year', function () {
    generator()->next(DocumentType::Quotation);
    generator()->next(DocumentType::Quotation);

    Carbon::setTestNow('2027-01-02');

    expect(generator()->next(DocumentType::Quotation))->toBe('QT-2027-0001')
        ->and(generator()->next(DocumentType::Quotation))->toBe('QT-2027-0002');
});

it('keeps counting when yearly reset is off', function () {
    sequence(DocumentType::Quotation, ['reset_yearly' => false]);
    generator()->next(DocumentType::Quotation);
    generator()->next(DocumentType::Quotation);

    Carbon::setTestNow('2027-01-02');

    expect(generator()->next(DocumentType::Quotation))->toBe('QT-2027-0003');
});

it('uses the document date, not today, for the year', function () {
    expect(generator()->next(DocumentType::Invoice, Carbon::parse('2025-12-31')))->toBe('INV-2025-0001');
});

it('omits the year when configured', function () {
    sequence(DocumentType::Quotation, ['include_year' => false]);

    expect(generator()->next(DocumentType::Quotation))->toBe('QT-0001');
});

it('honours padding and prefix', function () {
    sequence(DocumentType::Invoice, ['padding' => 6, 'prefix' => 'TAX']);

    expect(generator()->next(DocumentType::Invoice))->toBe('TAX-2026-000001');
});

it('does not truncate a number longer than the padding', function () {
    sequence(DocumentType::Receipt, ['padding' => 3, 'next_number' => 12345]);

    expect(generator()->next(DocumentType::Receipt))->toBe('RV-2026-12345');
});

it('issues 50 unique numbers in a row', function () {
    $numbers = collect(range(1, 50))->map(fn () => generator()->next(DocumentType::Expense));

    expect($numbers->unique())->toHaveCount(50)->and($numbers->last())->toBe('EXP-2026-0050');
});

it('does not consume a number when the outer transaction rolls back', function () {
    generator()->next(DocumentType::Project);

    try {
        DB::transaction(function () {
            expect(generator()->next(DocumentType::Project))->toBe('PRJ-2026-0002');

            throw new RuntimeException('the document failed to save');
        });
    } catch (RuntimeException) {
        // expected
    }

    expect(generator()->next(DocumentType::Project))->toBe('PRJ-2026-0002');
});

it('preview does not consume', function () {
    expect(generator()->preview(DocumentType::Lead))->toBe('LD-2026-0001')
        ->and(generator()->preview(DocumentType::Lead))->toBe('LD-2026-0001')
        ->and(generator()->next(DocumentType::Lead))->toBe('LD-2026-0001')
        ->and(generator()->preview(DocumentType::Lead))->toBe('LD-2026-0002');
});

it('preview shows the reset after a year change', function () {
    generator()->next(DocumentType::Lead);
    Carbon::setTestNow('2027-03-01');

    expect(generator()->preview(DocumentType::Lead))->toBe('LD-2027-0001');
});

it('has a default prefix for every document type', function () {
    DocumentSequence::ensureAll();

    expect(DocumentSequence::count())->toBe(count(DocumentType::cases()))
        ->and(DocumentSequence::pluck('prefix')->unique()->count())->toBe(count(DocumentType::cases()));
});

it('shows the numbering screen to settings managers only', function () {
    actingAs(userWith())->get(route('settings.numbering'))->assertForbidden();
    actingAs(userWith(['settings.manage']))->get(route('settings.numbering'))->assertOk()->assertSee('QT-2026-0001');
});

it('saves prefix, padding and flags', function () {
    actingAs(userWith(['settings.manage']))->put(route('settings.numbering.update'), numberingPayload([
        'quotation' => ['prefix' => 'OFFER', 'padding' => 5, 'include_year' => '0', 'reset_yearly' => '0'],
    ]))->assertSessionHasNoErrors();

    expect(generator()->next(DocumentType::Quotation))->toBe('OFFER-00001');
});

it('rejects lowering next_number', function () {
    sequence(DocumentType::Invoice, ['next_number' => 40]);

    actingAs(userWith(['settings.manage']))->put(route('settings.numbering.update'), numberingPayload(['invoice' => ['next_number' => 39]]))
        ->assertSessionHasErrors('sequences.invoice.next_number');

    actingAs(userWith(['settings.manage']))->put(route('settings.numbering.update'), numberingPayload(['invoice' => ['next_number' => 100]]))
        ->assertSessionHasNoErrors();

    expect(generator()->next(DocumentType::Invoice))->toBe('INV-2026-0100');
});

it('rejects a bad prefix and padding', function () {
    actingAs(userWith(['settings.manage']))->put(route('settings.numbering.update'), numberingPayload([
        'receipt' => ['prefix' => 'R V/1', 'padding' => 12],
    ]))->assertSessionHasErrors(['sequences.receipt.prefix', 'sequences.receipt.padding']);
});
