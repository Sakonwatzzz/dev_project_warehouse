$(document).ready(function () {
  const Toast = Swal.mixin({
    /* คงเดิม */
  });
  $("#btn-submit").prop("disabled", true);
  let isProcessing = false;

  function changeInputBackgroud(event) {
    console.log("Key pressed:", event.key, "Code:", event.code);
    const product_id_input = document.getElementById("product_id_input");
    if (event.key === "Delete") {
      product_id_input.classList.remove(
        "bg-white",
        "bg-red-500",
        "bg-green-600"
      );
      product_id_input.classList.add("bg-white");
    }
    if (event.key === "Insert") {
      product_id_input.classList.remove("bg-white", "bg-green-600");
      product_id_input.classList.add("bg-red-500");
    }
    if (event.key === "+") {
      product_id_input.classList.remove("bg-white", "bg-red-500");
      product_id_input.classList.add("bg-green-600");
    }
  }

  function resetInputBackgroud() {
    $("#product_id_input")
      .removeClass("bg-red-500 bg-green-600")
      .addClass("bg-white");
  }

  $("#product_id_input")
    .on("keydown", changeInputBackgroud)
    .on("focus", resetInputBackgroud)
    .on("keypress", function (event) {
      if (event.key === "Enter") {
        event.preventDefault();
        if (!$("#btn-submit").prop("disabled")) {
          $("#form_insert_stock").submit();
        }
      }
    })
    .on("input", function () {
      let input = $(this).val().trim();
      if (input.includes("*")) {
        let [stock_check_id, stock_check_actual] = input.split("*");
        let isDelete = stock_check_id.startsWith("-");
        stock_check_id = stock_check_id.replace(/^-/, "").trim();
        $(this).val(stock_check_id);
        stock_check_actual = stock_check_actual.trim() || "0";
        if (isDelete) {
          stock_check_actual = `-${stock_check_actual}`;
        }
        $("#stock_check_actual").val(stock_check_actual);
        const product_id_input = document.getElementById("product_id_input");
        product_id_input.classList.remove(
          "bg-white",
          "bg-red-500",
          "bg-green-600"
        );
        product_id_input.classList.add(
          isDelete ? "bg-red-500" : "bg-green-600"
        );
      }
    });

  function validateForm() {
    const product_id = $("#product_id_input").val().trim();
    const warehouse_id = $("#warehouse_id").val().trim();
    const stock_check_actual = $("#stock_check_actual").val().trim();
    const stock_check_date = $("#stock-check-date").val().trim();
    const personnel_id = $("#personnel_id").val().trim() || "1";
    const isValid =
      product_id &&
      warehouse_id &&
      stock_check_actual &&
      stock_check_date &&
      personnel_id &&
      isNumeric(stock_check_actual);
    $("#btn-submit").prop("disabled", !isValid);
  }

  $(
    "#product_id_input, #warehouse_id, #stock_check_actual, #stock-check-date, #stock-check-remark"
  ).on("input change", validateForm);

  $("#form_insert_stock").on("submit", function (event) {
    event.preventDefault();
    if (isProcessing) return;
    isProcessing = true;
    $("#btn-submit")
      .find(".spinner")
      .removeClass("hidden")
      .end()
      .find(".text")
      .addClass("hidden");

    let formData = {
      action: "insert_check_product",
      product_id: $("#product_id_input").val().trim(),
      warehouse_id: $("#warehouse_id").val().trim(),
      stock_check_actual: $("#stock_check_actual").val().trim(),
      stock_check_date:
        $("#stock-check-date").val() ||
        luxon.DateTime.now()
          .setZone("Asia/Bangkok")
          .toFormat("yyyy-MM-dd HH:mm:ss"),
      stock_check_remark: $("#stock-check-remark").val().trim(),
      personnel_id: $("#personnel_id").val().trim() || "1",
    };

    if (formData.stock_check_date) {
      formData.stock_check_date = luxon.DateTime.fromFormat(
        formData.stock_check_date,
        "yyyy-MM-dd HH:mm"
      )
        .setZone("Asia/Bangkok")
        .toFormat("yyyy-MM-dd HH:mm:ss");
    }
 
    if (
      !formData.product_id ||
      !formData.warehouse_id ||
      !formData.stock_check_actual
    ) {
      Toast.fire({
        icon: "warning",
        title: "กรุณากรอก รหัสสินค้า, คลังสินค้า, และจำนวนที่นับได้จริง",
      });
      $("#btn-submit")
        .find(".spinner")
        .addClass("hidden")
        .end()
        .find(".text")
        .removeClass("hidden");
      isProcessing = false;
      return;
    }

    if (
      !/^\d+$/.test(formData.product_id) ||
      !/^-?\d+$/.test(formData.stock_check_actual)
    ) {
      Toast.fire({
        icon: "error",
        title: "รหัสสินค้าและจำนวนต้องเป็นตัวเลข",
      });
      $("#btn-submit")
        .find(".spinner")
        .addClass("hidden")
        .end()
        .find(".text")
        .removeClass("hidden");
      isProcessing = false;
      return;
    }

    $.ajax({
      url: "page/stock/api/get_product.php",
      type: "GET",
      data: {
        product_id: formData.product_id,
        warehouse_id: formData.warehouse_id,
      },
      // ... คงเดิม
    });
  });

  // ... loadStockCheck และส่วนอื่นๆ คงเดิม
});
