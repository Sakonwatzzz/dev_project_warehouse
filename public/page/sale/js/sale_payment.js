// กำหนดตัวแปร global
let dynamicNetAmount = 0;
let dynamicVatAmount = 0;
let dynamicTotalAmount = 0;

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
  });

  // กำหนด saleId ก่อนเรียก resetPaymentValues
  const saleId = $('input[name="sale_id"]').val() || "";

  function resetPaymentValues() {
    if (!$(".sale-net-amount").length) {
      console.warn("DOM element .sale-net-amount not found");
      return;
    }
    dynamicNetAmount =
      parseFloat(
        $(".sale-net-amount")
          .text()
          .replace(/[^0-9.-]+/g, "")
      ) || 0;
    dynamicVatAmount =
      parseFloat(
        $(".sale-vat-amount")
          .text()
          .replace(/[^0-9.-]+/g, "")
      ) || 0;
    dynamicTotalAmount =
      parseFloat(
        $(".total-amount")
          .text()
          .replace(/[^0-9.-]+/g, "")
      ) || 0;
    calculateChange();
  }

  // เรียก resetPaymentValues หลังกำหนด saleId
  resetPaymentValues();

  function calculateChange() {
    console.log("Calculating change, dynamicTotalAmount:", dynamicTotalAmount);
    const paid = parseFloat($("#sale_paid_amount").val()) || 0;
    const total = dynamicTotalAmount;
    let change = paid - total;

    if (change < 0) {
      $("#sale_change_amount").val("เงินไม่พอ");
      $("#print_receipt").prop("disabled", true);
      Toast.fire({
        icon: "warning",
        title: "จำนวนเงินไม่เพียงพอ",
      });
      return;
    } else {
      $("#sale_change_amount").val(change.toFixed(2));
      $("#print_receipt").prop("disabled", false);
    }

    if (!saleId) {
      console.error("sale_id is not defined");
      Swal.fire({
        icon: "error",
        title: "ข้อผิดพลาด",
        text: "ไม่พบรหัสการขาย (sale_id)",
      });
      return;
    }

    const requestData = {
      sale_id: saleId,
      sale_net_amount: dynamicNetAmount,
      sale_vat_amount: dynamicVatAmount,
      total_amount: total,
      sale_paid_amount: paid,
      sale_change_amount: change >= 0 ? change : 0,
    };
    console.log("Sending Ajax request with data:", requestData);

    $.ajax({
      url: "page/sale/api/api_update_payment.php",
      method: "POST",
      data: requestData,
      dataType: "json",
      success: function (response) {
        console.log("Payment details updated:", response);
      },
      error: function (xhr, status, error) {
        console.error(
          "Failed to update payment details:",
          xhr.status,
          xhr.responseText
        );
        Swal.fire({
          icon: "error",
          title: "ข้อผิดพลาด",
          text: `ไม่สามารถอัปเดตข้อมูลการชำระเงินได้: ${xhr.status} ${
            xhr.responseText || error
          }`,
        });
      },
    });
  }

  $("#calculate_change").on("click", function () {
    console.log("Calculate change button clicked");
    calculateChange();
  });
  $("#sale_paid_amount").off("input").on("input", calculateChange);
  $(document).on("productListUpdated", function (e, saleNetAmount) {
    const netAmount = parseFloat(saleNetAmount);
    if (isNaN(netAmount)) {
      console.warn("Invalid saleNetAmount:", saleNetAmount);
      dynamicNetAmount = 0;
    } else {
      dynamicNetAmount = netAmount;
    }
    dynamicVatAmount = dynamicNetAmount * 0.07;
    dynamicTotalAmount = dynamicNetAmount + dynamicVatAmount;
    $(".sale-net-amount").text(dynamicNetAmount.toFixed(2) + " บาท");
    $(".sale-vat-amount").text(dynamicVatAmount.toFixed(2) + " บาท");
    $(".total-amount").text(dynamicTotalAmount.toFixed(2) + " บาท");
    calculateChange();
    // ตรวจสอบสถานะปุ่ม
    const saleStatusId = parseInt($('input[name="sale_status_id"]').val()) || 2;
    if (saleStatusId === 1) {
      $("#calculate_change")
        .prop("disabled", true)
        .addClass("opacity-50 cursor-not-allowed");
    } else {
      $("#calculate_change")
        .prop("disabled", false)
        .removeClass("opacity-50 cursor-not-allowed");
    }
  });

  $(document).on("billSwitched", resetPaymentValues);

  if (!saleId) {
    console.error("sale_id is not defined");
    Swal.fire({
      icon: "error",
      title: "ข้อผิดพลาด",
      text: "ไม่พบรหัสการขาย (sale_id)",
    });
  }
});
