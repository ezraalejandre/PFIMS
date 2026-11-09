<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

// Group the dashboard actions.
class DashboardController extends Controller
{
    // List the dashboard records.
    public function index(): View
    {
        return view('dashboard', ['portal' => 'admin']);
    }
}
