#!/usr/bin/env python3
"""
Import full RAB GIK UGM (1035 items) via artisan tinker
"""
import openpyxl
import json
import subprocess
import sys

def parse_gik_rab():
    """Parse RAB GIK UGM from Excel"""
    path = "C:/Users/faras/.hermes/desktop-attachments/C.1 RAB GIK UGM Ulang.xlsx"
    wb = openpyxl.load_workbook(path, read_only=True, data_only=True)
    ws = wb["RAB GIK UGM"]
    
    items = []
    current_category = None
    current_sub_category = None
    item_no = 0
    
    for i, row in enumerate(ws.iter_rows(values_only=True)):
        if i < 7:  # Skip header rows
            continue
            
        no = row[0]
        kode = row[1]
        uraian = row[2]
        satuan = row[3]
        volume = row[4]
        harga = row[5]
        jumlah = row[6]
        
        if not uraian:
            continue
            
        uraian_str = str(uraian).strip()
        
        # Detect section headers
        if kode and str(kode).strip() in ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z']:
            if 'MATA PEMBAYARAN' in uraian_str.upper():
                current_category = uraian_str
            elif any(x in uraian_str.upper() for x in ['PEKERJAAN', 'STRUKTUR', 'ARSITEKTUR', 'MEP', 'UTAMA', 'PERSIAPAN', 'SMKKK']):
                current_sub_category = uraian_str
            continue
            
        # Skip if no volume/price (category header)
        if volume is None and harga is None and jumlah is None:
            continue
            
        try:
            item_no += 1
            items.append({
                'item_no': item_no,
                'code': str(kode).strip() if kode else None,
                'description': uraian_str,
                'unit': str(satuan).strip() if satuan else None,
                'qty': float(volume) if volume else 0,
                'price': float(harga) if harga else 0,
                'total': float(jumlah) if jumlah else 0,
                'category': current_category,
                'sub_category': current_sub_category,
            })
        except (ValueError, TypeError):
            pass
    
    wb.close()
    print(f"Parsed {len(items)} items")
    return items

def run_tinker(code):
    """Run PHP code via artisan tinker"""
    cmd = [
        'C:/php83/php.exe', 'artisan', 'tinker', '--execute', code
    ]
    result = subprocess.run(cmd, cwd='C:/Users/faras/rep-sangar', 
                          capture_output=True, text=True, timeout=60)
    return result.stdout.strip(), result.stderr.strip()

def main():
    print("=" * 60)
    print("IMPORT FULL RAB GIK UGM (1035 items)")
    print("=" * 60)
    
    print("\n1. Parsing Excel...")
    items = parse_gik_rab()
    
    # 2. Get project ID
    project_code = """
    $project = App\\Models\\Project::where('project_name', 'GIK UGM')->first();
    echo $project ? $project->id : 'NOT_FOUND';
    """
    project_id, err = run_tinker(project_code)
    print(f"\nProject ID: {project_id}")
    if not project_id or project_id == 'NOT_FOUND':
        print("Project not found!")
        return
    
    # 3. Create categories
    categories = {}
    for item in items:
        cat_name = item['category'] or 'Umum'
        if cat_name not in categories:
            cat_name_escaped = cat_name.replace("'", "''")
            cat_code = f"""
            $cat = App\\Models\\RabBudget::firstOrCreate(
                ['description' => '{cat_name_escaped}', 'project_id' => {project_id}, 'parent_id' => null],
                [
                    'project_id' => {project_id},
                    'code_item' => 'CAT-' . strtoupper(substr('{cat_name_escaped}', 0, 3)),
                    'description' => '{cat_name_escaped}',
                    'unit' => '',
                    'volume' => 0,
                    'unit_price' => 0,
                    'total_price' => 0,
                    'category' => '{cat_name_escaped}',
                    'status' => 'APPROVED',
                ]
            );
            echo $cat->id;
            """
            cat_id, err = run_tinker(cat_code)
            if err:
                print(f"  Category error: {err[:200]}")
            categories[cat_name] = cat_id
            print(f"  Category: {cat_name} -> ID: {cat_id}")
    
    # 4. Import items
    print(f"\n3. Importing {len(items)} RAB items...")
    batch_size = 50
    total = len(items)
    
    for i in range(0, total, batch_size):
        batch = items[i:i+batch_size]
        batch_num = i//batch_size + 1
        total_batches = (total + batch_size - 1)//batch_size
        
        print(f"  Batch {batch_num}/{total_batches} ({len(batch)} items)...")
        
        for item in batch:
            if not item['description']:
                continue
                
            cat_name = item['category'] or 'Umum'
            parent_id = categories.get(cat_name, 'null')
            
            desc = item['description'].replace("'", "''")
            code = item['code'] or f"ITEM-{item['item_no']}"
            unit = item['unit'] or 'unit'
            cat = item['category'] or ''
            
            rab_code = f"""
            App\\Models\\RabBudget::create([
                'project_id' => {project_id},
                'parent_id' => {parent_id},
                'code_item' => '{code}',
                'description' => '{item['description'].replace("'", "''")}',
                'unit' => '{unit}',
                'volume' => {item['qty']},
                'unit_price' => {item['price']},
                'total_price' => {item['total']},
                'category' => '{cat.replace("'", "''")}',
                'status' => 'DRAFT',
            ]);
            """
            out, err = run_tinker(rab_code)
            if err and 'duplicate' not in err.lower():
                print(f"  Error: {err[:100]}")
        
        print(f"  Done batch {(i//batch_size)+1}")
    
    print("\n" + "=" * 60)
    print("IMPORT COMPLETE")
    print("=" * 60)

if __name__ == "__main__":
    main()