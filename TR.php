<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header("Location: login.php"); // Redirect to login if not logged in
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daily Expense Tracker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="container my-5">
        <h2 class="text-center mb-4">Daily Expense Tracker</h2>

        <!-- Form to Add Daily Transaction -->
        <form id="dailyTransactionForm">
            <div class="mb-3">
                <label for="date" class="form-label">Date</label>
                <input type="date" class="form-control" id="date" required>
            </div>

            <div class="mb-3">
                <label for="time" class="form-label">Time</label>
                <input type="time" class="form-control" id="time" readonly>
            </div>

            <div class="mb-3">
                <label for="amount" class="form-label">Amount</label>
                <input type="number" class="form-control" id="amount" placeholder="Enter Amount" required>
            </div>

            <div class="mb-3">
                <label for="category" class="form-label">Category</label>
                <select class="form-select" id="category" required>
                    <option value="" disabled selected>Select a Category</option>
                    <option value="Food">Food</option>
                    <option value="Transport">Transport</option>
                    <option value="Ironing Clothes">Ironing Clothes</option>
                    <option value="Vegetables">Vegetables</option>
                    <option value="Fruits">Fruits</option>
                    <option value="Clothes">Clothes</option>
                </select>
            </div>

            <div class="mb-3">
                <label for="description" class="form-label">Description</label>
                <textarea class="form-control" id="description" rows="3" placeholder="Enter Description"></textarea>
            </div>

            <button type="submit" class="btn btn-primary">Add Transaction</button>
            <button type="reset" class="btn btn-secondary">Reset</button>
            <button type="button" class="btn btn-success" id="exportCsvBtn">Export to CSV</button>
        </form>

        <!-- Search Bar for Filtering Transactions -->
        <div class="mt-4">
            <input type="text" class="form-control" id="searchInput" placeholder="Search by Category">
        </div>

        <!-- Transaction Table -->
        <div class="mt-5">
            <h4>Transaction Records</h4>
            <table class="table table-striped" id="transactionTable">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Amount</th>
                        <th>Category</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>

        <!-- Monthly Summary -->
        <div class="mt-5">
            <h4>Monthly Summary</h4>
            <canvas id="summaryChart"></canvas>
            <p id="totalAmount" class="mt-3"></p>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- JavaScript -->
    <script>
        const form = document.getElementById('dailyTransactionForm');
        const transactionTable = document.getElementById('transactionTable').getElementsByTagName('tbody')[0];
        const summaryChartCtx = document.getElementById('summaryChart').getContext('2d');
        const totalAmount = document.getElementById('totalAmount');
        const timeInput = document.getElementById('time');
        const exportCsvBtn = document.getElementById('exportCsvBtn');
        const searchInput = document.getElementById('searchInput');

        let transactions = JSON.parse(localStorage.getItem('transactions')) || [];
        let chart;

        function updateTime() {
            const now = new Date();
            timeInput.value = now.toTimeString().slice(0, 5);
        }

        function updateUI() {
            const searchQuery = searchInput.value.toLowerCase();
            transactionTable.innerHTML = '';

            const filteredTransactions = transactions.filter(transaction =>
                transaction.category.toLowerCase().includes(searchQuery)
            );

            filteredTransactions.forEach(transaction => {
                const row = transactionTable.insertRow();
                row.insertCell(0).textContent = transaction.date;
                row.insertCell(1).textContent = transaction.time;
                row.insertCell(2).textContent = transaction.amount;
                row.insertCell(3).textContent = transaction.category;
                row.insertCell(4).textContent = transaction.description;
            });

            const now = new Date();
            const month = now.getMonth() + 1;
            const year = now.getFullYear();
            const monthlyData = transactions.filter(t => {
                const [year_, month_] = t.date.split('-').map(Number);
                return year_ === year && month_ === month;
            }).reduce((acc, t) => {
                acc[t.category] = (acc[t.category] || 0) + parseFloat(t.amount);
                return acc;
            }, {});

            const total = Object.values(monthlyData).reduce((sum, amount) => sum + amount, 0);
            totalAmount.textContent = `Total for ${now.toLocaleString('default', { month: 'long' })} ${year}: ₹${total.toFixed(2)}`;

            if (chart) chart.destroy();
            chart = new Chart(summaryChartCtx, {
                type: 'bar',
                data: {
                    labels: Object.keys(monthlyData),
                    datasets: [{
                        label: 'Amount',
                        data: Object.values(monthlyData),
                        backgroundColor: 'rgba(75, 192, 192, 0.2)',
                        borderColor: 'rgba(75, 192, 192, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    scales: { y: { beginAtZero: true } }
                }
            });
        }

        function exportToCSV() {
            if (transactions.length === 0) {
                alert('No transactions to export.');
                return;
            }

            const csvRows = [['Date', 'Time', 'Amount', 'Category', 'Description']];
            transactions.forEach(transaction => {
                csvRows.push([transaction.date, transaction.time, transaction.amount, transaction.category, transaction.description]);
            });

            const csvString = csvRows.map(row => row.join(',')).join('\n');
            const blob = new Blob([csvString], { type: 'text/csv' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `transactions_${new Date().toISOString().slice(0, 10)}.csv`;
            a.click();
            URL.revokeObjectURL(url);
        }

        form.addEventListener('submit', (e) => {
            e.preventDefault();
            const transaction = {
                date: form.date.value,
                time: timeInput.value,
                amount: form.amount.value,
                category: form.category.value,
                description: form.description.value
            };

            transactions.push(transaction);
            localStorage.setItem('transactions', JSON.stringify(transactions));
            updateUI();
            form.reset();
            updateTime();
        });

        exportCsvBtn.addEventListener('click', exportToCSV);
        searchInput.addEventListener('input', updateUI);

        updateTime();
        updateUI();
    </script>
</body>
</html>
