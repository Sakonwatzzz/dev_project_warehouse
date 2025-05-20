$(document).ready(function () {
  const Toast = Swal.mixin({
    toast: true,
    position: "top-end",
    showConfirmButton: false,
    timer: 1500,
    timerProgressBar: true,
    customClass:{
      popup: "small-toast",
    },
    didOpen: (toast) => {
      toast.addEventListener("mouseenter", Swal.stopTimer);
      toast.addEventListener("mouseleave", Swal.resumeTimer);
    },
  });
  $("#btn-submit").prop("enabled", true);

  // ตัวแปรสำหรับตรวจสอบสถานะการประมวลผล
  let isProcessing = false;

  // ฟังก์ชันเปลี่ยนสีพื้นหลัง input
  function changeInputBackgroud(event) {
    // console.log("key pressed:", event.key, "Code : ", event.code);
    const product_id_input = document.getElementById("product_id_input");
    if (event.key === "Delete") {
      product_id_input.classList.remove(
        "bg-white",
        "bg-red-500",
        "bg-green-600"
      );
      product_id_input.classList.add("bg-white");
    }
    if (event.key === "Delete") {
      product_id_input.classList.remove("bg-white", "bg-green-600");
      product_id_input.classList.add("bg-red-500");
    }
    if (event.key === "Insert") {
      product_id_input.classList.remove("bg-white", "bg-red-500");
      product_id_input.classList.add("bg-green-600");
    }
  }
  // ฟังก์ชันรีเซ็ตสีพื้นหลัง
  function resetInputBackgroud() {
    $("#product_id_input")
      .removeClass("bg-red-500 bg-green-600")
      .addClass("bg-white");
  }

  // จัดการ keydown และ focus สำหรับ product_id_input
  $("#product_id_input")
    .on("keydown", changeInputBackgroud)
    .on("focus", resetInputBackgroud)
    .on("keypress", function (event) {
      if (event.key === "Enter") {
        event.preventDefault();
        if (!$("#btn-submit").prop("disabled")) {
          $("#form_insert_stock").submit();
        } else {
          Toast.fire({
            icon: "warning",
            title: "กรุณากรอกข้อมูลให้ครบถ้วนและถูกต้อง",
          });
        }
      }
    })
    .on("input", function () {
      // console.log("stock_insert_list input:", $(this).val());
      let input = $(this).val().trim();
      if (input.includes("*")) {
        let [stock_check_id, stock_check_actual] = input.split("*");
        let isDelete = stock_check_id.startsWith("-");
        stock_check_id = stock_check_id.replace(/^-/, "").trim();
        $(this).val(stock_check_id);
        stock_check_actual = stock_check_actual.trim() || "0";
        if (isDelete) {
          stock_check_actual = `-${stock_check_actual}`;
        }
        $("#stock_check_actual").val(stock_check_actual);
        const product_id_input = document.getElementById("product_id_input");
        product_id_input.classList.remove(
          "bg-white",
          "bg-red-500",
          "bg-green-600"
        );
        product_id_input.classList.add(
          isDelete ? "bg-red-500" : "bg-green-600"
        );
      }
    });

  $("#product_id_input").on("input", function () {
    let input = $(this).val().trim();
    if (input.includes("*")) {
      let [stock_check_id, stock_check_actual] = input.split("*");
      $(this).val(stock_check_id.trim());
      $("#stock_check_actual").val(stock_check_actual.trim() || "0");
    }
  });
  function validateForm() {
    const product_id = $("#product_id_input").val().trim();
    const warehouse_id = $("#warehouse_id").val().trim();
    const stock_check_actual = $("#stock_check_actual").val().trim();
    const stock_check_date = $("#stock_check_date ").val().trim();
    const personnel_id = $("#personnel_id").val().trim() || "1"; // ตั้งต่าเริ่มต้นไอดีเป็น 1 จากตารางก่อน เพราะ ยังไม่มีการขัดการ session

    // ตั้งค่าเริ่มต้นถ้า stock_check_actual ว่าง
    if (!stock_check_actual) {
      stock_check_actual = "0";
      $("#stock_check_actual").val(stock_check_actual);
    }

    // ตรวจสอบว่าค่าไม่ว่างและเป็นตัวเลขที่ถูกต้อง
    const isValid =
      product_id &&
      warehouse_id &&
      stock_check_actual &&
      stock_check_date &&
      personnel_id &&
      /^\d+$/.test(product_id) && // ตรวจสอบ product_id เป็นตัวเลข
      isNumeric(stock_check_actual) &&
      $("#btn-submit").prop("disabled", !isValid);
  }

  // ตรวจสอบฟอร์มเมื่อมีการเปลี่ยนแปลง input
  $(
    "#product_id_input",
    "#warehouse_id",
    "#stock_check_actual",
    "#stock-check-date",
    "#stock-check-remark"
  ).on("input change", validateForm);

  $("#form_insert_stock").on("submit", function (event) {
    event.preventDefault();
    if (isProcessing) return;
    isProcessing = true;
    $("#btn-submit")
      // .find(".spinner")
      .removeClass("hidden")
      .end()
      .find(".text")
      .addClass("hidden");
    // รวบรวมข้อมูลจากฟอร์ม
    let formData = {
      action: "insert_check_product",
      product_id: $("#product_id_input").val().trim(),
      warehouse_id: $("#warehouse_id").val().trim(),
      stock_check_actual: $("#stock_check_actual").val().trim(),
      stock_check_date:
        $("#stock-check-date").val() ||
        luxon.DateTime.now().toFormat("yyyy-MM-dd HH:mm:ss"),
      stock_check_remark: $("#stock-check-remark").val().trim(),
      personnel_id: $("#personnel_id").val() || "1",
    };
    // แปลง stock_check_date เป็น Y-m-d H:i:s
    if (formData.stock_check_date) {
      formData.stock_check_date = luxon.DateTime.fromFormat(
        formData.stock_check_date,
        "yyyy-MM-dd HH:mm"
      )
        .setZone("Asia/Bangkok")
        .toFormat("dd-MM-yyyy HH:mm");
    }
    // ตรวจสอบข้อมูลพื้นฐาน
    if (
      !formData.product_id ||
      !formData.warehouse_id ||
      !formData.stock_check_actual
    ) {
      Toast.fire({
        icon: "warning",
        title: "กรุณากรอก รหัสสินค้า, คลังสินค้า, และจำนวนที่นับได้จริง",
      });
      $("#btn-submit")
        .find(".spinner")
        .addClass("hidden")
        .end()
        .find(".text")
        .removeClass("hidden");
      isProcessing = false;
      return;
    }

    if (
      !/^\d+$/.test(formData.product_id) ||
      !/^\d+$/.test(formData.stock_check_actual)
    ) {
      Toast.fire({
        icon: "error",
        title: "รหัสสินค้าและจำนวนต้องเป็นตัวเลข",
      });
      $("#btn-submit")
        .find(".spinner")
        .addClass("hidden")
        .end()
        .find(".text")
        .removeClass("hidden");
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
      },
      dataType: "json",
      headers: {
        "X-Requested-With": "XMLHttpRequest",
      },
      success: function (productData) {
        if (
          !productData ||
          (Array.isArray(productData) && productData.length === 0)
        ) {
          Toast.fire({
            icon: "error",
            title: "ไม่พบข้อมูลสินค้าในคลังนี้",
          });
          $("#btn-submit")
            .find(".spinner")
            .addClass("hidden")
            .end()
            .find(".text")
            .removeClass("hidden");
          isProcessing = false;
          return;
        }

        // ใช้ record แรก
        let product = productData[0];
        formData.lot_id = product.lot_id || "";
        formData.lot_product_id = product.lot_product_id || "";
        formData.stock_check_system = product.lot_product_remain || "0";

        // ส่งข้อมูลไป insert_stock.php
        $.ajax({
          url: "page/stock/api/insert_stock.php",
          type: "POST",
          data: formData,
          dataType: "json",
          headers: {
            "X-Requested-With": "XMLHttpRequest",
          },
          success: function (response) {
            if (response.status === "success") {
              Toast.fire({
                icon: "success",
                title: response.message,
              });
              $("#form_insert_stock")[0].reset();
              $("#product_id_input").focus();
              $("#product_info").addClass("hidden").empty();
              loadStockCheck();
            } else {
              Toast.fire({
                icon: "error",
                title: response.message,
              });
            }
            $("#btn-submit")
              .find(".spinner")
              .addClass("hidden")
              .end()
              .find(".text")
              .removeClass("hidden");
            isProcessing = false;
          },
          error: function (xhr, status, error) {
            Toast.fire({
              icon: "error",
              title: "เกิดข้อผิดพลาดในการบันทึก: " + error,
            });
            $("#btn-submit")
              .find(".spinner")
              .addClass("hidden")
              .end()
              .find(".text")
              .removeClass("hidden");
            isProcessing = false;
          },
        });
      },
      error: function (xhr, status, error) {
        Toast.fire({
          icon: "error",
          title: "ไม่สามารถดึงข้อมูลสินค้าได้: " + error,
        });
        $("#btn-submit")
          .find(".spinner")
          .addClass("hidden")
          .end()
          .find(".text")
          .removeClass("hidden");
        isProcessing = false;
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
                             <td class="px-3 sm:px-6 py-2 border text-center">
                                                    <div class="flex flex-col sm:flex-row justify-center space-y-2 sm:space-y-0 sm:space-x-2">
                                                        <a href="?page=product_detail&tabel=product&id=<?php echo htmlspecialchars($product_list['product_id']); ?>"
                                                            class="bg-blue-500 text-white px-2 sm:px-3 py-1 rounded hover:bg-blue-600 transition flex items-center text-xs sm:text-sm">
                                                            <i class="fas fa-eye mr-1"></i>ดู
                                                        </a>
                                                        <a href="?page=product_update&id=<?php echo htmlspecialchars($product_list['product_id']); ?>"
                                                            class="bg-yellow-500 text-white px-2 sm:px-3 py-1 rounded hover:bg-yellow-600 transition flex items-center text-xs sm:text-sm">
                                                            <i class="fas fa-edit mr-1"></i>แก้ไข
                                                        </a>
                                                        <a href="?page=product_list&id=<?php echo htmlspecialchars($product_list['product_id']); ?><?php echo $incomplete_mode ? '&mode=incomplete' : ''; ?>"
                                                            onclick="return confirm('กรุณาตรวจสอบข้อมูลก่อนลบ ถ้าคุณแน่ใจแล้วให้กดลบ')"
                                                            class="bg-red-500 text-white px-2 sm:px-3 py-1 rounded hover:bg-red-600 transition flex items-center text-xs sm:text-sm">
                                                            <i class="fas fa-trash-alt mr-1"></i>ลบ
                                                        </a>
                                                    </div>
                                        </td>
                            </tr>`;
            tbody.append(row);
          });
        }
      },
      error: function (error) {
        console.log("Ajax error: ", error);
        Swal.fire({
          icon: "error",
          title: "ข้อผิดพลาด",
          text: "ไม่สามารถดึงข้อมูลจาก get_product.php: " + error,
        });
      },
    });
  }
  loadStockCheck();

  // setInterval(loadStockCheck, 30000);
});
