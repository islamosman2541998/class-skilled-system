<?php

namespace App\Http\Controllers;

use App\Actions\ImportPriceItems;
use App\Enums\LookupType;
use App\Http\Requests\SavePriceItemRequest;
use App\Models\Lookup;
use App\Models\PriceItem;
use App\Support\Like;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Spatie\SimpleExcel\SimpleExcelWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Price list (spec §8.4): list, add / edit in a modal by AJAX, Excel import, quotation picker search.
 */
class PriceItemController extends Controller
{
    public function index(): View
    {
        Gate::authorize('price_items.view');

        return view('price-items.index', [
            'services' => Lookup::options(LookupType::Service),
            'units' => Lookup::options(LookupType::Unit),
        ]);
    }

    public function store(SavePriceItemRequest $request): JsonResponse
    {
        $item = PriceItem::query()->create($request->values());

        return response()->json(['id' => $item->id, 'message' => __('app.saved')], 201);
    }

    public function update(SavePriceItemRequest $request, PriceItem $priceItem): JsonResponse
    {
        $priceItem->update($request->values());

        return response()->json(['id' => $priceItem->id, 'message' => __('app.saved')]);
    }

    public function import(Request $request, ImportPriceItems $import): RedirectResponse
    {
        Gate::authorize('price_items.create');

        $request->validate(['file' => ['required', 'file', 'mimes:xlsx,csv,txt', 'max:10240']]);

        $file = $request->file('file');
        $path = $file->getRealPath().'.'.strtolower($file->getClientOriginalExtension() === 'csv' ? 'csv' : 'xlsx');
        copy($file->getRealPath(), $path);

        try {
            $result = $import->handle($path, $request->user());
        } finally {
            @unlink($path);
        }

        $message = __('price_items.import_done', ['created' => $result->created, 'updated' => $result->updated]);

        return redirect()->route('price-items.index')
            ->with($result->created + $result->updated > 0 ? 'success' : 'error', $result->created + $result->updated > 0 ? $message : __('price_items.import_nothing'))
            ->with('import_errors', array_slice($result->errors, 0, 50));
    }

    /** An empty sheet with the expected header row. */
    public function template(): StreamedResponse
    {
        Gate::authorize('price_items.create');

        return response()->streamDownload(function () {
            $writer = SimpleExcelWriter::streamDownload('price-items.xlsx');
            $writer->addHeader(ImportPriceItems::HEADERS);
            $writer->close();
        }, 'price-items.xlsx');
    }

    /** Active items for the quotation item picker: [{id, description, unit_id, unit, price}]. */
    public function search(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('price_items.view') || $request->user()->can('quotations.create') || $request->user()->can('quotations.update'), 403);

        $items = Like::any(PriceItem::query()->with('unit')->where('is_active', true), ['description_ar', 'description_en'], (string) $request->query('q', ''))
            ->when($request->integer('service_id'), fn ($query, $service) => $query->where(fn ($q) => $q->where('service_id', $service)->orWhereNull('service_id')))
            ->orderBy('description_ar')
            ->limit(20)
            ->get();

        return response()->json($items->map(fn (PriceItem $item) => [
            'id' => $item->id,
            'value' => $item->id,
            'text' => $item->description,
            'description' => $item->description,
            'description_ar' => $item->description_ar,
            'description_en' => $item->description_en,
            'unit_id' => $item->unit_id,
            'unit' => $item->unit->name,
            'price' => $item->default_price,
        ])->values());
    }
}
