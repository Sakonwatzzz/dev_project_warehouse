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
    },
  });
  // Initialize Flatpickr for date range
  flatpickr("#date_range", {
    mode: "range",
    dateFormat: "d/m/Y",
    time_24hr: true,
  });
  flatpickr("#stock-check-date", {
    enableTime: true,
    dateFormat: "Y-m-d\\TH:i",
    time_24hr: true,
    defaultDate: new Date(),
  });
  // Toggle Modal
  let isProcessing = false;
  $("#btn-new-stock-check").click(function () {
    if (isProcessing) return;
    $("#new-stock-check-modal").removeClass("hidden");
  });
  $(".close-modal").click(function () {
    $("#new-stock-check-modal").addClass("hidden");
    $("#new-stock-check-form")[0].reset();
    $("#product-list").empty();
  });
  // Load products when warehouse is selected
  $("#modal-warehouse").change(function () {
    const warehouse_id = $(this).val();
    if (warehouse_id) {
      loadProducts(warehouse_id);
    } else {
      $("#product-list").html(
        '<tr><td colspan="6" class="px-3 py-2 text-center text-sm text-gray-500">กรุณาเลือกคลังสินค้า</td></tr>'
      );
    }
  });
  // Filter products
  $("#btn-filter-product").click(function () {
    const warehouse_id = $("#modal-warehouse").val();
    const query = $("#product-filter").val();
    if (warehouse_id) {
      loadProducts(warehouse_id, query);
    }
  });
  // Select all products
  $("#select-all-products").change(function () {
    $('#product-list input[type="checkbox"]').prop("checked", this.checked);
  });
  $("#select-all-list").change(function () {
    $('#active-stock-check input[type="checkbox"]');
  });
  // Load products via Ajax
  function loadProducts(warehouse_id, query = "") {
    $.ajax({
      url: "page/stock/api/api_search_product.php",
      method: "GET",
      data: { warehouse_id: warehouse_id, query: query },
      dataType: "json",
      success: function (products) {
        const tbody = $("#product-list").empty();
        if (products.length === 0) {
          tbody.append(
            '<tr><td colspan="6" class="px-3 py-2 text-center text-sm text-gray-500">ไม่พบสินค้า</td></tr>'
          );
          return;
        }
        products.forEach((product) => {
          tbody.append(`
                          <tr>
                              <td class="px-3 py-2"><input type="checkbox" class="product-select rounded text-blue-500 focus:ring-blue-400" data-product-id="${
                                product.product_id
                              }" data-lot-id="${
            product.lot_id
          }" data-lot-product-id="${
            product.lot_product_id
          }" data-system-quantity="${product.lot_product_remain}"></td>
                              <td class="px-3 py-2">${product.product_id}</td>
                              <td class="px-3 py-2">${product.product_name}</td>
                              <td class="px-3 py-2">${
                                product.lot_name || "N/A"
                              }</td>
                              <td class="px-3 py-2 text-right">${
                                product.lot_product_remain
                              }</td>
                              <td class="px-3 py-2 text-right"><input type="number" class="actual-quantity w-20 border rounded px-2 py-1" min="0" value="${
                                product.lot_product_remain
                              }"></td>
                          </tr>
                      `);
        });
      },
      error: function () {
        $("#product-list").html(
          '<tr><td colspan="6" class="px-3 py-2 text-center text-sm text-gray-500">เกิดข้อผิดพลาดในการโหลดสินค้า</td></tr>'
        );
      },
    });
  }
  // Create stock check
  $("#btn-create-stock-check").click(function () {
    if (isProcessing) return;
    isProcessing = true;
    $("#btn-create-stock-check").prop("disabled", true);
    const warehouse_id = $("#modal-warehouse").val();
    const stock_check_date = $("#stock-check-date").val();
    const stock_check_remark = $("#stock-check-remark").val();
    const personnel_id = 1; // Replace with actual logged-in personnel_id
    const products = [];
    $("#product-list .product-select:checked").each(function () {
      const row = $(this).closest("tr");
      const stock_check_actual = parseFloat(row.find(".actual-quantity").val());
      if (isNaN(stock_check_actual) || stock_check_actual < 0) {
        Swal.fire({
          icon: "warning",
          title: "ข้อผิดพลาด",
          text: `จำนวนจริงของ ${$(this).data(
            "product-id"
          )} ต้องเป็นตัวเลขและไม่ติดลบ`,
        });
        isProcessing = false;
        $("#btn-create-stock-check").prop("disabled", false);
        return false;
      }
      products.push({
        product_id: $(this).data("product-id"),
        lot_id: $(this).data("lot-id"),
        lot_product_id: $(this).data("lot-product-id"),
        stock_check_system: parseFloat($(this).data("system-quantity")),
        stock_check_actual: stock_check_actual,
      });
    });
    $("#stock_check_table .stock-list-select:checked").each(function () {
      const stockrow = $(this).closest("tr");
    });
    if (!warehouse_id || !stock_check_date || products.length === 0) {
      Swal.fire({
        icon: "warning",
        title: "ข้อผิดพลาด",
        text: "กรุณากรอกข้อมูลให้ครบถ้วนและเลือกสินค้าอย่างน้อย 1 รายการ",
      });
      isProcessing = false;
      $("#btn-create-stock-check").prop("disabled", false);
      return;
    }
    $.ajax({
      url: "page/stock/api/insert_stock.php",
      method: "POST",
      data: {
        action: "insert_check_products",
        warehouse_id: warehouse_id,
        stock_check_date: stock_check_date,
        stock_check_remark: stock_check_remark,
        personnel_id: personnel_id,
        products: JSON.stringify(products),
      },
      dataType: "json",
      success: function (response) {
        if (response.status === "success") {
          Toast.fire({
            icon: "success",
            title: "บันทึกการตรวจสอบสต็อกสำเร็จ",
          });
          $("#new-stock-check-modal").addClass("hidden");
          $("#new-stock-check-form")[0].reset();
          $("#product-list").empty();
          updateStockTable("active");
        } else {
          Swal.fire({
            icon: "error",
            title: "ข้อผิดพลาด",
            text: response.message || "ไม่สามารถบันทึกข้อมูล",
          });
        }
      },
      error: function () {
        Swal.fire({
          icon: "error",
          title: "ข้อผิดพลาด",
          text: "เกิดข้อผิดพลาดในการบันทึกข้อมูล",
        });
      },
      complete: function () {
        isProcessing = false;
        $("#btn-create-stock-check").prop("disabled", false);
      },
    });
  });
  // Tab switching
  $("#active-tab-btn").click(function () {
    $("#active-tab").removeClass("hidden");
    // $('#history-tab').addClass('hidden');
    $(this)
      .addClass("bg-blue-500 text-white")
      .removeClass("bg-gray-200 text-gray-700");
    // $('#history-tab-btn').addClass('bg-gray-200 text-gray-700').removeClass('bg-blue-500 text-white');
    updateStockTable("active");
  });
  // $('#history-tab-btn').click(function () {
  //     $('#history-tab').removeClass('hidden');
  //     $('#active-tab').addClass('hidden');
  //     $(this).addClass('bg-blue-500 text-white').removeClass('bg-gray-200 text-gray-700');
  //     $('#active-tab-btn').addClass('bg-gray-200 text-gray-700').removeClass('bg-blue-500 text-white');
  //     updateStockTable('history');
  // });
  // Search and update table
  $("#btn-search").click(function () {
    updateStockTable(
      $("#active-tab").hasClass("hidden") ? "history" : "active"
    );
  });
  function updateStockTable(tab) {
    const warehouse_id = $("#warehouse_id").val();
    const product_id = $("#product_id").val();
    const status = $("#status").val();
    const date_range = $("#date_range").val();
    const isHistory = tab === "history";
    $.ajax({
      url: "page/stock/api/api_stock_list.php",
      method: "GET",
      data: { warehouse_id, product_id, status, date_range, tab },
      dataType: "json",
      success: function (data) {
        const tbody = $(`#${tab}-stock-checks`).empty();
        if (!Array.isArray(data)) {
          tbody.append(
            `<tr><td colspan="${
              isHistory ? 8 : 7
            }" class="px-6 py-4 text-center text-sm text-gray-500">ไม่มีข้อมูลหรือรูปแบบข้อมูลไม่ถูกต้อง</td></tr>`
          );
          console.error("Response is not an array:", data);
          return;
        }
        if (data.length === 0) {
          tbody.append(
            `<tr><td colspan="${
              isHistory ? 8 : 7
            }" class="px-6 py-4 text-center text-sm text-gray-500">ไม่มีข้อมูล</td></tr>`
          );
          return;
        }
        data.forEach((item) => {
          console.log("Rendering iten :", item);
          const row = `
                      <tr>
                          <td class="px-3 py-2">
                              <input type="checkbox" class="stock-check-select rounded text-blue-500 focus:ring-blue-400"
                              data-stock-check-id="${item.stock_check_id}">
                          </td>
                          <td class="px-6 py-4">${item.stock_check_id}</td>
                          <td class="px-6 py-4">${item.stock_check_date}</td>
                          <td class="px-6 py-4">${item.warehouse_name}</td>
                          <td class="px-6 py-4">${item.product_id}</td>
                          <td class="px-6 py-4">${item.stock_check_system}</td>
                          <td class="px-6 py-4">${item.stock_check_actual}</td>
                          <td class="px-6 py-4">${item.item_count}</td>
                          <td class="px-6 py-4">${item.personnel_name}</td>
                          <td class="px-6 py-4">${item.check_status_name}</td>
                          <td class="px-6 py-4">${
                            item.stock_check_remark || ""
                          }</td>
                      </tr>`;
          tbody.append(row);
        });
      },
      error: function () {
        Swal.fire({
          icon: "error",
          title: "ข้อผิดพลาด",
          text: "ไม่สามารถโหลดข้อมูลตารางได้",
        });
      },
    });
  }
  // Update current time
  setInterval(() => {
    $("#current-time").text(
      luxon.DateTime.local()
        .setZone("Asia/Bangkok")
        .toFormat("dd/MM/yyyy HH:mm:ss")
    );
  }, 1000);
});

// <script>
//     const Toast = Swal.mixin({
//         toast: true,
//         position: "top",
//         showConfirmButton: false,
//         timer: 1500,
//         timerProgressBar: true,
//         didOpen: (toast) => {
//             toast.addEventListener("mouseenter", Swal.stopTimer);
//             toast.addEventListener("mouseleave", Swal.resumeTimer);
//         }
//     });

//     // Initialize Flatpickr for date range
//     flatpickr("#date_range", {
//         mode: "range",
//         dateFormat: "d/m/Y",
//         time_24hr: true
//     });

//     flatpickr("#stock-check-date", {
//         enableTime: true,
//         dateFormat: "Y-m-d\\TH:i",
//         time_24hr: true,
//         defaultDate: new Date()
//     });

//     // Toggle Modal
//     let isProcessing = false;
//     $('#btn-new-stock-check').click(function () {
//         if (isProcessing) return;
//         $('#new-stock-check-modal').removeClass('hidden');
//     });

//     $('.close-modal').click(function () {
//         $('#new-stock-check-modal').addClass('hidden');
//         $('#new-stock-check-form')[0].reset();
//         $('#product-list').empty();
//     });

//     // Load products when warehouse is selected
//     $('#modal-warehouse').change(function () {
//         const warehouse_id = $(this).val();
//         if (warehouse_id) {
//             loadProducts(warehouse_id);
//         } else {
//             $('#product-list').html('<tr><td colspan="6" class="px-3 py-2 text-center text-sm text-gray-500">กรุณาเลือกคลังสินค้า</td></tr>');
//         }
//     });

//     // Filter products
//     $('#btn-filter-product').click(function () {
//         const warehouse_id = $('#modal-warehouse').val();
//         const query = $('#product-filter').val();
//         if (warehouse_id) {
//             loadProducts(warehouse_id, query);
//         }
//     });

//     // Select all products
//     $('#select-all-products').change(function () {
//         $('#product-list input[type="checkbox"]').prop('checked', this.checked);
//     });

//     $('#select-all-list').change(function () {
//         $('#active-stock-check input[type="checkbox"]');
//     });

//     // Load products via Ajax
//     function loadProducts(warehouse_id, query = '') {
//         $.ajax({
//             url: 'page/stock/api/api_search_product.php',
//             method: 'GET',
//             data: { warehouse_id: warehouse_id, query: query },
//             dataType: 'json',
//             success: function (products) {
//                 const tbody = $('#product-list').empty();
//                 if (products.length === 0) {
//                     tbody.append('<tr><td colspan="6" class="px-3 py-2 text-center text-sm text-gray-500">ไม่พบสินค้า</td></tr>');
//                     return;
//                 }
//                 products.forEach(product => {
//                     tbody.append(`
//                         <tr>
//                             <td class="px-3 py-2"><input type="checkbox" class="product-select rounded text-blue-500 focus:ring-blue-400" data-product-id="${product.product_id}" data-lot-id="${product.lot_id}" data-lot-product-id="${product.lot_product_id}" data-system-quantity="${product.lot_product_remain}"></td>
//                             <td class="px-3 py-2">${product.product_id}</td>
//                             <td class="px-3 py-2">${product.product_name}</td>
//                             <td class="px-3 py-2">${product.lot_name || 'N/A'}</td>
//                             <td class="px-3 py-2 text-right">${product.lot_product_remain}</td>
//                             <td class="px-3 py-2 text-right"><input type="number" class="actual-quantity w-20 border rounded px-2 py-1" min="0" value="${product.lot_product_remain}"></td>
//                         </tr>
//                     `);
//                 });
//             },
//             error: function () {
//                 $('#product-list').html('<tr><td colspan="6" class="px-3 py-2 text-center text-sm text-gray-500">เกิดข้อผิดพลาดในการโหลดสินค้า</td></tr>');
//             }
//         });
//     }

//     // Create stock check
//     $('#btn-create-stock-check').click(function () {
//         if (isProcessing) return;
//         isProcessing = true;
//         $('#btn-create-stock-check').prop('disabled', true);

//         const warehouse_id = $('#modal-warehouse').val();
//         const stock_check_date = $('#stock-check-date').val();
//         const stock_check_remark = $('#stock-check-remark').val();
//         const personnel_id = 1; // Replace with actual logged-in personnel_id
//         const products = [];

//         $('#product-list .product-select:checked').each(function () {
//             const row = $(this).closest('tr');
//             const stock_check_actual = parseFloat(row.find('.actual-quantity').val());
//             if (isNaN(stock_check_actual) || stock_check_actual < 0) {
//                 Swal.fire({
//                     icon: 'warning',
//                     title: 'ข้อผิดพลาด',
//                     text: `จำนวนจริงของ ${$(this).data('product-id')} ต้องเป็นตัวเลขและไม่ติดลบ`
//                 });
//                 isProcessing = false;
//                 $('#btn-create-stock-check').prop('disabled', false);
//                 return false;
//             }
//             products.push({
//                 product_id: $(this).data('product-id'),
//                 lot_id: $(this).data('lot-id'),
//                 lot_product_id: $(this).data('lot-product-id'),
//                 stock_check_system: parseFloat($(this).data('system-quantity')),
//                 stock_check_actual: stock_check_actual
//             });
//         });
//         $('#stock_check_table .stock-list-select:checked').each(function () {
//             const stockrow = $(this).closest('tr');

//         });

//         if (!warehouse_id || !stock_check_date || products.length === 0) {
//             Swal.fire({
//                 icon: 'warning',
//                 title: 'ข้อผิดพลาด',
//                 text: 'กรุณากรอกข้อมูลให้ครบถ้วนและเลือกสินค้าอย่างน้อย 1 รายการ'
//             });
//             isProcessing = false;
//             $('#btn-create-stock-check').prop('disabled', false);
//             return;
//         }

//         $.ajax({
//             url: 'page/stock/api/insert_stock.php',
//             method: 'POST',
//             data: {
//                 action: 'insert_check_products',
//                 warehouse_id: warehouse_id,
//                 stock_check_date: stock_check_date,
//                 stock_check_remark: stock_check_remark,
//                 personnel_id: personnel_id,
//                 products: JSON.stringify(products)
//             },
//             dataType: 'json',
//             success: function (response) {
//                 if (response.status === 'success') {
//                     Toast.fire({
//                         icon: 'success',
//                         title: 'บันทึกการตรวจสอบสต็อกสำเร็จ'
//                     });
//                     $('#new-stock-check-modal').addClass('hidden');
//                     $('#new-stock-check-form')[0].reset();
//                     $('#product-list').empty();
//                     updateStockTable('active');
//                 } else {
//                     Swal.fire({
//                         icon: 'error',
//                         title: 'ข้อผิดพลาด',
//                         text: response.message || 'ไม่สามารถบันทึกข้อมูล'
//                     });
//                 }
//             },
//             error: function () {
//                 Swal.fire({
//                     icon: 'error',
//                     title: 'ข้อผิดพลาด',
//                     text: 'เกิดข้อผิดพลาดในการบันทึกข้อมูล'
//                 });
//             },
//             complete: function () {
//                 isProcessing = false;
//                 $('#btn-create-stock-check').prop('disabled', false);
//             }
//         });
//     });

//     // Tab switching
//     $('#active-tab-btn').click(function () {
//         $('#active-tab').removeClass('hidden');
//         // $('#history-tab').addClass('hidden');
//         $(this).addClass('bg-blue-500 text-white').removeClass('bg-gray-200 text-gray-700');
//         // $('#history-tab-btn').addClass('bg-gray-200 text-gray-700').removeClass('bg-blue-500 text-white');
//         updateStockTable('active');
//     });

//     // $('#history-tab-btn').click(function () {
//     //     $('#history-tab').removeClass('hidden');
//     //     $('#active-tab').addClass('hidden');
//     //     $(this).addClass('bg-blue-500 text-white').removeClass('bg-gray-200 text-gray-700');
//     //     $('#active-tab-btn').addClass('bg-gray-200 text-gray-700').removeClass('bg-blue-500 text-white');
//     //     updateStockTable('history');
//     // });

//     // Search and update table
//     $('#btn-search').click(function () {
//         updateStockTable($('#active-tab').hasClass('hidden') ? 'history' : 'active');
//     });

//     // function updateStockTable(tab) {
//     //     const warehouse_id = $('#warehouse_id').val();
//     //     const product_id = $('#product_id').val();
//     //     const status = $('#status').val();
//     //     const date_range = $('#date_range').val();
//     //     const isHistory = tab === 'history';
//     //     $.ajax({
//     //         url: 'page/stock/api/api_stock_list.php',
//     //         method: 'GET',
//     //         data: { warehouse_id, product_id, status, date_range, tab },
//     //         dataType: 'json',
//     //         success: function (data) {
//     //             const tbody = $(`#${tab}-stock-checks`).empty();
//     //             if (!Array.isArray(data)) {
//     //                 tbody.append(`<tr><td colspan="${isHistory ? 8 : 7}" class="px-6 py-4 text-center text-sm text-gray-500">ไม่มีข้อมูลหรือรูปแบบข้อมูลไม่ถูกต้อง</td></tr>`);
//     //                 console.error('Response is not an array:', data);
//     //                 return;
//     //             }
//     //             if (data.length === 0) {
//     //                 tbody.append(`<tr><td colspan="${isHistory ? 8 : 7}" class="px-6 py-4 text-center text-sm text-gray-500">ไม่มีข้อมูล</td></tr>`);
//     //                 return;
//     //             }
//     //             data.forEach(item => {
//     //                 console.log('Rendering iten :' , item)
//     //                 const row = `
//     //                     <tr>
//     //                         <td class="px-3 py-2">
//     //                             <input type="checkbox" class="stock-check-select rounded text-blue-500 focus:ring-blue-400"
//     //                             data-stock-check-id="${item.stock_check_id}">
//     //                         </td>
//     //                         <td class="px-6 py-4">${item.stock_check_id}</td>
//     //                         <td class="px-6 py-4">${item.stock_check_date}</td>
//     //                         <td class="px-6 py-4">${item.warehouse_name}</td>
//     //                         <td class="px-6 py-4">${item.product_id}</td>
//     //                         <td class="px-6 py-4">${item.stock_check_system}</td>
//     //                         <td class="px-6 py-4">${item.stock_check_actual}</td>
//     //                         <td class="px-6 py-4">${item.item_count}</td>
//     //                         <td class="px-6 py-4">${item.personnel_name}</td>
//     //                         <td class="px-6 py-4">${item.check_status_name}</td>
//     //                         <td class="px-6 py-4">${item.stock_check_remark || ''}</td>

//     //                     </tr>`;
//     //                 tbody.append(row);
//     //             });
//     //         },
//     //         error: function () {
//     //             Swal.fire({
//     //                 icon: 'error',
//     //                 title: 'ข้อผิดพลาด',
//     //                 text: 'ไม่สามารถโหลดข้อมูลตารางได้'
//     //             });
//     //         }
//     //     });
//     // }

//     // Update current time
//     setInterval(() => {
//         $('#current-time').text(luxon.DateTime.local().setZone('Asia/Bangkok').toFormat('dd/MM/yyyy HH:mm:ss'));
//     }, 1000);
// </script>
