@extends('layouts.admin')

@section('title', 'Create News Announcement')

@section('body')
    <div class=" mx-auto">
    <x-card-component class="">
        <div class="flex flex-col ">
            <h1 class="font-bold text-xl mb-5">
                Announcement Information
            </h1>
            <form method="POST" class="flex flex-col gap-5" action="{{route('admin.news.store')}}">
                @csrf

                <div>
                    <label for="title" class="label font-bold text-base-content">Title</label>
                    <input
                        type="text"
                        name="title"
                        class="input w-full"
                        value="{{old('title')}}"
                        required
                    >
                </div>

                <div>
                    <label for="content" class="label font-bold text-base-content">Content</label>
                    <x-markdown-editor name="content" :content="old('content')" />
                </div>

                <div class="form-control">
                    <label class="label cursor-not-allowed gap-3 w-fit">
                        <input type="checkbox" class="checkbox" disabled>
                        <span class="label-text">Send email to opted-in users</span>
                    </label>
                    <p class="text-sm opacity-70">
                        Not yet implemented — publishing an announcement does not send any email. It will only
                        appear on the homepage.
                    </p>
                </div>

                <div class="card-actions mt-5">
                    <button
                        class="btn btn-primary"
                        type="submit"
                    >Publish Announcement <i class="fa-regular fa-paper-plane"></i></button>
                </div>

            </form>
        </div>

    </x-card-component>
    </div>
@endsection
