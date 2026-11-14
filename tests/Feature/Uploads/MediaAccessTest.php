<?php

use App\Actions\AttachUploads;
use App\Models\User;
use App\Services\Uploads\TemporaryUpload;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;

beforeEach(fn () => Storage::fake('private'));

function attachedPhoto(User $record, User $by)
{
    $file = UploadedFile::fake()->image('photo.jpg', 800, 600);
    $bytes = file_get_contents($file->getPathname());
    $upload = TemporaryUpload::create($by->id, strlen($bytes), 'photo.jpg');
    file_put_contents($upload->path(), $bytes);
    $upload->saveMeta(['offset' => strlen($bytes)]);

    return app(AttachUploads::class)->handle($record, [$upload->id], $by)->first();
}

it('serves media only to users who can view the owner', function () {
    $record = User::factory()->create();
    $media = attachedPhoto($record, userWith());

    actingAs(userWith(['users.view']))->get(route('media.show', $media))->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff');
    actingAs(userWith(['users.view']))->get(route('media.thumb', $media))->assertOk();

    actingAs(userWith())->get(route('media.show', $media))->assertForbidden();
    actingAs(userWith())->get(route('media.thumb', $media))->assertForbidden();
});

it('never serves media to guests', function () {
    $media = attachedPhoto(User::factory()->create(), userWith());
    auth()->logout();

    $this->get(route('media.show', $media))->assertRedirect(route('login'));
});

it('sends private cache headers', function () {
    $media = attachedPhoto(User::factory()->create(), userWith());

    $cache = actingAs(userWith(['users.view']))->get(route('media.show', $media))->headers->get('Cache-Control');

    expect($cache)->toContain('private')->toContain('max-age=604800');
});

it('needs update permission to rename or delete media', function () {
    $record = User::factory()->create();
    $media = attachedPhoto($record, userWith());

    actingAs(userWith(['users.view']))->deleteJson(route('media.destroy', $media))->assertForbidden();
    actingAs(userWith(['users.view']))->patchJson(route('media.update', $media), ['title' => 'x'])->assertForbidden();

    actingAs(userWith(['users.view', 'users.update']))->patchJson(route('media.update', $media), ['title' => 'صورة الموقع'])->assertOk();
    expect($media->fresh()->name)->toBe('صورة الموقع');

    actingAs(userWith(['users.view', 'users.update']))->deleteJson(route('media.destroy', $media))->assertOk();
    expect($record->fresh()->getMedia('attachments'))->toBeEmpty();
});

it('attaches uploads from the user form and shows them', function () {
    $actor = userWith(['users.view', 'users.update']);
    $record = User::factory()->create();

    $file = UploadedFile::fake()->image('id-card.jpg', 800, 600);
    $bytes = file_get_contents($file->getPathname());
    $upload = TemporaryUpload::create($actor->id, strlen($bytes), 'id-card.jpg');
    file_put_contents($upload->path(), $bytes);
    $upload->saveMeta(['offset' => strlen($bytes)]);

    actingAs($actor)->post(route('users.attachments.store', $record), ['uploads' => [$upload->id]])->assertSessionHasNoErrors();

    expect($record->fresh()->getMedia('attachments'))->toHaveCount(1);

    actingAs($actor)->get(route('users.edit', $record))->assertOk()->assertSee('id-card');
});

it('shows a validation error on the form for a bad upload', function () {
    $actor = userWith(['users.view', 'users.update']);
    $record = User::factory()->create();

    $upload = TemporaryUpload::create($actor->id, 13, 'shell.jpg');
    file_put_contents($upload->path(), '<?php echo 1;');
    $upload->saveMeta(['offset' => 13]);

    actingAs($actor)->post(route('users.attachments.store', $record), ['uploads' => [$upload->id]])->assertSessionHasErrors('uploads');
});
