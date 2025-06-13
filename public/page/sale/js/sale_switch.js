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
  let currentSaleId = "<?php echo htmlspecialchars($sale_id); ?>";

  function updateTabStyles() {
    $('[id^="tab_"]').each(function () {
      const tabSaleId = $(this).attr("id").replace("tab_", "");
      $(this)
        .toggleClass("tab-active", tabSaleId === currentSaleId)
        .toggleClass("tab-inactive", tabSaleId !== currentSaleId);
    });
  }

  window.switchBill = function (saleId) {
    if (saleId === currentSaleId) return;
    $.ajax({
      url: "page/sale/ajax/ajax_switch_sale.php",
      method: "POST",
      data: { action: "switch_sale", sale_id: saleId },
      dataType: "json",
      beforeSend: function () {
        console.log(
          "Sending AJAX request to page/sale/ajax/ajax_switch_sale.php for sale_id: " +
            saleId
        );
      },
      success: function (response) {
        console.log("Switch sale response:", response);
        if (response.status === "success") {
          currentSaleId = saleId;
          updateTabStyles();
          window.location.href = response.redirect; // บังคับ redirect
          // Swal.fire({
          //   icon: "success",
          //   title: "สำเร็จ",
          //   text: response.message,
          //   confirmButtonText: "ตกลง",
          // });
        } else {
          Swal.fire({
            icon: "error",
            title: "ข้อผิดพลาด",
            text: response.message,
            confirmButtonText: "ตกลง",
          });
        }
      },
      error: function (xhr, status, error) {
        console.log("Switch sale error:", xhr.responseText);
        Swal.fire({
          icon: "error",
          title: "ข้อผิดพลาด",
          text: "เกิดข้อผิดพลาดในการสลับบิล: " + error,
          confirmButtonText: "ตกลง",
        });
      },
    });
  };
});
