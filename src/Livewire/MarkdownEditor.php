<?php

declare(strict_types=1);

namespace Mckenziearts\LivewireMarkdownEditor\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\Extension\TaskList\TaskListExtension;
use League\CommonMark\MarkdownConverter;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Modelable;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Spatie\CommonMarkShikiHighlighter\HighlightCodeExtension;

final class MarkdownEditor extends Component
{
    use WithFileUploads;

    #[Modelable]
    public ?string $content = '';

    /** @var array<int, TemporaryUploadedFile> */
    public array $attachments = [];

    public string $placeholder = 'Leave a comment...';

    public int $rows = 10;

    public bool $showToolbar = true;

    public bool $showUpload = true;

    public ?string $class = null;

    /**
     * Cursor position (character offset) sent from the frontend before upload.
     * The JS side updates this via $wire.cursorPosition = textarea.selectionStart
     * right before triggering the file input.
     */
    public int $cursorPosition = -1;

    /** @var list<string> Flash messages shown after upload */
    public array $uploadMessages = [];

    /** @var list<string> Validation error messages shown to the user */
    public array $uploadErrors = [];

    #[Computed]
    public function parsedContent(): string
    {
        if (blank($this->content)) {
            return '';
        }

        $environment = new Environment([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);

        $environment->addExtension(new CommonMarkCoreExtension);
        $environment->addExtension(new GithubFlavoredMarkdownExtension);
        $environment->addExtension(new TableExtension);
        $environment->addExtension(new TaskListExtension);
        $environment->addExtension(new HighlightCodeExtension(theme: config('livewire-markdown-editor.theme'))); // @phpstan-ignore-line

        $converter = new MarkdownConverter(environment: $environment);

        return $converter->convert($this->content)->getContent();
    }

    /**
     * @return array<string, array<int, string|null>>
     */
    public function rules(): array
    {
        /** @var array{max_size: int, allowed_extensions: array<int, string>, images_only: bool} $config */
        $config = config('livewire-markdown-editor.upload');

        $extensions = implode(',', $config['allowed_extensions']);

        return [
            'attachments.*' => array_values(array_filter([
                'required',
                'file',
                $config['images_only'] ? 'image' : null,
                'mimes:'.$extensions,
                'extensions:'.$extensions,
                'max:'.$config['max_size'],
            ])),
        ];
    }

    public function updatedAttachments(): void
    {
        // Clear previous flash state
        $this->uploadMessages = [];
        $this->uploadErrors = [];

        /** @var array{max_size: int, allowed_extensions: array<int, string>, images_only: bool, visibility: string} $uploadConfig */
        $uploadConfig = config('livewire-markdown-editor.upload');

        // Pre-validate extension before hitting the full validator so we can
        // show a friendly "file type not allowed" message instead of a generic one.
        foreach ($this->attachments as $attachment) {
            $ext = strtolower((string) $attachment->getClientOriginalExtension());
            if (! in_array($ext, array_map('strtolower', $uploadConfig['allowed_extensions']), true)) {
                $allowed = implode(', ', $uploadConfig['allowed_extensions']);
                $this->uploadErrors[] = __('livewire-markdown-editor::editor.upload_error_type', ['extensions' => $allowed]);
                $this->attachments = [];

                return;
            }
        }

        // Run Laravel/Livewire validation (size, mimes, image rule, etc.)
        $validated = $this->validate();

        if ($this->getErrorBag()->isNotEmpty()) {
            $this->attachments = [];

            return;
        }

        /** @var string $disk */
        $disk = config('livewire-markdown-editor.disk');

        /** @var string $visibility */
        $visibility = $uploadConfig['visibility'] ?? 'public';

        $insertions = [];

        foreach ($this->attachments as $attachment) {
            $extension = strtolower((string) $attachment->extension());
            $storedPath = $attachment->storeAs('', Str::random(40).'.'.$extension, [
                'disk'       => $disk,
                'visibility' => $visibility,
            ]);

            if ($storedPath === false) {
                continue;
            }

            $filesystem = Storage::disk($disk);
            $url        = $filesystem->url($storedPath);
            $filename   = $this->sanitizeFilename($attachment->getClientOriginalName());

            // Determine whether to render as an image using the file extension
            // (getMimeType() relies on the OS mime.types database and may return
            // "application/octet-stream" for valid images, so we use a hard-coded
            // extension map as the authoritative source).
            $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'bmp', 'ico', 'tiff', 'tif', 'svg'];
            $isImage         = in_array($extension, $imageExtensions, true);

            if ($isImage) {
                $insertions[] = "![{$filename}]({$url})";
            } else {
                $insertions[] = "[{$filename}]({$url})";
            }
        }

        if ($insertions !== []) {
            $insertionText = "\n".implode("\n", $insertions)."\n";
            $this->insertAtCursor($insertionText);
            $this->uploadMessages[] = __('livewire-markdown-editor::editor.upload_success');
        }

        $this->attachments  = [];
        // Reset cursor so subsequent uploads without an explicit cursor update
        // default back to end-of-content behaviour.
        $this->cursorPosition = -1;
    }

    public function render(): View
    {
        return view('livewire-markdown-editor::livewire.markdown-editor');
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function sanitizeFilename(string $filename): string
    {
        $filename = preg_replace('/[\x00-\x1F\x7F]/u', '', $filename) ?? '';
        $filename = preg_replace('/[\[\]\(\)<>"\'\\\\`]/', '', $filename) ?? '';

        return Str::limit(trim($filename), 100, '');
    }

    /**
     * Insert $text into $this->content at the stored cursor position.
     * If the cursor position is invalid or not set, append at the end.
     */
    private function insertAtCursor(string $text): void
    {
        $current = $this->content ?? '';
        $len     = mb_strlen($current);

        // Clamp to a valid range
        $pos = $this->cursorPosition;
        if ($pos < 0 || $pos > $len) {
            $pos = $len;
        }

        $this->content = mb_substr($current, 0, $pos).$text.mb_substr($current, $pos);
    }
}
