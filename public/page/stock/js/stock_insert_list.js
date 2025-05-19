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
  $("#btn-submit").prop("disabled", true);

  // ตัวแปรสำหรับตรวจสอบสถานะการประมวลผล
  let isProcessing = false;

  function changeInputBackgroud(event) {
    const product_id_input = document.getElementById("product_id_input");
    if (event.keyCode === 46) {
      // 46 = delete
      product_id_input.removeClass("bg-white bg-red-500 bg-green-600");
    }
    if (event.keyCode === 45) {
      // 45 = insert
      product_id_input.classList.remove("bg-white bg-green-600").addClass('bg-red-500');
    }
    if (event.keyCode === 43) {
      // 45 = insert
      product_id_input.classList.remove("bg-white bg-red-600").addClass('bg-green-500');
    }
  }

  function resetInputBackgroud() {
    $('#product_id_input').removeClass('bg-red-500 bg-green-600').addClass('bg-white');
  }

  $('#product_id_input').on('keydown', changeInputBackgroud).on('fucus',resetInputBackgroud);

  const state = {
    activeDeleteProductId: null,
    product_input: $('input[name="product_id"]'),
  };

  $("#form_insert_stock").on("submit", function (event) {
    event.preventDefault();
    let formData = {
      action: "insert_check_product",
      product_id: $("#product_id_input").val().trim(),
      warehouse_id: $("#warehouse_id").val(),
      stock_check_date: $("#stock_check_date").val() || luxon.DataTime.now().toFormat('yyyy-MM-dd HH:mm:ss'),
      stock_check_remark: $("#stock_check_remark").val(),
      personnel_id: $('#personnel_id').val() || '1', // สมมติว่า personnel_id มาจาก session หรือกำหนดค่าเริ่มต้น สำการทดสอบ***
      stock_check_actual: $("#stock_check_actual").val(),
    };

    if (!formData.product_id || !formData.warehouse_id || !formData.stock_check_actual) {
      Toast.fire({
        icon: "warnning",
        title: "กรุณากรอกรหัสสินค้า",
      });
      $('#btn-submit').addClass('hidden').end().find('.text').removeClass('hidden');
      isProcessing = false;
      return;
    }

    // ดึงข้อมูลสินค้าจาก get_product.php
    $.ajax({
      url: "page/stock/api/get_product.php",
      type: "GET",
      data: {
        product_id: formData.product_id,
        warehouse_id: formData.warehouse_id,
        lot_id: formData.lot_id,
        lot_product_id: formData.lot_product_id,
      },
      dataType: "json",
      success: function (productData) {
        if (!productData) {
          Toast.fire({
            icon: "error",
            title: "ไม่พบข้อมูลสินค้าจากระบบ",
          });
          return;
        }
        formData.lot_id = productData.lot_id || "";
        formData.lot_product_id = productData.lot_product_id || "";
        formData.stock_check_system = productData.stock_check_system || "";

        $.ajax({
          url: "page/stock/insert_stock.php",
          type: "POST",
          data: formData,
          dataType: "json",
          success: function (response) {
            if (response.status === "success") {
              Toast.fire({
                icon: "success",
                title: response.message,
              });
              // สำหรับรีเฟรชฟอร์ม
              $("#form_insert_stock")[0].reset();
              $("#product_id_input").focus();
              // รีเฟรชตาราง
              loadStockCheck();
              $("#product_info")
                .html(
                  `บันทึกสำเร็จ: ${DOMPurify.sanitize(product.product_name)}`
                )
                .removeClass("hidden");
            } else {
              Toast.fire({
                icon: "error",
                title: response.message,
              });
              $("#btn-submit").prop("disable", false).text("บันทึก");
            }
          },
          error: function (xhr, status, error) {
            Toast.fire({
              icon: "error",
              title: "เกิดข้อผิดพลาดในการบันทึก: " + error,
            });
            $("#btn-submit").prop("disabled", false).text("บันทึก");
          },
        });
      },
      error: function () {
        Toast.fire({
          icon: "error",
          title: "ไม่สามารถดึงข้อมูลสินค้าได้",
        });
        $("#btn-submit").prop("disabled", false).text("บันทึก");
      },
    });

  });

  function loadStockCheck() {
    $.ajax({
      url: "page/stock/api/get_product.php",
      type: "GET",
      dataType: "json",
      success: function (data) {
        let tbody = $("#stock_check_table");
        tbody.empty();
        if (data === null || data === undefined || data.length === 0) {
          console.error(
            "No data returned or element #stock_check_table not found"
          );
          tbody.append(
            '<tr><td colspan="12" class="text-center py-4 text-gray-500">ไม่พบรายการตรวจสอบสต๊อกสินค้า</td></tr>'
          );
          return;
        } else {
          $.each(data, function (index, item) {
            let row = `
                            <tr>
                                <td class="px-3 sm:px-6 py-2 border text-left text-xs sm:text-sm">
                                    <input type="checkbox" class="stock-list-select rounded text-blue-500 focus:ring-blue-400"
                                        data-product-id="${item.product_id}"
                                        data-lot-id="${item.lot_id || ""}"
                                        data-lot-product-id="${
                                          item.lot_product_id || ""
                                        }"
                                        data-system-quantity="${
                                          item.stock_check_system || 0
                                        }">
                                </td>
                                <td class="px-3 sm:px-6 py-2 border text-left text-xs sm:text-sm">${
                                  item.stock_check_id || "-"
                                }</td>
                                <td class="px-3 sm:px-6 py-2 border text-left text-xs sm:text-sm">${
                                  item.stock_check_date || "-"
                                }</td>
                                <td class="px-3 sm:px-6 py-2 border text-left text-xs sm:text-sm">${
                                  item.warehouse_name || "-"
                                }</td>
                                <td class="px-3 sm:px-6 py-2 border text-left text-xs sm:text-sm">${
                                  item.product_id || "-"
                                }</td>
                                <td class="px-3 sm:px-6 py-2 border text-left text-xs sm:text-sm">${
                                  item.product_name || "-"
                                }</td>
                                <td class="px-3 sm:px-6 py-2 border text-left text-xs sm:text-sm">${
                                  item.stock_check_system || "0"
                                }</td>
                                <td class="px-3 sm:px-6 py-2 border text-left text-xs sm:text-sm">${
                                  item.stock_check_actual || "0"
                                }</td>
                                <td class="px-3 sm:px-6 py-2 border text-left text-xs sm:text-sm">${
                                  item.personnel_name || "-"
                                }</td>
                                <td class="px-3 sm:px-6 py-2 border text-left text-xs sm:text-sm">${
                                  item.check_status_name || "-"
                                }</td>
                                <td class="px-3 sm:px-6 py-2 border text-left text-xs sm:text-sm">${
                                  item.stock_check_remark || "-"
                                }</td>
                                <td class="px-3 sm:px-6 py-2 border text-left text-xs sm:text-sm text-blue-600">
                                    <a href="edit_stock_check.php?id=${
                                      item.stock_check_id
                                    }">แก้ไข</a>
                                </td>
                            </tr>`;
            tbody.append(row);
          });
        }
      },
      error: function () {
        Swal.fire({
          icon: "error",
          title: "ข้อผิดพลาด",
          text: response.message,
        });
      },
    });
  }
  loadStockCheck();

  setInterval(loadStockCheck, 30000);
});
