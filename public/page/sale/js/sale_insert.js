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
      $(".main-body").removeAttr("aria-hidden");
    },
    didClose: () => {
      if (!$("#edit-product-modal").is(":visible")) {
        $("#barcode_input").focus();
      }
    },
  });

  const sale_id = $('input[name="sale_id"]').val();
  if (!sale_id) {
    Swal.fire({
      icon: "warning",
      title: "แจ้งเตือน",
      text: "ไม่พบบิลการขาย กรุณาเปิดบิลใหม่",
    });
    return;
  }

  function fetchProductInfo(barcode) {
    $.ajax({
      url: "page/sale/ajax/ajax_get_product.php",
      method: "GET",
      data: {
        barcode: barcode,
      },
      dataType: "json",
      success: function (response) {
        const product = response;
        const info = $("#product_info").removeClass("hidden");
        if (!product) {
          info.html(
            '<span class="text-red-500">ไม่พบสินค้าหรือสต็อกไม่เพียงพอ</span>'
          );
          $("#selected_price").val("");
          return;
        }
        const prices = [
          parseFloat(product.product_price1) || 0,
          parseFloat(product.product_price2) || 0,
          parseFloat(product.product_price3) || 0,
          parseFloat(product.product_price4) || 0,
        ];
        // dropdown สำหรับเลือกราคา
        if (prices.every((p) => p <= 0)) {
          info.html(
            '<span class="text-red-500">สินค้านี้ไม่มีราคาขายที่ถูกต้อง</span>'
          );
          $("#selected_price").val("");
          Swal.fire({
            icon: "error",
            title: "ข้อผิดพลาด",
            text: "ไม่มีราคาขายที่มากกว่า 0 บาท กรุณาตรวจสอบข้อมูลสินค้า",
          });
          return;
        }
        // สร้าง option เฉพาะราคา > 0
        let options = "";
        if (prices[0] > 0)
          options += `<option value="${prices[0].toFixed(
            2
          )}">ราคาขาย 1: ${prices[0].toFixed(2)} บาท</option>`;
        if (prices[1] > 0)
          options += `<option value="${prices[1].toFixed(
            2
          )}">ราคาขาย 2: ${prices[1].toFixed(2)} บาท</option>`;
        if (prices[2] > 0)
          options += `<option value="${prices[2].toFixed(
            2
          )}">ราคาขาย 3: ${prices[2].toFixed(2)} บาท</option>`;
        if (prices[3] > 0)
          options += `<option value="${prices[3].toFixed(
            2
          )}" selected>ราคาขาย 4: ${prices[3].toFixed(2)} บาท</option>`;
        info.html(`
           <div class="bg-white rounded-b-lg p-2">
            <p class="text-xl"><strong>ชื่อสินค้า:</strong> ${product.product_name}</p>
            <p class="text-xl"><strong>เลือกราคาขาย:</strong>
            <select id="price_select" class="border-2 border-solid cursor-pointer bg-white hover:bg-gray-200">
              ${options}
            </select>
            </p>
            <p class="text-xl"><strong>สต็อกคงเหลือ:</strong> ${product.total_stock} ชิ้น</p>
           </div>
        `);
        // เปลี่ยน dropdown อัีพเดท input
        $("#price_select").on("change", function () {
          const val = parseFloat($(this).val());
          if (val > 0) {
            $("#selected_price").val(val.toFixed(2));
          } else {
            $("#selected_price").val("");
            Swal.fire({
              icon: "warning",
              title: "แจ้งเตือน",
              text: "ราคาที่เลือกต้องมากกว่า 0 บาท",
            });
          }
        });
        // ค่าเริ่มต้นเป็นราคาที่มากกว่า 0 แรก (default price4 ถ้ามี)
        const defaultPrice =
          prices[3] > 0
            ? prices[3].toFixed(2)
            : prices.find((p) => p > 0)?.toFixed(2) || "";
        $("#selected_price").val(defaultPrice);
      },
      error: function (xhr) {
        $("#product_info").addClass("hidden").empty();
        $("#selected_price").val("");
        Swal.fire({
          icon: "error",
          title: "ข้อผิดพลาด",
          text: xhr.responseText || "ไม่สามารถดึงข้อมูลสินค้าได้",
          didOpen: () => {
            $(".main-body").removeAttr("aria-hidden");
          },
        });
      },
    });
  }

  let isProcessing = false;
  $("#barcode_input").on("input", function () {
    const query = $(this).val();
    if (query.length < 2) {
      $("#barcode_suggestions").addClass("hidden").empty();
      $("#product_info").addClass("hidden").empty();
      $("#selected_price").val("");
      return;
    }
    $.ajax({
      url: "page/sale/ajax/ajax_search_product.php",
      method: "GET",
      data: {
        query: query,
      },
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
                <div class="p-2 hover:bg-gray-100 cursor-pointer" data-barcode="${product.product_id}">
                    ${product.product_id} - ${product.product_name} (สต็อก: ${product.total_stock})
                </div>
            `);
        });
        suggestions.find("div").on("click", function () {
          const barcode = $(this).data("barcode");
          $("#barcode_input").val(barcode);
          suggestions.addClass("hidden").empty();
          fetchProductInfo(barcode);
        });
      },
      error: function (xhr) {
        Swal.fire({
          icon: "error",
          title: "ข้อผิดพลาด",
          text: xhr.responseText || "ไม่สามารถค้นหาสินค้าได้",
          didOpen: () => {
            $(".main-body").removeAttr("aria-hidden");
          },
        });
      },
    });
  });

  $("#form_add_product input").on("keypress", function (e) {
    if (e.which === 13 && !isProcessing) {
      e.preventDefault();
      e.stopPropagation();
      const barcode = $("#barcode_input").val();
      const qty = parseInt($('input[name="qty"]').val()) || 0;
      const selectedPrice = parseFloat($("#selected_price").val()) || 0;
      // console.log("Adding product:", { sale_id, barcode, qty }); // Debug
      if (!barcode) {
        Swal.fire({
          icon: "warning",
          title: "แจ้งเตือน",
          text: "กรุณาป้อนรหัสสินค้า",
        });
        return;
      }
      if (qty <= 0) {
        Swal.fire({
          icon: "warning",
          title: "แจ้งเตือน",
          text: "จำนวนต้องมากกว่า 0",
        });
        return;
      }
      if (selectedPrice <= 0) {
        Swal.fire({
          icon: "warning",
          title: "แจ้งเตือน",
          text: "กรุณาเลือกราคาขายที่มากกว่า 0 บาท",
        });
        return;
      }
      $.ajax({
        url: "page/sale/ajax/ajax_insert_product.php",
        method: "POST",
        data: $("#form_add_product").serialize(),
        dataType: "json",
        beforeSend: function () {
          isProcessing = true;
          $("#barcode_input").prop("disabled", true);
          $('input[name="qty"]').prop("disabled", true);
        },
        success: function (response) {
          //console.log("Insert product response:", response); // Debug
          if (response.status === "success") {
            // Toast.fire({
            //   icon: "success",
            //   title: response.message,
            // });
            updateProductTable();
            $("#form_add_product")[0].reset();
            $("#product_info").addClass("hidden").empty();
            $("#selected_price").val("");
          } else {
            Swal.fire({
              icon: "warning",
              title: "แจ้งเตือน",
              text: response.message,
            });
          }
        },
        complete: function () {
          isProcessing = false;
          $("#barcode_input").prop("disabled", false);
          $('input[name="qty"]').prop("disabled", false);
          $(".main-body").removeAttr("aria-hidden");
          if (!$("#edit-product-modal").is(":visible")) {
            $("#barcode_input").focus();
          }
        },
        error: function (xhr) {
        //  console.error("Insert product error:", xhr.responseText); // Debug
          Swal.fire({
            icon: "error",
            title: "ข้อผิดพลาด",
            text: xhr.responseText || "ไม่สามารถเพิ่มสินค้าได้",
          });
          isProcessing = false;
        },
      });
    }
  });

  $("#form_add_product")
    .off("submit")
    .on("submit", function (e) {
      e.preventDefault();
    });

  function updateProductTable() {
    const current_sale_id = $('input[name="sale_id"]').val();
    console.log("Updating product table with sale_id:", current_sale_id); // Debug
    if (!current_sale_id) {
      Swal.fire({
        icon: "error",
        title: "ข้อผิดพลาด",
        text: "ไม่พบ sale_id สำหรับการอัปเดตตาราง",
      });
      return;
    }
    $.ajax({
      url: "page/sale/ajax/ajax_get_sale_product.php",
      method: "GET",
      data: { sale_id: current_sale_id },
      dataType: "json",
      success: function (response) {
        //console.log("Update product table response:", response); // Debug
        if (response.status === "success") {
          $("#product_table_body").html(response.html);
          $(".sale-net-amount").text(response.sale_net_amount + " บาท");
          $(".sale-vat-amount").text(response.sale_vat_amount + " บาท");
          $(".total-amount").text(response.sale_total_amount + " บาท");
          $(".total-items").text(response.total_items + " รายการ");
          $(".total-qty").text(response.total_qty + " ชิ้น");
          const hasProducts = !response.html.includes("ไม่พบสินค้า");
          $("#confirm-sale-btn")
            .prop("disabled", !hasProducts)
            .toggleClass("opacity-50 cursor-not-allowed", !hasProducts);
          const saleNetAmount = parseFloat(response.sale_net_amount) || 0;
          const roundedNet = parseFloat(saleNetAmount.toFixed(2));
          if (isNaN(saleNetAmount)) {
            console.warn(
              "Invalid sale_net_amount from response:",
              response.sale_net_amount
            );
          }
          $(document).trigger("productListUpdated", [roundedNet]); // bindEditProductEvents();
          $(".main-body").removeAttr("aria-hidden");
        } else {
          Swal.fire({
            icon: "warning",
            title: "ข้อผิดพลาด",
            text: response.message || "ไม่สามารถเพิ่มสินค้าได้",
            didOpen: () => {
              $(".main-body").removeAttr("aria-hidden");
            },
          });
        }
      },
      error: function (xhr) {
        console.error("Update product table error:", xhr.responseText); // Debug
        Swal.fire({
          icon: "error",
          title: "ข้อผิดพลาด",
          text: xhr.responseText || "ไม่สามารถดึงข้อมูลตารางได้",
          didOpen: () => {
            $(".main-body").removeAttr("aria-hidden");
          },
        });
      },
    });
  }

  // function bindEditProductEvents() {
  //   $(".edit-product")
  //     .off("click")
  //     .on("click", function (e) {
  //       e.preventDefault();
  //       const barcode = $(this).data("barcode");
  //       const name = $(this).data("name");
  //       const qty = $(this).data("qty");
  //       const price = $(this).data("price");
  //       const capital = $(this).data("capital");
  //       const reference = $(this).data("reference") || "";
  //       $("#edit-barcode").val(barcode);
  //       $("#edit-barcode-display").val(barcode);
  //       $("#edit-product-name").val(name);
  //       $("#edit-qty").val(qty);
  //       $("#edit-price").val(parseFloat(price).toFixed(2));
  //       $("#edit-capital").val(parseFloat(capital).toFixed(2));
  //       $("#edit-reference").val(reference);
  //       $(".main-body").attr("aria-hidden", "true");
  //       $("#edit-product-modal").removeClass("hidden");
  //     });
  // }
});
