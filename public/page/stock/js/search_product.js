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

  let isProcessing = false;
  $("#barcode_input").on("input", function () {
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
          $("#barcode_input").val(product_id);
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

  function fetchProductInfo(product_id, lot_id, lot_product_id) {
    $.ajax({
      url: "page/stock/api/api_get_product.php",
      method: "GET",
      data: { product_id, lot_id, lot_product_id },
      dataType: "json",
      success: function (product) {
        if (product) {
          $("#product_info").removeClass("hidden").html(`
                        <div class="bg-white rounded-lg p-4 shadow">
                            <p><strong>สินค้า:</strong> ${
                              product.product_name
                            }</p>
                            <p hidden><strong>ล็อต:</strong> ${
                              product.lot_name || "N/A"
                            }</p>
                            <p><strong>จำนวนสินค้าในระบบ:</strong> ${
                              product.lot_product_remain
                            }</p>
                            <div class="mt-2">
                                <label class="block text-sm font-medium text-gray-700">จำนวนสินค้าที่นับได้จริง</label>
                                <input type="number" id="stock_check_actual" class="w-32 border rounded px-2 py-1" min="0" value="${
                                  product.lot_product_remain
                                }">
                            </div>
                            <div class="mt-2">
                                <label class="block text-sm font-medium text-gray-700">หมายเหตุ</label>
                                <textarea id="stock_check_remark" class="w-full border rounded px-2 py-1"></textarea>
                            </div>
                            <button id="btn-save-stock-check" class="mt-2 bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600" 
                                    data-product-id="${
                                      product.product_id
                                    }" data-lot-id="${
            product.lot_id
          }" data-lot-product-id="${product.lot_product_id}" 
                                    data-system-quantity="${
                                      product.lot_product_remain
                                    }" data-warehouse-id="${
            product.warehouse_id
          }">
                                บันทึก
                            </button>
                        </div>
                    `);

          $("#btn-save-stock-check").click(function () {
            if (isProcessing) return;
            isProcessing = true;
            $(this).prop("disabled", true);

            const stock_check_actual = parseFloat(
              $("#stock_check_actual").val()
            );
            if (isNaN(stock_check_actual) || stock_check_actual < 0) {
              Swal.fire({
                icon: "warning",
                title: "แจ้งเตือน",
                text: "จำนวนจริงต้องเป็นตัวเลขและไม่ติดลบ",
              });
              isProcessing = false;
              $(this).prop("disabled", false);
              return;
            }

            const data = {
              action: "insert_check_product",
              lot_id: $(this).data("lot-id"),
              lot_product_id: $(this).data("lot-product-id"),
              product_id: $(this).data("product-id"),
              warehouse_id: $(this).data("warehouse-id"),
              stock_check_system: $(this).data("system-quantity"),
              stock_check_actual: stock_check_actual,
              stock_check_date: luxon.DateTime.local().toFormat(
                "yyyy-MM-dd HH:mm:ss"
              ),
              stock_check_remark: $("#stock_check_remark").val(),
              personnel_id: 1,
            };

            $.ajax({
              url: "page/stock/api/api_insert_stock.php",
              method: "POST",
              data: data,
              dataType: "json",
              success: function (response) {
                if (response.status === "success") {
                  Toast.fire({
                    icon: "success",
                    title: "บันทึกสำเร็จ",
                  });
                  $("#product_info").addClass("hidden").empty();
                  $("#barcode_input").val("").focus();
                  updateStockTable();
                } else {
                  Swal.fire({
                    icon: "error",
                    title: "ข้อผิดพลาด",
                    text: response.message,
                  });
                }
              },
              error: function () {
                Swal.fire({
                  icon: "error",
                  title: "ข้อผิดพลาด",
                  text: "เกิดข้อผิดพลาดในการบันทึก",
                });
              },
              complete: function () {
                isProcessing = false;
                $("#btn-save-stock-check").prop("disabled", false);
              },
            });
          });
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
  const stock_check_id = $('input[name="stock_check_id"]').val();
  function updateStockTable() {
    $.ajax({
      url: "page/stock/api/api_get_product.php",
      method: "GET",
      data: { stock_check_id },
      dataType: "json",
      success: function (response) {
        console.log("Update stock check table response:", stock_check_id); // Debug
        if (response.status === "success") {
          $("#stock_check_table").html(response.html);
          $(".main-body").removeAttr("aria-hidden");
        } else {
          Swal.fire({
            icon: "warning",
            title: "ข้อผิดพลาด",
            text: response.message,
            didOpen: () => {
              $(".main-body").removeAttr("aria-hidden");
            },
          });
        }
      },
      error: function (xhr) {
        console.error("Update stock check table error:", xhr.responseText); // Debug
        Swal.fire({
          icon: "error",
          title: "ข้อผิดพลาด",
          text: xhr.responseText || "ไม่สามารถดึงข้อมูลตาราง stock_check ได้",
          didOpen: () => {
            $(".main-body").removeAttr("aria-hidden");
          },
        });
      },
    });
  }
});
