<?php

use App\Services\Uploads\TemporaryUpload;
use App\Settings\UploadSettings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;

beforeEach(fn () => Storage::fake('private'));

function startTransfer($test, int $length): string
{
    $response = $test->call('POST', '/uploads', [], [], [], ['HTTP_UPLOAD_LENGTH' => $length]);
    $response->assertOk();

    return trim($response->getContent());
}

function sendChunk($test, string $id, int $offset, int $length, string $bytes, string $name = 'site-video.mp4')
{
    return $test->call('PATCH', "/uploads/{$id}", [], [], [], [
        'HTTP_UPLOAD_OFFSET' => $offset,
        'HTTP_UPLOAD_LENGTH' => $length,
        'HTTP_UPLOAD_NAME' => $name,
        'CONTENT_TYPE' => 'application/offset+octet-stream',
    ], $bytes);
}

it('requires a signed-in user', function () {
    $this->call('POST', '/uploads', [], [], [], ['HTTP_UPLOAD_LENGTH' => 10])->assertRedirect(route('login'));
});

it('assembles a file sent in two chunks', function () {
    actingAs(userWith());
    $source = random_bytes(5 * 1024 * 1024);
    $first = substr($source, 0, 3 * 1024 * 1024);
    $second = substr($source, 3 * 1024 * 1024);

    $id = startTransfer($this, strlen($source));
    sendChunk($this, $id, 0, strlen($source), $first)->assertNoContent()->assertHeader('Upload-Offset', (string) strlen($first));
    sendChunk($this, $id, strlen($first), strlen($source), $second)->assertNoContent();

    $upload = TemporaryUpload::find($id);

    expect(sha1_file($upload->path()))->toBe(sha1($source))
        ->and($upload->isComplete())->toBeTrue()
        ->and($upload->name())->toBe('site-video.mp4');
});

it('resumes from the stored offset', function () {
    actingAs(userWith());
    $source = random_bytes(5 * 1024 * 1024);
    $first = substr($source, 0, 3 * 1024 * 1024);

    $id = startTransfer($this, strlen($source));
    sendChunk($this, $id, 0, strlen($source), $first);

    // The connection dropped; the browser asks where to continue.
    $this->call('HEAD', "/uploads/{$id}")->assertOk()->assertHeader('Upload-Offset', '3145728');

    sendChunk($this, $id, 3145728, strlen($source), substr($source, 3145728))->assertNoContent();

    expect(sha1_file(TemporaryUpload::find($id)->path()))->toBe(sha1($source));
});

it('rejects a chunk at the wrong offset with 409 and keeps what it has', function () {
    actingAs(userWith());
    $id = startTransfer($this, 100);
    sendChunk($this, $id, 0, 100, str_repeat('a', 40));

    sendChunk($this, $id, 10, 100, str_repeat('b', 40))->assertStatus(409)->assertHeader('Upload-Offset', '40');

    expect(TemporaryUpload::find($id)->offset())->toBe(40);
});

it('rejects bytes beyond the declared length', function () {
    actingAs(userWith());
    $id = startTransfer($this, 50);

    sendChunk($this, $id, 0, 50, str_repeat('a', 80))->assertStatus(422);

    expect(TemporaryUpload::find($id)->offset())->toBe(0);
});

it('refuses a transfer larger than every size limit', function () {
    actingAs(userWith());
    $settings = app(UploadSettings::class);
    $settings->image_max_mb = 1;
    $settings->video_max_mb = 2;
    $settings->file_max_mb = 1;
    $settings->save();

    $this->call('POST', '/uploads', [], [], [], ['HTTP_UPLOAD_LENGTH' => 3 * 1024 * 1024])->assertStatus(422);
});

it('forbids another user from touching a transfer', function () {
    actingAs(userWith());
    $id = startTransfer($this, 100);

    actingAs(userWith());
    sendChunk($this, $id, 0, 100, 'abc')->assertForbidden();
    $this->call('HEAD', "/uploads/{$id}")->assertForbidden();
    $this->call('DELETE', "/uploads/{$id}")->assertForbidden();

    expect(TemporaryUpload::find($id))->not->toBeNull();
});

it('returns 404 for an unknown or malformed transfer id', function () {
    actingAs(userWith());

    $this->call('HEAD', '/uploads/00000000-0000-4000-8000-000000000000')->assertNotFound();
    $this->call('HEAD', '/uploads/..%2F..%2Fenv')->assertNotFound();
});

it('stores a small file sent whole', function () {
    $user = userWith();
    actingAs($user);

    $response = $this->post('/uploads', ['filepond' => UploadedFile::fake()->image('receipt.jpg', 600, 400)]);
    $response->assertOk();

    $upload = TemporaryUpload::find(trim($response->getContent()));

    expect($upload->isComplete())->toBeTrue()->and($upload->name())->toBe('receipt.jpg')->and($upload->ownerId())->toBe($user->id);
});

it('stores a whole file sent with the uploader input name', function () {
    $user = userWith();
    actingAs($user);

    $file = UploadedFile::fake()->createWithContent('مخطط.pdf', "%PDF-1.4\n%%EOF");
    $response = $this->post('/uploads', ['uploads' => [$file]], [
        'Upload-Length' => $file->getSize(),
        'Upload-Name' => rawurlencode($file->getClientOriginalName()),
    ]);
    $response->assertOk();

    $upload = TemporaryUpload::find(trim($response->getContent()));

    expect($upload->isComplete())->toBeTrue()
        ->and($upload->name())->toBe('مخطط.pdf')
        ->and($upload->ownerId())->toBe($user->id);
});

it('reverts a transfer', function () {
    actingAs(userWith());
    $id = startTransfer($this, 10);
    $directory = TemporaryUpload::find($id)->directory();

    $this->call('DELETE', "/uploads/{$id}")->assertNoContent();

    expect(is_dir($directory))->toBeFalse()->and(TemporaryUpload::find($id))->toBeNull();
});

it('accepts a poster image for a transfer', function () {
    actingAs(userWith());
    $id = startTransfer($this, 10);

    $this->post("/uploads/{$id}/poster", ['poster' => UploadedFile::fake()->image('poster.jpg', 320, 180)])->assertNoContent();
    $this->post("/uploads/{$id}/poster", ['poster' => UploadedFile::fake()->create('poster.pdf', 10, 'application/pdf')])->assertStatus(422);

    expect(TemporaryUpload::find($id)->posterPath())->not->toBeNull();
});
