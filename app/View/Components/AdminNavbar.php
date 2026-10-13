<?php

namespace App\View\Components;

use App\Enums\LoaStatus;
use App\Enums\VisitRequestStatus;
use App\Models\Loa;
use App\Models\StaffingRequest;
use App\Models\VisitorRequest;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class AdminNavbar extends Component
{
    public int $pendingVisitorRequests;

    public int $pendingLoas;

    public int $openStaffingRequests;

    /**
     * Create a new component instance.
     */
    public function __construct()
    {
        $this->pendingVisitorRequests = VisitorRequest::where('status', VisitRequestStatus::PENDING)->count();
        $this->pendingLoas = Loa::where('status', LoaStatus::PENDING)->count();
        $this->openStaffingRequests = StaffingRequest::where('closed', false)->count();
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        return view('components.admin-navbar');
    }
}
