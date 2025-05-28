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
    const query = $(this).val().trim();
    const warehouse_id = $("#warehouse_select").val();

    if (!warehouse_id) {
      $("#barcode_suggestions").addClass("hidden").empty();
      $("#product_info").addClass("hidden").empty();
      $("#warehouse_id").val("");
      Toast.fire({
        icon: "warning",
        title: "กรุณาเลือกคลังก่อนค้นหาสินค้า",
      });
      return;
    }

    if (query.length < 2) {
      $("#barcode_suggestions").addClass("hidden").empty();
      $("#product_info").addClass("hidden").empty();
      $("#warehouse_id").val("");
      return;
    }

    console.log("Searching with query:", query, "warehouse_id:", warehouse_id);

    $.ajax({
      url: "page/stock/api/api_search_product.php",
      method: "GET",
      data: { query: query, warehouse_id: warehouse_id },
      dataType: "json",
      success: function (response) {
        console.log("API response:", response);
        const products = response.slice(0, 5);
        const suggestions = $("#barcode_suggestions")
          .removeClass("hidden")
          .empty();

        suggestions.append(`
                    <div class="flex justify-end sticky top-0 bg-white p-2 border-b">
                        <button id="clear-suggestions" class="text-gray-500 hover:text-red-600">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                `);

        if (products.length === 0) {
          suggestions.append(
            '<div class="p-2 text-gray-500">ไม่พบสินค้าในคลังที่เลือก</div>'
          );
        } else {
          products.forEach((product) => {
            suggestions.append(`
                            <div class="p-2 hover:bg-gray-100 cursor-pointer" 
                                 data-product-id="${product.product_id}" 
                                 data-lot-id="${product.lot_id}" 
                                 data-lot-product-id="${product.lot_product_id}"
                                 data-warehouse-id="${product.warehouse_id}"
                                 data-warehouse-name="${
                                   product.warehouse_name
                                 }">
                                ${product.product_id} - ${product.product_name} 
                                (ล็อต: ${product.lot_name || "N/A"}, สต็อก: ${
              product.lot_product_remain
            }, คลัง: ${product.warehouse_name})
                            </div>
                        `);
          });
        }

        $("#clear-suggestions").on("click", function () {
          $("#barcode_suggestions").addClass("hidden").empty();
          $("#product_id_input").val("").focus();
          $("#product_info").addClass("hidden").empty();
          $("#warehouse_id").val("");
        });

        suggestions.find("div[data-product-id]").on("click", function () {
          const product_id = $(this).data("product-id");
          const lot_id = $(this).data("lot-id");
          const lot_product_id = $(this).data("lot-product-id");
          const selected_warehouse_id = $(this).data("warehouse-id");
          const warehouse_name = $(this).data("warehouse-name");
          if (warehouse_id && selected_warehouse_id != warehouse_id) {
            Swal.fire({
              icon: "warning",
              title: "ข้อผิดพลาด",
              text: "สินค้านี้อยู่ในคลังอื่น",
            });
            return;
          }
          $("#product_id_input").val(product_id);
          $("#form_insert_stock").data("lot_id", lot_id);
          $("#form_insert_stock").data("lot_product_id", lot_product_id);
          $("#warehouse_id").val(selected_warehouse_id);
          suggestions.addClass("hidden").empty();
          fetchProductInfo(product_id, lot_id, lot_product_id, warehouse_id);
        });
      },
      error: function (xhr, status, error) {
        console.error("API error:", xhr.responseText);
        Swal.fire({
          icon: "error",
          title: "ข้อผิดพลาด",
          text: "ไม่สามารถค้นหาสินค้าได้",
        });
      },
    });
  });

  function fetchProductInfo(product_id, lot_id, lot_product_id, warehouse_id) {
    console.log("Fetching product info:", {
      product_id,
      lot_id,
      lot_product_id,
      warehouse_id,
    });
    $.ajax({
      url: "page/stock/api/get_product.php",
      method: "GET",
      data: { product_id, lot_id, lot_product_id, warehouse_id },
      dataType: "json",
      headers: {
        "X-Requested-With": "XMLHttpRequest",
      },
      success: function (product) {
        console.log("Product info response:", product);
        if (product && product.length > 0) {
          const item = product[0];
          $("#product_info").removeClass("hidden").html(`
                        <div class="bg-white rounded-lg p-4 shadow">
                            <p><strong>สินค้า:</strong> ${item.product_name}</p>
                            <p><strong>คลัง:</strong> ${item.warehouse_name}</p>
                            <p><strong>จำนวนสินค้าในระบบ:</strong> ${item.lot_product_remain}</p>
                        </div>
                    `);
          $("#stock_check_system").val(item.lot_product_remain);
          $("#warehouse_id").val(item.warehouse_id);
        } else {
          $("#product_info").addClass("hidden").empty();
          $("#warehouse_id").val("");
          Swal.fire({
            icon: "warning",
            title: "ข้อผิดพลาด",
            text: "ไม่พบสินค้าในคลังที่เลือก",
          });
        }
      },
      error: function (xhr, status, error) {
        console.error("Fetch product error:", xhr.responseText);
        $("#product_info").addClass("hidden").empty();
        $("#warehouse_id").val("");
        Swal.fire({
          icon: "error",
          title: "ข้อผิดพลาด",
          text: "เกิดข้อผิดพลาดในการดึงข้อมูลสินค้า",
        });
      },
    });
  }
});
