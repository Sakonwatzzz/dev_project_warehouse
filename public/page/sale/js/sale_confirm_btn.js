$(document).ready(function () {
  // จัดการ submit ของ #confirm-sale-form เฉพาะเมื่อกดปุ่ม
  $("#confirm-sale-btn").on("click", function (e) {
    e.preventDefault();
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
              //  ซ่อนปุ่ม แก้ไข และ ลบ
              $(".edit-product").addClass("hidden");
              $(".delete-product").addClass("hidden");
              // $('#cancel_sale').addClass('hidden');

              // ปิดการใช้งานการเลือกสมาชิกและพนักงาน
              $('#member_search, #member_id, select[name="personnel_id"]').prop(
                "disabled",
                true
              );
              // ปิดการใช้งานปุ่มยืนยันการขาย
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
            },
          }).then(() => {
            window.location.href = response.redirect; // รีเฟรชหน้า
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
          title: "ข้อผิดพลาด",
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
});
