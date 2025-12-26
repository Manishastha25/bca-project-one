<?php
session_start();
require_once 'db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Only admin can access reports
if ($_SESSION['user_type'] != 'admin') {
    header("Location: dashboard.php");
    exit();
}

// Get filter values
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$ward_filter = $_GET['ward'] ?? '';
$status_filter = $_GET['status'] ?? '';
$category_filter = $_GET['category'] ?? '';
$report_type = $_GET['report_type'] ?? 'summary';

// Get dropdown options
$wards_result = mysqli_query($conn, "SELECT DISTINCT ward_number FROM complaints ORDER BY ward_number");
$categories_result = mysqli_query($conn, "SELECT * FROM categories ORDER BY category_name");
$statuses = ['pending', 'in_progress', 'resolved', 'rejected'];

// Build WHERE conditions
$where_conditions = [];
$where_conditions[] = "DATE(c.created_at) BETWEEN '$start_date' AND '$end_date'";

if ($ward_filter) {
    $where_conditions[] = "c.ward_number = '$ward_filter'";
}

if ($status_filter) {
    $where_conditions[] = "c.status = '$status_filter'";
}

if ($category_filter) {
    $where_conditions[] = "c.category_id = '$category_filter'";
}

$where_sql = "";
if ($where_conditions) {
    $where_sql = "WHERE " . implode(" AND ", $where_conditions);
}

// Simple WHERE for queries without joins
$simple_where_conditions = [];
$simple_where_conditions[] = "DATE(created_at) BETWEEN '$start_date' AND '$end_date'";

if ($ward_filter) {
    $simple_where_conditions[] = "ward_number = '$ward_filter'";
}

if ($status_filter) {
    $simple_where_conditions[] = "status = '$status_filter'";
}

if ($category_filter) {
    $simple_where_conditions[] = "category_id = '$category_filter'";
}

$simple_where_sql = "";
if ($simple_where_conditions) {
    $simple_where_sql = "WHERE " . implode(" AND ", $simple_where_conditions);
}

// Get statistics
$stats_query = "SELECT 
    COUNT(*) as total,
    SUM(status = 'pending') as pending,
    SUM(status = 'in_progress') as in_progress,
    SUM(status = 'resolved') as resolved,
    SUM(status = 'rejected') as rejected
    FROM complaints $simple_where_sql";

$stats_result = mysqli_query($conn, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);

// Get complaints by category for chart
$cat_query = "SELECT cat.category_name, COUNT(*) as count 
    FROM complaints c 
    JOIN categories cat ON c.category_id = cat.id 
    $where_sql
    GROUP BY cat.category_name 
    ORDER BY count DESC";
$cat_result = mysqli_query($conn, $cat_query);

// Prepare data for category chart
$category_labels = [];
$category_data = [];
$category_colors = [];
while($cat = mysqli_fetch_assoc($cat_result)) {
    $category_labels[] = $cat['category_name'];
    $category_data[] = $cat['count'];
    $category_colors[] = sprintf('#%06X', mt_rand(0, 0xFFFFFF));
}

// Get complaints by ward for chart
$ward_query = "SELECT ward_number, COUNT(*) as count 
    FROM complaints 
    $simple_where_sql
    GROUP BY ward_number 
    ORDER BY ward_number";
$ward_result = mysqli_query($conn, $ward_query);

// Prepare data for ward chart
$ward_labels = [];
$ward_data = [];
while($ward = mysqli_fetch_assoc($ward_result)) {
    $ward_labels[] = "Ward " . $ward['ward_number'];
    $ward_data[] = $ward['count'];
}

// Get complaints by status for chart
$status_data = [
    'pending' => $stats['pending'] ?? 0,
    'in_progress' => $stats['in_progress'] ?? 0,
    'resolved' => $stats['resolved'] ?? 0,
    'rejected' => $stats['rejected'] ?? 0
];

// Get complaints by day (last 30 days)
$daily_query = "SELECT 
    DATE(created_at) as day, 
    COUNT(*) as count 
    FROM complaints 
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY DATE(created_at) 
    ORDER BY day";
$daily_result = mysqli_query($conn, $daily_query);

$daily_labels = [];
$daily_data = [];
while($day = mysqli_fetch_assoc($daily_result)) {
    $daily_labels[] = date('M d', strtotime($day['day']));
    $daily_data[] = $day['count'];
}

// Get detailed complaints
$complaints = [];
$det_query = "SELECT 
    c.id, c.tracking_code, c.title, c.ward_number, 
    c.status, c.created_at, cat.category_name, u.name as user_name
    FROM complaints c
    LEFT JOIN users u ON c.user_id = u.id
    JOIN categories cat ON c.category_id = cat.id
    $where_sql
    ORDER BY c.created_at DESC";
    
$det_result = mysqli_query($conn, $det_query);
if ($det_result) {
    while ($row = mysqli_fetch_assoc($det_result)) {
        $complaints[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complaint Reports with Charts</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Chart.js Library -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * { 
            margin: 0; 
            padding: 0; 
            box-sizing: border-box; 
            font-family: 'Poppins', sans-serif;
        }
        
        body { 
            background: #f7f9fc; 
            color: #333;
            font-weight: 400;
        }
        
        .container { 
            max-width: 1200px; 
            margin: 0 auto; 
            padding: 20px; 
        }
        
        header {
            background: linear-gradient(to right, darkblue,red);
            color: white;
            padding: 18px 0;
            margin-bottom: 25px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 20px;
        }
        
        .header-content h1 {
            font-weight: 600;
            font-size: 24px;
        }
        
        .header-links a {
            color: white;
            text-decoration: none;
            margin-left: 20px;
            padding: 8px 16px;
            border-radius: 6px;
            transition: all 0.3s;
            font-weight: 500;
        }
        
        .header-links a:hover {
            background: rgba(255,255,255,0.15);
        }
        
        .filters {
            background: white;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }
        
        .filter-row {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .filter-group {
            flex: 1;
            min-width: 220px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #2c3e50;
            font-size: 14px;
        }
        
        input, select {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #e0e6ed;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        input:focus, select:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }
        
        .btn-group {
            display: flex;
            gap: 15px;
            margin-top: 10px;
        }
        
        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 15px;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: #3498db;
            color: white;
        }
        
        .btn-primary:hover {
            background: #2980b9;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(52, 152, 219, 0.3);
        }
        
        .btn-secondary {
            background: #95a5a6;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #7f8c8d;
            transform: translateY(-2px);
        }
        
        .btn-success {
            background: #27ae60;
            color: white;
        }
        
        .btn-success:hover {
            background: #219653;
            transform: translateY(-2px);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 35px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            transition: transform 0.3s;
            border-top: 4px solid #3498db;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-number {
            font-size: 36px;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 10px;
        }
        
        .stat-label {
            color: #7f8c8d;
            font-size: 15px;
            font-weight: 500;
        }
        
        .charts-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 25px;
            margin-bottom: 35px;
        }
        
        @media (max-width: 768px) {
            .charts-container {
                grid-template-columns: 1fr;
            }
        }
        
        .chart-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }
        
        .chart-title {
            color: #2c3e50;
            margin-bottom: 20px;
            font-weight: 600;
            font-size: 18px;
            text-align: center;
        }
        
        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }
        
        .table-container {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }
        
        .table-container h2 {
            color: #2c3e50;
            margin-bottom: 20px;
            font-weight: 600;
            font-size: 20px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            margin-top: 10px;
        }
        
        th {
            background: #f8fafc;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #2c3e50;
            border-bottom: 2px solid #eef2f7;
        }
        
        td {
            padding: 15px;
            border-bottom: 1px solid #eef2f7;
            color: #4a5568;
        }
        
        tr:hover {
            background: #f8fafc;
        }
        
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .pending { 
            background: #fff3cd; 
            color: #856404; 
        }
        .in_progress { 
            background: #d1ecf1; 
            color: #0c5460; 
        }
        .resolved { 
            background: #d4edda; 
            color: #155724; 
        }
        .rejected { 
            background: #f8d7da; 
            color: #721c24; 
        }
        
        .empty-message {
            text-align: center;
            padding: 40px;
            color: #95a5a6;
            font-style: italic;
        }
        
        .date-range {
            background: #e8f4fc;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            font-weight: 500;
            color: #2c3e50;
        }
        
        .export-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            justify-content: flex-end;
        }
        
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                text-align: center;
                gap: 15px;
            }
            
            .header-links {
                display: flex;
                flex-wrap: wrap;
                justify-content: center;
                gap: 10px;
            }
            
            .header-links a {
                margin: 0;
            }
            
            .filter-row {
                flex-direction: column;
            }
            
            .stats-grid {
                grid-template-columns: 1fr 1fr;
            }
            
            .btn-group {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
            }
            
            .export-buttons {
                justify-content: center;
                flex-wrap: wrap;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="header-content">
            <h1>📊 Complaint Reports & Analytics</h1>
            <div class="header-links">
                <a href="admin_dashboard.php">← Dashboard</a>
                <a href="logout.php">Logout</a>
            </div>
        </div>
    </header>
    
    <div class="container">
        <!-- Date Range Info -->
        <div class="date-range">
            Showing reports from <strong><?php echo date('F d, Y', strtotime($start_date)); ?></strong> 
            to <strong><?php echo date('F d, Y', strtotime($end_date)); ?></strong>
            <?php if($ward_filter): ?> | Ward: <strong><?php echo $ward_filter; ?></strong><?php endif; ?>
            <?php if($status_filter): ?> | Status: <strong><?php echo ucwords(str_replace('_', ' ', $status_filter)); ?></strong><?php endif; ?>
        </div>
        
        <!-- Filters -->
        <div class="filters">
            <form method="GET" action="">
                <div class="filter-row">
                    <div class="filter-group">
                        <label>📅 Start Date</label>
                        <input type="date" name="start_date" value="<?php echo $start_date; ?>" required>
                    </div>
                    
                    <div class="filter-group">
                        <label>📅 End Date</label>
                        <input type="date" name="end_date" value="<?php echo $end_date; ?>" required>
                    </div>
                    
                    <div class="filter-group">
                        <label>🏘️ Ward Number</label>
                        <select name="ward">
                            <option value="">All Wards</option>
                            <?php 
                            mysqli_data_seek($wards_result, 0);
                            while($ward = mysqli_fetch_assoc($wards_result)): ?>
                                <option value="<?php echo $ward['ward_number']; ?>" 
                                    <?php echo ($ward_filter == $ward['ward_number']) ? 'selected' : ''; ?>>
                                    Ward <?php echo $ward['ward_number']; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label>📋 Status</label>
                        <select name="status">
                            <option value="">All Status</option>
                            <?php foreach($statuses as $status): ?>
                                <option value="<?php echo $status; ?>" 
                                    <?php echo ($status_filter == $status) ? 'selected' : ''; ?>>
                                    <?php echo ucwords(str_replace('_', ' ', $status)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="filter-row">
                    <div class="filter-group">
                        <label>📁 Category</label>
                        <select name="category">
                            <option value="">All Categories</option>
                            <?php 
                            mysqli_data_seek($categories_result, 0);
                            while($cat = mysqli_fetch_assoc($categories_result)): ?>
                                <option value="<?php echo $cat['id']; ?>" 
                                    <?php echo ($category_filter == $cat['id']) ? 'selected' : ''; ?>>
                                    <?php echo $cat['category_name']; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label>📄 Report Type</label>
                        <select name="report_type">
                            <option value="summary" <?php echo ($report_type == 'summary') ? 'selected' : ''; ?>>Summary with Charts</option>
                            <option value="detailed" <?php echo ($report_type == 'detailed') ? 'selected' : ''; ?>>Detailed List</option>
                        </select>
                    </div>
                </div>
                
                <div class="btn-group">
                    <button type="submit" class="btn btn-primary">
                        Apply Filters
                    </button>
                    <a href="reports.php" class="btn btn-secondary">
                        Reset Filters
                    </a>
                </div>
            </form>
        </div>
        
        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total'] ?? 0; ?></div>
                <div class="stat-label">Total Complaints</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['pending'] ?? 0; ?></div>
                <div class="stat-label">Pending Complaints</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['in_progress'] ?? 0; ?></div>
                <div class="stat-label">In Progress</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['resolved'] ?? 0; ?></div>
                <div class="stat-label">Resolved</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['rejected'] ?? 0; ?></div>
                <div class="stat-label">Rejected</div>
            </div>
        </div>
        
        <!-- Charts Section -->
        <?php if($report_type == 'summary'): ?>
        <div class="charts-container">
            <!-- Status Distribution Pie Chart -->
            <div class="chart-card">
                <h3 class="chart-title">📊 Complaint Status Distribution</h3>
                <div class="chart-container">
                    <canvas id="statusChart"></canvas>
                </div>
            </div>
            
            <!-- Category Distribution Bar Chart -->
            <div class="chart-card">
                <h3 class="chart-title">📈 Complaints by Category</h3>
                <div class="chart-container">
                    <canvas id="categoryChart"></canvas>
                </div>
            </div>
            
            <!-- Ward Distribution Bar Chart -->
            <div class="chart-card">
                <h3 class="chart-title">📍 Complaints by Ward</h3>
                <div class="chart-container">
                    <canvas id="wardChart"></canvas>
                </div>
            </div>
            
            <!-- Daily Trend Line Chart -->
            <div class="chart-card">
                <h3 class="chart-title">📅 Daily Complaint Trend (Last 30 Days)</h3>
                <div class="chart-container">
                    <canvas id="dailyChart"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Tables Section -->
        <div class="tables-container">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 25px;">
                <!-- Category Table -->
                <div class="table-container">
                    <h2>📋 Complaints by Category</h2>
                    <?php if (!empty($category_labels)): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Category</th>
                                    <th>Count</th>
                                    <th>Percentage</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                mysqli_data_seek($cat_result, 0);
                                $total = $stats['total'] ?: 1;
                                $counter = 0;
                                while($cat = mysqli_fetch_assoc($cat_result)): 
                                    $percentage = ($cat['count'] / $total) * 100;
                                ?>
                                    <tr>
                                        <td><?php echo $cat['category_name']; ?></td>
                                        <td><?php echo $cat['count']; ?></td>
                                        <td><?php echo number_format($percentage, 1); ?>%</td>
                                    </tr>
                                <?php 
                                    $counter++;
                                    if ($counter >= 5) break; // Show top 5 only
                                endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p class="empty-message">No category data available</p>
                    <?php endif; ?>
                </div>
                
                <!-- Ward Table -->
                <div class="table-container">
                    <h2>📍 Complaints by Ward</h2>
                    <?php if (!empty($ward_labels)): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Ward</th>
                                    <th>Count</th>
                                    <th>Percentage</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                mysqli_data_seek($ward_result, 0);
                                $counter = 0;
                                while($ward = mysqli_fetch_assoc($ward_result)): 
                                    $percentage = ($ward['count'] / $total) * 100;
                                ?>
                                    <tr>
                                        <td>Ward <?php echo $ward['ward_number']; ?></td>
                                        <td><?php echo $ward['count']; ?></td>
                                        <td><?php echo number_format($percentage, 1); ?>%</td>
                                    </tr>
                                <?php 
                                    $counter++;
                                    if ($counter >= 5) break; // Show top 5 only
                                endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p class="empty-message">No ward data available</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Detailed Report -->
        <?php if($report_type == 'detailed'): ?>
            <div class="table-container">
                <div class="export-buttons">
                    <button onclick="printReport()" class="btn btn-success">🖨️ Print Report</button>
                    <button onclick="exportToPDF()" class="btn btn-primary">📄 Export as PDF</button>
                </div>
                
                <h2>📋 Detailed Complaints List (<?php echo count($complaints); ?> found)</h2>
                <?php if(!empty($complaints)): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Tracking Code</th>
                                <th>Title</th>
                                <th>User</th>
                                <th>Category</th>
                                <th>Ward</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($complaints as $complaint): ?>
                                <tr>
                                    <td>#<?php echo $complaint['id']; ?></td>
                                    <td><code><?php echo $complaint['tracking_code']; ?></code></td>
                                    <td><?php echo substr($complaint['title'], 0, 30); ?>...</td>
                                    <td><?php echo $complaint['user_name'] ?: 'Guest'; ?></td>
                                    <td><?php echo $complaint['category_name']; ?></td>
                                    <td>Ward <?php echo $complaint['ward_number']; ?></td>
                                    <td>
                                        <span class="status-badge <?php echo $complaint['status']; ?>">
                                            <?php echo ucwords(str_replace('_', ' ', $complaint['status'])); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($complaint['created_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="empty-message">No complaints found for the selected filters.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
        // Status Distribution Pie Chart
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        const statusChart = new Chart(statusCtx, {
            type: 'pie',
            data: {
                labels: ['Pending', 'In Progress', 'Resolved', 'Rejected'],
                datasets: [{
                    data: [
                        <?php echo $status_data['pending']; ?>,
                        <?php echo $status_data['in_progress']; ?>,
                        <?php echo $status_data['resolved']; ?>,
                        <?php echo $status_data['rejected']; ?>
                    ],
                    backgroundColor: [
                        '#ffc107',
                        '#17a2b8',
                        '#28a745',
                        '#dc3545'
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
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            font: {
                                family: 'Poppins',
                                size: 12
                            }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                label += context.raw + ' complaints';
                                return label;
                            }
                        }
                    }
                }
            }
        });
        
        // Category Distribution Bar Chart
        const categoryCtx = document.getElementById('categoryChart').getContext('2d');
        const categoryChart = new Chart(categoryCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($category_labels); ?>,
                datasets: [{
                    label: 'Number of Complaints',
                    data: <?php echo json_encode($category_data); ?>,
                    backgroundColor: <?php echo json_encode($category_colors); ?>,
                    borderColor: '#2c3e50',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            font: {
                                family: 'Poppins'
                            }
                        },
                        grid: {
                            color: 'rgba(0,0,0,0.05)'
                        }
                    },
                    x: {
                        ticks: {
                            font: {
                                family: 'Poppins'
                            }
                        },
                        grid: {
                            display: false
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
        
        // Ward Distribution Bar Chart
        const wardCtx = document.getElementById('wardChart').getContext('2d');
        const wardChart = new Chart(wardCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($ward_labels); ?>,
                datasets: [{
                    label: 'Number of Complaints',
                    data: <?php echo json_encode($ward_data); ?>,
                    backgroundColor: 'rgba(52, 152, 219, 0.7)',
                    borderColor: '#3498db',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            font: {
                                family: 'Poppins'
                            }
                        },
                        grid: {
                            color: 'rgba(0,0,0,0.05)'
                        }
                    },
                    x: {
                        ticks: {
                            font: {
                                family: 'Poppins'
                            }
                        },
                        grid: {
                            display: false
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
        
        // Daily Trend Line Chart
        const dailyCtx = document.getElementById('dailyChart').getContext('2d');
        const dailyChart = new Chart(dailyCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($daily_labels); ?>,
                datasets: [{
                    label: 'Complaints per Day',
                    data: <?php echo json_encode($daily_data); ?>,
                    backgroundColor: 'rgba(46, 204, 113, 0.2)',
                    borderColor: '#2ecc71',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            font: {
                                family: 'Poppins'
                            }
                        },
                        grid: {
                            color: 'rgba(0,0,0,0.05)'
                        }
                    },
                    x: {
                        ticks: {
                            font: {
                                family: 'Poppins'
                            }
                        },
                        grid: {
                            color: 'rgba(0,0,0,0.05)'
                        }
                    }
                }
            }
        });
        
        // Export Functions
        function printReport() {
            window.print();
        }
        
        function exportToPDF() {
            alert('PDF export feature would be implemented here. You would need a PDF library like TCPDF or mPDF.');
        }
    </script>
</body>
</html>