$(document).ready(function () {
  const Toast = Swal.mixin({
    toast: true,
    position: "top-end",
    showConfirmButton: false,
    timer: 1500,
    timerProgressBar: true,
    customClass: { popup: "small-toast" },
    didOpen: (toast) => {
      toast.addEventListener("mouseenter", Swal.stopTimer);
      toast.addEventListener("mouseleave", Swal.resumeTimer);
    },
  });
  $("#btn-submit").prop("disabled", true);

  let isProcessing = false;
  let currentStockId = null;
  const urlParams = new URLSearchParams(window.location.search);
  const stockIdFromUrl = urlParams.get("stock_id");

  function getValue(selector, defaultValue = "") {
    const value = $(selector).val();
    return value !== undefined && value !== null ? value.trim() : defaultValue;
  }

  function setStockDate() {
    let date;
    try {
      date = luxon.DateTime.now()
        .setZone("Asia/Bangkok")
        .toFormat("yyyy-MM-dd HH:mm:ss");
    } catch (e) {
      console.error("Luxon error:", e);
      date = new Date().toISOString().slice(0, 19).replace("T", " ");
    }
    $("#stock_date").val(date);
    console.log("stock_date set to:", date);
    return date;
  }

  function processInput(input) {
    const addPattern = /^\d+\*\d+$/;
    const deletePattern = /^-\d+\*\d+$/;
    input = input.trim();

    let product_id, stock_check_actual;
    if (addPattern.test(input)) {
      [product_id, stock_check_actual] = input.split("*");
    } else if (deletePattern.test(input)) {
      [product_id, stock_check_actual] = input.replace(/^-/, "").split("*");
      stock_check_actual = `-${stock_check_actual}`;
    } else {
      product_id = input;
      stock_check_actual = getValue("#stock_check_actual", "0");
    }

    product_id = product_id.trim();
    stock_check_actual = stock_check_actual.trim() || "0";

    $("#product_id_input").val(product_id);
    $("#stock_check_actual").val(stock_check_actual);
    validateForm();
    return true;
  }

  $("#product_id_input").on("keypress", function (event) {
    if (event.key === "Enter") {
      event.preventDefault();
      const input = $(this).val().trim();
      if (input) {
        processInput(input);
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
          title: "กรุณาป้อนรหัสสินค้า",
        });
      }
    }
  });

  function validateForm() {
    const product_id = getValue("#product_id_input");
    const stock_check_actual = getValue("#stock_check_actual", "0");
    const stock_date = getValue("#stock_date");

    const isValid =
      product_id &&
      stock_check_actual &&
      stock_date &&
      currentStockId &&
      /^\d+$/.test(product_id) &&
      /^-?\d+$/.test(stock_check_actual);

    $("#btn-submit").prop("disabled", !isValid);

    if (!isValid) {
      console.log("validateForm failed:", {
        product_id,
        stock_check_actual,
        stock_date,
        currentStockId,
        product_id_valid: /^\d+$/.test(product_id),
        stock_check_actual_valid: /^-?\d+$/.test(stock_check_actual),
      });
    }

    return isValid;
  }

  function initializePage() {
    setStockDate();
    if (stockIdFromUrl) {
      $.ajax({
        url: "page/stock/api/get_stock_session.php",
        type: "GET",
        data: { stock_id: stockIdFromUrl },
        dataType: "json",
        headers: { "X-Requested-With": "XMLHttpRequest" },
        cache: false,
        success: function (response) {
          if (response.status === "success" && response.stock_id) {
            currentStockId = response.stock_id;
            $("#current_stock_id").val(currentStockId);
            loadStockCheck();
            loadStockSessions();
            Toast.fire({
              icon: "success",
              title: `โหลดเซสชันสต๊อก ${currentStockId} เรียบร้อย`,
            });
          } else {
            Toast.fire({
              icon: "warning",
              title: "เซสชันสต๊อกไม่ถูกต้องหรือถูกยืนยันแล้ว",
              text: "เริ่มเซสชันใหม่...",
            });
            createNewStockSession();
          }
        },
        error: function (xhr, status, error) {
          console.error("get_stock_session.php error:", xhr.responseText);
          Toast.fire({
            icon: "error",
            title: "ไม่สามารถโหลดเซสชัน: " + error,
          });
          createNewStockSession();
        },
      });
    } else {
      createNewStockSession();
    }
    validateForm();
  }

  function createNewStockSession() {
    $.ajax({
      url: "page/stock/api/insert_new_stock.php",
      type: "POST",
      data: {
        action: "insert_new_stock",
        stock_date: setStockDate(),
        personnel_id: getValue("#personnel_id", "1"),
        check_status_id: "1",
      },
      dataType: "json",
      headers: { "X-Requested-With": "XMLHttpRequest" },
      cache: false,
      success: function (response) {
        if (response.status === "success" && response.stock_id) {
          currentStockId = response.stock_id;
          $("#current_stock_id").val(currentStockId);
          startNewStockCheck();
          loadStockSessions();
          Toast.fire({
            icon: "success",
            title: `เริ่มเซสชันตรวจสอบสต๊อกใหม่ (${currentStockId}) เรียบร้อย`,
          });
          window.location.href = `?page=stock_insert&stock_id=${currentStockId}`;
        } else {
          console.error("createNewStockSession failed:", response.message);
          Toast.fire({
            icon: "error",
            title: response.message || "ไม่สามารถสร้างเซสชันใหม่ได้",
          });
        }
      },
      error: function (xhr, status, error) {
        console.error("insert_new_stock.php error:", xhr.responseText);
        Toast.fire({
          icon: "error",
          title: "เกิดข้อผิดพลาดในการเริ่มเซสชันใหม่: " + error,
        });
      },
    });
  }

  function startNewStockCheck() {
    $("#form_insert_stock")[0].reset();
    setStockDate();
    $("#product_id_input").focus();
    $("#product_info").addClass("hidden").empty();
    $("#current_stock_id").val(currentStockId || "");
    $("#warehouse_id").val("");
    $("#lot_id").val("");
    $("#lot_product_id").val("");
    loadStockCheck();
    validateForm();
    console.log("startNewStockCheck: stock_date =", $("#stock_date").val());
  }

  function loadStockCheck() {
    console.log("Loading stock check table for stock_id:", currentStockId);
    let url = "page/stock/api/stock_list.php";
    let data = currentStockId ? { stock_id: currentStockId } : { status: 1 };
    $.ajax({
      url: url,
      type: "GET",
      data: data,
      dataType: "json",
      headers: { "X-Requested-With": "XMLHttpRequest" },
      cache: false,
      success: function (data) {
        console.log("stock_list.php response:", data);
        let tbody = $("#stock_check_table");
        tbody.empty();
        if (!data || data.length === 0) {
          tbody.append(
            '<tr><td colspan="9" class="text-center py-4 text-gray-500">ไม่พบรายการตรวจสอบสต๊อกสินค้า</td></tr>'
          );
          $("#btn-confirm-check").prop("disabled", true);
          return;
        }

        let hasPendingStatus = false;
        $.each(data, function (index, item) {
          if (parseInt(item.check_status_id) === 1) hasPendingStatus = true;
          let row = `
                        <tr data-stock-check-id="${item.stock_check_id}">
                            <td class="px-3 sm:px-6 py-2 border text-left text-xs sm:text-sm">${
                              item.stock_date || "-"
                            }</td>
                            <td class="px-3 sm:px-6 py-2 border text-left text-xs sm:text-sm">${
                              item.product_id || "-"
                            }</td>
                            <td class="px-3 sm:px-6 py-2 border text-left text-xs sm:text-sm">${
                              item.product_name || "-"
                            }</td>
                            <td class="px-3 sm:px-6 py-2 border text-right text-xs sm:text-sm">${
                              item.stock_check_system || "0"
                            }</td>
                            <td class="px-3 sm:px-6 py-2 border text-right text-xs sm:text-sm stock-check-actual">${
                              item.stock_check_actual || "0"
                            }</td>
                            <td class="px-3 sm:px-6 py-2 border text-left text-xs sm:text-sm">${
                              item.personnel_name || "-"
                            }</td>
                            <td class="px-3 sm:px-6 py-2 border text-left text-xs sm:text-sm">${
                              item.check_status_name || "-"
                            }</td>
                            <td class="px-3 sm:px-6 py-2 border text-left text-xs sm:text-sm stock-check-remark">${
                              item.stock_check_remark || "-"
                            }</td>
                            <td class="px-3 sm:px-6 py-2 text-center text-xs sm:text-sm flex gap-4 actions">
                                ${
                                  parseInt(item.check_status_id) === 1
                                    ? `
                                <a class="btn-edit-stock bg-yellow-500 text-white px-2 sm:px-3 py-1 rounded hover:bg-yellow-600 transition flex items-center text-xs sm:text-sm cursor-pointer">
                                    <i class="fas fa-edit mr-1"></i>แก้ไข
                                </a>
                                <a class="btn-delete-stock bg-red-500 text-white px-2 sm:px-3 py-1 rounded hover:bg-red-600 transition flex items-center text-xs sm:text-sm cursor-pointer" data-stock-check-id="${item.stock_check_id}">
                                    <i class="fas fa-trash-alt mr-1"></i>ลบ
                                </a>`
                                    : ""
                                }
                            </td>
                        </tr>`;
          tbody.append(row);
        });

        $("#btn-confirm-check").prop("disabled", !hasPendingStatus);
      },
      error: function (xhr, status, error) {
        console.error("stock_list.php error:", xhr.responseText);
        Toast.fire({
          icon: "error",
          title: "ไม่สามารถดึงข้อมูลจาก stock_list.php: " + error,
        });
        $("#btn-confirm-check").prop("disabled", true);
      },
    });
  }

  // Event สำหรับปุ่มแก้ไข
  $(document).on("click", ".btn-edit-stock", function () {
    const row = $(this).closest("tr");
    const stockCheckId = row.data("stock-check-id");
    const actualCell = row.find(".stock-check-actual");
    const remarkCell = row.find(".stock-check-remark");
    const actionsCell = row.find(".actions");

    // เก็บข้อมูลเดิม
    const originalActual = actualCell.text().trim();
    const originalRemark =
      remarkCell.text().trim() === "-" ? "" : remarkCell.text().trim();

    // เปลี่ยนเป็น input
    actualCell.html(`
            <input type="number" class="edit-actual w-full border rounded-sm px-2 py-1" value="${originalActual}" min="0">
        `);
    remarkCell.html(`
            <textarea class="edit-remark w-full border rounded-sm px-2 py-1" rows="2">${originalRemark}</textarea>
        `);

    // เปลี่ยนปุ่มเป็น บันทึก/ยกเลิก
    actionsCell.html(`
            <a class="btn-save-stock bg-green-500 text-white px-2 sm:px-3 py-1 rounded hover:bg-green-600 transition flex items-center text-xs sm:text-sm cursor-pointer">
                <i class="fas fa-save mr-1"></i>บันทึก
            </a>
            <a class="btn-cancel-stock bg-yellow-500 text-white px-2 sm:px-3 py-1 rounded hover:bg-yellow-600 transition flex items-center text-xs sm:text-sm cursor-pointer">
                <i class="fas fa-times mr-1"></i>ยกเลิก
            </a>
        `);

    // เก็บข้อมูลเดิมใน data attributes
    row.data("original-actual", originalActual);
    row.data("original-remark", originalRemark);
    row.data("stock-check-id", stockCheckId);
  });

  // Event สำหรับปุ่มบันทึก
  $(document).on("click", ".btn-save-stock", function () {
    const row = $(this).closest("tr");
    const stockCheckId = row.data("stock-check-id");
    const newActual = row.find(".edit-actual").val().trim();
    const newRemark = row.find(".edit-remark").val().trim();

    if (!/^-?\d+$/.test(newActual)) {
      Toast.fire({
        icon: "warning",
        title: "จำนวนที่นับได้จริงต้องเป็นตัวเลข",
      });
      return;
    }

    $.ajax({
      url: "page/stock/api/update_stock.php",
      type: "POST",
      data: {
        action: "update_stock_check",
        stock_check_id: stockCheckId,
        stock_check_actual: newActual,
        stock_check_remark: newRemark,
      },
      dataType: "json",
      headers: { "X-Requested-With": "XMLHttpRequest" },
      success: function (response) {
        if (response.status === "success") {
          Toast.fire({ icon: "success", title: response.message });
          row.find(".stock-check-actual").text(newActual);
          row.find(".stock-check-remark").text(newRemark || "-");
          row.find(".actions").html(`
                        <a class="btn-edit-stock bg-yellow-500 text-white px-2 sm:px-3 py-1 rounded hover:bg-yellow-600 transition flex items-center text-xs sm:text-sm cursor-pointer">
                            <i class="fas fa-edit mr-1"></i>แก้ไข
                        </a>
                        <a class="btn-delete-stock bg-red-500 text-white px-2 sm:px-3 py-1 rounded hover:bg-red-600 transition flex items-center text-xs sm:text-sm cursor-pointer" data-stock-check-id="${stockCheckId}">
                            <i class="fas fa-trash-alt mr-1"></i>ลบ
                        </a>
                    `);
        } else {
          Toast.fire({ icon: "error", title: response.message });
        }
      },
      error: function (xhr, status, error) {
        console.error("update_stock.php error:", xhr.responseText);
        Toast.fire({
          icon: "error",
          title: "เกิดข้อผิดพลาดในการบันทึก: " + error,
        });
      },
    });
  });

  // Event สำหรับปุ่มยกเลิก
  $(document).on("click", ".btn-cancel-stock", function () {
    const row = $(this).closest("tr");
    const stockCheckId = row.data("stock-check-id");
    const originalActual = row.data("original-actual");
    const originalRemark = row.data("original-remark");

    row.find(".stock-check-actual").text(originalActual);
    row.find(".stock-check-remark").text(originalRemark || "-");
    row.find(".actions").html(`
            <a class="btn-edit-stock bg-yellow-500 text-white px-2 sm:px-3 py-1 rounded hover:bg-yellow-600 transition flex items-center text-xs sm:text-sm cursor-pointer">
                <i class="fas fa-edit mr-1"></i>แก้ไข
            </a>
            <a class="btn-delete-stock bg-red-500 text-white px-2 sm:px-3 py-1 rounded hover:bg-red-600 transition flex items-center text-xs sm:text-sm cursor-pointer" data-stock-check-id="${stockCheckId}">
                <i class="fas fa-trash-alt mr-1"></i>ลบ
            </a>
        `);
  });

  $("#btn-add-stock-check").on("click", function () {
    if (isProcessing) return;
    isProcessing = true;
    $(this)
      .find(".spinner")
      .removeClass("hidden")
      .end()
      .find(".text")
      .addClass("hidden");

    $.ajax({
      url: "page/stock/api/stock_list.php",
      type: "GET",
      data: { status: 1 },
      dataType: "json",
      headers: { "X-Requested-With": "XMLHttpRequest" },
      cache: false,
      success: function (data) {
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
              $("#btn-confirm-check").click();
            } else if (result.isDenied) {
              createNewStockSession();
            }
            isProcessing = false;
            $("#btn-add-stock-check")
              .find(".spinner")
              .addClass("hidden")
              .end()
              .find(".text")
              .removeClass("hidden");
          });
        } else {
          createNewStockSession();
          isProcessing = false;
          $("#btn-add-stock-check")
            .find(".spinner")
            .addClass("hidden")
            .end()
            .find(".text")
            .removeClass("hidden");
        }
      },
      error: function (xhr, status, error) {
        console.error("stock_list.php error:", xhr.responseText);
        Toast.fire({
          icon: "error",
          title: "ไม่สามารถตรวจสอบรายการค้างได้: " + error,
        });
        isProcessing = false;
        $("#btn-add-stock-check")
          .find(".spinner")
          .addClass("hidden")
          .end()
          .find(".text")
          .removeClass("hidden");
      },
    });
  });

  function loadStockSessions() {
    $.ajax({
      url: "page/stock/api/stock_list.php",
      type: "GET",
      data: { status: 1 },
      dataType: "json",
      success: function (data) {
        let select = $("#stock_id_select");
        select.empty().append('<option value="">เลือกเซสชัน</option>');
        let sessions = [...new Set(data.map((item) => item.stock_id))];
        sessions.forEach((id) => {
          select.append(`<option value="${id}">${id}</option>`);
        });
        if (stockIdFromUrl && sessions.includes(parseInt(stockIdFromUrl))) {
          select.val(stockIdFromUrl);
        }
      },
    });
  }

  $("#stock_id_select").on("change", function () {
    currentStockId = $(this).val();
    $("#current_stock_id").val(currentStockId);
    loadStockCheck();
  });

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

    if (!validateForm()) {
      let errorMessage = "กรุณากรอกข้อมูลให้ครบถ้วนและถูกต้อง";
      if (!getValue("#product_id_input")) errorMessage = "กรุณาป้อนรหัสสินค้า";
      else if (!getValue("#stock_check_actual"))
        errorMessage = "กรุณาป้อนจำนวนที่นับได้จริง";
      else if (!getValue("#stock_date"))
        errorMessage = "วันที่สต๊อกไม่ถูกตั้งค่า";
      else if (!currentStockId) errorMessage = "ไม่มีเซสชันสต๊อกที่ใช้งาน";
      else if (!/^\d+$/.test(getValue("#product_id_input")))
        errorMessage = "รหัสสินค้าต้องเป็นตัวเลข";
      else if (!/^-?\d+$/.test(getValue("#stock_check_actual")))
        errorMessage = "จำนวนต้องเป็นตัวเลข";

      Toast.fire({ icon: "warning", title: errorMessage });
      $("#btn-submit")
        .find(".spinner")
        .addClass("hidden")
        .end()
        .find(".text")
        .removeClass("hidden");
      isProcessing = false;
      return;
    }

    let formData = {
      action: "insert_check_product",
      stock_id: currentStockId,
      product_id: getValue("#product_id_input"),
      stock_check_actual: getValue("#stock_check_actual", "0"),
      stock_date: getValue("#stock_date"),
      stock_check_remark: getValue("#stock-check-remark", ""),
      personnel_id: getValue("#personnel_id", "1"),
    };

    console.log("Submitting formData:", formData);
    console.log(
      "Sending product_id to get_product_location.php:",
      formData.product_id
    );

    $.ajax({
      url: "page/stock/api/get_product_location.php",
      type: "GET",
      data: { product_id: formData.product_id },
      dataType: "json",
      headers: { "X-Requested-With": "XMLHttpRequest" },
      cache: false,
      success: function (productData) {
        console.log("get_product_location.php response:", productData);
        if (
          !productData ||
          (Array.isArray(productData) && productData.length === 0)
        ) {
          Toast.fire({
            icon: "error",
            title: `ไม่พบข้อมูลสินค้า ${formData.product_id} ในคลังใด`,
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
        formData.warehouse_id = product.warehouse_id || null;
        formData.lot_id = product.lot_id || null;
        formData.lot_product_id = product.lot_product_id || null;
        formData.stock_check_system = product.lot_product_remain || "0";

        $("#warehouse_id").val(formData.warehouse_id);
        $("#lot_id").val(formData.lot_id);
        $("#lot_product_id").val(formData.lot_product_id);

        console.log("Sending AJAX to insert_stock.php:", formData);

        $.ajax({
          url: "page/stock/api/insert_stock.php",
          type: "POST",
          data: formData,
          dataType: "json",
          headers: { "X-Requested-With": "XMLHttpRequest" },
          cache: false,
          success: function (response) {
            console.log("insert_stock.php response:", response);
            if (response.status === "success") {
              Toast.fire({ icon: "success", title: response.message });
              $("#form_insert_stock")[0].reset();
              setStockDate();
              $("#product_id_input").focus();
              $("#product_info").addClass("hidden").empty();
              $("#warehouse_id").val("");
              $("#lot_id").val("");
              $("#lot_product_id").val("");
              loadStockCheck();
            } else {
              Toast.fire({ icon: "error", title: response.message });
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
            console.error("insert_stock.php error:", xhr.responseText);
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
        console.error("get_product_location.php error:", xhr.responseText);
        Toast.fire({
          icon: "error",
          title: "ไม่สามารถดึงข้อมูลสินค้า: " + error,
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

  $("#btn-confirm-check").on("click", function () {
    if (isProcessing) return;
    isProcessing = true;
    $(this)
      .find(".spinner")
      .removeClass("hidden")
      .end()
      .find(".text")
      .addClass("hidden");

    if (!currentStockId) {
      Toast.fire({ icon: "warning", title: "ไม่มีเซสชันสต๊อกที่ใช้งาน" });
      isProcessing = false;
      $(this)
        .find(".spinner")
        .addClass("hidden")
        .end()
        .find(".text")
        .removeClass("hidden");
      return;
    }

    $.ajax({
      url: "page/stock/api/stock_list.php",
      type: "GET",
      data: { stock_id: currentStockId },
      dataType: "json",
      headers: { "X-Requested-With": "XMLHttpRequest" },
      success: function (data) {
        const pendingItems = data.filter(
          (item) => parseInt(item.check_status_id) === 1
        );
        if (pendingItems.length === 0) {
          Toast.fire({
            icon: "warning",
            title: "ไม่มีรายการที่รอการยืนยันในเซสชันนี้",
          });
          isProcessing = false;
          $("#btn-confirm-check")
            .find(".spinner")
            .addClass("hidden")
            .end()
            .find(".text")
            .removeClass("hidden");
          return;
        }

        Swal.fire({
          title: "ยืนยันเซสชันสต๊อก?",
          text: `คุณต้องการยืนยัน ${pendingItems.length} รายการในเซสชัน ${currentStockId} หรือไม่?`,
          icon: "warning",
          showCancelButton: true,
          confirmButtonText: "ยืนยัน",
          cancelButtonText: "ยกเลิก",
        }).then((result) => {
          if (result.isConfirmed) {
            $.ajax({
              url: "page/stock/api/update_status.php",
              type: "POST",
              data: { action: "confirm_check", stock_ids: [currentStockId] },
              dataType: "json",
              headers: { "X-Requested-With": "XMLHttpRequest" },
              success: function (response) {
                if (response.status === "success") {
                  Toast.fire({ icon: "success", title: response.message });
                  loadStockCheck();
                  loadStockSessions();
                  createNewStockSession();
                } else {
                  Toast.fire({ icon: "error", title: response.message });
                }
                isProcessing = false;
                $("#btn-confirm-check")
                  .find(".spinner")
                  .addClass("hidden")
                  .end()
                  .find(".text")
                  .removeClass("hidden");
              },
              error: function (xhr, status, error) {
                console.error("update_status.php error:", xhr.responseText);
                Toast.fire({
                  icon: "error",
                  title: "เกิดข้อผิดพลาดในการยืนยัน: " + error,
                });
                isProcessing = false;
                $("#btn-confirm-check")
                  .find(".spinner")
                  .addClass("hidden")
                  .end()
                  .find(".text")
                  .removeClass("hidden");
              },
            });
          } else {
            isProcessing = false;
            $("#btn-confirm-check")
              .find(".spinner")
              .addClass("hidden")
              .end()
              .find(".text")
              .removeClass("hidden");
          }
        });
      },
      error: function (xhr, status, error) {
        console.error("stock_list.php error:", xhr.responseText);
        Toast.fire({
          icon: "error",
          title: "ไม่สามารถตรวจสอบรายการ: " + error,
        });
        isProcessing = false;
        $("#btn-confirm-check")
          .find(".spinner")
          .addClass("hidden")
          .end()
          .find(".text")
          .removeClass("hidden");
      },
    });
  });

  $(document).on("click", ".btn-delete-stock", function () {
    let stock_check_id = $(this).data("stock-check-id");
    Swal.fire({
      title: "ยืนยันการลบ?",
      text: "รายการนี้จะถูกลบ",
      icon: "warning",
      showCancelButton: true,
      confirmButtonText: "ลบ",
      cancelButtonText: "ยกเลิก",
    }).then((result) => {
      if (result.isConfirmed) {
        $.ajax({
          url: "page/stock/api/delete_stock.php",
          type: "POST",
          data: { action: "delete_stock_check", stock_check_id },
          dataType: "json",
          success: function (response) {
            if (response.status === "success") {
              Toast.fire({ icon: "success", title: "Success" });
              loadStockCheck();
            } else {
              Toast.fire({ icon: "error", title: response.message });
            }
          },
          error: function (xhr, status, error) {
            console.error("delete_stock.php error:", xhr.responseText);
            Toast.fire({
              icon: "error",
              title: "Error: " + error,
            });
          },
        });
      }
    });
  });

  $("#btn-delete-stock-session").on("click", function () {
    if (!currentStockId) {
      Toast.fire({
        icon: "warning",
        title: "ไม่มีเซสชันสต๊อกที่สามารถลบได้",
      });
      return;
    }

    Swal.fire({
      title: "ยืนยันการลบเซสชัน?",
      text: `เซสชัน ${currentStockId} และรายการตรวจสอบทั้งหมดจะถูกลบ`,
      icon: "warning",
      showCancelButton: true,
      confirmButtonText: "ลบ",
      cancelButtonText: "ยกเลิก",
    }).then((result) => {
      if (result.isConfirmed) {
        $.ajax({
          url: "page/stock/api/delete_stock.php",
          type: "POST",
          data: { action: "delete_stock_session", stock_id: currentStockId },
          dataType: "json",
          headers: { "X-Requested-With": "XMLHttpRequest" },
          success: function (response) {
            if (response.status === "success") {
              Toast.fire({ icon: "success", title: response.message });
              currentStockId = null;
              $("#current_stock_id").val("");
              loadStockCheck();
              loadStockSessions();
              createNewStockSession();
            } else {
              Toast.fire({ icon: "error", title: response.message });
            }
          },
          error: function (xhr, status, error) {
            console.error("delete_stock.php error:", xhr.responseText);
            Toast.fire({
              icon: "error",
              title: "เกิดข้อผิดพลาดในการลบเซสชัน: " + error,
            });
          },
        });
      }
    });
  });

  initializePage();
});
