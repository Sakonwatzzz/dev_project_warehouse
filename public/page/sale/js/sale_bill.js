// public/page/sale/script/sale_bills.js

// ใช้ตัวแปรจาก window.saleBillsData
$(document).ready(function () {
  const Toast = Swal.mixin({
    toast: true,
    position: "top",
    showConfirmButton: false,
    timer: 1000,
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

  $(document).on("click", '[id^="close_tab_"]', function (e) {
    e.stopPropagation(); // ป้องกันการเรียก switchBill
    const saleId = $(this).attr("id").replace("close_tab_", "");
    Swal.fire({
      title: "ยืนยันการปิดบิล?",
      icon: "warning",
      showCancelButton: true,
      confirmButtonText: "ปิดบิล",
      cancelButtonText: "ยกเลิก",
    }).then((result) => {
      if (result.isConfirmed) {
        $.ajax({
          url: "page/sale/ajax/ajax_closebill.php",
          method: "POST",
          data: {
            action: "close_tab",
            sale_id: saleId,
          },
          dataType: "json",
          success: function (response) {
            if (response.status === "success") {
              Toast.fire({
                icon: "success",
                title: response.message,
              }).then(() => {
                window.location.href = response.redirect;
              });
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
              text: xhr.responseText || "ไม่สามารถปิดแท็บบิลได้",
            });
          },
        });
      }
    });
  });

  $("#cancel_sale").on("click", function () {
    const saleId = $('input[name="sale_id"]').val();
    if (!saleId) {
      Swal.fire({
        icon: "error",
        title: "ข้อผิดพลาด",
        text: "ไม่พบ sale_id กรุณาเปิดบิลใหม่",
      });
      return;
    }
    Swal.fire({
      title: "ยืนยันการยกเลิกการขาย?",
      icon: "warning",
      showCancelButton: true,
      confirmButtonText: "ยกเลิกการขาย",
      cancelButtonText: "ยกเลิก",
    }).then((result) => {
      if (result.isConfirmed) {
        $.ajax({
          url: "page/sale/ajax/ajax_cancel_sale.php",
          method: "POST",
          data: {
            action: "cancel_sale",
            sale_id: saleId,
          },
          dataType: "json",
          success: function (response) {
            if (response.status === "success") {
              Toast.fire({
                icon: "success",
                title: response.message,
              }).then(() => {
                window.location.href = response.redirect;
              });
            } else {
              Swal.fire({
                icon: "error",
                title: "ข้อผิดพลาด",
                text: response.message,
              });
            }
          },
          error: function (xhr) {
            console.error("Ajax error:", xhr.responseText);
            Swal.fire({
              icon: "error",
              title: "ข้อผิดพลาด",
              text: xhr.responseText || "ไม่สามารถยกเลิกบิลได้",
            });
          },
        });
      }
    });
  });
});
