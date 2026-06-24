@extends('layouts.admin')

@section('title', 'Edit Training Ticket - #'.$trainingTicket->id)

@section('body')
    <div class="card card-body bg-base-300 w-full max-w-3xl">
        <a
            class="link mb-3 sm:absolute sm:top-5 sm:right-5"
            href="{{route('training-tickets.show', ['ticket' => $trainingTicket->id])}}"
        >
            View
        </a>
        <form
            class="flex flex-col"
            action="{{route('training-tickets.update', ['ticket' => $trainingTicket->id])}}"
            method="POST"
        >
            @method('PUT')
            @csrf

            <label for="student" class="label">Student</label>
            <input class="input" type="text" readonly value="{{$trainingTicket->student->name}} ({{$trainingTicket->student->id}})">

            <br>

            <label for="position" class="label">Position (must be in XXX_XXX form)</label>
            <input
                type="text"
                name="position"
                class="input"
                value="{{ old('position', $trainingTicket->position) }}"
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
                <option
                    value=""
                    {{ old('location', $trainingTicket->location) == "" ? 'selected' : '' }}>Select a Location</option>
                <option
                    value="0"
                    {{ old('location', $trainingTicket->location) == "0" ? 'selected' : '' }}>Classroom</option>
                <option
                    value="1"
                    {{ old('location', $trainingTicket->location) == "1" ? 'selected' : '' }}>Live Network</option>
                <option
                    value="2"
                    {{ old('location', $trainingTicket->location) == "2" ? 'selected' : '' }}>Sweatbox</option>
            </select>

            <br>

            <label for="sessionStart" class="label">Start Date</label>
            <input
                type="datetime-local"
                name="sessionStart"
                value="{{ old('sessionStart', $trainingTicket->session_start) }}"
                class="input"
                required
            >

            <br>

            <label for="sessionEnd" class="label">End Date</label>
            <input
                type="datetime-local"
                name="sessionEnd"
                value="{{ old('sessionEnd', $trainingTicket->session_end) }}"
                class="input"
                required
            >

            <br>

            <label for="movements" class="label">Number of Movements</label>
            <input
                name="movements"
                type="number"
                class="input"
                value="{{ old('movements', $trainingTicket->movements) }}"
                required
            >

            <br>

            <label for="score" class="label">Score</label>
            <div class="rating">
                <input
                    type="radio"
                    name="score"
                    value="1"
                    class="mask mask-star"
                    aria-label="1 star"
                    {{ old('score', $trainingTicket->score) == 1 ? 'checked' : "" }}
                />
                <input
                    type="radio"
                    name="score"
                    value="2"
                    class="mask mask-star"
                    aria-label="2 star"
                    {{ old('score', $trainingTicket->score) == 2 ? 'checked' : "" }}
                />
                <input
                    type="radio"
                    name="score"
                    value="3"
                    class="mask mask-star"
                    aria-label="3 star"
                    {{ old('score', $trainingTicket->score) == 3 ? 'checked' : "" }}
                />
                <input
                    type="radio"
                    name="score"
                    value="4"
                    class="mask mask-star"
                    aria-label="4 star"
                    {{ old('score', $trainingTicket->score) == 4 ? 'checked' : "" }}
                />
                <input
                    type="radio"
                    name="score"
                    value="5"
                    class="mask mask-star"
                    aria-label="5 star"
                    {{ old('score', $trainingTicket->score) == 5 ? 'checked' : "" }}
                />
            </div>

            <br>

            <label for="notes" class="label">Notes</label>
            <textarea
                name="notes"
                id="notes"
                class="textarea"
                value="{{ old('notes', $trainingTicket->notes) }}"
                minlength="20"
                maxlength="2048">{{ old('notes', $trainingTicket->notes) }}
            </textarea>

            <div class="card-actions mt-5">
                <button
                    class="btn btn-primary"
                    type="submit"
                >Submit</button>
            </div>
        </form>
    </div>
@endsection
