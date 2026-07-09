<?php

$migrationsDir = __DIR__ . '/database/migrations/';
$files = scandir($migrationsDir);

$schemas = [
    'create_users_table' => <<<'PHP'
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->unsignedBigInteger('role_id')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }
PHP,
    'create_roles_table' => <<<'PHP'
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('role_name');
            $table->timestamps();
        });
    }
PHP,
    'create_projects_table' => <<<'PHP'
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('project_name');
            $table->string('location')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('status')->default('ACTIVE');
            $table->timestamps();
        });
    }
PHP,
    'create_rab_budgets_table' => <<<'PHP'
    public function up(): void
    {
        Schema::create('rab_budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->onDelete('cascade');
            $table->string('code_item')->nullable();
            $table->text('description');
            $table->string('unit');
            $table->decimal('volume', 12, 2);
            $table->decimal('unit_price', 20, 2);
            $table->decimal('total_price', 20, 2);
            $table->string('category')->nullable();
            $table->timestamps();
        });
    }
PHP,
    'create_purchase_orders_table' => <<<'PHP'
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->onDelete('cascade');
            $table->string('po_number')->unique();
            $table->date('date');
            $table->string('supplier_name');
            $table->decimal('subtotal', 20, 2)->default(0);
            $table->decimal('tax_amount', 20, 2)->default(0);
            $table->decimal('total_amount', 20, 2)->default(0);
            $table->string('payment_terms')->nullable();
            $table->string('status')->default('DRAFT');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }
PHP,
    'create_po_items_table' => <<<'PHP'
    public function up(): void
    {
        Schema::create('po_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->onDelete('cascade');
            $table->foreignId('rab_budget_id')->nullable()->constrained('rab_budgets')->nullOnDelete();
            $table->string('item_name');
            $table->decimal('qty', 12, 2);
            $table->decimal('unit_price', 20, 2);
            $table->decimal('total_price', 20, 2);
            $table->timestamps();
        });
    }
PHP,
    'create_spks_table' => <<<'PHP'
    public function up(): void
    {
        Schema::create('spks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->onDelete('cascade');
            $table->string('spk_number')->unique();
            $table->string('subcon_name');
            $table->decimal('subtotal', 20, 2)->default(0);
            $table->decimal('tax_amount', 20, 2)->default(0);
            $table->decimal('total_amount', 20, 2)->default(0);
            $table->string('payment_terms')->nullable();
            $table->string('status')->default('DRAFT');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }
PHP,
    'create_spk_progress_table' => <<<'PHP'
    public function up(): void
    {
        Schema::create('spk_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('spk_id')->constrained('spks')->onDelete('cascade');
            $table->foreignId('rab_budget_id')->nullable()->constrained('rab_budgets')->nullOnDelete();
            $table->text('work_description');
            $table->decimal('progress_percentage', 5, 2);
            $table->decimal('amount', 20, 2);
            $table->timestamps();
        });
    }
PHP,
    'create_goods_receipts_table' => <<<'PHP'
    public function up(): void
    {
        Schema::create('goods_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->onDelete('cascade');
            $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders')->nullOnDelete();
            $table->string('receipt_number');
            $table->date('receipt_date');
            $table->string('received_by')->nullable();
            $table->timestamps();
        });
    }
PHP,
    'create_opnames_table' => <<<'PHP'
    public function up(): void
    {
        Schema::create('opnames', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->onDelete('cascade');
            $table->foreignId('spk_id')->constrained('spks')->onDelete('cascade');
            $table->date('opname_date');
            $table->string('period')->nullable();
            $table->decimal('progress_percentage', 5, 2);
            $table->string('checked_by')->nullable();
            $table->timestamps();
        });
    }
PHP,
    'create_invoices_table' => <<<'PHP'
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders')->nullOnDelete();
            $table->foreignId('spk_id')->nullable()->constrained('spks')->nullOnDelete();
            $table->string('invoice_number');
            $table->date('invoice_date');
            $table->decimal('amount_due', 20, 2);
            $table->decimal('tax_amount', 20, 2)->default(0);
            $table->string('status')->default('DRAFT');
            $table->timestamps();
        });
    }
PHP,
    'create_invoice_attachments_table' => <<<'PHP'
    public function up(): void
    {
        Schema::create('invoice_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->onDelete('cascade');
            $table->string('doc_type');
            $table->string('file_path');
            $table->timestamps();
        });
    }
PHP,
    'create_fund_requests_table' => <<<'PHP'
    public function up(): void
    {
        Schema::create('fund_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->onDelete('cascade');
            $table->string('request_number');
            $table->decimal('amount', 20, 2);
            $table->text('description')->nullable();
            $table->string('status')->default('PENDING');
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }
PHP,
    'create_taxes_table' => <<<'PHP'
    public function up(): void
    {
        Schema::create('taxes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->onDelete('cascade');
            $table->string('tax_invoice_number')->nullable();
            $table->string('tax_type')->nullable();
            $table->decimal('amount', 20, 2);
            $table->boolean('is_credited')->default(false);
            $table->timestamp('csv_exported_at')->nullable();
            $table->timestamps();
        });
    }
PHP,
    'create_transactions_table' => <<<'PHP'
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->foreignId('fund_request_id')->nullable()->constrained('fund_requests')->nullOnDelete();
            $table->string('payment_method');
            $table->decimal('amount', 20, 2);
            $table->date('payment_date');
            $table->string('proof_of_payment')->nullable();
            $table->timestamps();
        });
    }
PHP,
    'create_general_ledgers_table' => <<<'PHP'
    public function up(): void
    {
        Schema::create('general_ledgers', function (Blueprint $table) {
            $table->id();
            $table->date('transaction_date');
            $table->string('account_code');
            $table->text('description');
            $table->decimal('debit', 20, 2)->default(0);
            $table->decimal('credit', 20, 2)->default(0);
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->timestamps();
        });
    }
PHP,
    'create_approval_logs_table' => <<<'PHP'
    public function up(): void
    {
        Schema::create('approval_logs', function (Blueprint $table) {
            $table->id();
            $table->string('record_type');
            $table->unsignedBigInteger('record_id');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('action');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }
PHP,
];

foreach ($files as $file) {
    if (str_ends_with($file, '.php')) {
        $content = file_get_contents($migrationsDir . $file);
        
        foreach ($schemas as $key => $schemaCode) {
            if (str_contains($file, $key)) {
                // Find up() method and replace it
                $pattern = '/\s*public function up\(\): void\s*\{.*?\}(?=\s*public function down\(\): void)/s';
                $content = preg_replace($pattern, "\n" . $schemaCode . "\n", $content);
                file_put_contents($migrationsDir . $file, $content);
                echo "Patched $file\n";
                break;
            }
        }
    }
}

echo "Done.\n";
