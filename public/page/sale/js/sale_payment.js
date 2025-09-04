// sale_payment.js
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

  // ดึง saleId จาก DOM
  const saleId = $('input[name="sale_id"]').val() || "";

  if (!saleId) {
    console.error("sale_id is not defined");
    Swal.fire({
      icon: "error",
      title: "ข้อผิดพลาด",
      text: "ไม่พบรหัสการขาย (sale_id)",
    });
    return;
  }

  function resetPaymentValues() {
    if (!$(".sale-net-amount").length) {
      console.warn("DOM element .sale-net-amount not found");
      return;
    }
    // ใช้ parseFloat และลบตัวอักษรที่ไม่ใช่ตัวเลข
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
      ) || 0; // แก้ .sale_total-amount เป็น .total-amount
    console.log("resetPaymentValues:", {
      dynamicNetAmount,
      dynamicVatAmount,
      dynamicTotalAmount,
    });
    calculateChange();
  }

  function calculateChange() {
    console.log("Calculating change, dynamicTotalAmount:", dynamicTotalAmount);
    const paid = parseFloat($("#sale_paid_amount").val()) || 0; // แก้ $sale_paid_amount เป็น $('#sale_paid_amount')
    const total = dynamicTotalAmount;
    let change = paid - total;

    if (change < 0) {
      $("#sale_change_amount").val("เงินไม่พอ");
      $("#print_receipt").prop("disabled", true);
      // Toast.fire({
      //   icon: "warning",
      //   title: "จำนวนเงินไม่เพียงพอ",
      // });
      return;
    } else {
      $("#sale_change_amount").val(change.toFixed(2));
      $("#print_receipt").prop("disabled", false);
    }

    const requestData = {
      sale_id: saleId,
      sale_net_amount: dynamicNetAmount,
      sale_vat_amount: dynamicVatAmount,
      sale_total_amount: dynamicTotalAmount,
      sale_paid_amount: paid,
      sale_change_amount: change >= 0 ? change : 0,
    };
    console.log("Sending AJAX request with data:", requestData);

    $.ajax({
      url: "page/sale/api/api_update_payment.php",
      method: "POST",
      data: requestData,
      dataType: "json",
      success: function (response) {
        console.log("Payment details updated:", response);
        if (response.status === "success") {
          if (
            response.updated_total &&
            response.updated_total !== dynamicTotalAmount
          ) {
            dynamicTotalAmount = parseFloat(response.updated_total);
            $(".total-amount").text(dynamicTotalAmount.toFixed(2) + " บาท");
            calculateChange(); // Recalculate ถ้ามี override
            Toast.fire({
              icon: "info",
              title: "ยอดรวมถูกปรับให้ตรงกับฐานข้อมูล",
            });
          }
        } else {
          Swal.fire({
            icon: "error",
            title: "ข้อผิดพลาด",
            text: response.message,
          });
        }
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
        setTimeout(calculateChange, 1000);
      },
    });
  }

  $("#calculate_change").on("click", function () {
    console.log("Calculate change button clicked");
    calculateChange();
  });

  $("#sale_paid_amount").off("input").on("input", calculateChange);

  $(document).on("productListUpdated", function (event, saleNetAmount) {
    console.log(
      "productListUpdated triggered with saleNetAmount:",
      saleNetAmount
    );
    const netAmount = parseFloat(saleNetAmount) || 0;
    if (isNaN(netAmount)) {
      console.warn("Invalid saleNetAmount:", saleNetAmount);
      Swal.fire({
        icon: "warning",
        title: "ข้อผิดพลาด",
        text: "ยอดสุทธิไม่ถูกต้อง กรุณาตรวจสอบข้อมูล",
      });
      return;
    }

    // อัปเดตตัวแปร global จาก saleNetAmount ที่ได้รับ
    dynamicNetAmount = parseFloat(netAmount.toFixed(2));
    dynamicVatAmount = parseFloat((dynamicNetAmount * 0.07).toFixed(2));
    dynamicTotalAmount = parseFloat(
      (dynamicNetAmount + dynamicVatAmount).toFixed(2)
    );

    // อัปเดต DOM
    $(".sale-net-amount").text(dynamicNetAmount.toFixed(2) + " บาท");
    $(".sale-vat-amount").text(dynamicVatAmount.toFixed(2) + " บาท");
    $(".total-amount").text(dynamicTotalAmount.toFixed(2) + " บาท");

    console.log("Updated dynamic values:", {
      dynamicNetAmount,
      dynamicVatAmount,
      dynamicTotalAmount,
    });

    // เรียก calculateChange เพื่ออัปเดตเงินทอน
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

  // $(document).on("billSwitched", resetPaymentValues);
  $(document).on("billSwitched", function () {
    resetPaymentValues();
    $.ajax({
      url: "page/sale/api/api_get_sale.php",
      method: "GET",
      data: { sale_id: $('input[name="sale_id"]').val() },
      dataType: "json",
      success: function (response) {
        if (response.status === "success") {
          dynamicNetAmount = parseFloat(response.sale_net_amount) || 0;
          dynamicVatAmount = parseFloat(response.sale_vat_amount) || 0;
          dynamicTotalAmount = parseFloat(response.sale_total_amount) || 0;
          $(".sale-net-amount").text(dynamicNetAmount.toFixed(2) + " บาท");
          $(".sale-vat-amount").text(dynamicVatAmount.toFixed(2) + " บาท");
          $(".total-amount").text(dynamicTotalAmount.toFixed(2) + " บาท");
          $("#sale_paid_amount").val(response.sale_paid_amount || "");
          $("#sale_change_amount").val(response.sale_change_amount || "");
          calculateChange();
        } else {
          Swal.fire({
            icon: "error",
            title: "ข้อผิดพลาด",
            text: response.message,
          });
        }
      },
      error: function (xhr) {
        Swal.fire({
          icon: "error",
          title: "ข้อผิดพลาด",
          text:
            "ไม่สามารถโหลดข้อมูลบิล: " + (xhr.responseText || "เกิดข้อผิดพลาด"),
        });
      },
    });
  });

  // Initial check
  resetPaymentValues();
});
