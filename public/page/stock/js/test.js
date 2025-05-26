$(document).ready(function () {
  const Toast = Swal.mixin({
    toast: true,
    position: "top-end",
    showConfirmButton: false,
    timer: 1500,
    timerProgressBar: true,
    customClass: {
      popup: "small-toast",
    },
    didOpen: (toast) => {
      toast.addEventListener("mouseenter", Swal.stopTimer);
      toast.addEventListener("mouseleave", Swal.resumeTimer);
    },
  });
  $("#btn-submit").prop("disabled", true);

  let isProcessing = false;
  let isInsertMode = true;

  const requiredIds = [
    "product_id_input",
    "warehouse_id",
    "stock_check_actual",
    "stock_date",
    "personnel_id",
    "stock-check-remark",
    "check_status_id",
  ];
  requiredIds.forEach((id) => {
    if (!$(`#${id}`).length) {
      console.error(`Element #${id} not found in DOM`);
    }
  });

  function getValue(selector, defaultValue = "") {
    const element = $(selector);
    if (!element.length) {
      console.error(`Element ${selector} not found in DOM`);
      return defaultValue;
    }
    const value = element.val();
    return value !== undefined && value !== null ? value.trim() : defaultValue;
  }

  function changeInputBackground(event) {
    const product_id_input = document.getElementById("product_id_input");
    if (event.key === "Insert") {
      isInsertMode = true;
      product_id_input.classList.remove(
        "bg-white",
        "bg-red-500",
        "bg-white-600"
      );
      product_id_input.classList.add("bg-white-600");
    }
    console.log(
      "changeInputBackground: key =",
      event.key,
      "isInsertMode =",
      isInsertMode
    );
  }

  function resetInputBackground() {
    const product_id_input = document.getElementById("product_id_input");
    product_id_input.classList.remove("bg-red-500", "bg-white-600");
    product_id_input.classList.add(
      isInsertMode ? "bg-white-600" : "bg-red-500"
    );
  }

  function processInput(input) {
    const addPattern = /^\d+\*\d+$/;
    const deletePattern = /^-\d+\*\d+$/;
    input = input.trim();

    let isDelete = false;
    let product_id, stock_check_actual;

    if (deletePattern.test(input)) {
      isDelete = true;
      [product_id, stock_check_actual] = input.replace(/^-/, "").split("*");
    } else if (addPattern.test(input)) {
      [product_id, stock_check_actual] = input.split("*");
    } else {
      return false;
    }

    product_id = product_id.trim();
    stock_check_actual = stock_check_actual.trim() || "0";
    if (isDelete || !isInsertMode) {
      stock_check_actual = `-${stock_check_actual}`;
    }

    $("#product_id_input").val(product_id);
    $("#stock_check_actual").val(stock_check_actual);
    const product_id_input = document.getElementById("product_id_input");
    product_id_input.classList.remove("bg-white", "bg-red-500", "bg-white-600");
    product_id_input.classList.add(
      isDelete || !isInsertMode ? "bg-red-500" : "bg-white-600"
    );

    validateForm();
    return true;
  }

  $("#product_id_input")
    .on("keydown", changeInputBackground)
    .on("focus", resetInputBackground)
    .on("keypress", function (event) {
      if (event.key === "Enter") {
        event.preventDefault();
        const input = $(this).val().trim();
        if (processInput(input)) {
          if (!$("#btn-submit").prop("disabled")) {
            $("#form_insert_stock").submit();
          } else {
            Toast.fire({
              icon: "warning",
              title: "กรุณากรอกข้อมูลให้ครบถ้วนและถูกต้อง",
            });
          }
        } else {
          Toast.fire({
            icon: "warning",
            title: "รูปแบบไม่ถูกต้อง (เช่น 12345*2 หรือ -12345*3)",
          });
        }
      }
    })
    .on("input", function () {
      console.log("stock_insert_list input:", $(this).val());
    });

  function validateForm() {
    const product_id = getValue("#product_id_input");
    const warehouse_id = getValue("#warehouse_id");
    let stock_check_actual = getValue("#stock_check_actual", "0");
    const stock_date = getValue("#stock_date");
    const personnel_id = getValue("#personnel_id", "1");
    const check_status_id = getValue("#check_status_id", "1");

    console.log("Validating:", {
      product_id,
      warehouse_id,
      stock_check_actual,
      stock_date,
      personnel_id,
      check_status_id,
    });

    if (!stock_check_actual) {
      stock_check_actual = "0";
      $("#stock_check_actual").val(stock_check_actual);
    }

    const isValid =
      product_id &&
      warehouse_id &&
      stock_check_actual &&
      stock_date &&
      personnel_id &&
      check_status_id &&
      /^\d+$/.test(product_id) &&
      /^-?\d+$/.test(stock_check_actual);
    $("#btn-submit").prop("disabled", !isValid);
    return isValid;
  }

  if (document.readyState === "complete") {
    validateForm();
  } else {
    $(window).on("load", validateForm);
  }

  $(
    "#product_id_input, #warehouse_id, #stock_check_actual, #stock_date, #stock-check-remark, #check_status_id"
  ).on("input change", validateForm);

  $("#form_insert_stock").on("submit", function (event) {
    event.preventDefault();
    if (isProcessing) return;
    isProcessing = true;
    $("#btn-submit")
      .find(".spinner")
      .removeClass("hidden")
      .end()
      .find(".text")
      .addClass("hidden");

    let formData = {
      action: "insert_check_product",
      product_id: getValue("#product_id_input"),
      warehouse_id: getValue("#warehouse_id"),
      stock_check_actual: getValue("#stock_check_actual", "0"),
      stock_date: getValue(
        "#stock_date",
        luxon.DateTime.now()
          .setZone("Asia/Bangkok")
          .toFormat("yyyy-MM-dd HH:mm:ss")
      ),
      stock_check_remark: getValue("#stock-check-remark", ""),
      personnel_id: getValue("#personnel_id", "1"),
      lot_id: $("#form_insert_stock").data("lot_id") || null,
      lot_product_id: $("#form_insert_stock").data("lot_product_id") || null,
      check_status_id: getValue("#check_status_id", "1"),
    };

    console.log("Submitting formData:", formData);

    if (formData.stock_date) {
      try {
        const date = luxon.DateTime.fromFormat(
          formData.stock_date,
          "yyyy-MM-dd HH:mm:ss",
          { zone: "Asia/Bangkok" }
        );
        if (!date.isValid) {
          throw new Error("Invalid date format: " + formData.stock_date);
        }
        formData.stock_date = date.toFormat("yyyy-MM-dd HH:mm:ss");
      } catch (e) {
        console.error("Date parsing error:", e.message);
        Toast.fire({
          icon: "error",
          title: "รูปแบบวันที่ไม่ถูกต้อง",
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
    } else {
      formData.stock_date = luxon.DateTime.now()
        .setZone("Asia/Bangkok")
        .toFormat("yyyy-MM-dd HH:mm:ss");
    }

    if (
      !formData.product_id ||
      !formData.warehouse_id ||
      !formData.stock_check_actual
    ) {
      Toast.fire({
        icon: "warning",
        title: "กรุณากรอกข้อมูลให้ครบถ้วน (รหัสสินค้า, คลัง, และจำนวน)",
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
      !/^-?\d+$/.test(formData.stock_check_actual)
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

    console.log("Sending AJAX to get_product.php:", {
      product_id: formData.product_id,
      warehouse_id: formData.warehouse_id,
      lot_id: formData.lot_id,
      lot_product_id: formData.lot_product_id,
    });

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
      headers: {
        "X-Requested-With": "XMLHttpRequest",
      },
      cache: false,
      success: function (productData) {
        console.log("get_product.php response:", productData);
        if (
          !productData ||
          (Array.isArray(productData) && productData.length === 0)
        ) {
          Toast.fire({
            icon: "error",
            title: `ไม่พบข้อมูลสินค้า ${formData.product_id} ในคลัง ${formData.warehouse_id}`,
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

        let product = productData[0];
        formData.lot_id = product.lot_id || null;
        formData.lot_product_id = product.lot_product_id || null;
        formData.stock_check_system = product.lot_product_remain || "0";

        console.log("Sending AJAX to insert_stock.php:", formData);

        $.ajax({
          url: "page/stock/api/insert_stock.php",
          type: "POST",
          data: formData,
          dataType: "json",
          headers: {
            "X-Requested-With": "XMLHttpRequest",
          },
          cache: false,
          success: function (response) {
            console.log("insert_stock.php response:", response);
            if (response.status === "success") {
              Toast.fire({
                icon: "success",
                title: response.message,
              });
              $("#form_insert_stock")[0].reset();
              $("#stock_date").val(
                luxon.DateTime.now()
                  .setZone("Asia/Bangkok")
                  .toFormat("yyyy-MM-dd HH:mm:ss")
              );
              $("#product_id_input").focus();
              $("#product_info").addClass("hidden").empty();
              $("#warehouse_name").val("");
              $("#warehouse_id").val("");
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
          error: toastrError,
        });
      },
      error: toastrError,
    });
  });

  function toastrError(xhr, status, error) {
    console.error("AJAX error:", xhr.responseText);
    Toast.fire({
      icon: "error",
      title: "เกิดข้อผิดพลาด: " + error,
    });
    $("#btn-submit")
      .find(".spinner")
      .addClass("hidden")
      .end()
      .find(".text")
      .removeClass("hidden");
    isProcessing = false;
  }

  function loadStockCheck() {
    console.log("Loading stock check table...");
    $.ajax({
      url: "page/stock/api/stock_list.php",
      type: "GET",
      dataType: "json",
      headers: {
        "X-Requested-With": "XMLHttpRequest",
      },
      cache: false,
      success: function (data) {
        console.log("stock_list.php response:", data);
        let tbody = $("#stock_check_table");
        tbody.empty();
        if (!data || data.length === 0) {
          console.warn("No data returned from stock_list.php");
          tbody.append(
            '<tr><td colspan="10" class="text-center py-4 text-gray-500">ไม่พบรายการตรวจสอบสต๊อกสินค้า</td></tr>'
          );
          $("#checkbox-header").addClass("hidden");
          $("#btn-confirm-check").addClass("hidden");
          return;
        }

        let hasPendingStatus = false;
        $.each(data, function (index, item) {
          let showCheckbox = parseInt(item.check_status_id) === 1;
          if (showCheckbox) {
            hasPendingStatus = true;
          }
          let row = `
                        <tr>
                            ${
                              showCheckbox
                                ? `
                            <td class="px-3 sm:px-6 py-2 border text-left text-xs sm:text-sm">
                                <input type="checkbox" class="stock-list-select rounded text-blue-500 focus:ring-blue-400"
                                    data-stock-check-id="${item.stock_check_id}"
                                    data-product-id="${item.product_id}"
                                    data-lot-id="${item.lot_id || ""}"
                                    data-lot-product-id="${
                                      item.lot_product_id || ""
                                    }"
                                    data-system-quantity="${
                                      item.stock_check_system || 0
                                    }"
                                    data-check-status-id="${
                                      item.check_status_id || 1
                                    }">
                            </td>`
                                : '<td class="px-3 sm:px-6 py-2 border text-left text-xs sm:text-sm"></td>'
                            }
                            <td class="px-3 sm:px-6 py-2 border text-left text-xs sm:text-sm" hidden>${
                              item.stock_check_id || "-"
                            }</td>
                            <td class="px-3 sm:px-6 py-2 border text-left text-xs sm:text-sm">${
                              item.stock_date || "-"
                            }</td>
                            <td class="px-3 sm:px-6 py-2 border text-left text-xs sm:text-sm" hidden>${
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
                            <td class="px-3 sm:px-6 py-2 border text-left text-xs sm:text-sm"></td>
                        </tr>`;
          tbody.append(row);
        });

        if (hasPendingStatus) {
          $("#checkbox-header").removeClass("hidden");
          $("#btn-confirm-check").removeClass("hidden");
        } else {
          $("#checkbox-header").addClass("hidden");
          $("#btn-confirm-check").addClass("hidden");
        }

        updateConfirmButtonState();
      },
      error: function (xhr, status, error) {
        console.error("stock_list.php error:", xhr.responseText);
        Swal.fire({
          icon: "error",
          title: "ข้อผิดพลาด",
          text: "ไม่สามารถดึงข้อมูลจาก stock_list.php: " + error,
        });
        $("#checkbox-header").addClass("hidden");
        $("#btn-confirm-check").addClass("hidden");
      },
    });
  }

  function updateConfirmButtonState() {
    const selectedCheckboxes = $(".stock-list-select:checked");
    const canConfirm =
      selectedCheckboxes.length > 0 &&
      selectedCheckboxes
        .toArray()
        .every(
          (checkbox) => parseInt($(checkbox).data("check-status-id")) === 1
        );
    $("#btn-confirm-check").prop("disabled", !canConfirm);
  }

  $("#btn-add-stock-check").on("click", function () {
    if (isProcessing) return;
    isProcessing = true;
    $(this)
      .find(".spinner")
      .removeClass("hidden")
      .end()
      .find(".text")
      .addClass("hidden");

    // Check if there are pending stock checks
    $.ajax({
      url: "page/stock/api/stock_list.php",
      type: "GET",
      data: { status: 1 },
      dataType: "json",
      headers: {
        "X-Requested-With": "XMLHttpRequest",
      },
      cache: false,
      success: function (data) {
        console.log("stock_list.php response for pending checks:", data);
        // Filter data to ensure only check_status_id = 1
        const pendingItems = data.filter(
          (item) => parseInt(item.check_status_id) === 1
        );
        if (pendingItems.length > 0) {
          Swal.fire({
            title: "พบรายการที่ยังไม่ยืนยัน",
            text: `มี ${pendingItems.length} รายการที่อยู่ในสถานะ "กำลังตรวจสอบ" กรุณายืนยันรายการเหล่านี้ก่อนเริ่มรายการใหม่ หรือเลือกข้ามเพื่อเริ่มใหม่ทันที`,
            icon: "warning",
            showCancelButton: true,
            showDenyButton: true,
            confirmButtonText: "ยืนยันรายการ",
            denyButtonText: "ข้ามและเริ่มใหม่",
            cancelButtonText: "ยกเลิก",
          }).then((result) => {
            if (result.isConfirmed) {
              // Select all pending items and trigger confirm
              $(".stock-list-select").each(function () {
                if (parseInt($(this).data("check-status-id")) === 1) {
                  $(this).prop("checked", true);
                }
              });
              updateConfirmButtonState();
              $("#btn-confirm-check").click();
            } else if (result.isDenied) {
              startNewStockCheck();
            }
            isProcessing = false;
          });
        } else {
          startNewStockCheck();
          isProcessing = false;
        }
        $(this)
          .find(".spinner")
          .addClass("hidden")
          .end()
          .find(".text")
          .removeClass("hidden");
      },
      error: toastrError,
    });
  });

  function startNewStockCheck() {
    // Reset form
    $("#form_insert_stock")[0].reset();
    $("#stock_date").val(
      luxon.DateTime.now()
        .setZone("Asia/Bangkok")
        .toFormat("yyyy-MM-dd HH:mm:ss")
    );
    $("#product_id_input").focus();
    $("#product_info").addClass("hidden").empty();
    $("#warehouse_name").val("");
    $("#warehouse_id").val("");
    $("#check_status_id").val("1"); // Set to "กำลังตรวจสอบ"

    Toast.fire({
      icon: "success",
      title: "เริ่มรายการตรวจสอบสต๊อกใหม่เรียบร้อย",
    });
    loadStockCheck();
  }

  $(document).on("change", ".stock-list-select, #select-all-list", function () {
    if (this.id === "select-all-list") {
      $(".stock-list-select").prop("checked", this.checked);
    }
    updateConfirmButtonState();
  });

  $("#btn-confirm-check").on("click", function () {
    if (isProcessing) return;
    isProcessing = true;
    $("#btn-confirm-check")
      .find(".spinner")
      .removeClass("hidden")
      .end()
      .find(".text")
      .addClass("hidden");

    const selectedIds = $(".stock-list-select:checked")
      .map(function () {
        return $(this).data("stock-check-id");
      })
      .get();

    if (selectedIds.length === 0) {
      const hasPendingItems = $(".stock-list-select").length > 0;
      Toast.fire({
        icon: "warning",
        title: hasPendingItems
          ? "กรุณาเลือกอย่างน้อยหนึ่งรายการที่ต้องการยืนยัน"
          : "ไม่มีรายการที่อยู่ในสถานะ 'กำลังตรวจสอบ' ให้ยืนยัน",
      });
      $("#btn-confirm-check")
        .find(".spinner")
        .addClass("hidden")
        .end()
        .find(".text")
        .removeClass("hidden");
      isProcessing = false;
      return;
    }

    Swal.fire({
      title: "ยืนยันการเปลี่ยนสถานะ?",
      text: `คุณต้องการเปลี่ยนสถานะ ${selectedIds.length} รายการเป็น "ถูกตรวจสอบแล้ว" หรือไม่?`,
      icon: "warning",
      showCancelButton: true,
      confirmButtonText: "ยืนยัน",
      cancelButtonText: "ยกเลิก",
    }).then((result) => {
      if (result.isConfirmed) {
        $.ajax({
          url: "page/stock/api/update_status.php",
          type: "POST",
          data: {
            action: "confirm_check",
            stock_check_ids: selectedIds,
          },
          dataType: "json",
          headers: {
            "X-Requested-With": "XMLHttpRequest",
          },
          cache: false,
          success: function (response) {
            console.log("update_status.php response:", response);
            if (response.status === "success") {
              Toast.fire({
                icon: "success",
                title: response.message,
              });
              // Ensure table is refreshed after update
              loadStockCheck();
              $("#select-all-list").prop("checked", false);
            } else {
              Toast.fire({
                icon: "error",
                title: response.message,
              });
            }
            $("#btn-confirm-check")
              .find(".spinner")
              .addClass("hidden")
              .end()
              .find(".text")
              .removeClass("hidden");
            isProcessing = false;
          },
          error: toastrError,
        });
      } else {
        $("#btn-confirm-check")
          .find(".spinner")
          .addClass("hidden")
          .end()
          .find(".text")
          .removeClass("hidden");
        isProcessing = false;
      }
    });
  });

  loadStockCheck();
  // Remove setInterval to avoid unexpected refreshes
  // setInterval(loadStockCheck, 30000);
});
