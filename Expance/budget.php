<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Handle budget submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category = $_POST['category'];
    $amount = $_POST['amount'];
    $period = $_POST['period'];
    $start_date = $_POST['start_date'];
    
    // Calculate end date based on period
    $end_date = date('Y-m-d', strtotime($start_date));
    switch ($period) {
        case 'daily':
            $end_date = date('Y-m-d', strtotime($start_date . ' +1 day'));
            break;
        case 'weekly':
            $end_date = date('Y-m-d', strtotime($start_date . ' +1 week'));
            break;
        case 'monthly':
            $end_date = date('Y-m-d', strtotime($start_date . ' +1 month'));
            break;
        case 'yearly':
            $end_date = date('Y-m-d', strtotime($start_date . ' +1 year'));
            break;
    }

    $stmt = $conn->prepare("INSERT INTO budgets (user_id, category, amount, period, start_date, end_date) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$_SESSION['user_id'], $category, $amount, $period, $start_date, $end_date]);
    
    header("Location: budget.php?success=1");
    exit();
}

// Get current budgets
$stmt = $conn->prepare("
    SELECT b.*, 
           COALESCE(SUM(e.amount), 0) as spent_amount
    FROM budgets b
    LEFT JOIN expenses e ON e.user_id = b.user_id 
        AND e.category = b.category 
        AND e.date BETWEEN b.start_date AND b.end_date
    WHERE b.user_id = ? AND b.end_date >= CURDATE()
    GROUP BY b.id
");
$stmt->execute([$_SESSION['user_id']]);
$budgets = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Budget Management - Expance</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <!-- Navigation -->
    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between items-center h-16">
                <div class="flex items-center">
                    <a href="dashboard.php" class="text-2xl font-bold text-blue-600">Expance</a>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="dashboard.php" class="text-gray-700 hover:text-blue-600">Dashboard</a>
                    <span class="text-gray-700">Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></span>
                    <a href="logout.php" class="text-gray-700 hover:text-blue-600">Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto px-4 py-8">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Budget Form -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-semibold mb-4">Set New Budget</h2>
                <form method="POST" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Category</label>
                        <select name="category" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="Groceries">Groceries</option>
                            <option value="Transportation">Transportation</option>
                            <option value="Utilities">Utilities</option>
                            <option value="Entertainment">Entertainment</option>
                            <option value="Shopping">Shopping</option>
                            <option value="Healthcare">Healthcare</option>
                            <option value="Education">Education</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Amount</label>
                        <input type="number" name="amount" step="0.01" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Period</label>
                        <select name="period" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="daily">Daily</option>
                            <option value="weekly">Weekly</option>
                            <option value="monthly">Monthly</option>
                            <option value="yearly">Yearly</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Start Date</label>
                        <input type="date" name="start_date" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    
                    <button type="submit" class="w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                        Set Budget
                    </button>
                </form>
            </div>

            <!-- Current Budgets -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-semibold mb-4">Current Budgets</h2>
                <div class="space-y-4">
                    <?php foreach ($budgets as $budget): 
                        $percentage = ($budget['spent_amount'] / $budget['amount']) * 100;
                        $color = $percentage > 90 ? 'bg-red-500' : ($percentage > 70 ? 'bg-yellow-500' : 'bg-green-500');
                    ?>
                    <div class="border rounded-lg p-4">
                        <div class="flex justify-between items-center mb-2">
                            <h3 class="font-medium"><?php echo htmlspecialchars($budget['category']); ?></h3>
                            <span class="text-sm text-gray-500"><?php echo ucfirst($budget['period']); ?></span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2.5 mb-2">
                            <div class="<?php echo $color; ?> h-2.5 rounded-full" style="width: <?php echo min($percentage, 100); ?>%"></div>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span>$<?php echo number_format($budget['spent_amount'], 2); ?> / $<?php echo number_format($budget['amount'], 2); ?></span>
                            <span><?php echo number_format($percentage, 1); ?>%</span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-gray-800 text-white mt-8">
        <div class="max-w-7xl mx-auto px-4 py-8">
            <div class="text-center text-gray-400">
                <p>&copy; 2024 Expance. All rights reserved.</p>
            </div>
        </div>
    </footer>
</body>
</html> 