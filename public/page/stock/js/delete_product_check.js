$(document).on("click", ".btn-delete-stock", function () {
  let stock_check_id = $(this).data("stock-check-id");
  const Toast = Swal.mixin({
    toast: true,
    position: "top-end",
    showConfirmButton: false,
    timer: 1500,
    timerProgressBar: true,
    customClass: {
      popup: "small-toast",
    },
    didOpen: (toast) => {
      toast.addEventListener("mouseenter", Swal.stopTimer);
      toast.addEventListener("mouseleave", Swal.resumeTimer);
    },
  });
  Swal.fire({
    title: "ยืนยันการลบ?",
    text: "เซสชันและรายการทั้งหมดจะถูกลบ",
    icon: "warning",
    showCancelButton: true,
    confirmButtonText: "ลบ",
    cancelButtonText: "ยกเลิก",
  }).then((result) => {
    if (result.isConfirmed) {
      $.ajax({
        url: "page/stock/api/delete_stock.php",
        type: "POST",
        data: { stock_check_id: stock_check_id },
        dataType: "json",
        success: function (response) {
          if (response.status === "success") {
            Toast.fire({ icon: "success", title: response.message });
            if (parseInt(stock_check_id) === parseInt(currentStockId)) {
              currentStockId = null;
              $("#current_stock_id").val("");
            }
            loadStockCheck();
          } else {
            Toast.fire({ icon: "error", title: response.message });
          }
        },
      });
    }
  });
});
