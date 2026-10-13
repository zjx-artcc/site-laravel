<?php

namespace App\Http\Controllers;

class FaqController extends Controller
{
    /**
     * Public, publicly-accessible FAQ page.
     */
    public function index()
    {
        return view('faq.index');
    }

    /**
     * Staff-facing FAQ management page.
     */
    public function manage()
    {
        return view('admin.faqs.index');
    }
}
