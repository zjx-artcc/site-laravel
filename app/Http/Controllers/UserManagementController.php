<?php

namespace App\Http\Controllers;

class UserManagementController extends Controller
{
    public function index()
    {
        return view('user-management.index');
    }
}
