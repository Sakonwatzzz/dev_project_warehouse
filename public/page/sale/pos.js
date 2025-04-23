let cart = [];

function searchProduct(event) {
  if (event.key === 'Enter') {
    const barcode = event.target.value.trim();
    fetch(`/public/page/sale/pos-handler.php?action=scan&code=${barcode}`)
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          addToCart(data.product);
        } else {
          Swal.fire('ไม่พบสินค้า', data.message, 'error');
        }
        event.target.value = '';
      });
  }
}

function addToCart(product) {
  const index = cart.findIndex(p => p.product_id === product.product_id);
  if (index > -1) {
    cart[index].qty++;
  } else {
    product.qty = 1;
    cart.push(product);
  }
  renderCart();
}

function renderCart() {
  const list = document.getElementById('productList');
  list.innerHTML = '';
  let total = 0;
  cart.forEach((p, i) => {
    const subtotal = p.qty * p.price;
    total += subtotal;
    list.innerHTML += `
      <tr>
        <td class="px-2 py-1">${p.product_id}</td>
        <td class="px-2 py-1">${p.name}</td>
        <td class="px-2 py-1">${p.price}</td>
        <td class="px-2 py-1">${p.qty}</td>
        <td class="px-2 py-1">${subtotal}</td>
        <td class="px-2 py-1"><button onclick="removeFromCart(${i})" class="text-red-500">ลบ</button></td>
      </tr>`;
  });
  document.getElementById('totalInput').value = total;
  calculateChange();
}

function calculateChange() {
  const cash = parseFloat(document.getElementById('cashInput').value) || 0;
  const total = parseFloat(document.getElementById('totalInput').value) || 0;
  document.getElementById('changeInput').value = (cash - total).toFixed(2);
}

function removeFromCart(index) {
  cart.splice(index, 1);
  renderCart();
}

function confirmSale() {
  Swal.fire({
    title: 'คุณแน่ใจหรือไม่?',
    text: "ยืนยันการขายสินค้าทั้งหมด",
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'ยืนยัน',
    cancelButtonText: 'ยกเลิก'
  }).then((result) => {
    if (result.isConfirmed) {
      fetch('/public/page/sale/pos-handler.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({action: 'save', cart: cart})
      })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          Swal.fire('สำเร็จ', 'บันทึกรายการเรียบร้อย', 'success');
          cart = [];
          renderCart();
        } else {
          Swal.fire('ผิดพลาด', data.message, 'error');
        }
      });
    }
  });
}
