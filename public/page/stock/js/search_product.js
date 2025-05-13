$(document).ready(function () {
  $("#barcode_input").on("input", function () {
    const query = $(this).val();
    if (query.length < 2) {
      $("#barcode_suggestions").addClass("hidden").empty();
      $("#product_info").addClass("hidden").empty();
      return;
    }
    $.ajax({
      url: "page/stock/ajax/ajax_search_product.php",
      method: "GET",
      data: {
        query: query,
      },
      dataType: "json",
      success: function (response) {
        const products = response;
        const suggestions = $("#barcode_suggestions")
          .removeClass("hidden")
          .empty();
        if (products.length === 0) {
          suggestions.append(
            '<div class="p-2 text-gray-500">ไม่พบสินค้า</div>'
          );
          return;
        }
        products.forEach((product) => {
          suggestions.append(`
                <div class="p-2 hover:bg-gray-100 cursor-pointer" data-barcode="${product.product_id}">
                    ${product.product_id} - ${product.product_name} (สต็อก: ${product.total_stock})
                </div>
            `);
        });
        suggestions.find("div").on("click", function () {
          const barcode = $(this).data("barcode");
          $("#barcode_input").val(barcode);
          suggestions.addClass("hidden").empty();
          fetchProductInfo(barcode);
        });
      },
      error: function (xhr) {
        Swal.fire({
          icon: "error",
          title: "ข้อผิดพลาด",
          text: xhr.responseText || "ไม่สามารถค้นหาสินค้าได้",
          didOpen: () => {
            $(".main-body").removeAttr("aria-hidden");
          },
        });
      },
    });
  });
});
