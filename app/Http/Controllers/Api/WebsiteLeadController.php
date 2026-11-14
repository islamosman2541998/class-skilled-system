<?php

namespace App\Http\Controllers\Api;

use App\Actions\Leads\ReceiveWebsiteLead;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\WebsiteLeadRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;

class WebsiteLeadController extends Controller
{
    public function store(WebsiteLeadRequest $request, ReceiveWebsiteLead $receive): JsonResponse
    {
        $files = array_map(
            fn (UploadedFile $file) => ['path' => (string) $file->getRealPath(), 'name' => $file->getClientOriginalName()],
            array_values(array_filter((array) $request->file('files', []), fn ($file) => $file instanceof UploadedFile)),
        );

        $result = $receive->handle($request->safe()->except('files'), $files);

        return response()->json(['id' => $result['lead']->id, 'number' => $result['lead']->number], $result['created'] ? 201 : 200);
    }
}
