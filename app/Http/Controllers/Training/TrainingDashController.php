<?php

namespace App\Http\Controllers\Training;

use App\Http\Controllers\Controller;

class TrainingDashController extends Controller
{
    public function index()
    {
        return view('admin.training-dashboard');
    }
}
