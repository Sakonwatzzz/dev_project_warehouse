$(document).ready(function () {
  const $confirmBtn = $("#confirm-sale-btn");
  const $paidAmountInput = $("#sale_paid_amount");
  const $changeAmountInput = $("#sale_change_amount");

  // ฟังก์ชันตรวจสอบและอัปเดตสถานะปุ่มยืนยัน
  function updateConfirmButtonState() {
    const paidAmount = parseFloat($paidAmountInput.val()) || 0;
    const totalAmount =
      parseFloat($(".total-amount").val()) ||
      parseFloat($(".total-amount").text().replace(" บาท", "")) ||
      0;
    if (paidAmount <= 0 || paidAmount < totalAmount || paidAmount === 1) {
      // เพิ่มเช็ค paidAmount !== 1
      $confirmBtn
        .prop("disabled", true)
        .addClass("opacity-50 cursor-not-allowed")
        .attr(
          "title",
          paidAmount === 1
            ? "จำนวนเงินที่ชำระห้ามเป็น 1.00"
            : "กรุณากรอกจำนวนเงินที่ชำระให้ครบถ้วน"
        );
    } else {
      $confirmBtn
        .prop("disabled", false)
        .removeClass("opacity-50 cursor-not-allowed")
        .removeAttr("title");
    }
  }

  // เรียกใช้เมื่อเริ่มต้นและเมื่อมีการเปลี่ยนแปลงใน sale_paid_amount
  updateConfirmButtonState();
  $paidAmountInput.on("input", updateConfirmButtonState);

  // จัดการ submit ของ #confirm-sale-form
  $confirmBtn.on("click", function (e) {
    e.preventDefault();
    const paidAmount = parseFloat($paidAmountInput.val()) || 0;
    const totalAmount =
      parseFloat($(".total-amount").val()) ||
      parseFloat($(".total-amount").text().replace(" บาท", "")) ||
      0;

    if (paidAmount === 1) {
      // เพิ่มการเช็ค
      Swal.fire({
        icon: "warning",
        title: "แจ้งเตือน",
        text: "จำนวนเงินที่ชำระห้ามเป็น 1.00",
        didOpen: () => {
          $(".main-body").removeAttr("aria-hidden");
        },
      });
      return;
    }

    if (paidAmount <= 0) {
      Swal.fire({
        icon: "warning",
        title: "แจ้งเตือน",
        text: "กรุณากรอกจำนวนเงินที่ชำระ",
        didOpen: () => {
          $(".main-body").removeAttr("aria-hidden");
        },
      });
      return;
    }

    if (paidAmount < totalAmount) {
      Swal.fire({
        icon: "warning",
        title: "แจ้งเตือน",
        text:
          "จำนวนเงินที่ชำระไม่เพียงพอ (ต้องมากกว่าหรือเท่ากับยอดรวม: " +
          totalAmount.toFixed(2) +
          " บาท)",
        didOpen: () => {
          $(".main-body").removeAttr("aria-hidden");
        },
      });
      return;
    }

    console.log(
      "Submitting #confirm-sale-form:",
      $("#confirm-sale-form").serialize()
    );
    $("#confirm-sale-btn .btn-text").addClass("hidden");
    $("#confirm-sale-btn .loading-spinner").removeClass("hidden");
    $.ajax({
      url: "page/sale/ajax/ajax_confirm_sale.php",
      method: "POST",
      data: $("#confirm-sale-form").serialize(),
      dataType: "json",
      success: function (response) {
        $("#confirm-sale-btn .btn-text").removeClass("hidden");
        $("#confirm-sale-btn .loading-spinner").addClass("hidden");
        if (response.status === "success") {
          Swal.fire({
            icon: "success",
            title: "สำเร็จ",
            text: response.message,
            timer: 1500,
            didOpen: () => {
              $(".main-body").removeAttr("aria-hidden");
              $("#print_receipt").removeClass("hidden");
              $(".edit-product").addClass("hidden");
              $(".delete-product").addClass("hidden");
              $('#member_search, #member_id, select[name="personnel_id"]').prop(
                "disabled",
                true
              );
              $("#confirm-sale-btn")
                .prop("disabled", true)
                .addClass("opacity-50 cursor-not-allowed");
              $("#barcode_input, #barcode-qty").prop("disabled", true);
              $("#form_add_product")
                .find("input, select, button")
                .prop("disabled", true);
              $("#sale-status").text(
                response.sale_status_name || "ไม่ทราบสถานะ"
              );
              // Disable sale_paid_amount และ sale_change_amount
              $paidAmountInput
                .prop("disabled", true)
                .addClass("bg-gray-200 cursor-not-allowed");
              $changeAmountInput
                .prop("disabled", true)
                .addClass("bg-gray-200 cursor-not-allowed");
            },
          });
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
      error: function (xhr) {
        $("#confirm-sale-btn .btn-text").removeClass("hidden");
        $("#confirm-sale-btn .loading-spinner").addClass("hidden");
        Swal.fire({
          icon: "error",
          title: "check_confirmed_status.php?",
          text: xhr.responseText || "ไม่สามารถยืนยันการขายได้",
          didOpen: () => {
            $(".main-body").removeAttr("aria-hidden");
          },
        });
      },
    });
  });

  // ป้องกัน Enter key ใน #confirm-sale-form
  $("#confirm-sale-form input").on("keypress", function (e) {
    if (e.which === 13) {
      e.preventDefault();
      console.log("Blocked Enter key in #confirm-sale-form");
    }
  });
  // // เพิ่ม: เช็คสถานะ confirmed จาก server เมื่อโหลดหน้า
  // $.get(
  //   "check_confirmed_status.php",
  //   { sale_id: $('input[name="sale_id"]').val() },
  //   function (response) {
  //     if (response.confirmed) {
  //       $paidAmountInput
  //         .prop("disabled", true)
  //         .addClass("bg-gray-200 cursor-not-allowed");
  //       $changeAmountInput
  //         .prop("disabled", true)
  //         .addClass("bg-gray-200 cursor-not-allowed");
  //       $confirmBtn
  //         .prop("disabled", true)
  //         .addClass("opacity-50 cursor-not-allowed");
  //     }
  //   }
  // );
});
