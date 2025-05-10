$(document).ready(function() {
  let dynamicTotalAmount = parseFloat($('.total-amount').text().replace(/[^0-9.-]+/g, '')) || 0;
  const saleId = $('input[name="sale_id"]').val();

  // Function to calculate change
  function calculateChange() {
      const paid = parseFloat($('#sale_paid_amount').val()) || 0;
      const total = dynamicTotalAmount;
      let change = paid - total;

      // Ensure change is not negative
      if (change < 0) {
          $('#sale_change_amount').val('เงินไม่พอ');
          $('#print_receipt').prop('disabled', true);
      } else {
          $('#sale_change_amount').val(change.toFixed(2));
          $('#print_receipt').prop('disabled', false);
      }

      // Check if saleId is available before making Ajax call
      if (!saleId) {
          console.error('sale_id is not defined');
          Swal.fire({
              icon: 'error',
              title: 'ข้อผิดพลาด',
              text: 'ไม่พบรหัสการขาย (sale_id)'
          });
          return;
      }

      // Log data being sent
      const requestData = {
          sale_id: saleId,
          total_amount: total,
          sale_paid_amount: paid,
          sale_change_amount: change >= 0 ? change : 0
      };
      console.log('Sending Ajax request with data:', requestData);

      // Update payment details to api_update_payment.php
      $.ajax({
          url: 'page/sale/api/api_update_payment.php',
          method: 'POST',
          data: requestData,
          dataType: 'json',
          success: function(response) {
              console.log('Payment details updated:', response);
          },
          error: function(xhr, status, error) {
              console.error('Failed to update payment details:', xhr.status, xhr.responseText);
              Swal.fire({
                  icon: 'error',
                  title: 'ข้อผิดพลาด',
                  text: `ไม่สามารถอัปเดตข้อมูลการชำระเงินได้: ${xhr.status} ${xhr.responseText || error}`
              });
          }
      });
  }

  // Event listeners
  $('#calculate_change').off('click').on('click', calculateChange);
  $('#sale_paid_amount').off('input').on('input', calculateChange);

  // Update total amount when product list changes
  $(document).on('productListUpdated', function(e, newTotal) {
      dynamicTotalAmount = parseFloat(newTotal) || 0;
      $('.total-amount').text(dynamicTotalAmount.toFixed(2) + ' บาท');
      calculateChange();
  });

  // Validate sale_id on page load
  if (!saleId) {
      console.error('sale_id is not defined');
      Swal.fire({
          icon: 'error',
          title: 'ข้อผิดพลาด',
          text: 'ไม่พบรหัสการขาย (sale_id)'
      });
  }
});