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

    public function store(Request $request)
    {
        $validated = $request->validate([
            'project_name' => 'required|string|max:255',
            'location' => 'required|string|max:255',
            'start_date' => 'required|date',
        ]);

        $project = Project::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Proyek baru berhasil dibuat.',
            'data' => $project
        ], 201);
    }
}