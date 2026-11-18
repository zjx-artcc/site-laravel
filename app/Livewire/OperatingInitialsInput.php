<?php

namespace App\Livewire;

use App\Models\VisitorRequest;
use App\Rules\NotReservedOperatingInitials;
use Livewire\Component;

class OperatingInitialsInput extends Component
{
    public VisitorRequest $visitRequest;

    public ?string $operatingInitials = '';

    public function mount(VisitorRequest $visitRequest): void
    {
        $this->visitRequest = $visitRequest;
        $this->operatingInitials = strtoupper($visitRequest->user->operating_initials ?? '');
    }

    public function rules(): array
    {
        return [
            'operatingInitials' => [
                'nullable',
                'string',
                'size:2',
                'alpha',
                new NotReservedOperatingInitials,
                'unique:users,operating_initials,'.$this->visitRequest->user_id,
            ],
        ];
    }

    public function updatedOperatingInitials(string $value): void
    {
        $this->operatingInitials = strtoupper($value);
        $this->validateOnly('operatingInitials');
    }

    public function render()
    {
        return view('livewire.operating-initials-input');
    }
}
