<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\Spk;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\RabBudget;
use App\Models\PoItem;

class CreateGikUgmSpkGrSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info("Creating SPKs and Goods Receipts for GIK UGM...");

        $project = Project::where('project_name', 'GIK UGM')->first();
        if (!$project) {
            $this->command->error("Project GIK UGM not found!");
            return;
        }

        $pos = PurchaseOrder::where('project_id', $project->id)
            ->where('status', 'draft')
            ->get();

        $this->command->info("Found {$pos->count()} POs to process");

        foreach ($pos as $po) {
            // Create SPK from PO
            $spkNumber = 'SPK-' . $po->po_number;
            $spkNumber = str_replace('/', '-', $spkNumber);

            $spk = Spk::firstOrCreate(
                ['spk_number' => $spkNumber],
                [
                    'project_id' => $project->id,
                    'spk_type' => 'material',
                    'subcon_name' => $po->supplier_name,
                    'subtotal' => 0,
                    'tax_amount' => 0,
                    'total_amount' => 0,
                    'include_ppn' => true,
                    'payment_terms' => $po->payment_terms ?? '30 hari',
                    'jadwal_kirim' => now()->addDays(30),
                    'status' => 'DRAFT',
                    'created_by' => 1,
                ]
            );

            $this->command->line("  Created SPK: {$spk->spk_number} for {$po->supplier_name}");

            // Create Goods Receipt from SPK
            $grNumber = 'GR-' . $po->po_number;
            $grNumber = str_replace('/', '-', $grNumber);

            $goodsReceipt = GoodsReceipt::firstOrCreate(
                ['receipt_number' => $grNumber],
                [
                    'purchase_order_id' => $po->id,
                    'receipt_number' => $grNumber,
                    'receipt_date' => now(),
                    'delivery_note_number' => 'DO-' . $po->po_number,
                    'receiver_name' => 'Bima',
                    'notes' => 'Auto-generated from PO: ' . $po->po_number,
                ]
            );

            $this->command->line("  Created GR: {$goodsReceipt->receipt_number} for PO: {$po->po_number}");

            // Add some GR items (linked to PO items if they exist)
            $poItems = PoItem::where('purchase_order_id', $po->id)->get();
            
            if ($poItems->isEmpty()) {
                // Create some PO items first if none exist
                $rabItems = RabBudget::where('project_id', $project->id)
                    ->where('status', 'DRAFT')
                    ->take(3)
                    ->get();
                
                foreach ($rabItems as $rabItem) {
                    $poItem = PoItem::create([
                        'purchase_order_id' => $po->id,
                        'rab_budget_id' => $rabItem->id,
                        'item_name' => $rabItem->description,
                        'qty' => min($rabItem->volume, rand(1, 10)),
                        'unit_price' => $rabItem->unit_price,
                        'total_price' => min($rabItem->volume, rand(1, 10)) * $rabItem->unit_price,
                    ]);
                    $poItems->push($poItem);
                }
            }
            
            foreach ($poItems as $index => $poItem) {
                $qty = $poItem->qty ?? rand(1, 10);
                GoodsReceiptItem::firstOrCreate(
                    [
                        'goods_receipt_id' => $goodsReceipt->id,
                        'po_item_id' => $poItem->id,
                    ],
                    [
                        'quantity_received' => $qty,
                    ]
                );
            }
        }

        $this->command->info('SPKs and Goods Receipts for GIK UGM created!');
    }
}