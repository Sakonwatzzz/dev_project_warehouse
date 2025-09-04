// การเรียกใช้งาน Ajax เพื่อสร้างบิลใหม่ (new sale) โดยทำงานร่วมกับไฟล์ ajax/ajax_new_sale.php 
// ซึ่งเรียกผ่านปุ่ม #new_sale และใช้ SweetAlert (Swal.fire) สำหรับแจ้งเตือนผลลัพธ์


//รอให้ DOM โหลดเสร็จ
$(document).ready(function () {  

    //เมื่อคลิกปุ่มที่มี id new_sale
    $('#new_sale').on('click', function () {
        //ส่ง request แบบ POST ไปยัง ajax/ajax_new_sale.php พร้อม action=new_sale
        $.ajax({
            url: 'page/sale/ajax/ajax_new_sale.php',
            method: 'POST',
            data: { action: 'new_sale' },
            dataType: 'json',
            success: function (response) {
                //แสดงข้อความสำเร็จ หรือ ผิดพลาดด้วย SweetAlert
                if (response.status === 'success') {
                    Swal.fire({
                        toast: true,
                        icon: 'success',
                        title: response.message,
                        showConfirmButton: false,
                        position:'top-end',
                        timer: 1000
                    })
                    .then(() => {
                        //ถ้าสำเร็จให้เปลี่ยนหน้าไปยัง URL ที่ส่งกลับมา
                        window.location.href = response.redirect;
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'ข้อผิดพลาด',
                        text: response.message
                    });
                }
            },
            error: function (xhr) {
                Swal.fire({
                    icon: 'error',
                    title: 'ข้อผิดพลาด',
                    text: xhr.responseText || 'ไม่สามารถสร้างบิลใหม่ได้'
                });
            }
        });
    });
});