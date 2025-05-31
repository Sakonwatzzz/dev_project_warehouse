   // function processInput(input) {
  //   const addPattern = /^\d+\*\d+$/;
  //   const deletePattern = /^-\d+\*\d+$/;
  //   input = input.trim();

  // let product_id, stock_check_actual;
  // if (addPattern.test(input)) {
  //   [product_id, stock_check_actual] = input.split("*");
  // } else if (deletePattern.test(input)) {
  //   [product_id, stock_check_actual] = input.replace(/^-/, "").split("*");
  //   stock_check_actual = `-${stock_check_actual}`;
  // } else {
  //   product_id = input;
  //   stock_check_actual = getValue("#stock_check_actual", "0");
  // }

  //   product_id = product_id.trim();
  //   stock_check_actual = stock_check_actual.trim() || "0";

  //   $("#product_id_input").val(product_id);
  //   $("#stock_check_actual").val(stock_check_actual);
  //   validateForm();
  //   return true;
  // }

  // $("#product_id_input").on("keypress", function (event) {
  //   if (event.key === "Enter") {
  //     event.preventDefault();
  //     const input = $(this).val().trim();
  //     if (input) {
  //       processInput(input);
  //       if (!$("#btn-submit").prop("disabled")) {
  //         $("#form_insert_stock").submit();
  //       } else {
  //         Toast.fire({
  //           icon: "warning",
  //           title: "กรุณากรอกข้อมูลให้ครบถ้วนและถูกต้อง",
  //         });
  //       }
  //     } else {
  //       Toast.fire({
  //         icon: "warning",
  //         title: "กรุณาป้อนรหัสสินค้า",
  //       });
  //     }
  //   }
  // });

  // function validateForm() {
  //   const product_id = getValue("#product_id_input");
  //   const stock_check_actual = getValue("#stock_check_actual", "0");
  //   const stock_date = getValue("#stock_date");

  //   const isValid =
  //     product_id &&
  //     stock_check_actual &&
  //     stock_date &&
  //     currentStockId &&
  //     /^\d+$/.test(product_id) &&
  //     /^-?\d+$/.test(stock_check_actual);

  //   $("#btn-submit").prop("disabled", !isValid);
  //   $("#btn-confirm-check").prop("disabled", !currentStockId);

  //   if (!isValid) {
  //     console.log("validateForm failed:", {
  //       product_id,
  //       stock_check_actual,
  //       stock_date,
  //       currentStockId,
  //       product_id_valid: /^\d+$/.test(product_id),
  //       stock_check_actual_valid: /^-?\d+$/.test(stock_check_actual),
  //     });
  //   }

  //   return isValid;
  // }

   //   function loadStockCheck(resolve, reject) {
  //     console.log("Loading stock check table for stock_id:", currentStockId);
  //     let url = "page/stock/api/stock_list.php";
  //     let data = currentStockId ? { stock_id: currentStockId } : { status: 1 };
  //     $.ajax({
  //       url: url,
  //       type: "GET",
  //       data: data,
  //       dataType: "json",
  //       headers: { "X-Requested-With": "XMLHttpRequest" },
  //       cache: false,
  //       success: function (data) {
  //         console.log("stock_list.php response:", data);
  //         let tbody = $("#stock_check_table");
  //         tbody.empty();
  //         if (!data || data.length === 0) {
  //           tbody.append(
  //             '<tr><td colspan="9" class="text-center py-4 text-gray-500">ไม่พบรายการตรวจสอบสต๊อกสินค้า</td></tr>'
  //           );
  //           $("#btn-confirm-check").prop("disabled", true);
  //           if (resolve) resolve(); // แจ้งว่าเสร็จ
  //           return;
  //         }

  //         let hasPendingStatus = false;
  //         $.each(data, function (index, item) {
  //           if (parseInt(item.check_status_id) === 1) hasPendingStatus = true;
  //           let formattedDate = "-";
  //           if (item.stock_date) {
  //             try {
  //               formattedDate = luxon.DateTime.fromFormat(
  //                 item.stock_date,
  //                 "yyyy-MM-dd HH:mm:ss"
  //               ).toFormat("dd/MM/yyyy HH:mm:ss");
  //             } catch (e) {
  //               console.error(
  //                 "Date format error for stock_date:",
  //                 item.stock_date,
  //                 e
  //               );
  //               formattedDate = item.stock_date;
  //             }
  //           }

  //           let row = `
  //                     <tr data-stock-check-id="${item.stock_check_id}">
  //                         <td class="px-3 sm:px-6 py-2 border text-left text-xs sm:text-sm">${formattedDate}</td>
  //                         <td class="px-3 sm:px-6 py-2 border text-left text-xs sm:text-sm">${
  //                           item.product_id || "-"
  //                         }</td>
  //                         <td class="px-3 sm:px-6 py-2 border text-left text-xs sm:text-sm">${
  //                           item.product_name || "-"
  //                         }</td>
  //                         <td class="px-3 sm:px-6 py-2 border text-right text-xs sm:text-sm">${
  //                           item.stock_check_system || "0"
  //                         }</td>
  //                         <td class="px-3 sm:px-6 py-2 border text-right text-xs sm:text-sm stock-check-actual">${
  //                           item.stock_check_actual || "0"
  //                         }</td>
  //                         <td class="px-3 sm:px-6 py-2 border text-left text-xs sm:text-sm" hidden>${
  //                           item.personnel_name || "-"
  //                         }</td>
  //                         <td class="px-3 sm:px-6 py-2 border text-left text-xs sm:text-sm" hidden>${
  //                           item.check_status_name || "-"
  //                         }</td>
  //                         <td class="px-3 sm:px-6 py-2 border text-left text-xs sm:text-sm stock_check_comment">
  //                             ${item.stock_check_remark_name || (item.stock_check_remark_id ? "ไม่พบหมายเหตุ" : "-")}
  //                         </td>>
  //                         <td class="px-3 sm:px-6 py-2 border text-left text-xs sm:text-sm stock_check_comment">${
  //                           item.stock_check_comment || "-"
  //                         }</td>
  //                         <td class="px-3 sm:px-6 py-2 text-center text-xs sm:text-sm flex gap-4 actions">
  //                             ${
  //                               parseInt(item.check_status_id) === 1
  //                                 ? `
  //                                 <a class="btn-edit-stock bg-yellow-500 text-white px-2 sm:px-3 py-1 rounded hover:bg-yellow-600 transition flex items-center text-xs sm:text-sm cursor-pointer">
  //                                     <i class="fas fa-edit mr-1"></i>แก้ไข
  //                                 </a>
  //                                 <a class="btn-delete-stock bg-red-500 text-white px-2 sm:px-3 py-1 rounded hover:bg-red-600 transition flex items-center text-xs sm:text-sm cursor-pointer" data-stock-check-id="${item.stock_check_id}">
  //                                     <i class="fas fa-trash-alt mr-1"></i>ลบ
  //                                 </a>`
  //                                 : ""
  //                             }
  //                         </td>
  //                     </tr>`;
  //           tbody.append(row);
  //         });

  //         $("#btn-confirm-check").prop("disabled", !hasPendingStatus);
  //         if (resolve) resolve(); // แจ้งว่าเสร็จ
  //       },
  //       error: function (xhr, status, error) {
  //         console.error("stock_list.php error:", xhr.responseText);
  //         Toast.fire({
  //           icon: "error",
  //           title: "ไม่สามารถดึงข้อมูลจาก stock_list.php: " + error,
  //         });
  //         $("#btn-confirm-check").prop("disabled", true);
  //         if (reject) reject(error); // แจ้งว่า error
  //       },
  //     });
  //   }
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
            else if (!getValue("#stock_check_actual")) errorMessage = "กรุณาป้อนจำนวนที่นับได้จริง";
            else if (!getValue("#stock_date")) errorMessage = "วันที่สต๊อกไม่ถูกตั้งค่า";
            else if (!currentStockId) errorMessage = "ไม่มีเซสชันสต๊อกที่ใช้งาน";
            else if (!/^\d+$/.test(getValue("#product_id_input"))) errorMessage = "รหัสสินค้าต้องเป็นตัวเลข";
            else if (!/^-?\d+$/.test(getValue("#stock_check_actual"))) errorMessage = "จำนวนต้องเป็นตัวเลข";

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

        let stockDate = getValue("#stock_date");
        try {
            stockDate = luxon.DateTime.fromFormat(stockDate, "dd-MM-yyyy HH:mm:ss").toFormat("yyyy-MM-dd HH:mm:ss");
        } catch (e) {
            console.error("Date conversion error:", e);
            Toast.fire({ icon: "error", title: "รูปแบบวันที่ไม่ถูกต้อง" });
            isProcessing = false;
            $("#btn-submit")
                .find(".spinner")
                .addClass("hidden")
                .end()
                .find(".text")
                .removeClass("hidden");
            return;
        }

        let formData = {
            stock_id: currentStockId,
            product_id: getValue("#product_id_input"),
            stock_check_actual: parseInt(getValue("#stock_check_actual", "1")),
            stock_date: stockDate,
            stock_check_remark: getValue("#stock-check-remark", ""),
            personnel_id: getValue("#personnel_id", "1"),
        };

        const selected_warehouse_id = $("#warehouse_select").val();

        $.ajax({
            url: "page/stock/api/get_product_location.php",
            type: "GET",
            data: {
                product_id: formData.product_id,
                warehouse_id: selected_warehouse_id,
            },
            dataType: "json",
            headers: { "X-Requested-With": "XMLHttpRequest" },
            cache: false,
            success: function (productData) {
                console.log("get_product_location.php response:", productData);
                if (!productData || (Array.isArray(productData) && productData.length === 0)) {
                    Toast.fire({ icon: "error", title: `ไม่พบข้อมูลสินค้า ${formData.product_id} ในคลังใด` });
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
                if (selected_warehouse_id && product.warehouse_id != selected_warehouse_id) {
                    Toast.fire({ icon: "error", title: `สินค้านี้อยู่ในคลังอื่น (${product.warehouse_name})` });
                    $("#btn-submit")
                        .find(".spinner")
                        .addClass("hidden")
                        .end()
                        .find(".text")
                        .removeClass("hidden");
                    isProcessing = false;
                    return;
                }

                formData.warehouse_id = product.warehouse_id || null;
                formData.lot_id = product.lot_id || null;
                formData.lot_product_id = product.lot_product_id || null;
                formData.stock_check_system = product.lot_product_remain || "0";

                $("#warehouse_id").val(formData.warehouse_id);
                $("#lot_id").val(formData.lot_id);
                $("#lot_product_id").val(formData.lot_product_id);

                // ตรวจสอบว่ามีรายการใน stock_check หรือไม่
                $.ajax({
                    url: "page/stock/api/stock_list.php",
                    type: "GET",
                    data: {
                        stock_id: currentStockId,
                        product_id: formData.product_id,
                        warehouse_id: formData.warehouse_id,
                        lot_id: formData.lot_id,
                        lot_product_id: formData.lot_product_id,
                    },
                    dataType: "json",
                    headers: { "X-Requested-With": "XMLHttpRequest" },
                    success: function (checkData) {
                        console.log("stock_list.php check response:", checkData);
                        if (checkData && checkData.length > 0) {
                            // พบรายการที่มีอยู่แล้ว อัปเดต stock_check_actual
                            formData.action = "update_check_product";
                            formData.stock_check_id = checkData[0].stock_check_id;
                            formData.stock_check_actual = parseInt(checkData[0].stock_check_actual) + formData.stock_check_actual;
                        } else {
                            // ไม่พบรายการ ใช้ insert
                            formData.action = "insert_check_product";
                        }

                        console.log("Sending AJAX to insert_stock.php:", formData);
                        $.ajax({
                            url: "page/stock/api/insert_stock.php",
                            type: "POST",
                            data: formData,
                            dataType: "json",
                            success: function (response) {
                                if (response.status === "success") {
                                    Toast.fire({ icon: "success", title: response.message });
                                    $("#form_insert_stock")[0].reset();
                                    setStockDate();
                                    $("#product_id_input").focus();
                                    $("#product_info").addClass("hidden").html("");
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
                                Toast.fire({ icon: "error", title: "เกิดข้อผิดพลาดในการบันทึก: " + error });
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
                        console.error("stock_list.php check error:", xhr.responseText);
                        Toast.fire({ icon: "error", title: "ไม่สามารถตรวจสอบรายการ: " + error });
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
                Toast.fire({ icon: "error", title: "ไม่พบข้อมูลในคลังที่เลือก" });
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