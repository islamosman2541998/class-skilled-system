<?php

use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\Fixtures\Widget;
use Tests\Fixtures\WidgetsTable;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Widget::createTable();
    actingAs(userWith(['widgets.view']));
});

function tableNames($component): array
{
    return collect($component->viewData('rows')->items())->pluck('name_ar')->all();
}

it('lists rows with their translated column headings', function () {
    Widget::make1(['name_ar' => 'عزل الأسطح']);

    Livewire::test(WidgetsTable::class)->assertSee('عزل الأسطح')->assertSee(__('table.amount'));
});

it('searches Arabic text', function () {
    Widget::make1(['name_ar' => 'عزل الأسطح']);
    Widget::make1(['name_ar' => 'أرضيات إيبوكسي']);

    $table = Livewire::test(WidgetsTable::class)->set('search', 'الأسطح');

    expect(tableNames($table))->toBe(['عزل الأسطح']);
});

it('searches the English name too', function () {
    Widget::make1(['name_ar' => 'عزل', 'name_en' => 'Roof insulation']);
    Widget::make1(['name_ar' => 'دهان', 'name_en' => 'Painting']);

    expect(tableNames(Livewire::test(WidgetsTable::class)->set('search', 'roof')))->toBe(['عزل']);
});

it('treats % and _ literally', function () {
    Widget::make1(['name_ar' => '50% خصم']);
    Widget::make1(['name_ar' => '500 خصم']);

    expect(tableNames(Livewire::test(WidgetsTable::class)->set('search', '50%')))->toBe(['50% خصم'])
        ->and(tableNames(Livewire::test(WidgetsTable::class)->set('search', '_')))->toBe([]);
});

it('filters by select', function () {
    Widget::make1(['name_ar' => 'أ', 'status' => 'open']);
    Widget::make1(['name_ar' => 'ب', 'status' => 'closed']);

    expect(tableNames(Livewire::test(WidgetsTable::class)->set('filters.status', 'closed')))->toBe(['ب']);
});

it('filters by multi-select', function () {
    Widget::make1(['name_ar' => 'أ', 'status' => 'open']);
    Widget::make1(['name_ar' => 'ب', 'status' => 'closed']);
    Widget::make1(['name_ar' => 'ج', 'status' => 'draft']);

    $table = Livewire::test(WidgetsTable::class)->set('filters.statuses', ['open', 'draft'])->set('sortBy', 'name')->set('sortDir', 'asc');

    expect(tableNames($table))->toBe(['أ', 'ج']);
});

it('filters by boolean in both directions', function () {
    Widget::make1(['name_ar' => 'أ', 'is_featured' => true]);
    Widget::make1(['name_ar' => 'ب', 'is_featured' => false]);

    expect(tableNames(Livewire::test(WidgetsTable::class)->set('filters.featured', '1')))->toBe(['أ'])
        ->and(tableNames(Livewire::test(WidgetsTable::class)->set('filters.featured', '0')))->toBe(['ب']);
});

it('filters by amount range inclusive', function () {
    Widget::make1(['name_ar' => 'أ', 'amount' => 99.99]);
    Widget::make1(['name_ar' => 'ب', 'amount' => 100]);
    Widget::make1(['name_ar' => 'ج', 'amount' => 200]);
    Widget::make1(['name_ar' => 'د', 'amount' => 200.01]);

    $table = Livewire::test(WidgetsTable::class)->set('filters.amount', ['min' => '100', 'max' => '200'])->set('sortBy', 'amount')->set('sortDir', 'asc');

    expect(tableNames($table))->toBe(['ب', 'ج']);
});

it('ignores an empty filter value', function () {
    Widget::seedMany(3);

    expect(Livewire::test(WidgetsTable::class)->set('filters.status', '')->viewData('rows')->total())->toBe(3);
});

it('includes the whole end day in the date range', function () {
    Widget::make1(['name_ar' => 'قبل', 'issued_on' => '2026-10-31']);
    Widget::make1(['name_ar' => 'أول', 'issued_on' => '2026-11-01']);
    Widget::make1(['name_ar' => 'آخر', 'issued_on' => '2026-11-30']);
    Widget::make1(['name_ar' => 'بعد', 'issued_on' => '2026-12-01']);

    $table = Livewire::test(WidgetsTable::class)->set('dateFrom', '2026-11-01')->set('dateTo', '2026-11-30')->set('sortBy', 'issued_on')->set('sortDir', 'asc');

    expect(tableNames($table))->toBe(['أول', 'آخر']);
});

it('sets the range from a preset', function () {
    Carbon::setTestNow('2026-11-12');

    Livewire::test(WidgetsTable::class)->call('setDatePreset', 'this_month')
        ->assertSet('dateFrom', '2026-11-01')->assertSet('dateTo', '2026-11-30')
        ->call('setDatePreset', 'last_month')
        ->assertSet('dateFrom', '2026-10-01')->assertSet('dateTo', '2026-10-31')
        ->call('setDatePreset', 'today')
        ->assertSet('dateFrom', '2026-11-12')->assertSet('dateTo', '2026-11-12')
        ->call('setDatePreset', 'this_week')
        ->assertSet('dateFrom', '2026-11-09')->assertSet('dateTo', '2026-11-15')
        ->call('setDatePreset', 'this_year')
        ->assertSet('dateFrom', '2026-01-01')->assertSet('dateTo', '2026-12-31');
});

it('ignores a malformed date', function () {
    Widget::seedMany(2);

    expect(Livewire::test(WidgetsTable::class)->set('dateFrom', 'not-a-date')->viewData('rows')->total())->toBe(2);
});

it('toggles sort direction on the same column', function () {
    Widget::make1(['name_ar' => 'أ', 'amount' => 5]);
    Widget::make1(['name_ar' => 'ب', 'amount' => 50]);

    $table = Livewire::test(WidgetsTable::class)->call('sort', 'amount');
    expect(tableNames($table))->toBe(['أ', 'ب']);

    $table->call('sort', 'amount');
    expect(tableNames($table))->toBe(['ب', 'أ']);
});

it('refuses to sort by an unknown column', function () {
    Livewire::test(WidgetsTable::class)->call('sort', 'password')->assertSet('sortBy', '');
});

it('falls back to 25 for an unknown page size', function () {
    Livewire::test(WidgetsTable::class)->set('perPage', 999)->assertSet('perPage', 25)
        ->set('perPage', 50)->assertSet('perPage', 50);
});

it('totals the whole filtered result, not the page', function () {
    Widget::seedMany(40, ['status' => 'closed']);
    Widget::seedMany(20, ['status' => 'open']);

    $table = Livewire::test(WidgetsTable::class)->set('perPage', 15)->set('filters.status', 'closed');

    expect($table->viewData('totals'))->toBe(['amount' => '400.00'])
        ->and($table->viewData('rows')->total())->toBe(40)
        ->and($table->viewData('rows')->count())->toBe(15);
});

it('resets to page 1 when the search changes', function () {
    Widget::seedMany(60);

    $table = Livewire::test(WidgetsTable::class)->set('perPage', 15)->call('gotoPage', 3);
    expect($table->viewData('rows')->currentPage())->toBe(3);

    $table->set('search', 'عنصر');
    expect($table->viewData('rows')->currentPage())->toBe(1);
});

it('reads its state from the query string', function () {
    Widget::make1(['name_ar' => 'عزل']);
    Widget::make1(['name_ar' => 'دهان']);

    $table = Livewire::withQueryParams(['q' => 'عزل', 'per' => 50])->test(WidgetsTable::class);

    $table->assertSet('search', 'عزل')->assertSet('perPage', 50);
    expect(tableNames($table))->toBe(['عزل']);
});

it('clears every filter at once and removes one chip', function () {
    $table = Livewire::test(WidgetsTable::class)
        ->set('search', 'x')->set('filters.status', 'open')->set('dateFrom', '2026-11-01')->set('dateTo', '2026-11-30');

    $table->call('removeFilter', 'status')->assertSet('filters', [])->assertSet('search', 'x');
    $table->call('removeFilter', 'date')->assertSet('dateFrom', '')->assertSet('dateTo', '');
    $table->call('clearFilters')->assertSet('search', '');
});

it('shows the empty state when nothing matches', function () {
    Livewire::test(WidgetsTable::class)->assertSee(__('table.empty'));
});
