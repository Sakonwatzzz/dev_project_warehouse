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

$(document).on("click", ".delete-product", function (e) {
  e.preventDefault();
  if (isProcessing) {
    console.warn("Processing in progress, ignoring click");
    return;
  }
  const barcode = $(this).data("barcode");
  const qty = parseInt($(this).data("qty")) || 1;
  const sale_id = $(this).data("sale-id") || SALE_ID;
  console.log("Delete request data:", { barcode, qty, sale_id });

  if (!barcode || !sale_id) {
    Swal.fire({
      icon: "error",
      title: "ข้อผิดพลาด",
      text: "รหัสสินค้าหรือ sale_id ไม่ถูกต้อง",
    });
    return;
  }

  Swal.fire({
    title: "ยืนยันการลบสินค้า?",
    icon: "warning",
    showCancelButton: true,
    confirmButtonText: "ลบ",
    cancelButtonText: "ยกเลิก",
  }).then((result) => {
    if (result.isConfirmed) {
      $.ajax({
        url: "page/sale/ajax/ajax_delete_product.php",
        method: "POST",
        data: {
          action: "delete",
          sale_id: sale_id,
          barcode: barcode,
          qty: qty,
        },
        dataType: "json",
        beforeSend: function (xhr) {
          xhr.setRequestHeader("X-Requested-With", "XMLHttpRequest");
          isProcessing = true;
          console.log("Sending delete request:", {
            action: "delete",
            sale_id,
            barcode,
            qty,
          });
        },
        success: function (response) {
          console.log("Delete response:", response);
          if (response.status === "success") {
            Toast.fire({
              icon: "success",
              title: response.message,
            });
            updateProductTable(sale_id);
          } else {
            Swal.fire({
              icon: "error",
              title: "ข้อผิดพลาด",
              text: response.message,
            });
          }
        },
        complete: function () {
          isProcessing = false;
          $("#barcode_input").focus();
        },
        error: function (xhr, status, error) {
          console.error("Delete AJAX error:", xhr.responseText);
          Swal.fire({
            icon: "error",
            title: "ข้อผิดพลาด",
            html: `<pre>${xhr.responseText}</pre>`,
          });
          isProcessing = false;
        },
      });
    }
  });
});

function updateProductTable(sale_id) {
  $.ajax({
    url: "page/sale/ajax/ajax_get_sale_product.php",
    method: "GET",
    data: { sale_id: sale_id },
    dataType: "json",
    cache: false,
    success: function (response) {
      console.log("Update table response:", response);
      if (response.status === "success") {
        $("#product_table_body").html(response.html);
        $(".total-amount").text(response.total_amount + " บาท");
        $(".total-items").text(response.total_items + " รายการ");
        $(".total-qty").text(response.total_qty + " ชิ้น");
        const hasProducts = !response.html.includes("ไม่พบสินค้า");
        $("#confirm-sale-btn")
          .prop("disabled", !hasProducts)
          .toggleClass("opacity-50 cursor-not-allowed", !hasProducts);
        // Trigger productListUpdated event for sale_payment.js
        $(document).trigger("productListUpdated", [response.total_amount]);
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
