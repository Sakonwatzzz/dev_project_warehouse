$(document).on("click", ".btn-delete-stock", function () {
  let stock_check_id = $(this).data("stock-check-id");
  Swal.fire({
    title: "ยืนยันการลบ?",
    text: "รายการนี้จะถูกลบ",
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
            loadStockCheck();
          } else {
            Toast.fire({ icon: "error", title: response.message });
          }
        },
      });
    }
  });
});
