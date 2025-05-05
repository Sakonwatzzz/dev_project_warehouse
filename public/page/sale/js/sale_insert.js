
//ยังไม่ได้ถูกเรียกใช้งานจากหน้าหลัก แยกออกมารอ

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

  let isProcessing = false;

  // Handle product addition
  $("#form_add_product input").on("keypress", function (e) {
    if (e.which === 13 && !isProcessing) {
      e.preventDefault();
      e.stopPropagation();
      const barcode = $("#barcode_input").val();
      const qty = parseInt($('input[name="qty"]').val()) || 0;
      if (!barcode) {
        Swal.fire({
          icon: "error",
          title: "ข้อผิดพลาด",
          text: "กรุณาป้อนรหัสสินค้า",
        });
        return;
      }
      if (qty <= 0) {
        Swal.fire({
          icon: "error",
          title: "ข้อผิดพลาด",
          text: "จำนวนต้องมากกว่า 0",
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
          if (response.status === "success") {
            Toast.fire({
              icon: "success",
              title: response.message,
            });
            updateProductTable();
            $("#form_add_product")[0].reset();
            $("#product_info").addClass("hidden").empty();
          } else {
            Swal.fire({
              icon: "warning",
              title: "",
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

  // Barcode search for realtime
  $("#barcode_input").on("input", function () {
    const query = $(this).val();
    if (query.length < 2) {
      $("#barcode_suggestions").addClass("hidden").empty();
      $("#product_info").addClass("hidden").empty();
      return;
    }
    $.ajax({
      url: "page/sale/ajax/ajax_search_product.php",
      method: "GET",
      data: { query: query },
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
        });
      },
    });
  });

  function fetchProductInfo(barcode) {
    $.ajax({
      url: "page/sale/ajax/ajax_get_product.php",
      method: "GET",
      data: { barcode: barcode },
      dataType: "json",
      success: function (response) {
        const product = response;
        const info = $("#product_info").removeClass("hidden");
        if (!product) {
          info.html(
            '<span class="text-red-500">ไม่พบสินค้าหรือสต็อกไม่เพียงพอ</span>'
          );
          return;
        }
        info.html(`
                    <p><strong>ชื่อสินค้า:</strong> ${product.product_name}</p>
                    <p><strong>ราคาขาย:</strong> ${product.product_price4} บาท</p>
                    <p><strong>สต็อกคงเหลือ:</strong> ${product.total_stock} ชิ้น</p>
                `);
      },
      error: function (xhr) {
        $("#product_info").addClass("hidden").empty();
        Swal.fire({
          icon: "error",
          title: "ข้อผิดพลาด",
          text: xhr.responseText || "ไม่สามารถดึงข้อมูลสินค้าได้",
        });
      },
    });
  }

  function updateProductTable() {
    $.ajax({
      url: "page/sale/ajax/ajax_get_sale_products.php",
      method: "GET",
      data: { sale_id: "<?php echo htmlspecialchars($sale_id); ?>" },
      dataType: "json",
      success: function (response) {
        if (response.status === "success") {
          $("#product_table_body").html(response.html);
          $(".total-amount").text(response.total_amount + " บาท");
          $(".total-items").text(response.total_items + " รายการ"); // เพิ่ม
          $(".total-qty").text(response.total_qty + " ชิ้น"); // เพิ่ม
          const hasProducts = !response.html.includes("ไม่พบสินค้า");
          $("#confirm-sale-btn")
            .prop("disabled", !hasProducts)
            .toggleClass("opacity-50 cursor-not-allowed", !hasProducts);
          $("#calculate_change").click();
          bindEditProductEvents();
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

  // Placeholder for bindEditProductEvents (used in updateProductTable)
  function bindEditProductEvents() {
    $(".edit-product")
      .off("click")
      .on("click", function (e) {
        e.preventDefault();
        const barcode = $(this).data("barcode");
        const name = $(this).data("name");
        const qty = $(this).data("qty");
        const price = $(this).data("price");
        const capital = $(this).data("capital");
        const reference = $(this).data("reference") || "";
        $("#edit-barcode").val(barcode);
        $("#edit-barcode-display").val(barcode);
        $("#edit-product-name").val(name);
        $("#edit-qty").val(qty);
        $("#edit-price").val(parseFloat(price).toFixed(2));
        $("#edit-capital").val(parseFloat(capital).toFixed(2));
        $("#edit-reference").val(reference);
        $(".main-body").attr("aria-hidden", "true");
        $("#edit-product-modal").removeClass("hidden");
      });
  }
});
