#!/usr/bin/env python3
"""Import Suppliers & POs to ERP using artisan tinker"""
import subprocess
import json
from datetime import datetime

def run_tinker(code: str):
    """Run PHP code via artisan tinker"""
    # Escape quotes properly for Windows
    escaped = code.replace('"', '\\"').replace('\n', ' ')
    cmd = f'cd /c/Users/faras/rep-sangar && /c/php83/php.exe artisan tinker --execute="{escaped}"'
    result = subprocess.run(cmd, shell=True, capture_output=True, text=True, timeout=60)
    return result.stdout.strip(), result.stderr.strip()

# Load parsed data
with open('/c/Users/faras/rep-sangar/storage/app/gik_parsed.json') as f:
    data = json.load(f)

po_data = data['po']

print(f"Found {len(po_data)} POs to import")

# Get project ID
project_id_code = """
$project = App\Models\Project::where('project_name', 'GIK UGM')->first();
echo $project->id;
"""
project_id, err = run_tinker(project_id_code)
print(f"Project ID: {project_id}")

# Import each PO
for idx, po in enumerate(po_data):
    vendor = po['vendor'].replace("'", "\\'")
    po_number = po['po_number'].replace("'", "\\'")
    location = (po.get('location') or '').replace("'", "\\'")
    contact = (po.get('contact') or '').replace("'", "\\'")
    po_date = str(po.get('po_date') or '2026-01-01')[:10]
    
    # Create/Get supplier
    supplier_code = f"""
    $supplier = App\Models\Supplier::firstOrCreate(
        ['name' => '{vendor}'],
        [
            'code' => 'SUP-' . strtoupper(substr('{vendor}', 0, 10)),
            'address' => '{location}',
            'phone' => '{contact}',
            'email' => '',
            'contact_person' => '{contact}',
        ]
    );
    echo $supplier->id;
    """
    supplier_id, err = run_tinker(supplier_code)
    
    if err:
        print(f"  Supplier error: {err[:200]}")
        continue
    
    # Create PO
    po_code = f"""
    $po = App\Models\PurchaseOrder::firstOrCreate(
        ['po_number' => '{po_number}'],
        [
            'project_id' => {project_id},
            'supplier_id' => {supplier_id},
            'date' => '{po_date}',
            'status' => 'draft',
            'po_type' => 'supplier',
            'payment_terms' => '30 hari',
            'contact_person' => '{contact}',
            'supplier_address' => '{location}',
        ]
    );
    echo $po->id;
    """
    po_id, err = run_tinker(po_code)
    
    if err:
        print(f"  PO Error ({po_number}): {err[:200]}")
    else:
        print(f"  ✓ Created PO: {po_number} for {vendor} (ID: {po_id})")

print("\nSupplier & PO import complete!")