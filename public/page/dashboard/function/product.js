// ประกาศตัวแปรเก็บ instance กราฟ
let salesChart = null;
let topProductsChartInstance = null;

const salesCtx = document.getElementById('salesChart').getContext('2d');

function initializeSalesChart(labels, data, timeframe) {
    if (salesChart) {
        salesChart.destroy();
    }
    salesChart = new Chart(salesCtx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'ยอดขาย (บาท)',
                data: data,
                fill: true,
                backgroundColor: 'rgba(54, 162, 235, 0.2)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 2,
                tension: 0.3,
                pointBackgroundColor: 'rgba(54, 162, 235, 1)'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                title: {
                    display: true,
                    text: timeframe === 'daily' ? 'ยอดขายรายวัน' :
                        timeframe === 'weekly' ? 'ยอดขายรายสัปดาห์' :
                            timeframe === 'monthly' ? 'ยอดขายรายเดือน' :
                                'ยอดขายรายปี'
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'ยอดขาย (บาท)'
                    },
                    ticks: {
                        callback: function (value) {
                            return value.toLocaleString('th-TH');
                        }
                    }
                }
            }
        }
    });
}

function renderTopProductsChart(topProducts) {
    const canvas = document.getElementById('topProductsChart');
    if (!canvas) return;

    const ctx = canvas.getContext('2d');

    if (topProductsChartInstance) {
        topProductsChartInstance.destroy();
    }

    // ตรวจสอบว่ามีข้อมูลหรือไม่
    if (!topProducts || topProducts.length === 0) {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        ctx.font = '16px Arial';
        ctx.fillStyle = '#666';
        ctx.textAlign = 'center';
        ctx.fillText('ไม่มีข้อมูลสินค้าขายดี', canvas.width / 2, canvas.height / 2);
        return;
    }

    topProductsChartInstance = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: topProducts.map(p => p.product_name),
            datasets: [{
                data: topProducts.map(p => parseInt(p.total_qty)),
                backgroundColor: [
                    '#36A2EB', '#FF6384', '#FFCE56', '#4BC0C0', '#9966FF'
                ],
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right', // เปลี่ยนเป็น right เพื่อความสมดุล
                    labels: {
                        padding: 15,
                        usePointStyle: true,
                        font: {
                            family: 'Arial',
                            size: 12
                        }
                    }
                },
                title: {
                    display: true,
                    text: 'Top 5 สินค้าขายดี (จำนวนขาย)',
                    font: {
                        size: 14,
                        weight: 'bold'
                    },
                    padding: {
                        top: 10,
                        bottom: 20
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function (context) {
                            const label = context.label || '';
                            const value = context.parsed;
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = ((value / total) * 100).toFixed(1);
                            return `${label}: ${value} ชิ้น (${percentage}%)`;
                        }
                    }
                }
            }
        }
    });
}

function loadCounts(timeframe = 'monthly') {
    fetch(`/page/dashboard/ajax/product.php?timeframe=${timeframe}`)
        .then(res => {
            if (!res.ok) throw new Error('การตอบสนองเครือข่ายไม่ปกติ');
            return res.json();
        })
        .then(data => {
            // อัปเดตการ์ดสถิติ
            document.getElementById('totalCountDisplay').textContent = data.total_count || 0;
            document.getElementById('totalCountLotDisplay').textContent = data.total_count_lot || 0;
            document.getElementById('totalCountMemberDisplay').textContent = data.total_count_member || 0;
            document.getElementById('totalCountSaleTodayDisplay').textContent =
                (data.total_sale_today && data.total_sale_today > 0)
                    ? data.total_sale_today.toLocaleString('th-TH')
                    : '0';
            document.getElementById('totalCountSaleDisplay').textContent = data.total_count_sale || 0;

            // อัปเดตตารางสินค้าใกล้หมด
            const lowStockTable = document.getElementById('lowStockProductsTable');
            lowStockTable.innerHTML = '';
            if (data.low_stock_products && data.low_stock_products.length > 0) {
                data.low_stock_products.forEach(product => {
                    const status = product.total_qty <= 3 ? 'วิกฤต' : 'ต่ำ';
                    const statusClass = product.total_qty <= 3 ? 'cancelled' : 'pending';
                    const row = `
                        <tr>
                            <td>${product.product_id}</td>
                            <td>${product.product_name}</td>
                            <td>${product.total_qty}</td>
                            <td>${product.warehouse_name}</td>
                            <td><span class="status ${statusClass}">${status}</span></td>
                        </tr>
                    `;
                    lowStockTable.innerHTML += row;
                });
            } else {
                lowStockTable.innerHTML = `
                    <tr>
                        <td colspan="5" class="text-center py-4">ไม่มีสินค้าใกล้หมด</td>
                    </tr>
                `;
            }

            // อัปเดตตารางการขายล่าสุด
            const latestSalesTable = document.getElementById('LatestSalesTable');
            latestSalesTable.innerHTML = '';
            if (data.latest_sales && data.latest_sales.length > 0) {
                data.latest_sales.forEach(sale => {
                    let statusText = '';
                    let statusClass = '';
                    switch (sale.sale_status_id) {
                        case 1:
                            statusText = 'เสร็จสิ้น';
                            statusClass = 'success';
                            break;
                        case 2:
                            statusText = 'กำลังขาย';
                            statusClass = 'pending';
                            break;
                        case 3:
                            statusText = 'ยกเลิก';
                            statusClass = 'cancelled';
                            break;
                        default:
                            statusText = 'ไม่ทราบสถานะ';
                            statusClass = 'unknown';
                    }
                    const row = `
                        <tr>
                            <td>${sale.sale_id}</td>
                            <td>${sale.member_name}</td>
                            <td>${new Date(sale.sale_date).toLocaleDateString('th-TH')}</td>
                            <td>${sale.sale_total_amount.toLocaleString('th-TH')}</td>
                            <td><span class="status ${statusClass}">${statusText}</span></td>
                        </tr>
                    `;
                    latestSalesTable.innerHTML += row;
                });
            } else {
                latestSalesTable.innerHTML = `
                    <tr>
                        <td colspan="5" class="text-center py-4">ไม่มีข้อมูลการขายล่าสุด</td>
                    </tr>
                `;
            }

            // อัปเดตกราฟสินค้าขายดี (เฉพาะกราฟ)
            if (data.top_products && data.top_products.length > 0) {
                renderTopProductsChart(data.top_products);
            } else {
                const canvas = document.getElementById('topProductsChart');
                const ctx = canvas.getContext('2d');
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                ctx.font = '16px Arial';
                ctx.fillStyle = '#666';
                ctx.textAlign = 'center';
                ctx.fillText('ไม่มีข้อมูลสินค้าขายดี', canvas.width / 2, canvas.height / 2);
            }

            // ประมวลผลข้อมูลยอดขายสำหรับกราฟ
            let labels = [];
            let values = [];
            const tf = data.timeframe || 'monthly';

            if (tf === 'daily') {
                const today = new Date();
                for (let i = 6; i >= 0; i--) {
                    const date = new Date(today);
                    date.setDate(today.getDate() - i);
                    labels.push(date.toLocaleDateString('th-TH'));
                }
                values = new Array(7).fill(0);
                if (data.sales_data && data.sales_data.length > 0) {
                    data.sales_data.forEach(item => {
                        const date = new Date(item.date).toLocaleDateString('th-TH');
                        const index = labels.indexOf(date);
                        if (index !== -1) {
                            values[index] = parseFloat(item.total_sales);
                        }
                    });
                }
            } else if (tf === 'weekly') {
                const today = new Date();
                for (let i = 3; i >= 0; i--) {
                    const weekStart = new Date(today);
                    weekStart.setDate(today.getDate() - (today.getDay() || 7) - (i * 7));
                    labels.push(`สัปดาห์ ${4 - i}`);
                }
                values = new Array(4).fill(0);
                if (data.sales_data && data.sales_data.length > 0) {
                    data.sales_data.forEach((item, index) => {
                        values[3 - index] = parseFloat(item.total_sales);
                    });
                }
            } else if (tf === 'monthly') {
                const monthNames = ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
                labels = monthNames;
                values = new Array(12).fill(0);
                if (data.sales_data && data.sales_data.length > 0) {
                    data.sales_data.forEach(item => {
                        const monthIndex = parseInt(item.month) - 1;
                        values[monthIndex] = parseFloat(item.total_sales);
                    });
                }
            } else if (tf === 'yearly') {
                const currentYear = new Date().getFullYear();
                for (let i = 4; i >= 0; i--) {
                    labels.push((currentYear - i).toString());
                }
                values = new Array(5).fill(0);
                if (data.sales_data && data.sales_data.length > 0) {
                    data.sales_data.forEach(item => {
                        const yearIndex = labels.indexOf(item.year.toString());
                        if (yearIndex !== -1) {
                            values[yearIndex] = parseFloat(item.total_sales);
                        }
                    });
                }
            }

            initializeSalesChart(labels, values, tf);
        })
        .catch(error => {
            console.error('เกิดข้อผิดพลาดในการโหลดข้อมูล:', error);
            document.getElementById('lowStockProductsTable').innerHTML = `
                <tr>
                    <td colspan="5" class="text-center py-4">เกิดข้อผิดพลาดในการโหลดข้อมูล</td>
                </tr>
            `;
            document.getElementById('LatestSalesTable').innerHTML = `
                <tr>
                    <td colspan="5" class="text-center py-4">เกิดข้อผิดพลาดในการโหลดข้อมูล</td>
                </tr>
            `;
            const canvas = document.getElementById('topProductsChart');
            const ctx = canvas.getContext('2d');
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            ctx.font = '16px Arial';
            ctx.fillStyle = '#666';
            ctx.textAlign = 'center';
            ctx.fillText('เกิดข้อผิดพลาดในการโหลดข้อมูล', canvas.width / 2, canvas.height / 2);
        });
}

// เริ่มต้นเมื่อโหลดหน้า
document.addEventListener('DOMContentLoaded', () => {
    updateDateTime();
    setInterval(updateDateTime, 60000);
    loadCounts('monthly'); // โหลดเริ่มต้น
});

// อัปเดตวันที่และเวลา
function updateDateTime() {
    const now = new Date();
    const options = {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    };
    document.getElementById('currentDateTime').textContent = now.toLocaleDateString('th-TH', options);
}

// จัดการปุ่มเปลี่ยนช่วงเวลา
document.querySelectorAll('.chart-button').forEach(button => {
    button.addEventListener('click', () => {
        document.querySelectorAll('.chart-button').forEach(btn => btn.classList.remove('active'));
        button.classList.add('active');
        const timeframe = button.getAttribute('data-timeframe');
        loadCounts(timeframe);
    });
});
