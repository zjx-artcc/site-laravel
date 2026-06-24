<?php

namespace App\Http\Controllers;

use App\Models\PublicationCategory;

class PublicationsController extends Controller
{
    public function index()
    {
        $categories = PublicationCategory::with(['publications' => function ($query) {
            $query->orderBy('name');
        }])
            ->orderBy('display_order')
            ->orderBy('title')
            ->get();

        return view('publications.index', compact('categories'));
    }
}
