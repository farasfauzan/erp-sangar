import openpyxl
import json
import re

# Parse the RAB file
path = "C:/Users/faras/rep-sangar/storage/app/excel/C.1 Rev. RAB SEKOLAH GORONTALO.xlsx"
wb = openpyxl.load_workbook(path, read_only=False, data_only=False)
sheet = wb['RAB']

items = []
item_no = 0

# Columns: A=No, B=Uraian (Description), C=Empty, D=Satuan (Unit), E=Volume, F=Harga (Price), G=Jumlah (Formula =E*F)
for i in range(10, sheet.max_row + 1):
    no = sheet['A' + str(i)].value
    uraian = sheet['B' + str(i)].value
    satuan = sheet['D' + str(i)].value
    volume = sheet['E' + str(i)].value
    harga = sheet['F' + str(i)].value
    jumlah = sheet['G' + str(i)].value

    if not uraian:
        continue
    
    uraian_str = str(uraian).strip()
    
    # Skip section headers (codes like "A.", "B.", etc. in column B)
    if no and isinstance(no, str) and no.strip():
        no_str = no.strip()
        if re.match(r'^[A-Z]\.$', no_str):
            continue

    # Parse numeric values
    vol = float(volume) if volume and isinstance(volume, (int, float)) else 0
    hrg = float(harga) if harga and isinstance(harga, (int, float)) else 0
    
    # Evaluate jumlah: if it's a formula =E*F, calculate it
    jml = 0
    if jumlah and isinstance(jumlah, str) and jumlah.startswith('='):
        jml = vol * hrg
    elif jumlah and isinstance(jumlah, (int, float)):
        jml = float(jumlah)
    
    if vol == 0 and hrg == 0 and jml == 0:
        continue

    items.append({
        'item_no': item_no + 1,
        'code': str(no).strip() if no else None,
        'description': uraian_str,
        'unit': str(satuan).strip() if satuan else 'unit',
        'qty': vol,
        'price': hrg,
        'total': jml,
        'category': 'Material',
    })
    item_no += 1

wb.close()

# Save to JSON
output_path = "C:/Users/faras/rep-sangar/storage/app/gorontalo_rab.json"
with open(output_path, 'w', encoding='utf-8') as f:
    json.dump(items, f, ensure_ascii=False, indent=2)

print(f"Parsed {len(items)} items")
print(f"Saved to {output_path}")

# Show first 10 items
for item in items[:10]:
    print(f"  {item['item_no']}: {item['description'][:60]}... | Qty: {item['qty']} | Price: {item['price']} | Total: {item['total']}")