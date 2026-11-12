<?php

use App\Models\SavedFilter;
use App\Models\TablePreference;
use Livewire\Livewire;
use Spatie\SimpleExcel\SimpleExcelReader;
use Tests\Fixtures\ReadOnlyWidgetsTable;
use Tests\Fixtures\Widget;
use Tests\Fixtures\WidgetsTable;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Widget::createTable();
});

const WIDGET_ALL = ['widgets.view', 'widgets.delete', 'widgets.restore', 'widgets.force-delete', 'widgets.export'];

/**
 * @return list<array<string, mixed>>
 */
function exportedRows($component): array
{
    $path = storage_path('framework/testing/export-'.uniqid().'.xlsx');
    @mkdir(dirname($path), 0777, true);
    file_put_contents($path, base64_decode($component->effects['download']['content']));

    $rows = SimpleExcelReader::create($path)->getRows()->all();
    @unlink($path);

    return $rows;
}

it('delete all removes every filtered row across pages and nothing else', function () {
    actingAs(userWith(WIDGET_ALL));
    Widget::seedMany(30, ['status' => 'closed']);
    Widget::seedMany(30, ['status' => 'open']);

    Livewire::test(WidgetsTable::class)->set('perPage', 15)->set('filters.status', 'closed')
        ->call('deleteAll')->assertDispatched('toast');

    expect(Widget::onlyTrashed()->count())->toBe(30)
        ->and(Widget::onlyTrashed()->where('status', 'open')->count())->toBe(0)
        ->and(Widget::where('status', 'open')->count())->toBe(30);
});

it('skips guarded rows and reports them', function () {
    actingAs(userWith(WIDGET_ALL));
    Widget::seedMany(3);
    Widget::seedMany(2, ['is_featured' => true]);

    Livewire::test(WidgetsTable::class)->call('deleteAll')
        ->assertDispatched('toast', fn ($event, $params) => ($params['type'] ?? null) === 'warning'
            && str_contains($params['message'], '2'));

    expect(Widget::count())->toBe(2)->and(Widget::onlyTrashed()->count())->toBe(3);
});

it('deletes one row', function () {
    actingAs(userWith(WIDGET_ALL));
    $keep = Widget::make1();
    $drop = Widget::make1();

    Livewire::test(WidgetsTable::class)->call('deleteOne', $drop->id);

    expect($drop->fresh()->trashed())->toBeTrue()->and($keep->fresh()->trashed())->toBeFalse();
});

it('deletes the selected rows and clears the selection', function () {
    actingAs(userWith(WIDGET_ALL));
    [$a, $b, $c] = [Widget::make1(), Widget::make1(), Widget::make1()];

    Livewire::test(WidgetsTable::class)->set('selected', [(string) $a->id, (string) $b->id])
        ->call('deleteSelected')->assertSet('selected', []);

    expect(Widget::pluck('id')->all())->toBe([$c->id]);
});

it('ignores selected ids outside the filtered result', function () {
    actingAs(userWith(WIDGET_ALL));
    $open = Widget::make1(['status' => 'open']);
    $closed = Widget::make1(['status' => 'closed']);

    Livewire::test(WidgetsTable::class)->set('filters.status', 'closed')
        ->set('selected', [(string) $open->id, (string) $closed->id])->call('deleteSelected');

    expect($open->fresh()->trashed())->toBeFalse()->and($closed->fresh()->trashed())->toBeTrue();
});

it('forbids delete without the permission', function () {
    actingAs(userWith(['widgets.view']));
    $row = Widget::make1();

    Livewire::test(WidgetsTable::class)->call('deleteOne', $row->id)->assertForbidden();
    Livewire::test(WidgetsTable::class)->call('deleteAll')->assertForbidden();

    expect($row->fresh()->trashed())->toBeFalse();
});

it('lists only trashed rows on the trash tab and restores one', function () {
    actingAs(userWith(WIDGET_ALL));
    $live = Widget::make1(['name_ar' => 'حي']);
    $gone = Widget::make1(['name_ar' => 'محذوف']);
    $gone->delete();

    $table = Livewire::test(WidgetsTable::class)->set('trashed', true);
    expect(collect($table->viewData('rows')->items())->pluck('id')->all())->toBe([$gone->id]);

    $table->call('restore', $gone->id);
    expect($gone->fresh()->trashed())->toBeFalse()->and($live->fresh()->trashed())->toBeFalse();
});

it('hides the trash tab from users who cannot restore', function () {
    actingAs(userWith(['widgets.view', 'widgets.delete']));
    Widget::make1()->delete();

    $table = Livewire::test(WidgetsTable::class)->set('trashed', true);

    expect($table->viewData('rows')->total())->toBe(0);
    $table->assertSet('trashed', false);
});

it('needs force-delete permission for permanent delete', function () {
    $row = Widget::make1();
    $row->delete();

    actingAs(userWith(['widgets.view', 'widgets.delete', 'widgets.restore']));
    Livewire::test(WidgetsTable::class)->set('trashed', true)->call('forceDelete', $row->id)->assertForbidden();
    expect(Widget::withTrashed()->count())->toBe(1);

    actingAs(userWith(WIDGET_ALL));
    Livewire::test(WidgetsTable::class)->set('trashed', true)->call('forceDelete', $row->id);
    expect(Widget::withTrashed()->count())->toBe(0);
});

it('never permanently deletes a row that is not in the trash', function () {
    actingAs(userWith(WIDGET_ALL));
    $row = Widget::make1();

    Livewire::test(WidgetsTable::class)->call('forceDelete', $row->id);

    expect(Widget::count())->toBe(1);
});

it('does nothing destructive when the table is read-only', function () {
    actingAs(userWith(WIDGET_ALL));
    $row = Widget::make1();

    Livewire::test(ReadOnlyWidgetsTable::class)->call('deleteOne', $row->id)->assertForbidden();
    Livewire::test(ReadOnlyWidgetsTable::class)->call('deleteAll')->assertForbidden();

    expect(Widget::count())->toBe(1);
});

it('exports the filtered rows with visible columns only', function () {
    actingAs(userWith(WIDGET_ALL));
    Widget::seedMany(30, ['status' => 'closed', 'amount' => 12.5]);
    Widget::seedMany(30, ['status' => 'open']);

    $table = Livewire::test(WidgetsTable::class)->set('perPage', 15)->set('filters.status', 'closed')
        ->call('toggleColumn', 'amount')->call('export')->assertFileDownloaded('widgets.xlsx');

    $rows = exportedRows($table);

    expect($rows)->toHaveCount(30)
        ->and(array_keys($rows[0]))->toBe([__('table.name'), __('table.status'), __('table.date')])
        ->and($rows[0][__('table.status')])->toBe('closed');
});

it('exports only the selected rows', function () {
    actingAs(userWith(WIDGET_ALL));
    $a = Widget::make1(['name_ar' => 'أول']);
    Widget::make1(['name_ar' => 'ثان']);

    $rows = exportedRows(Livewire::test(WidgetsTable::class)->set('selected', [(string) $a->id])->call('exportSelected'));

    expect($rows)->toHaveCount(1)->and($rows[0][__('table.name')])->toBe('أول');
});

it('forbids export without the permission', function () {
    actingAs(userWith(['widgets.view']));

    Livewire::test(WidgetsTable::class)->call('export')->assertForbidden();
});

it('stores hidden columns per user and table', function () {
    $user = userWith(WIDGET_ALL);
    actingAs($user);

    Livewire::test(WidgetsTable::class)->call('toggleColumn', 'amount');

    expect(TablePreference::where('user_id', $user->id)->where('table_key', 'widgets')->first()->hidden_columns)->toBe(['amount']);

    $visible = collect(Livewire::test(WidgetsTable::class)->viewData('visibleColumns'))->pluck('key')->all();
    expect($visible)->not->toContain('amount')->toContain('name');

    actingAs(userWith(WIDGET_ALL));
    expect(collect(Livewire::test(WidgetsTable::class)->viewData('visibleColumns'))->pluck('key')->all())->toContain('amount');
});

it('never hides the last visible column', function () {
    actingAs(userWith(WIDGET_ALL));

    $table = Livewire::test(WidgetsTable::class);
    foreach (['name', 'status', 'amount', 'issued_on'] as $key) {
        $table->call('toggleColumn', $key);
    }

    expect($table->viewData('visibleColumns'))->toHaveCount(1);
});

it('restores a saved filter', function () {
    actingAs(userWith(WIDGET_ALL));

    $table = Livewire::test(WidgetsTable::class)
        ->set('search', 'عزل')->set('filters.status', 'closed')
        ->set('dateFrom', '2026-11-01')->set('dateTo', '2026-11-30')->call('sort', 'amount')
        ->call('saveFilter', 'مغلق هذا الشهر');

    $saved = SavedFilter::first();
    expect($saved->name)->toBe('مغلق هذا الشهر');

    $table->call('clearFilters')->assertSet('search', '')
        ->call('applySavedFilter', $saved->id)
        ->assertSet('search', 'عزل')->assertSet('filters', ['status' => 'closed'])
        ->assertSet('dateFrom', '2026-11-01')->assertSet('dateTo', '2026-11-30')
        ->assertSet('sortBy', 'amount')->assertSet('sortDir', 'asc');
});

it('rejects a blank saved filter name', function () {
    actingAs(userWith(WIDGET_ALL));

    Livewire::test(WidgetsTable::class)->call('saveFilter', '   ');

    expect(SavedFilter::count())->toBe(0);
});

it('cannot apply or delete a saved filter of another user', function () {
    actingAs(userWith(WIDGET_ALL));
    Livewire::test(WidgetsTable::class)->set('search', 'سر')->call('saveFilter', 'خاص');
    $saved = SavedFilter::first();

    actingAs(userWith(WIDGET_ALL));
    Livewire::test(WidgetsTable::class)->call('applySavedFilter', $saved->id)->assertSet('search', '')
        ->call('deleteSavedFilter', $saved->id);

    expect(SavedFilter::count())->toBe(1);
});
