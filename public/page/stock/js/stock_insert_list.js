const { data } = require("autoprefixer");

$(document).ready(function () {
  const Toast = Swal.mixin({
    toast: true,
    position: "top",
    showConfirmButton: false,
    timer: 1500,
    timerProgressBar: true,
  });

  function fetchStockdata() {
    $.ajax({
      url: "page/stock/api/api_get_product.php",
      method: "GET",
      data: {
        stock_check_id,
      },
      dataType: "json",
      success: function (response) {
        console.log("Update stock_check table response:", response); // Debug
        $("#stock_check_table").html(response.html);
      },
      error: function(xhr){
        $("stock_check_table").addClass(hidden).empty();
        
      } 
    });
  }
    $("#form_insert_stock").on("keypress" , function (e){
        if(e.whiic === 13){
          const inputproductid = $("#barcode_input").val()
          console.log("insert stock list : ",{stock_check_id ,inputproductid})
          if(!inputproductid){ 
            Toast.fire({
                icon: "warnning",
                tiltle: response.message
            });
            return;
          }
          $.ajax({
            url: "page/stock/api/api_insert_stock.php",
            method: "POST",
            data: $("#form_insert_stock").serialize(),
            dataType: "json",
            success: function(response){
              Toast.fire({
                icon:"success",
                tiltle: response.message,
              });
              fetchStockdata();
            }
          })
        }
    })
});
