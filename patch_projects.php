<?php

$controllersDir = __DIR__ . '/app/Http/Controllers/Api/';

$projectControllerCode = <<<'PHP'
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
PHP;

file_put_contents($controllersDir . 'ProjectController.php', $projectControllerCode);
echo "Created ProjectController.php\n";

// Update routes/api.php
$routesFile = __DIR__ . '/routes/api.php';
$routesContent = file_get_contents($routesFile);
if (!str_contains($routesContent, 'ProjectController')) {
    $routesContent = str_replace(
        "use App\Http\Controllers\Api\SpkController;",
        "use App\Http\Controllers\Api\SpkController;\nuse App\Http\Controllers\Api\ProjectController;",
        $routesContent
    );
    $routesContent .= "\n// Master Data\nRoute::get('/projects', [ProjectController::class, 'index']);\nRoute::get('/projects/{id}', [ProjectController::class, 'show']);\n";
    file_put_contents($routesFile, $routesContent);
    echo "Updated routes/api.php\n";
}

// Ensure default project exists via Seeder if needed, but for now we can just rely on the API returning empty list or we can create a quick dummy project.
// Let's create a quick DB seed script.
$seederCode = <<<'PHP'
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Project;
use App\Models\RabBudget;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $project = Project::create([
            'project_name' => 'Proyek RSUD Mentawai',
            'location' => 'Mentawai',
            'start_date' => '2026-08-01',
        ]);

        RabBudget::create([
            'project_id' => $project->id,
            'code_item' => 'A.1.1',
            'description' => 'Semen Portland (PC) 50kg',
            'unit' => 'Zak',
            'volume' => 1000,
            'unit_price' => 75000,
            'total_price' => 75000000,
            'category' => 'Material'
        ]);

        RabBudget::create([
            'project_id' => $project->id,
            'code_item' => 'A.1.2',
            'description' => 'Besi Beton Polos',
            'unit' => 'Kg',
            'volume' => 5000,
            'unit_price' => 12500,
            'total_price' => 62500000,
            'category' => 'Material'
        ]);
    }
}
PHP;

file_put_contents(__DIR__ . '/database/seeders/DatabaseSeeder.php', $seederCode);
echo "Created DatabaseSeeder.php\n";

