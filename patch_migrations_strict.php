<?php

$migrationsPath = __DIR__ . '/database/migrations/';
$files = glob($migrationsPath . '*.php');

$schemas = [
    'create_purchase_orders_table' => <<<'PHP'
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects');
            $table->string('po_number')->unique();
            $table->date('date');
            $table->string('supplier_name');
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->text('payment_terms')->nullable();
            $table->string('status')->default('DRAFT'); // DRAFT, PENDING_APPROVAL, APPROVED, REJECTED, COMPLETED
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamps();
        });
    }
PHP,
    'create_po_items_table' => <<<'PHP'
    public function up(): void
    {
        Schema::create('po_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders');
            $table->foreignId('rab_budget_id')->constrained('rab_budgets');
            $table->string('item_name');
            $table->decimal('qty', 15, 2);
            $table->decimal('unit_price', 15, 2);
            $table->decimal('total_price', 15, 2);
            $table->timestamps();
        });
    }
PHP,
    'create_goods_receipts_table' => <<<'PHP'
    public function up(): void
    {
        Schema::create('goods_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders');
            $table->string('receipt_number')->unique();
            $table->date('receipt_date');
            $table->string('delivery_note_number')->nullable(); // Nomor Surat Jalan
            $table->string('receiver_name'); // Nama penerima di lapangan
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }
PHP,
    'create_spks_table' => <<<'PHP'
    public function up(): void
    {
        Schema::create('spks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects');
            $table->string('spk_number')->unique();
            $table->string('subcon_name');
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->text('payment_terms')->nullable();
            $table->string('status')->default('DRAFT');
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();
        });
    }
PHP,
    'create_opnames_table' => <<<'PHP'
    public function up(): void
    {
        Schema::create('opnames', function (Blueprint $table) {
            $table->id();
            $table->foreignId('spk_id')->constrained('spks');
            $table->string('opname_number')->unique();
            $table->date('date');
            $table->decimal('progress_percentage', 5, 2);
            $table->decimal('amount', 15, 2);
            $table->string('status')->default('PENDING'); // PENDING, APPROVED
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamps();
        });
    }
PHP,
    'create_invoices_table' => <<<'PHP'
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->morphs('invoiceable'); // Bisa PO atau SPK
            $table->string('invoice_number')->unique();
            $table->date('invoice_date');
            $table->date('due_date')->nullable();
            $table->decimal('amount', 15, 2);
            $table->string('status')->default('UNPAID'); // UNPAID, PARTIAL, PAID
            $table->timestamps();
        });
    }
PHP,
];

foreach ($files as $file) {
    $content = file_get_contents($file);
    foreach ($schemas as $key => $schema) {
        if (strpos($file, $key) !== false) {
            $content = preg_replace(
                '/public function up\(\): void\s*\{.*?\}(?=\s*\/\*\*|\s*public function down)/s',
                $schema,
                $content
            );
            file_put_contents($file, $content);
            echo "Patched " . basename($file) . "\n";
        }
    }
}
echo "Done.\n";
