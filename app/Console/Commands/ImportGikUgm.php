<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Project;
use App\Models\Supplier;
use App\Models\PurchaseOrder;
use App\Models\RabCategory;
use App\Models\RabItem;

class ImportGikUgm extends Command
{
    protected $signature = 'import:gik-ugm';
    protected $description = 'Import GIK UGM data (RAB, Suppliers, POs)';

    public function handle()
    {
        $this->info('Starting GIK UGM import...');
        
        // 1. Get/Create Project
        $project = Project::firstOrCreate(
            ['project_name' => 'GIK UGM'],
            [
                'location' => 'Yogyakarta',
                'start_date' => '2025-01-01',
                'status' => 'planning',
            ]
        );
        $this->info("Project: {$project->project_name} (ID: {$project->id})");

        // 2. Load parsed data
        $dataPath = storage_path('app/gik_parsed.json');
        if (!file_exists($dataPath)) {
            $this->error("Parsed data not found at $dataPath");
            return 1;
        }
        $data = json_decode(file_get_contents($dataPath), true);
        $rabItems = $data['rab'] ?? [];
        $poData = $data['po'] ?? [];

        // 3. Import RAB Items
        $this->info("Importing " . count($rabItems) . " RAB items...");
        $categories = [];
        $imported = 0;
        
        foreach ($rabItems as $item) {
            if (empty($item['description'])) continue;
            
            // Get or create category
            $catName = $item['category'] ?? 'Umum';
            if (!isset($categories[$catName])) {
                $cat = RabCategory::firstOrCreate(
                    ['name' => $catName, 'project_id' => $project->id],
                    ['code' => 'CAT-' . strtoupper(substr($catName, 0, 3))]
                );
                $categories[$catName] = $cat;
            }
            
            RabItem::create([
                'rab_category_id' => $categories[$catName]->id,
                'code' => $item['code'] ?? 'ITEM-' . rand(1000, 9999),
                'description' => $item['description'],
                'unit' => $item['unit'] ?? 'unit',
                'quantity' => $item['qty'],
                'price' => $item['price'],
                'total' => $item['total'],
            ]);
            $imported++;
        }
        $this->info("Imported $imported RAB items");

        // 4. Import Suppliers & POs
        $this->info("Importing " . count($poData) . " POs...");
        
        foreach ($poData as $po) {
            $vendor = $po['vendor'] ?? '';
            $poNumber = $po['po_number'] ?? '';
            $poDate = $po['po_date'] ?? '2026-01-01';
            $location = $po['location'] ?? '';
            $contact = $po['contact'] ?? '';
            
            if (!$vendor || !$poNumber) continue;
            
            // Create/Get Supplier
            $supplier = Supplier::firstOrCreate(
                ['name' => $vendor],
                [
                    'code' => 'SUP-' . strtoupper(substr($vendor, 0, 10)),
                    'address' => $location,
                    'phone' => $contact,
                    'email' => '',
                    'contact_person' => $contact,
                ]
            );
            
            // Create PO
            $existingPo = PurchaseOrder::where('po_number', $poNumber)->first();
            if (!$existingPo) {
                PurchaseOrder::create([
                    'project_id' => $project->id,
                    'supplier_id' => $supplier->id,
                    'po_number' => $poNumber,
                    'date' => $poDate,
                    'status' => 'draft',
                    'po_type' => 'supplier',
                    'payment_terms' => '30 hari',
                    'contact_person' => $contact,
                    'supplier_address' => $location,
                ]);
                $this->info("Created PO: $poNumber for $vendor");
            } else {
                $this->info("PO exists: $poNumber");
            }
        }
        
        $this->info('GIK UGM Import Complete!');
        return 0;
    }
}