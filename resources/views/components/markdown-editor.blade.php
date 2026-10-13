@props(['name', 'content' => ''])

@php($editorId = 'markdown-editor-'.$name)
@php($fieldId = $name.'-field')

<textarea name="{{ $name }}" id="{{ $fieldId }}" hidden></textarea>
<div id="{{ $editorId }}" class="bg-base-100 text-base-content min-h-50">
    {{-- Defense in depth: the caller is expected to store already-sanitized HTML,
         but this is untrusted output either way, so it's purified again here. --}}
    {!! \Stevebauman\Purify\Facades\Purify::clean($content ?? '') !!}
</div>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/quill@2.0.0/dist/quill.snow.css" />
<script src="https://cdn.jsdelivr.net/npm/quill@2.0.0/dist/quill.js"></script>
<script src="https://cdn.jsdelivr.net/npm/quilljs-markdown@latest/dist/quilljs-markdown.js"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/quilljs-markdown@latest/dist/quilljs-markdown-common-style.css" />
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const editorEl = document.getElementById(@js($editorId));
        const field = document.getElementById(@js($fieldId));

        const quill = new Quill(editorEl, {
            theme: 'snow',
            modules: {
                toolbar: [
                    ['bold', 'italic', 'underline', 'strike'],
                    ['link', 'image']
                ]
            }
        });

        // Enable markdown shortcuts
        new QuillMarkdown(quill);

        // Keep the hidden textarea in sync so Laravel receives HTML
        field.value = editorEl.innerHTML;

        quill.on('text-change', function () {
            field.value = quill.root.innerHTML;
        });

        // Defensive: sync on submit to capture last keystrokes
        const form = field.form;
        if (form) {
            form.addEventListener('submit', function () {
                field.value = quill.root.innerHTML;
            });
        }
    });
</script>
