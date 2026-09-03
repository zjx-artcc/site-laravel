@extends('layouts.main')

@section('title', 'Visit the Virtual Jacksonville ARTCC')

@section('body')
    <x-card-component title='Eligibility Requirements'>
        <p class="mb-4">To submit a visiting request to the Virtual Jacksonville ARTCC (vZJX), you must meet the following eligibility requirements:</p>
        @if ($checklist->error)
            <p class='text-lg'>It appears you are not a member of VATUSA. Please read <a class='link link-primary' href="https://www.vatusa.net/help/kb#q12">this FAQ</a> on how to join as an out-of-division controller.</p>
        @else
            <div class='overflow-x-auto'>
            <table class='table w-full table-auto'>
                <tr>
                    <td>You are not a member of vZJX</td>
                    <td>
                        <x-true-false-display :value="!auth()->user()->rostered"/>
                    </td>
                </tr>
                <tr>
                    <td>You have a home facility</td>
                    <td>
                        <x-true-false-display :value="$checklist->hasHomeFacility"/>
                    </td>
                </tr>
                <tr>
                    <td>You have completed the <a class='link link-primary' href="https://www.vatusa.net/help/kb#q12">appropriate RCE</a> (out-of-division visitors only)</td>
                    <td>
                        <x-true-false-display :value="$checklist->needsBasic"/>
                    </td>
                </tr>
                <tr>
                    <td>You have an S3 rating or higher</td>
                    <td>
                        <x-true-false-display :value="(auth()->user()->rating->value >= \App\Enums\ControllerRating::S3->value)"/>
                    </td>
                </tr>
                <tr>
                    <td>It has been at least 60 days since visiting another facility</td>
                    <td>
                        <x-true-false-display :value="$checklist->visitingDaysMet"/>
                    </td>
                </tr>
                <tr>
                    <td>It has been at least 90 days since promotion</td>
                    <td>
                        <x-true-false-display :value="$checklist->ninetyDaysSincePromotion"/>
                    </td>
                </tr>
                <tr>
                    <td>You controlled at least 50 hours since your last promotion</td>
                    <td>
                        <x-true-false-display :value="$checklist->fiftyHoursSincePromotion"/>
                    </td>
                </tr>
    
            </table>
            </div>

            <p>For more detailed information, please check your <a class='link link-primary' href="https://www.vatusa.net/my/profile">VATUSA profile.</a></p>

            
            @if (!$checklist->visitEligible || auth()->user()->rostered || $checklist->error)
                <p class='text-lg text-error'>You are not eligible to submit a visiting request to the Virtual Jacksonville ARTCC (vZJX).</p>
            @else
                <p class='text-lg text-success'>You are eligible to submit a visiting request to the Virtual Jacksonville ARTCC (vZJX)!</p>
            @endif
        @endif
        
        @if ($checklist->visitEligible && !auth()->user()->rostered && !$checklist->error)
            <form class='flex flex-col w-full max-w-120 gap-5 mt-5' action="{{ route('visit.store') }}" method="post">
                @csrf
                <div>
                    <label for="cid">VATSIM CID</label>
                    <br>
                    <input type="text" class='input' disabled value="{{ auth()->user()->id }}">
                </div>

                <div>
                    <label for="personalNote">Why do you want to visit vZJX?</label>
                    <br>
                    <textarea id="personalNote" name="personalNote" class="textarea textarea-bordered w-full" rows="4" maxlength='1000'></textarea>
                </div>
                
                <button type="submit" class="btn btn-primary">Submit Visiting Request</button>
            </form>
        @endif
    </x-card-component>
@endsection