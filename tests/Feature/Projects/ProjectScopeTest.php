<?php

use App\Actions\AttachUploads;
use App\Livewire\Tables\ProjectsTable;
use App\Models\Project;
use App\Services\Uploads\TemporaryUpload;
use Database\Seeders\DocumentSequenceSeeder;
use Database\Seeders\LookupSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed([LookupSeeder::class, DocumentSequenceSeeder::class]);
    $this->engineer = scopedUser(['projects.view', 'projects.update', 'projects.delete', 'projects.export'], 'assigned');
    $this->managed = Project::factory()->create(['manager_id' => $this->engineer->id, 'title_ar' => 'مشروع مخصص']);
    $this->member = Project::factory()->create(['title_ar' => 'مشروع مخصص']);
    $this->member->members()->attach($this->engineer->id);
    $this->other = Project::factory()->create(['title_ar' => 'مشروع مخصص']);
});

it('lists projects an engineer manages or belongs to only', function () {
    actingAs($this->engineer);

    expect(Livewire::test(ProjectsTable::class)->viewData('rows')->total())->toBe(2);
});

it('returns 404 on another project page, its media and in search', function () {
    Storage::fake('private');
    $file = UploadedFile::fake()->image('site.jpg', 400, 300);
    $bytes = file_get_contents($file->getPathname());
    $owner = scopedUser();
    $upload = TemporaryUpload::create($owner->id, strlen($bytes), 'site.jpg');
    file_put_contents($upload->path(), $bytes);
    $upload->saveMeta(['offset' => strlen($bytes)]);
    $media = app(AttachUploads::class)->handle($this->other, [$upload->id], $owner)->first();

    actingAs($this->engineer)->get(route('projects.show', $this->other))->assertNotFound();
    actingAs($this->engineer)->get(route('media.show', $media))->assertNotFound();
    actingAs($this->engineer)->get(route('projects.show', $this->member))->assertOk();
    actingAs($this->engineer)->getJson('/search?q='.urlencode('مشروع مخصص'))->assertJsonCount(2, '0.results');
});

it('keeps export and delete all inside visible projects', function () {
    actingAs($this->engineer);

    Livewire::test(ProjectsTable::class)->call('deleteAll');

    expect(Project::query()->count())->toBe(1)->and(Project::query()->first()->id)->toBe($this->other->id);
});
