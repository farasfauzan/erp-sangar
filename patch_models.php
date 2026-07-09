<?php

$modelsDir = __DIR__ . '/app/Models/';
$files = scandir($modelsDir);

$modelsCode = [
    'User' => <<<'PHP'
    protected $fillable = [
        'name',
        'email',
        'password',
        'role_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }
PHP,

    'Role' => <<<'PHP'
    protected $fillable = ['role_name'];

    public function users()
    {
        return $this->hasMany(User::class);
    }
PHP,

    'Project' => <<<'PHP'
    protected $fillable = ['project_name', 'location', 'start_date', 'end_date', 'status'];

    public function rabBudgets()
    {
        return $this->hasMany(RabBudget::class);
    }

    public function purchaseOrders()
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function spks()
    {
        return $this->hasMany(Spk::class);
    }
PHP,

    'RabBudget' => <<<'PHP'
    protected $fillable = ['project_id', 'code_item', 'description', 'unit', 'volume', 'unit_price', 'total_price', 'category'];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }
PHP,

    'PurchaseOrder' => <<<'PHP'
    protected $fillable = ['project_id', 'po_number', 'date', 'supplier_name', 'subtotal', 'tax_amount', 'total_amount', 'payment_terms', 'status', 'created_by'];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function items()
    {
        return $this->hasMany(PoItem::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }
PHP,

    'PoItem' => <<<'PHP'
    protected $fillable = ['purchase_order_id', 'rab_budget_id', 'item_name', 'qty', 'unit_price', 'total_price'];

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function rabBudget()
    {
        return $this->belongsTo(RabBudget::class);
    }
PHP,

    'Spk' => <<<'PHP'
    protected $fillable = ['project_id', 'spk_number', 'subcon_name', 'subtotal', 'tax_amount', 'total_amount', 'payment_terms', 'status', 'created_by'];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function progress()
    {
        return $this->hasMany(SpkProgress::class);
    }
PHP,

    'SpkProgress' => <<<'PHP'
    protected $fillable = ['spk_id', 'rab_budget_id', 'work_description', 'progress_percentage', 'amount'];

    public function spk()
    {
        return $this->belongsTo(Spk::class);
    }
PHP,

    'GoodsReceipt' => <<<'PHP'
    protected $fillable = ['project_id', 'purchase_order_id', 'receipt_number', 'receipt_date', 'received_by'];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }
PHP,

    'Opname' => <<<'PHP'
    protected $fillable = ['project_id', 'spk_id', 'opname_date', 'period', 'progress_percentage', 'checked_by'];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function spk()
    {
        return $this->belongsTo(Spk::class);
    }
PHP,

    'Invoice' => <<<'PHP'
    protected $fillable = ['purchase_order_id', 'spk_id', 'invoice_number', 'invoice_date', 'amount_due', 'tax_amount', 'status'];

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function spk()
    {
        return $this->belongsTo(Spk::class);
    }

    public function attachments()
    {
        return $this->hasMany(InvoiceAttachment::class);
    }
PHP,

    'InvoiceAttachment' => <<<'PHP'
    protected $fillable = ['invoice_id', 'doc_type', 'file_path'];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }
PHP,

    'FundRequest' => <<<'PHP'
    protected $fillable = ['project_id', 'request_number', 'amount', 'description', 'status', 'requested_by'];
PHP,

    'Tax' => <<<'PHP'
    protected $fillable = ['invoice_id', 'tax_invoice_number', 'tax_type', 'amount', 'is_credited', 'csv_exported_at'];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }
PHP,

    'Transaction' => <<<'PHP'
    protected $fillable = ['invoice_id', 'fund_request_id', 'payment_method', 'amount', 'payment_date', 'proof_of_payment'];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }
PHP,

    'GeneralLedger' => <<<'PHP'
    protected $fillable = ['transaction_date', 'account_code', 'description', 'debit', 'credit', 'project_id'];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }
PHP,

    'ApprovalLog' => <<<'PHP'
    protected $fillable = ['record_type', 'record_id', 'user_id', 'action', 'notes'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
PHP,

];

foreach ($files as $file) {
    if (str_ends_with($file, '.php')) {
        $modelName = str_replace('.php', '', $file);
        
        if (isset($modelsCode[$modelName])) {
            $content = file_get_contents($modelsDir . $file);
            
            // For User model, it has existing traits and properties. 
            // We just replace the inside of the class.
            // A simple regex to replace everything between { and } at the class level is hard.
            // Instead, we will replace the whole file for standard models, and for User we will inject carefully.
            
            if ($modelName === 'User') {
                $content = preg_replace(
                    '/protected \$fillable = \[.*?\];/s',
                    "protected \$fillable = [\n        'name',\n        'email',\n        'password',\n        'role_id',\n    ];",
                    $content
                );
                
                if (!str_contains($content, 'public function role()')) {
                    $content = preg_replace(
                        '/\}$/',
                        "\n    public function role()\n    {\n        return \$this->belongsTo(Role::class);\n    }\n}\n",
                        $content
                    );
                }
            } else {
                $content = preg_replace(
                    '/class ' . $modelName . ' extends Model\s*\{.*?\}/s',
                    "class " . $modelName . " extends Model\n{\n" . $modelsCode[$modelName] . "\n}",
                    $content
                );
            }
            
            file_put_contents($modelsDir . $file, $content);
            echo "Patched Model $file\n";
        }
    }
}

echo "Done.\n";
