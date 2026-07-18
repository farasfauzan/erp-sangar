<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Project;
use App\Models\RabBudget;
use App\Models\Supplier;
use App\Models\PurchaseOrder;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportGorontaloRAB extends Seeder
{
    public function run(): void
    {
        $this->command->info("Importing Gorontalo RAB from JSON...");

        $project = Project::where('project_name', 'Sekolah Rakyat Gorontalo')->first();
        if (!$project) {
            $this->command->error("Project not found!");
            return;
        }
        $this->command->info("Project: {$project->project_name} (ID: {$project->id})");

        $jsonPath = storage_path('app/gorontalo_rab.json');
        if (!file_exists($jsonPath)) {
            $this->command->error("JSON file not found: $jsonPath");
            return;
        }

        $items = json_decode(file_get_contents($jsonPath), true);
        $this->command->info("Loaded " . count($items) . " items from JSON");

        $batchSize = 100;
        $total = count($items);
        
        foreach (array_chunk($items, $batchSize) as $batchIndex => $batch) {
            $this->command->line("  Batch " . ($batchIndex + 1) . " (" . count($batch) . " items)...");
            foreach ($batch as $item) {
                if (empty($item['description'])) continue;

                RabBudget::create([
                    'project_id' => $project->id,
                    'code_item' => $item['code'] ?? 'ITEM-' . rand(1000, 9999),
                    'description' => $item['description'],
                    'unit' => $item['unit'],
                    'volume' => $item['qty'],
                    'unit_price' => $item['price'],
                    'total_price' => $item['total'],
                    'category' => 'Material',
                    'status' => 'DRAFT',
                ]);
            }
            $this->command->line("  Done batch " . ($batchIndex + 1));
        }
        $this->command->info("Imported " . count($items) . " RAB items");
    }
}