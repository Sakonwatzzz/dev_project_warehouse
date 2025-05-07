$(document).ready(function () {
  // เปิด Modal และกรอกข้อมูลแก้ไข
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
  // Submit ฟอร์มแก้ไขสินค้า
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
    const formData = $(this).serialize();
    console.log("Form data sent:", formData); // ดีบักข้อมูลที่ส่ง
    $.ajax({
      url: "page/sale/ajax/ajax_update_product.php",
      method: "POST",
      data: formData,
      dataType: "json",
      beforeSend: function () {
        isProcessing = true;
      },
      success: function (response) {
        console.log("Response:", response); // ดีบักการตอบกลับ
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
        console.log("Error:", xhr.responseText); // ดีบักข้อผิดพลาด
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
  $("#close-modal, #cancel-edit").on("click", function () {
    $("#edit-product-modal").addClass("hidden");
    $(".main-body").removeAttr("aria-hidden");
    $("#barcode_input").focus();
  });

  // Bind event สำหรับปุ่มแก้ไขและลบ
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
