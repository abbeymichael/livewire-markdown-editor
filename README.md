# Laravel Markdown Editor

<p>
    <img src="art/banner.png" alt="Markdown Livewire Banner" />
</p>

<p>
    <a href="https://github.com/abbeymichael/livewire-markdown-editor/actions"><img src="https://github.com/abbeymichael/livewire-markdown-editor/actions/workflows/ci.yml/badge.svg" alt="Build Status"></a>
    <a href="https://packagist.org/packages/abbeymichael/livewire-markdown-editor"><img src="https://img.shields.io/packagist/dt/abbeymichael/livewire-markdown-editor" alt="Total Downloads"></a>
    <a href="https://packagist.org/packages/abbeymichael/livewire-markdown-editor"><img src="https://img.shields.io/packagist/v/abbeymichael/livewire-markdown-editor" alt="Latest Stable Version"></a>
    <a href="https://packagist.org/packages/abbeymichael/livewire-markdown-editor"><img src="https://img.shields.io/packagist/l/abbeymichael/livewire-markdown-editor" alt="License"></a>
</p>

GitHub-style Markdown editor for Laravel with Livewire and Alpine.js. This module provides a complete, standalone Markdown editing experience with full dark mode support.

## Dependencies

- Laravel 11+
- Livewire 3.6+
- Tailwind CSS 4.1
- League CommonMark
- GitHub Markdown Toolbar Element
- GitHub Text Expander Element

## Features

- 🎨 GitHub-style toolbar with all formatting options
- 📝 Live markdown preview
- 🌓 Full dark mode support
- 📎 File upload with automatic Markdown insertion
- ✨ GitHub Flavored Markdown (GFM) support
- 🔖 Spatie Shiki Highlight code blocks
- 📋 Tables, task lists, and more
- 🔄 Livewire integration with two-way binding
- 🎯 Multiple editor instances support

## Installation

Install via Composer:

```bash
composer require abbeymichael/livewire-markdown-editor
```

Install the required JS dependencies:

```bash
npm install --save @github/markdown-toolbar-element @github/text-expander-element
```

### 2. Load assets

Add the module's JavaScript to your `resources/js/app.js`:

```js
import '../../vendor/abbeymichael/livewire-markdown-editor/resources/js/markdown-editor.js';
```

And the CSS to your `resources/css/app.css`:

```css
@import "../../vendor/abbeymichael/livewire-markdown-editor/resources/css/markdown-editor.css";
```

Then build:

```bash
npm run build
```

### 3. Register the module

The service provider is auto-discovered via Laravel's package discovery.

## Usage

### Basic Usage

```blade
<livewire:markdown-editor wire:model="content" />
```

### With Custom Configuration

```blade
<livewire:markdown-editor
    wire:model="comment"
    placeholder="Write your comment..."
    :rows="15"
    :show-toolbar="true"
    :show-upload="true"
/>
```

### In Livewire Components

```php
use Livewire\Component;

class CreatePost extends Component
{
    public string $content = '';

    public function save()
    {
        $this->validate([
            'content' => 'required|min:10',
        ]);

        // $this->content contains the markdown
    }

    public function render()
    {
        return view('livewire.create-post');
    }
}
```

```blade
<div>
    <livewire:markdown-editor wire:model="content" />
    <button wire:click="save">Save</button>
</div>
```

## Component Properties

| Property      | Type   | Default                | Description                                  |
|---------------|--------|------------------------|----------------------------------------------|
| `content`     | string | `''`                   | The markdown content (use with `wire:model`) |
| `placeholder` | string | `'Leave a comment...'` | Textarea placeholder text                    |
| `class`       | string | `null`                 | Textarea custom classes                      |
| `rows`        | int    | `10`                   | Number of textarea rows                      |
| `showToolbar` | bool   | `true`                 | Show/hide the markdown toolbar               |
| `showUpload`  | bool   | `true`                 | Show/hide the file upload button             |

## Configuration

You can configure the editor without publishing the config file by setting environment variables in your `.env`:

```env
# Storage disk to use (must be defined in config/filesystems.php)
MARKDOWN_EDITOR_FILESYSTEM_DISK=public

# Shiki syntax highlighting theme
# Options: github-dark, github-light, monokai, nord, one-dark-pro, dracula
# Full list: https://github.com/shikijs/textmate-grammars-themes/tree/main/packages/tm-themes
MARKDOWN_EDITOR_THEME=github-light

# File upload visibility: 'public' or 'private'
# Set to 'public' so uploaded images are accessible via URL in rendered markdown.
# Set to 'private' if you want to restrict access and serve signed/temporary URLs yourself.
MARKDOWN_EDITOR_UPLOAD_VISIBILITY=public
```

If you need deeper customization, publish the full config file:

```bash
php artisan vendor:publish --tag=livewire-markdown-editor-config
```

This creates `config/livewire-markdown-editor.php`:

```php
return [
    'disk'  => env('MARKDOWN_EDITOR_FILESYSTEM_DISK', 'local'),
    'theme' => env('MARKDOWN_EDITOR_THEME', 'github-light'),

    'upload' => [
        'max_size'           => 4096, // kilobytes
        'allowed_extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif'],
        'images_only'        => true,
        'visibility'         => env('MARKDOWN_EDITOR_UPLOAD_VISIBILITY', 'public'),
    ],
];
```

## File Uploads

Files are automatically uploaded to the configured disk when selected. Images are inserted as `![filename](url)` and other files as `[filename](url)`.

Make sure your storage symlink is set up:

```bash
php artisan storage:link
```

### Visibility

By default uploaded files are stored as `public` so they are accessible via URL in rendered markdown. You can switch to `private` storage via your `.env` if you want to restrict access and generate signed or temporary URLs yourself:

```env
MARKDOWN_EDITOR_UPLOAD_VISIBILITY=private
```

### Security

To prevent arbitrary file upload vulnerabilities (stored XSS, phishing page hosting, malware distribution), only images are accepted by default. Uploaded files are stored under a randomly generated filename with the validated extension, and the original client-provided filename is sanitized before being inserted into the markdown output.

You can allow non-image files by updating your config:

```php
'upload' => [
    'max_size'           => 4096,
    'allowed_extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'pdf', 'zip'],
    'images_only'        => false,
    'visibility'         => env('MARKDOWN_EDITOR_UPLOAD_VISIBILITY', 'public'),
],
```

> When using a public cloud disk (S3, Spaces, R2, Scaleway), review your bucket policy to ensure non-whitelisted Content-Types cannot be served inline.

To disable file uploads entirely:

```blade
<livewire:markdown-editor
    wire:model="content"
    :show-upload="false"
/>
```

## Toolbar Features

- **Heading** - Insert heading
- **Bold** - Make text bold
- **Italic** - Make text italic
- **Quote** - Insert blockquote
- **Code** - Insert code block
- **Link** - Insert link
- **Unordered List** - Insert bullet list
- **Ordered List** - Insert numbered list
- **Task List** - Insert checklist
- **File Upload** - Upload and insert files/images

## Markdown Support

The editor supports full GitHub Flavored Markdown including:

- Headings
- Bold, italic, strikethrough
- Links and images
- Code blocks with syntax highlighting
- Task lists
- Blockquotes
- Horizontal rules

## Dark Mode

Dark mode is fully supported and automatically follows your Tailwind CSS dark mode configuration.

## Customization

### Publishing Views

```bash
php artisan vendor:publish --tag=livewire-markdown-editor-views
```

Views will be published to `resources/views/vendor/livewire-markdown-editor/`.

## License

Distributed under the MIT license. See LICENSE for details.
