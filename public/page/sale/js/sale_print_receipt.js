$(document).ready(function () {
  // Print receipt
  $("#print_receipt").on("click", function () {
    const paid = parseFloat($("#sale_paid_amount").val()) || 0;
    const change = parseFloat($("#sale_change_amount").val()) || 0;
    const sale_id = $('#confirm-sale-form input[name="sale_id"]').val(); // ดึง sale_id จากฟอร์ม
    if (!sale_id) {
      Swal.fire({
        icon: "error",
        title: "ข้อผิดพลาด",
        text: "ไม่พบรหัสบิล",
      });
      return;
    }
    $.ajax({
      url: "page/sale/sale_add.php",
      method: "POST",
      data: {
        action: "print_receipt",
        sale_id: sale_id,
        sale_paid_amount: paid,
        sale_change_amount: change,
      },
      dataType: "json",
      success: function (response) {
        if (response.status === "success") {
          const receipt = response.receipt;
          const printWindow = window.open("", "_blank");
          printWindow.document.write(`
                        <html>
                        <head>
                            <title>ใบเสร็จ #${receipt.sale_id}</title>
                            <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
                            <style>
                                @media print {
                                    body { font-size: 12pt; margin: 0; }
                                    .no-print { display: none; }
                                }
                            </style>
                        </head>
                        <body class="p-4">
                            <div class="max-w-md mx-auto bg-white p-6 rounded-lg shadow-md">
                                <h1 class="text-xl font-bold text-center">Sampeng Buriram</h1>
                                <p class="text-center text-sm">จำหน่ายสินค้าราคาปลีกและส่ง</p>
                                <p class="text-center text-sm">เลขที่บิล: #${
                                  receipt.sale_id
                                }</p>
                                <p class="text-center text-sm">วันที่: ${
                                  receipt.sale_date
                                }</p>
                                <p class="text-center text-sm">ลูกค้า: ${
                                  receipt.member_name
                                }</p>
                                <p class="text-center text-sm">พนักงาน: ${
                                  receipt.personnel_name
                                }</p>
                                <hr class="my-4">
                                <table class="w-full text-sm">
                                    <thead>
                                        <tr class="border-b">
                                            <th class="text-left py-1">สินค้า</th>
                                            <th class="text-center py-1">จำนวน</th>
                                            <th class="text-right py-1">ราคา</th>
                                            <th class="text-right py-1">รวม</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${receipt.products
                                          .map(
                                            (product) => `
                                            <tr>
                                                <td class="py-1">${
                                                  product.product_name
                                                }</td>
                                                <td class="text-center py-1">${
                                                  product.sale_product_qty
                                                }</td>
                                                <td class="text-right py-1">${parseFloat(
                                                  product.sale_product_price
                                                ).toFixed(2)}</td>
                                                <td class="text-right py-1">${(
                                                  product.sale_product_qty *
                                                  product.sale_product_price
                                                ).toFixed(2)}</td>
                                            </tr>
                                        `
                                          )
                                          .join("")}
                                    </tbody>
                                </table>
                                <hr class="my-4">
                                 <p>ยอดสุทธิ (ไม่รวม VAT): ${parseFloat(
                                   receipt.sale_net_amount
                                 ).toFixed(2)} บาท</p>
                  <p>VAT (7%): ${parseFloat(receipt.sale_vat_amount).toFixed(
                    2
                  )} บาท</p>
                  <p>ยอดรวมสุทธิ: ${parseFloat(
                    receipt.sale_total_amount
                  ).toFixed(2)} บาท</p>
                  <p>เงินที่ชำระ: ${parseFloat(
                    receipt.sale_paid_amount
                  ).toFixed(2)} บาท</p>
                  <p>เงินทอน: ${parseFloat(receipt.sale_change_amount).toFixed(
                    2
                  )} บาท</p>
                                <p class="text-center text-sm mt-4">ขอบคุณที่ใช้บริการ</p>
                                <button onclick="window.print()" class="no-print w-full py-2 mt-4 bg-blue-600 text-white rounded hover:bg-blue-700">พิมพ์</button>
                            </div>
                        </body>
                        </html>
                    `);
          printWindow.document.close();
          printWindow.focus();
          printWindow.print();
        } else {
          Swal.fire({
            icon: "error",
            title: "ข้อผิดพลาด",
            text: response.message,
          });
        }
      },
      error: function (xhr) {
        Swal.fire({
          icon: "error",
          title: "ข้อผิดพลาด",
          text: xhr.responseText || "ไม่สามารถสร้างใบเสร็จได้",
        });
      },
    });
  });
});
