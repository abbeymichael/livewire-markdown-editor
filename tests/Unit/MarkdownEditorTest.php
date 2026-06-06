<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mckenziearts\LivewireMarkdownEditor\Livewire\MarkdownEditor;

// ---------------------------------------------------------------------------
// Basic rendering
// ---------------------------------------------------------------------------

it('renders successfully', function (): void {
    Livewire\Livewire::test(MarkdownEditor::class)
        ->assertOk();
});

it('display correct editor content value', function (): void {
    Livewire\Livewire::test(MarkdownEditor::class)
        ->set('content', 'foo')
        ->assertSet('content', 'foo');
});

// ---------------------------------------------------------------------------
// Security: rejected uploads
// ---------------------------------------------------------------------------

it('rejects html file uploads', function (): void {
    Storage::fake('local');

    $htmlFile = UploadedFile::fake()->createWithContent(
        'phishing.html',
        '<html><body>phishing</body></html>',
    );

    Livewire\Livewire::test(MarkdownEditor::class)
        ->set('attachments', [$htmlFile])
        ->assertHasErrors(['attachments.0']);

    expect(Storage::disk('local')->allFiles())->toBeEmpty();
});

it('rejects svg file uploads by default', function (): void {
    Storage::fake('local');

    $svgFile = UploadedFile::fake()->createWithContent(
        'payload.svg',
        '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>',
    );

    Livewire\Livewire::test(MarkdownEditor::class)
        ->set('attachments', [$svgFile])
        ->assertHasErrors(['attachments.0']);

    expect(Storage::disk('local')->allFiles())->toBeEmpty();
});

it('rejects files with disallowed extension even if renamed', function (): void {
    Storage::fake('local');

    $exeFile = UploadedFile::fake()->createWithContent(
        'malware.exe',
        'MZ binary content',
    );

    Livewire\Livewire::test(MarkdownEditor::class)
        ->set('attachments', [$exeFile])
        ->assertHasErrors(['attachments.0']);

    expect(Storage::disk('local')->allFiles())->toBeEmpty();
});

it('rejects files exceeding the configured max size', function (): void {
    Storage::fake('local');
    config()->set('livewire-markdown-editor.upload.max_size', 100);

    $largeImage = UploadedFile::fake()->image('large.png')->size(200);

    Livewire\Livewire::test(MarkdownEditor::class)
        ->set('attachments', [$largeImage])
        ->assertHasErrors(['attachments.0']);

    expect(Storage::disk('local')->allFiles())->toBeEmpty();
});

// ---------------------------------------------------------------------------
// Security: disallowed extension gives friendly error, no upload occurs
// ---------------------------------------------------------------------------

it('sets uploadErrors and stores nothing when a pdf is uploaded under images_only mode', function (): void {
    Storage::fake('local');

    // images_only is true by default; pdf is not in allowed_extensions
    $pdf = UploadedFile::fake()->createWithContent('document.pdf', '%PDF-1.4 content');

    $component = Livewire\Livewire::test(MarkdownEditor::class)
        ->set('attachments', [$pdf]);

    // No validation error bag error — our guard runs before validate()
    // The uploadErrors array should contain the friendly message
    $uploadErrors = $component->get('uploadErrors');
    expect($uploadErrors)->not->toBeEmpty();

    // Nothing stored
    expect(Storage::disk('local')->allFiles())->toBeEmpty();
});

// ---------------------------------------------------------------------------
// Successful uploads
// ---------------------------------------------------------------------------

it('accepts valid image uploads and inserts sanitized markdown', function (): void {
    Storage::fake('local');

    $image = UploadedFile::fake()->image('photo.png');

    Livewire\Livewire::test(MarkdownEditor::class)
        ->set('attachments', [$image])
        ->assertHasNoErrors()
        ->assertSet('attachments', []);

    expect(Storage::disk('local')->allFiles())->toHaveCount(1);
});

it('sets uploadMessages after a successful upload', function (): void {
    Storage::fake('local');

    $image = UploadedFile::fake()->image('photo.png');

    $component = Livewire\Livewire::test(MarkdownEditor::class)
        ->set('attachments', [$image])
        ->assertHasNoErrors();

    $messages = $component->get('uploadMessages');
    expect($messages)->not->toBeEmpty();
});

// ---------------------------------------------------------------------------
// Image markdown syntax: uses extension-based detection, not getMimeType()
// ---------------------------------------------------------------------------

it('inserts image markdown syntax for png using extension not getMimeType', function (): void {
    Storage::fake('local');

    $image = UploadedFile::fake()->image('photo.png');

    $component = Livewire\Livewire::test(MarkdownEditor::class)
        ->set('attachments', [$image])
        ->assertHasNoErrors();

    $content = (string) $component->get('content');
    // Must use image markdown (![...](...)  not plain link [...](...))
    expect($content)->toContain('![');
    expect($content)->not->toMatch('/^\n\[[^\]]+\]\([^)]+\)\n$/');
});

it('inserts link markdown syntax for non-image files when images_only is false', function (): void {
    Storage::fake('local');
    config()->set('livewire-markdown-editor.upload.images_only', false);
    config()->set('livewire-markdown-editor.upload.allowed_extensions', ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'pdf']);

    $pdf = UploadedFile::fake()->createWithContent('doc.pdf', '%PDF-1.4 content');

    $component = Livewire\Livewire::test(MarkdownEditor::class)
        ->set('attachments', [$pdf])
        ->assertHasNoErrors();

    $content = (string) $component->get('content');
    // pdf is not an image extension — must use plain link syntax
    expect($content)->toContain('[doc.pdf]');
    expect($content)->not->toContain('![');
});

// ---------------------------------------------------------------------------
// Cursor-position insertion
// ---------------------------------------------------------------------------

it('inserts the uploaded file markdown at the cursor position', function (): void {
    Storage::fake('local');

    $image = UploadedFile::fake()->image('avatar.png');

    $initialContent = "Hello world\nSecond line";
    // Cursor is after "Hello" (position 5)
    $cursorPos = 5;

    $component = Livewire\Livewire::test(MarkdownEditor::class)
        ->set('content', $initialContent)
        ->set('cursorPosition', $cursorPos)
        ->set('attachments', [$image])
        ->assertHasNoErrors();

    $content = (string) $component->get('content');

    // The first 5 characters must still be "Hello"
    expect(mb_substr($content, 0, 5))->toBe('Hello');

    // The image markdown must appear somewhere before position 5+some offset
    // (i.e. not purely at the end of the original text)
    $endOfOriginal = mb_strpos($content, 'Second line');
    $imagePos      = mb_strpos($content, '![');
    expect($imagePos)->not->toBeFalse();
    expect($imagePos)->toBeLessThan((int) $endOfOriginal);
});

it('falls back to end-of-content insertion when cursorPosition is -1', function (): void {
    Storage::fake('local');

    $image = UploadedFile::fake()->image('photo.png');

    $initialContent = "Hello world";

    $component = Livewire\Livewire::test(MarkdownEditor::class)
        ->set('content', $initialContent)
        ->set('cursorPosition', -1)
        ->set('attachments', [$image])
        ->assertHasNoErrors();

    $content = (string) $component->get('content');
    // Original text must come first
    expect($content)->toStartWith('Hello world');
    // Image markdown must follow
    expect($content)->toContain('![');
});

// ---------------------------------------------------------------------------
// Storage visibility
// ---------------------------------------------------------------------------

it('stores uploaded file with public visibility by default', function (): void {
    Storage::fake('local');
    config()->set('livewire-markdown-editor.upload.visibility', 'public');

    $image = UploadedFile::fake()->image('pub.png');

    Livewire\Livewire::test(MarkdownEditor::class)
        ->set('attachments', [$image])
        ->assertHasNoErrors();

    $files = Storage::disk('local')->allFiles();
    expect($files)->toHaveCount(1);

    // Visibility should be public
    expect(Storage::disk('local')->getVisibility($files[0]))->toBe('public');
});

it('stores uploaded file with private visibility when configured', function (): void {
    Storage::fake('local');
    config()->set('livewire-markdown-editor.upload.visibility', 'private');

    $image = UploadedFile::fake()->image('priv.png');

    Livewire\Livewire::test(MarkdownEditor::class)
        ->set('attachments', [$image])
        ->assertHasNoErrors();

    $files = Storage::disk('local')->allFiles();
    expect($files)->toHaveCount(1);

    expect(Storage::disk('local')->getVisibility($files[0]))->toBe('private');
});

// ---------------------------------------------------------------------------
// Existing security: filename sanitisation & random disk name
// ---------------------------------------------------------------------------

it('sanitizes malicious filenames to prevent markdown breakout', function (): void {
    Storage::fake('local');

    $image = UploadedFile::fake()->image('evil](javascript:alert(1))![x.png');

    $component = Livewire\Livewire::test(MarkdownEditor::class)
        ->set('attachments', [$image])
        ->assertHasNoErrors();

    $content = (string) $component->get('content');

    expect($content)
        ->not->toContain('](javascript:')
        ->not->toContain(')![')
        ->not->toContain('](http://evil')
        ->toMatch('/\n!\[[^\[\]\(\)]*\]\([^)]+\)\n/');
});

it('generates a random filename on disk independent of client input', function (): void {
    Storage::fake('local');

    $image = UploadedFile::fake()->image('original-name.png');

    Livewire\Livewire::test(MarkdownEditor::class)
        ->set('attachments', [$image])
        ->assertHasNoErrors();

    $files = Storage::disk('local')->allFiles();

    expect($files)->toHaveCount(1);
    expect($files[0])->not->toContain('original-name');
    expect($files[0])->toEndWith('.png');
});
