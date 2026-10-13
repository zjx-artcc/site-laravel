@extends('layouts.main')

@section('title', 'FAQ & Help')

@section('body')
    @livewire('faqs')
@endsection

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/quill@2.0.0/dist/quill.snow.css" />
<script src="https://cdn.jsdelivr.net/npm/quill@2.0.0/dist/quill.js"></script>
