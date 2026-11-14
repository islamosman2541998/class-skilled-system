<?php

use App\Actions\AttachUploads;
use App\Enums\LookupType;
use App\Models\Lookup;
use App\Models\User;
use App\Services\Uploads\TemporaryUpload;
use App\Settings\UploadSettings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

beforeEach(fn () => Storage::fake('private'));

/** A finished transfer owned by the given user. */
function finishedUpload(User $owner, string $name, string $bytes): TemporaryUpload
{
    $upload = TemporaryUpload::create($owner->id, strlen($bytes), $name);
    file_put_contents($upload->path(), $bytes);
    $upload->saveMeta(['offset' => strlen($bytes)]);

    return $upload;
}

function imageBytes(int $width = 800, int $height = 600, string $name = 'photo.jpg'): string
{
    // Keep the fake in a variable: its temp file is removed as soon as the object is freed.
    $file = UploadedFile::fake()->image($name, $width, $height);

    return file_get_contents($file->getPathname());
}

function mp4Bytes(int $padding = 2048): string
{
    return "\x00\x00\x00\x18ftypmp42\x00\x00\x00\x00mp42isom".str_repeat("\x00", $padding);
}

function attach(User $owner, User $record, array $ids, array $meta = [])
{
    return app(AttachUploads::class)->handle($record, $ids, $owner, $meta);
}

it('attaches an image and creates a webp thumbnail', function () {
    $user = userWith();
    $upload = finishedUpload($user, 'site photo.jpg', imageBytes());

    $media = attach($user, $user, [$upload->id])->first();

    expect($media->collection_name)->toBe('attachments')
        ->and($media->disk)->toBe('private')
        ->and($media->getCustomProperty('kind'))->toBe('image')
        ->and($media->getCustomProperty('uploaded_by'))->toBe($user->id)
        ->and($media->hasGeneratedConversion('thumb'))->toBeTrue()
        ->and($media->getPath('thumb'))->toEndWith('.webp')
        ->and(is_dir($upload->directory()))->toBeFalse();
});

it('keeps an Arabic file name as the title and stores a safe file name', function () {
    $user = userWith();
    $upload = finishedUpload($user, 'مخطط الدور الأول.pdf', "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF");

    $media = attach($user, $user, [$upload->id])->first();

    expect($media->name)->toBe('مخطط الدور الأول')
        ->and($media->getCustomProperty('kind'))->toBe('file')
        ->and($media->file_name)->toMatch('/^[a-f0-9-]{36}\.pdf$/');
});

it('stores the title and category given with the upload', function () {
    $user = userWith();
    $category = Lookup::factory()->create(['type' => LookupType::AttachmentCategory]);
    $upload = finishedUpload($user, 'x.jpg', imageBytes());

    $media = attach($user, $user, [$upload->id], ['title' => 'واجهة المبنى', 'category_id' => $category->id])->first();

    expect($media->name)->toBe('واجهة المبنى')->and($media->getCustomProperty('category_id'))->toBe($category->id);
});

it('rejects a fake image and removes its temp data', function () {
    $user = userWith();
    $upload = finishedUpload($user, 'shell.jpg', '<?php echo 1;');

    expect(fn () => attach($user, $user, [$upload->id]))->toThrow(ValidationException::class);

    expect($user->getMedia('attachments'))->toBeEmpty()
        ->and(is_dir($upload->directory()))->toBeFalse();
});

it('rejects a script even when its extension is allowed for files', function () {
    $user = userWith();
    $upload = finishedUpload($user, 'report.pdf', "<?php system('id'); ?>");

    expect(fn () => attach($user, $user, [$upload->id]))->toThrow(ValidationException::class);
    expect($user->getMedia('attachments'))->toBeEmpty();
});

it('rejects an extension that is not allowed', function () {
    $user = userWith();
    $upload = finishedUpload($user, 'tool.exe', 'MZ'.str_repeat("\x00", 100));

    expect(fn () => attach($user, $user, [$upload->id]))->toThrow(ValidationException::class);
});

it('rejects a file larger than the limit of its kind', function () {
    $user = userWith();
    $settings = app(UploadSettings::class);
    $settings->image_max_mb = 1;
    $settings->save();

    $upload = finishedUpload($user, 'big.png', imageBytes(400, 300, 'big.png').random_bytes(1024 * 1024 + 10));

    expect(fn () => attach($user, $user, [$upload->id]))->toThrow(ValidationException::class);
});

it('rejects an unfinished transfer', function () {
    $user = userWith();
    $upload = TemporaryUpload::create($user->id, 500, 'half.jpg');
    file_put_contents($upload->path(), substr(imageBytes(), 0, 100));
    $upload->saveMeta(['offset' => 100]);

    expect(fn () => attach($user, $user, [$upload->id]))->toThrow(ValidationException::class);
});

it('cannot attach a transfer of another user', function () {
    $owner = userWith();
    $thief = userWith();
    $upload = finishedUpload($owner, 'photo.jpg', imageBytes());

    expect(fn () => attach($thief, $thief, [$upload->id]))->toThrow(ValidationException::class);

    expect($thief->getMedia('attachments'))->toBeEmpty()
        ->and(TemporaryUpload::find($upload->id))->not->toBeNull();
});

it('attaches a video and links its poster', function () {
    $user = userWith();
    $upload = finishedUpload($user, 'walkthrough.mp4', mp4Bytes());
    file_put_contents($upload->directory().'/poster.jpg', imageBytes(320, 180));

    $media = attach($user, $user, [$upload->id])->first();
    $poster = $user->fresh()->getMedia('posters')->first();

    expect($media->getCustomProperty('kind'))->toBe('video')
        ->and($poster)->not->toBeNull()
        ->and($poster->getCustomProperty('media_id'))->toBe($media->id);
});

it('attaches several transfers in one call', function () {
    $user = userWith();
    $ids = [
        finishedUpload($user, 'a.jpg', imageBytes())->id,
        finishedUpload($user, 'b.jpg', imageBytes(640, 480))->id,
    ];

    expect(attach($user, $user, $ids))->toHaveCount(2)
        ->and($user->fresh()->getMedia('attachments'))->toHaveCount(2);
});
