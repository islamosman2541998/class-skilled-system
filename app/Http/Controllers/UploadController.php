<?php

namespace App\Http\Controllers;

use App\Services\Uploads\ChunkReceiver;
use App\Services\Uploads\TemporaryUpload;
use App\Services\Uploads\UploadValidator;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;

/**
 * Server side of FilePond's process / patch / head / revert protocol (spec §5.4).
 * Transfers belong to the user who started them; nothing here touches a business record.
 */
class UploadController extends Controller
{
    public function __construct(private readonly UploadValidator $validator) {}

    public function store(Request $request): Response
    {
        $file = $request->file('filepond');

        if ($file instanceof UploadedFile) {
            return $this->storeWhole($request, $file);
        }

        $length = (int) $request->header('Upload-Length', '0');

        abort_if($length <= 0 || $length > $this->validator->largestBytes(), 422, __('uploads.too_large_any'));

        $name = ChunkReceiver::cleanName((string) $request->header('Upload-Name', ''));
        $upload = TemporaryUpload::create($request->user()->id, $length, $name);

        return response($upload->id, 200, ['Content-Type' => 'text/plain']);
    }

    public function patch(Request $request, ChunkReceiver $receiver, string $id): Response
    {
        $upload = $this->owned($request, $id);
        $length = $request->header('Upload-Length');

        $receiver->receive(
            $upload,
            (int) $request->header('Upload-Offset', '-1'),
            $request->getContent(),
            $length === null ? null : (int) $length,
            $request->header('Upload-Name'),
        );

        return response()->noContent()->header('Upload-Offset', (string) $upload->offset());
    }

    public function head(Request $request, string $id): Response
    {
        $upload = $this->owned($request, $id);

        return response('', 200, ['Upload-Offset' => (string) $upload->offset()]);
    }

    public function destroy(Request $request, string $id): Response
    {
        $this->owned($request, $id)->delete();

        return response()->noContent();
    }

    /** A JPEG frame captured in the browser, shown as the cover of a video. */
    public function poster(Request $request, string $id): Response
    {
        $upload = $this->owned($request, $id);

        // FilePond reads only the status code, so a bad poster is a plain 422 rather than a redirect.
        $validator = Validator::make($request->all(), ['poster' => ['required', 'file', 'mimes:jpg,jpeg', 'max:1024']]);
        abort_if($validator->fails(), 422, (string) $validator->errors()->first('poster'));

        /** @var UploadedFile $poster */
        $poster = $request->file('poster');
        $poster->move($upload->directory(), 'poster.jpg');

        return response()->noContent();
    }

    private function storeWhole(Request $request, UploadedFile $file): Response
    {
        abort_unless($file->isValid(), 422, __('uploads.failed'));
        abort_if($file->getSize() > $this->validator->largestBytes(), 422, __('uploads.too_large_any'));

        $upload = TemporaryUpload::create($request->user()->id, (int) $file->getSize(), ChunkReceiver::cleanName($file->getClientOriginalName()));
        $file->move($upload->directory(), 'data');
        $upload->saveMeta(['offset' => $upload->size()]);

        return response($upload->id, 200, ['Content-Type' => 'text/plain']);
    }

    private function owned(Request $request, string $id): TemporaryUpload
    {
        $upload = TemporaryUpload::find($id);

        abort_if($upload === null, 404);
        abort_unless($upload->ownerId() === $request->user()->id, 403);

        return $upload;
    }
}
