@extends('layouts.admin')

@section('title', 'Manage FAQs')

@section('body')
    <x-card-component title="FAQ Management">
        @livewire('manage-faqs')
    </x-card-component>
@endsection

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/quill@2.0.0/dist/quill.snow.css" />
<script src="https://cdn.jsdelivr.net/npm/quill@2.0.0/dist/quill.js"></script>
<script src="https://cdn.jsdelivr.net/npm/quilljs-markdown@latest/dist/quilljs-markdown.js"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/quilljs-markdown@latest/dist/quilljs-markdown-common-style.css" />
