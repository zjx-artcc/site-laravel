<?php

use App\Models\Publication;
use App\Models\PublicationCategory;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
});

function makeCategory(): PublicationCategory
{
    return PublicationCategory::create([
        'title' => 'Standard Operating Procedures',
        'description' => 'Facility SOPs',
        'display_order' => 0,
    ]);
}

function makePublication(string $filePath = 'documents/example.pdf', string $originalFilename = 'example.pdf'): Publication
{
    return Publication::create([
        'publication_category_id' => makeCategory()->id,
        'name' => 'Example Document',
        'description' => 'An example document.',
        'file_path' => $filePath,
        'original_filename' => $originalFilename,
        'file_size' => 1234,
    ]);
}

// Serving is decided by sniffing the stored bytes, so fixtures need real magic numbers.
function pdfBytes(): string
{
    return "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n%%EOF\n";
}

function pngBytes(): string
{
    ob_start();
    imagepng(imagecreatetruecolor(4, 4));

    return ob_get_clean();
}

function zipBytes(): string
{
    return "PK\x03\x04\x14\x00\x00\x00\x08\x00".str_repeat("\x00", 64);
}

/** Writes real bytes to the faked disk so the sniffed type matches the extension. */
function storeFile(string $path, string $contents): void
{
    Storage::disk('public')->put($path, $contents);
}

// --- Public file serving (the 403 fix) ---

test('the public file route serves a stored document without authentication', function () {
    Storage::fake('public');
    storeFile('documents/example.pdf', pdfBytes());

    $publication = makePublication();

    $response = $this->get(route('publications.file', $publication));

    $response->assertStatus(200);
    expect($response->streamedContent())->toBe(pdfBytes());
});

test('the public file route returns 404 when the physical file is missing', function () {
    Storage::fake('public');

    $publication = makePublication('documents/missing.pdf');

    $this->get(route('publications.file', $publication))->assertStatus(404);
});

test('file_url points at the serving route instead of the storage symlink', function () {
    $publication = makePublication();

    expect($publication->file_url)->toBe(route('publications.file', $publication));
});

// --- Admin permission (documents:write) ---

test('a user without documents:write cannot access publication management', function () {
    $user = User::factory()->create();
    $user->assignRole('staff'); // has "view dashboard" but not "documents:write"

    $this->actingAs($user)
        ->get(route('admin.publications.index'))
        ->assertStatus(403);
});

test('a facilities user with documents:write can access publication management', function () {
    $user = User::factory()->create();
    $user->assignRole('facilities', 'staff'); // mirrors real roster assignment

    $this->actingAs($user)
        ->get(route('admin.publications.index'))
        ->assertStatus(200);
});

// --- Upload validation (allowed types) ---

test('an allowed file type can be uploaded', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $user->assignRole('facilities', 'staff');
    $category = makeCategory();

    $response = $this->actingAs($user)->post(route('admin.publications.store'), [
        'publication_category_id' => $category->id,
        'name' => 'New SOP',
        'description' => 'A new SOP.',
        'file' => UploadedFile::fake()->create('sop.pdf', 200, 'application/pdf'),
    ]);

    $response->assertRedirect(route('admin.publications.index'));
    expect(Publication::where('name', 'New SOP')->exists())->toBeTrue();
});

test('a disallowed file type is rejected', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $user->assignRole('facilities', 'staff');
    $category = makeCategory();

    $response = $this->actingAs($user)->post(route('admin.publications.store'), [
        'publication_category_id' => $category->id,
        'name' => 'Bad Upload',
        'description' => 'Should be rejected.',
        'file' => UploadedFile::fake()->create('notes.txt', 10, 'text/plain'),
    ]);

    $response->assertSessionHasErrors('file');
    expect(Publication::where('name', 'Bad Upload')->exists())->toBeFalse();
});

// --- Public listing ---

test('a docx uploaded before the PDF ban still links to its file', function () {
    $docx = makePublication('documents/sop.docx', 'sop.docx');

    $response = $this->get(route('publications.index'));

    $response->assertOk();
    $response->assertSee($docx->file_url, false);
});

test('the listing wraps the whole document row in one link rather than separate buttons', function () {
    $publication = makePublication();

    $response = $this->get(route('publications.index'));

    $response->assertOk();
    // The name, description and timestamp all sit inside the single anchor, and
    // nothing forces a browser download separately from that link.
    $response->assertSee('<a href="'.$publication->file_url.'"', false);
    $response->assertDontSee('download=', false);
    expect(substr_count($response->getContent(), $publication->file_url))->toBe(1);
});

// The download arrow is only rendered for files that save to disk, so it
// distinguishes the two labels without tripping over the page heading, which
// itself contains the word "Downloads".
const DOWNLOAD_ICON = 'M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4';

test('a pdf is labelled View because it opens in a tab', function () {
    Storage::fake('public');
    storeFile('documents/example.pdf', pdfBytes());

    makePublication();

    $response = $this->get(route('publications.index'));

    $response->assertOk();
    $response->assertSee('View');
    $response->assertDontSee(DOWNLOAD_ICON, false);
});

test('a json profile is labelled Download because it saves to disk', function () {
    Storage::fake('public');
    storeFile('documents/profile.json', '{"id":"ZJX"}');

    makePublication('documents/profile.json', 'profile.json');

    $response = $this->get(route('publications.index'));

    $response->assertOk();
    $response->assertSee(DOWNLOAD_ICON, false);
});

test('a document with no description renders without an empty description line', function () {
    $publication = makePublication();
    $publication->update(['description' => null]);

    $response = $this->get(route('publications.index'));

    $response->assertOk();
    $response->assertSee($publication->name);
});

test('a category with no description renders without an empty description line', function () {
    $category = makeCategory();
    $category->update(['description' => null]);

    $response = $this->get(route('publications.index'));

    $response->assertOk();
    $response->assertSee($category->title);
});

test('a document can be uploaded without a description', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $user->assignRole('facilities', 'staff');
    $category = makeCategory();

    $response = $this->actingAs($user)->post(route('admin.publications.store'), [
        'publication_category_id' => $category->id,
        'name' => 'Undescribed SOP',
        'file' => UploadedFile::fake()->create('sop.pdf', 200, 'application/pdf'),
    ]);

    $response->assertRedirect(route('admin.publications.index'));
    expect(Publication::where('name', 'Undescribed SOP')->exists())->toBeTrue();
});

test('a word document is rejected because official documents must be immutable PDFs', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $user->assignRole('facilities', 'staff');
    $category = makeCategory();

    $response = $this->actingAs($user)->post(route('admin.publications.store'), [
        'publication_category_id' => $category->id,
        'name' => 'Word SOP',
        'file' => UploadedFile::fake()->create('sop.docx', 200, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
    ]);

    $response->assertSessionHasErrors('file');
    expect(Publication::where('name', 'Word SOP')->exists())->toBeFalse();
});

test('a zip renamed to .pdf is rejected because its contents are sniffed', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $user->assignRole('facilities', 'staff');
    $category = makeCategory();

    $response = $this->actingAs($user)->post(route('admin.publications.store'), [
        'publication_category_id' => $category->id,
        'name' => 'Zip Bomb',
        'file' => UploadedFile::fake()->create('item.pdf', 200, 'application/zip'),
    ]);

    $response->assertSessionHasErrors('file');
    expect(Publication::where('name', 'Zip Bomb')->exists())->toBeFalse();
});

test('an allowed type under the wrong allowed extension is rejected', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $user->assignRole('facilities', 'staff');
    $category = makeCategory();

    // Both halves are individually on the allowlist; only the pairing is wrong.
    $response = $this->actingAs($user)->post(route('admin.publications.store'), [
        'publication_category_id' => $category->id,
        'name' => 'Mislabelled Chart',
        'file' => UploadedFile::fake()->create('chart.pdf', 200, 'image/jpeg'),
    ]);

    $response->assertSessionHasErrors('file');
    expect(Publication::where('name', 'Mislabelled Chart')->exists())->toBeFalse();
});

test('an image can still be uploaded for maps and charts', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $user->assignRole('facilities', 'staff');
    $category = makeCategory();

    $response = $this->actingAs($user)->post(route('admin.publications.store'), [
        'publication_category_id' => $category->id,
        'name' => 'Sector Map',
        'file' => UploadedFile::fake()->create('map.png', 100, 'image/png'),
    ]);

    $response->assertRedirect(route('admin.publications.index'));
    expect(Publication::where('name', 'Sector Map')->exists())->toBeTrue();
});

test('a json file can be uploaded', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $user->assignRole('facilities', 'staff');
    $category = makeCategory();

    $response = $this->actingAs($user)->post(route('admin.publications.store'), [
        'publication_category_id' => $category->id,
        'name' => 'Sector File',
        'file' => UploadedFile::fake()->createWithContent('sectors.json', '{"sectors":[{"id":"ZJX"}]}'),
    ]);

    $response->assertRedirect(route('admin.publications.index'));
    expect(Publication::where('name', 'Sector File')->exists())->toBeTrue();
});

// --- How files are served (inline vs download) ---

test('a pdf is served inline so it opens in a browser tab', function () {
    Storage::fake('public');
    storeFile('documents/example.pdf', pdfBytes());

    $publication = makePublication();

    $response = $this->get(route('publications.file', $publication));

    $response->assertOk();
    expect($response->headers->get('Content-Disposition'))->toStartWith('inline');
    expect($response->headers->get('Content-Type'))->toBe('application/pdf');
});

test('a json profile is served as a download rather than raw text in a tab', function () {
    Storage::fake('public');
    storeFile('documents/profile.json', '{"id":"ZJX"}');

    $publication = makePublication('documents/profile.json', 'profile.json');

    $response = $this->get(route('publications.file', $publication));

    $response->assertOk();
    expect($response->headers->get('Content-Disposition'))->toStartWith('attachment');
});

test('an image is served inline so it opens in a browser tab', function () {
    Storage::fake('public');
    storeFile('documents/map.png', pngBytes());

    $publication = makePublication('documents/map.png', 'map.png');

    $response = $this->get(route('publications.file', $publication));

    $response->assertOk();
    expect($response->headers->get('Content-Disposition'))->toStartWith('inline');
    expect($response->headers->get('Content-Type'))->toBe('image/png');
});

test('every served file carries nosniff so a mislabelled upload cannot run as HTML', function () {
    Storage::fake('public');
    storeFile('documents/example.pdf', pdfBytes());

    $publication = makePublication();

    $this->get(route('publications.file', $publication))
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff');
});

test('a file whose contents do not match its extension is served as a download, not inline', function () {
    Storage::fake('public');
    // A zip that slipped in as .pdf before this validation existed.
    storeFile('documents/legacy.pdf', zipBytes());

    $publication = makePublication('documents/legacy.pdf', 'legacy.pdf');

    $response = $this->get(route('publications.file', $publication));

    $response->assertOk();
    expect($response->headers->get('Content-Disposition'))->toStartWith('attachment');
    expect($response->headers->get('Content-Type'))->toBe('application/octet-stream');
});

test('a description is still saved and shown when one is written', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $user->assignRole('facilities', 'staff');
    $category = makeCategory();

    $this->actingAs($user)->post(route('admin.publications.store'), [
        'publication_category_id' => $category->id,
        'name' => 'Described SOP',
        'description' => 'Covers ground movement at KJAX.',
        'file' => UploadedFile::fake()->create('sop.pdf', 200, 'application/pdf'),
    ]);

    $publication = Publication::where('name', 'Described SOP')->sole();
    expect($publication->description)->toBe('Covers ground movement at KJAX.');

    $this->get(route('publications.index'))
        ->assertOk()
        ->assertSee('Covers ground movement at KJAX.');
});

test('a category description is still saved when one is written', function () {
    $user = User::factory()->create();
    $user->assignRole('facilities', 'staff');

    $this->actingAs($user)->post(route('admin.publications.categories.store'), [
        'title' => 'vATIS Profiles',
        'description' => 'Import these into vATIS.',
        'display_order' => 1,
    ]);

    $category = PublicationCategory::where('title', 'vATIS Profiles')->sole();
    expect($category->description)->toBe('Import these into vATIS.');
});
