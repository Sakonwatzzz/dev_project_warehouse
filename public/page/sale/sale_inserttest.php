<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>ระบบขายสินค้า (POS)</title>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script src="/public/page/sale/pos.js" defer></script>
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 p-4">
  <div class="max-w-4xl mx-auto bg-white p-6 rounded shadow">
    <h1 class="text-2xl font-bold mb-4">ระบบขายสินค้า (POS)</h1>

    <!-- ช่องกรอกรหัสสินค้า (Barcode) -->
    <div class="mb-4">
      <input type="text" id="barcodeInput" placeholder="สแกนหรือกรอกรหัสสินค้า..." 
             class="w-full px-3 py-2 border rounded shadow" onkeyup="searchProduct(event)">
    </div>

    <!-- แสดงรายการสินค้าในบิล -->
    <table class="w-full text-left border mb-4">
      <thead>
        <tr class="bg-gray-200">
          <th class="px-2 py-1">รหัสสินค้า</th>
          <th class="px-2 py-1">ชื่อสินค้า</th>
          <th class="px-2 py-1">ราคาต่อหน่วย</th>
          <th class="px-2 py-1">จำนวน</th>
          <th class="px-2 py-1">ราคารวม</th>
          <th class="px-2 py-1">ลบ</th>
        </tr>
      </thead>
      <tbody id="productList"></tbody>
    </table>

    <!-- แสดงข้อมูลการขาย -->
    <div class="mb-4">
      <h2 class="font-semibold text-lg mb-2">ข้อมูลการขาย</h2>
      <div class="grid grid-cols-2 gap-4">
        <input type="text" id="memberName" placeholder="ชื่อสมาชิก" readonly class="w-full px-3 py-2 border rounded">
        <input type="text" id="personnelName" placeholder="ชื่อพนักงาน" readonly class="w-full px-3 py-2 border rounded">
        <input type="text" id="saleStatus" placeholder="สถานะการขาย" readonly class="w-full px-3 py-2 border rounded">
      </div>
    </div>

    <!-- ช่องสำหรับคำนวณยอดเงิน -->
    <div class="grid grid-cols-2 gap-4 mb-4">
      <input type="number" id="totalInput" placeholder="ยอดรวม" readonly class="border px-3 py-2">
      <input type="number" id="cashInput" placeholder="รับเงิน" class="border px-3 py-2" oninput="calculateChange()">
      <input type="number" id="changeInput" placeholder="เงินทอน" readonly class="border px-3 py-2 col-span-2">
    </div>

    <!-- ปุ่มยืนยันการขาย -->
    <button onclick="confirmSale()" class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">ยืนยันรายการ</button>
  </div>

  <script>
    let cart = [];
    let saleStatus = 'รอการยืนยัน'; // ตัวอย่างการตั้งค่าของสถานะการขาย

    // ฟังก์ชันค้นหาสินค้า
    function searchProduct(event) {
      const barcode = event.target.value;

      if (barcode.length >= 3) {
        fetch(`/public/page/sale/sale_insert.php?action=scan&code=${barcode}`)
          .then(response => response.json())
          .then(data => {
            if (data.success) {
              addToCart(data.product);
            } else {
              alert(data.message);
            }
          });
      }
    }

    // ฟังก์ชันเพิ่มสินค้าลงในตะกร้า
    function addToCart(product) {
      const existingProduct = cart.find(item => item.product_id === product.product_id);
      if (existingProduct) {
        existingProduct.qty += 1;
      } else {
        cart.push({ ...product, qty: 1 });
      }
      renderCart();
    }

    // ฟังก์ชันแสดงรายการสินค้าในตะกร้า
    function renderCart() {
      const productList = document.getElementById('productList');
      productList.innerHTML = '';
      let total = 0;

      cart.forEach(item => {
        const row = document.createElement('tr');
        row.innerHTML = `
          <td class="px-2 py-1">${item.product_id}</td>
          <td class="px-2 py-1">${item.product_name}</td>
          <td class="px-2 py-1">${item.product_price4}</td>
          <td class="px-2 py-1">
            <button onclick="changeQuantity(${item.product_id}, -1)">-</button>
            ${item.qty}
            <button onclick="changeQuantity(${item.product_id}, 1)">+</button>
          </td>
          <td class="px-2 py-1">${(item.product_price4 * item.qty).toFixed(2)}</td>
          <td class="px-2 py-1">
            <button onclick="removeFromCart(${item.product_id})">ลบ</button>
          </td>
        `;
        productList.appendChild(row);
        total += item.product_price4 * item.qty;
      });

      document.getElementById('totalInput').value = total.toFixed(2);
    }

    // ฟังก์ชันเปลี่ยนจำนวนสินค้าในตะกร้า
    function changeQuantity(product_id, delta) {
      const product = cart.find(item => item.product_id === product_id);
      if (product) {
        product.qty = Math.max(1, product.qty + delta);
        renderCart();
      }
    }

    // ฟังก์ชันลบสินค้าออกจากตะกร้า
    function removeFromCart(product_id) {
      cart = cart.filter(item => item.product_id !== product_id);
      renderCart();
    }

    // ฟังก์ชันคำนวณเงินทอน
    function calculateChange() {
      const total = parseFloat(document.getElementById('totalInput').value) || 0;
      const cash = parseFloat(document.getElementById('cashInput').value) || 0;
      document.getElementById('changeInput').value = (cash - total).toFixed(2);
    }

    // ฟังก์ชันยืนยันการขาย
    function confirmSale() {
      const saleData = {
        cart: cart,
        sale_date: new Date().toISOString(),
        sale_status_id: 1, // สถานะการขาย (ตัวอย่าง)
        member_id: 1, // สมาชิก (ตัวอย่าง)
        personnel_id: 1, // พนักงาน (ตัวอย่าง)
        store_id: 1 // ร้านค้า (ตัวอย่าง)
      };

      fetch('/public/page/sale/sale_insert.php', {
        method: 'POST',
        body: JSON.stringify(saleData),
        headers: {
          'Content-Type': 'application/json',
        },
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          Swal.fire('สำเร็จ', 'การขายสำเร็จ', 'success');
          cart = [];
          renderCart();
        } else {
          Swal.fire('เกิดข้อผิดพลาด', data.message, 'error');
        }
      });
    }
  </script>
</body>
</html>
