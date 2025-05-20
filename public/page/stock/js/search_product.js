$(document).ready(function () {
  const Toast = Swal.mixin({
    toast: true,
    position: "top",
    showConfirmButton: false,
    timer: 1500,
    timerProgressBar: true,
    didOpen: (toast) => {
      toast.addEventListener("mouseenter", Swal.stopTimer);
      toast.addEventListener("mouseleave", Swal.resumeTimer);
    },
  });

  $("#product_id_input").on("input", function () {
    const query = $(this).val();
    const warehouse_id = $("#warehouse_id").val();
    if (query.length < 2) {
      $("#barcode_suggestions").addClass("hidden").empty();
      $("#product_info").addClass("hidden").empty();
      return;
    }
    $.ajax({
      url: "page/stock/api/api_search_product.php",
      method: "GET",
      data: { query: query, warehouse_id: warehouse_id },
      dataType: "json",
      success: function (response) {
        const products = response;
        const suggestions = $("#barcode_suggestions")
          .removeClass("hidden")
          .empty();
        if (products.length === 0) {
          suggestions.append(
            '<div class="p-2 text-gray-500">ไม่พบสินค้า</div>'
          );
          return;
        }
        products.forEach((product) => {
          suggestions.append(`
                        <div class="p-2 hover:bg-gray-100 cursor-pointer" data-product-id="${
                          product.product_id
                        }" data-lot-id="${
            product.lot_id
          }" data-lot-product-id="${product.lot_product_id}">
                            ${product.product_id} - ${
            product.product_name
          } (ล็อต: ${product.lot_name || "N/A"}, สต็อก: ${
            product.lot_product_remain
          })
                        </div>
                    `);
        });
        suggestions.find("div").on("click", function () {
          const product_id = $(this).data("product-id");
          const lot_id = $(this).data("lot-id");
          const lot_product_id = $(this).data("lot-product-id");
          $("#product_id_input").val(product_id);
          suggestions.addClass("hidden").empty();
          fetchProductInfo(product_id, lot_id, lot_product_id);
        });
      },
      error: function () {
        Swal.fire({
          icon: "error",
          title: "ข้อผิดพลาด",
          text: "ไม่สามารถค้นหาสินค้าได้",
        });
      },
    });
  });

  // แสดงข้อมูลสินค้าใน #product_info
  function fetchProductInfo(product_id, lot_id, lot_product_id) {
    $.ajax({
      url: "page/stock/api/get_product.php",
      method: "GET",
      data: { product_id, lot_id, lot_product_id },
      dataType: "json",
      headers: {
        "X-Requested-With": "XMLHttpRequest",
      },
      success: function (product) {
        if (product && product.length > 0) {
          const item = product[0];
          $("#product_info").removeClass("hidden").html(`
                        <div class="bg-white rounded-lg p-4 shadow">
                            <p><strong>สินค้า:</strong> ${
                              item.product_name
                            }</p>
                            <p><strong>ล็อต:</strong> ${
                              item.lot_name || "N/A"
                            }</p>
                            <p><strong>จำนวนสินค้าในระบบ:</strong> ${
                              item.lot_product_remain
                            }</p>
                        </div>
                    `);
          // อัปเดต input ในฟอร์มหลัก
          $("#stock_check_actual").val(item.lot_product_remain);
        } else {
          $("#product_info").addClass("hidden").empty();
          Swal.fire({
            icon: "warning",
            title: "ข้อผิดพลาด",
            text: "ไม่พบสินค้า",
          });
        }
      },
      error: function () {
        $("#product_info").addClass("hidden").empty();
        Swal.fire({
          icon: "error",
          title: "ข้อผิดพลาด",
          text: "เกิดข้อผิดพลาดในการดึงข้อมูลสินค้า",
        });
      },
    });
  }
});
