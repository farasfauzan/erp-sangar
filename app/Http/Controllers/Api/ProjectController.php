<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    public function index()
    {
        return response()->json(Project::all());
    }

    public function show($id)
    {
        return response()->json(Project::with('rabBudgets')->findOrFail($id));
    }
}