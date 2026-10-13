@extends('layouts.admin')

@section('title', 'Create Training Ticket')

@section('body')
    <x-card-component>
        <form
        class="flex flex-col"
        action="{{route('training-tickets.store')}}"
        method="POST"
        >
            @csrf

            <label for="student" class="label">Student</label>
            <select
                name="student"
                id=""
                class="select"
                required
            >
                <option value="">Select a Student</option>

                @foreach($users as $user)
                    <option value="{{$user->id}}" @selected(old('student') == $user->id)>{{$user->nameReversed}} ({{$user->id}})</option>
                @endforeach
            </select>

            <br>

            <label for="position" class="label">Position (must be in XXX_XXX form)</label>
            @error('position')
                <p>Position must be in XXX_XXX format</p>
            @enderror
            <input
                type="text"
                name="position"
                class="input"
                value="{{old('position')}}"
                oninput="this.value = this.value.toUpperCase();"
                pattern="^([A-Z]{2,3})(_([A-Z]{1,3}))?_(DEL|GND|TWR|APP|DEP|CTR)$"
                required
            >

            <br>

            <label for="location" class="label">Session Location</label>
            <select
                name="location"
                id=""
                class="select"
                required
            >
                <option value="">Select a Location</option>
                <option value="0" @selected(old('location') == 0)>Classroom</option>
                <option value="1" @selected(old('location') == 1)>Live Network</option>
                <option value="2" @selected(old('location') == 2)>Sweatbox</option>
            </select>

            <br>

            <label for="sessionStart" class="label">Start Date</label>
            @error('sessionStart')
                <p class="text-error">Must be after session end</p>
            @enderror
            <input
                type="datetime-local"
                name="sessionStart"
                class="input"
                value="{{old('sessionStart')}}"
                required
            >

            <br>

            <label for="sessionEnd" class="label">End Date</label>
            <input
                type="datetime-local"
                name="sessionEnd"
                class="input"
                value="{{old('sessionEnd')}}"
                required
            >

            <br>

            <label for="movements" class="label">Number of Movements</label>
            <input
                name="movements"
                type="number"
                class="input"
                value="{{old('movements', 0)}}"
                required
            >

            <br>

            <label for="score" class="label">Score</label>
            <div class="rating">
                <input type="radio" name="score" value="1" class="mask mask-star" aria-label="1 star" @checked(old('score') == 1)>
                <input type="radio" name="score" value="2" class="mask mask-star" aria-label="2 star" @checked(old('score') == 2)>
                <input type="radio" name="score" value="3" class="mask mask-star" aria-label="3 star" @checked(old('score') == 3)>
                <input type="radio" name="score" value="4" class="mask mask-star" aria-label="4 star" @checked(old('score') == 4)>
                <input type="radio" name="score" value="5" class="mask mask-star" aria-label="5 star" @checked(old('score') == 5)>
            </div>

            <br>

            <label for="notes" class="label">Session Notes</label>
            <p class="text-sm opacity-70 mb-1">Shared with the student &mdash; they see this on their training ticket.</p>
            <textarea name="notes" hidden></textarea>
            <div id="editor" class='bg-base-100 text-base-content min-h-50'>
                {!! old('notes') !!}
            </div>

            <br>

            <label for="instructor_notes" class="label">Instructor Notes (Optional)</label>
            <p class="text-sm opacity-70 mb-1">Visible only to training staff. The student never sees this. Markdown shortcuts are supported.</p>
            @error('instructor_notes')
                <p class="text-error">{{ $message }}</p>
            @enderror
            <x-markdown-editor name="instructor_notes" :content="old('instructor_notes')" />

            <br>

            @if($certificationFacilities->isNotEmpty())
                <label for="certification_level_id" class="label">Issue Certification (optional)</label>
                <select name="certification_level_id" id="certification_level_id" class="select">
                    <option value="">Do not issue a certification</option>
                    @foreach($certificationFacilities as $facility)
                        @if($facility->certificationLevels->isNotEmpty())
                            <optgroup label="{{ $facility->identifier }} — {{ $facility->name }}">
                                @foreach($facility->certificationLevels as $level)
                                    <option value="{{ $level->id }}" @selected(old('certification_level_id') == $level->id)>
                                        {{ $level->name }} ({{ $level->abbreviation }})
                                    </option>
                                @endforeach
                            </optgroup>
                        @endif
                    @endforeach
                </select>

                <br>
            @endif

            <div class="flex flex-row max-w-full">
                <input type="checkbox" class="checkbox mr-2" name="confirm" required>
                <label for="confirm" class="">I understand that this training ticket, once synced to VATUSA, will be immutable and final.</label>
            </div>
            <div class="card-actions mt-5">
                <button
                    class="btn btn-primary"
                    type="submit"
                >Submit</button>
            </div>
        </form>
    </x-card-component>
@endsection

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/quill@2.0.0/dist/quill.snow.css" />
<script src="https://cdn.jsdelivr.net/npm/quill@2.0.0/dist/quill.js"></script>
<script src="https://cdn.jsdelivr.net/npm/quilljs-markdown@latest/dist/quilljs-markdown.js"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/quilljs-markdown@latest/dist/quilljs-markdown-common-style.css" />
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const quill = new Quill('#editor', {
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

        // Keep a hidden textarea in sync so Laravel receives HTML
        const notesField = document.querySelector('textarea[name="notes"]');
        if (notesField) {
            // Initialize from existing editor HTML (populated by old('notes'))
            // Ensure the hidden field has the same HTML so validation re-renders correctly
            notesField.value = document.getElementById('editor').innerHTML;

            // Sync on every change
            quill.on('text-change', function () {
                notesField.value = quill.root.innerHTML;
            });

            // Defensive: sync on submit to capture last keystrokes
            const form = notesField.form;
            if (form) {
                form.addEventListener('submit', function () {
                    notesField.value = quill.root.innerHTML;
                });
            }
        }
    });
  
</script>
