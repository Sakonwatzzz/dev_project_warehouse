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