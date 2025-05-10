$(document).ready(function () {
  const Toast = Swal.mixin({
    toast: true,
    position: 'top',
    showConfirmButton: false,
    timer: 1500,
    timerProgressBar: true,
    didOpen: (toast) => {
      toast.addEventListener('mouseenter', Swal.stopTimer);
      toast.addEventListener('mouseleave', Swal.resumeTimer);
      $('.main-body').removeAttr('aria-hidden');
    },
    didClose: () => {
      if (!$('#edit-product-modal').is(':visible')) {
        $('#barcode_input').focus();
      }
    }
  });

  $(document).on("click", ".edit-product", function (e) {
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

  function updateProductTable() {
    console.log("currentSaleId:", currentSaleId);
    if (!currentSaleId) {
      console.error("currentSaleId is undefined");
      Swal.fire({
        icon: "error",
        title: "ข้อผิดพลาด",
        text: "ไม่พบ sale_id ปัจจุบัน กรุณาเลือกบิลใหม่",
        didOpen: () => {
          $(".main-body").removeAttr("aria-hidden");
        },
      });
      return;
    }
    $.ajax({
      url: "page/sale/ajax/ajax_get_sale_product.php",
      method: "GET",
      data: {
        sale_id: currentSaleId,
        _t: new Date().getTime(),
      },
      cache: false,
      dataType: "json",
      success: function (response) {
        console.log("updateProductTable response:", response);
        if (response.status === "success") {
          if (!response.html || response.html.includes("ไม่พบสินค้าในการขายนี้")) {
            console.warn("response.html is empty or indicates no products");
            Swal.fire({
              icon: "warning",
              title: "แจ้งเตือน",
              text: "ไม่พบสินค้าในบิลนี้ กรุณาเพิ่มสินค้าก่อน",
              didOpen: () => {
                $(".main-body").removeAttr("aria-hidden");
              },
            });
            $("#product_table_body").html('<tr><td colspan="7" class="text-center py-8 text-gray-500">ไม่พบสินค้า</td></tr>');
          } else {
            $("#product_table_body").html(response.html);
            $(".total-amount").text(response.sale_total_amount + " บาท");
            $(".total-items").text(response.total_items + " รายการ");
            $(".total-qty").text(response.total_qty + " ชิ้น");
            const hasProducts = !response.html.includes("ไม่พบสินค้า");
            $("#confirm-sale-btn")
              .prop("disabled", !hasProducts)
              .toggleClass("opacity-50 cursor-not-allowed", !hasProducts);
            $("#calculate_change").click();
            bindEditProductEvents();
          }
          // อัปเดตสถานะบิล
          $("#sale-status").text(response.sale_status_name || 'ไม่ทราบสถานะ');
          $(".main-body").removeAttr("aria-hidden");
        } else {
          console.error("updateProductTable failed:", response.message);
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
        console.error("updateProductTable error:", xhr.responseText);
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

  let isProcessing = false;

  $("#edit-product-form").on("submit", function (e) {
    e.preventDefault();
    if (isProcessing) return;
    const qty = parseInt($("#edit-qty").val()) || 0;
    if (qty <= 0) {
      Swal.fire({
        icon: "warning",
        title: "แจ้งเตือน",
        text: "จำนวนต้องมากกว่า 0",
        didOpen: () => {
          $(".main-body").removeAttr("aria-hidden");
        },
      });
      return;
    }
    const formData = $(this).serialize() + "&sale_id=" + encodeURIComponent(currentSaleId);
    console.log("Form data sent:", formData);
    $.ajax({
      url: "page/sale/ajax/ajax_update_product.php",
      method: "POST",
      data: formData,
      dataType: "json",
      beforeSend: function () {
        isProcessing = true;
      },
      success: function (response) {
        console.log("Response:", response);
        if (response.status === "success") {
          Toast.fire({
            icon: "success",
            title: response.message,
          });
          $("#edit-product-modal").addClass("hidden");
          updateProductTable();
        } else {
          Swal.fire({
            icon: "warning",
            title: "แจ้งเตือน",
            text: response.message,
            didOpen: () => {
              $(".main-body").removeAttr("aria-hidden");
            },
          });
        }
      },
      complete: function () {
        isProcessing = false;
        $(".main-body").removeAttr("aria-hidden");
        if (!$("#edit-product-modal").is(":visible")) {
          $("#barcode_input").focus();
        }
      },
      error: function (xhr) {
        console.error("Error:", xhr.responseText);
        Swal.fire({
          icon: "error",
          title: "ข้อผิดพลาด",
          text: xhr.responseText || "ไม่สามารถแก้ไขสินค้าได้",
          didOpen: () => {
            $(".main-body").removeAttr("aria-hidden");
          },
        });
        isProcessing = false;
      },
    });
  });

  // จัดการการยืนยันการขาย
  $("#confirm-sale-btn").on("click", function (e) {
    e.preventDefault();
    if (isProcessing) return;
    Swal.fire({
      title: 'ยืนยันการขาย',
      text: 'คุณต้องการยืนยันการขายนี้หรือไม่?',
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: 'ยืนยัน',
      cancelButtonText: 'ยกเลิก',
      didOpen: () => {
        $(".main-body").removeAttr("aria-hidden");
      },
    }).then((result) => {
      if (result.isConfirmed) {
        $.ajax({
          url: "page/sale/ajax/ajax_confirm_sale.php",
          method: "POST",
          data: {
            sale_id: currentSaleId,
            _t: new Date().getTime(),
          },
          cache: false,
          dataType: "json",
          beforeSend: function () {
            isProcessing = true;
          },
          success: function (response) {
            console.log("Confirm sale response:", response);
            if (response.status === "success") {
              Toast.fire({
                icon: "success",
                title: response.message,
              });
              // อัปเดตสถานะและตาราง
              $("#sale-status").text(response.sale_status_name || 'ไม่ทราบสถานะ');
              updateProductTable();
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
          complete: function () {
            isProcessing = false;
            $(".main-body").removeAttr("aria-hidden");
            $("#barcode_input").focus();
          },
          error: function (xhr) {
            console.error("Confirm sale error:", xhr.responseText);
            Swal.fire({
              icon: "error",
              title: "ข้อผิดพลาด",
              text: xhr.responseText || "ไม่สามารถยืนยันการขายได้",
              didOpen: () => {
                $(".main-body").removeAttr("aria-hidden");
              },
            });
            isProcessing = false;
          },
        });
      }
    });
  });

  $("#close-modal, #cancel-edit").on("click", function () {
    $("#edit-product-modal").addClass("hidden");
    $(".main-body").removeAttr("aria-hidden");
    $("#barcode_input").focus();
  });

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