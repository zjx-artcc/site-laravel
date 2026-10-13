<?php

namespace App\View\Components;

use App\Models\PublicationCategory;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class Navbar extends Component
{
    public $publicationCategories;

    public $mobilePublicationCategories;

    /**
     * Create a new component instance.
     */
    public function __construct()
    {
        $this->publicationCategories = PublicationCategory::forNavbar();
        $this->mobilePublicationCategories = PublicationCategory::forMobileNav();
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        return view('components.navbar');
    }
}
