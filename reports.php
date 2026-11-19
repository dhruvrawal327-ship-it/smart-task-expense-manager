
<?php
require_once "includes/auth.php";
require_once "includes/functions.php";

$userId = $_SESSION['user_id'];

// Task statistics
$stmt = $pdo->prepare(
    "SELECT
        COUNT(*) AS total,
        SUM(status='Completed') AS completed,
        SUM(status='Pending') AS pending
     FROM tasks WHERE user_id=?"
);
$stmt->execute([$userId]);
$taskStats = $stmt->fetch();

// Expense statistics
$stmt = $pdo->prepare(
    "SELECT COALESCE(SUM(amount),0) AS total
     FROM expenses WHERE user_id=?"
);
$stmt->execute([$userId]);
$expenseTotal = $stmt->fetchColumn();

// Category summary
$stmt = $pdo->prepare(
    "SELECT category, SUM(amount) AS total
     FROM expenses WHERE user_id=?
     GROUP BY category ORDER BY total DESC"
);
$stmt->execute([$userId]);
$categories = $stmt->fetchAll();

// Prepare chart data
$chartLabels = [];
$chartValues = [];

foreach ($categories as $category) {
    $chartLabels[] = $category['category'];
    $chartValues[] = (float)$category['total'];
}


// Monthly expense chart - Last 6 months

$monthlyStart = new DateTimeImmutable('first day of this month');
$monthlyStart = $monthlyStart->modify('-5 months');

$monthlyEnd = (new DateTimeImmutable('first day of this month'))
    ->modify('+1 month');

$stmt = $pdo->prepare(
    "SELECT
        DATE_FORMAT(expense_date, '%Y-%m') AS month_key,
        SUM(amount) AS total
     FROM expenses
     WHERE user_id = ?
     AND expense_date >= ?
     AND expense_date < ?
     GROUP BY month_key
     ORDER BY month_key"
);

$stmt->execute([
    $userId,
    $monthlyStart->format('Y-m-d'),
    $monthlyEnd->format('Y-m-d')
]);

$monthlyResults = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

$monthlyLabels = [];
$monthlyValues = [];

for ($i = 0; $i < 6; $i++) {
    $month = $monthlyStart->modify("+$i months");
    $key = $month->format('Y-m');

    $monthlyLabels[] = $month->format('M Y');
    $monthlyValues[] = (float)($monthlyResults[$key] ?? 0);
}


include "includes/header.php";
?>

<h2 class="page-title">Reports & Analytics</h2>

<?php showMessage(); ?>

<!-- Statistics -->
<div class="row g-3 mb-4">

    <div class="col-md-6">
        <div class="card p-3">
            <p class="text-muted">Total Tasks</p>
            <h3><?= $taskStats['total'] ?? 0 ?></h3>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card p-3">
            <p class="text-muted">Total Expenses</p>
            <h3 class="text-danger">
                ₹<?= number_format((float)$expenseTotal, 2) ?>
            </h3>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card p-3">
            <p class="text-muted">Completed Tasks</p>
            <h3 class="text-success">
                <?= $taskStats['completed'] ?? 0 ?>
            </h3>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card p-3">
            <p class="text-muted">Pending Tasks</p>
            <h3 class="text-warning">
                <?= $taskStats['pending'] ?? 0 ?>
            </h3>
        </div>
    </div>

</div>

<!-- Expense Pie Chart -->

<!-- Expense Analytics -->
<div class="card p-4 mb-4">
    <h5 class="mb-1">Expense Analytics</h5>
    <p class="text-muted">Understand where your money goes.</p>

    <?php if (!empty($categories) && (float)$expenseTotal > 0): ?>

    <div class="row align-items-center">

        <div class="col-md-6">
            <div style="max-width:400px; height:320px; margin:auto;">
                <canvas id="expenseChart"></canvas>
            </div>
        </div>

        <div class="col-md-6">
            <h6 class="mb-3">Spending Breakdown</h6>

            <?php foreach ($categories as $category): 
                $percentage = ($category['total'] / $expenseTotal) * 100;
            ?>

            <div class="d-flex justify-content-between align-items-center border-bottom py-3">
                <div>
                    <strong><?= htmlspecialchars($category['category']) ?></strong>
                    <div class="text-muted small">
                        <?= number_format($percentage, 1) ?>% of total spending
                    </div>
                </div>

                <strong>
                    ₹<?= number_format((float)$category['total'], 2) ?>
                </strong>
            </div>

            <?php endforeach; ?>
        </div>

    </div>

    <?php else: ?>

        <p class="text-muted text-center py-5">
            Add expenses to view your spending analytics.
        </p>

    <?php endif; ?>
</div>


<!-- Monthly Expense Chart -->

<div class="card p-4 mb-4">
    <h5 class="mb-1">Monthly Expense Overview</h5>
    <p class="text-muted">
        Compare your spending over the last 6 months.
    </p>

    <div style="position: relative; height: 320px; width: 100%;">
        <canvas id="monthlyExpenseChart"></canvas>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<?php if (!empty($categories) && (float)$expenseTotal > 0): ?>
<script>
const chartLabels = <?= json_encode($chartLabels) ?>;
const chartValues = <?= json_encode($chartValues) ?>;
const totalExpense = <?= json_encode((float)$expenseTotal) ?>;

const centerText = {
    id: 'centerText',
    afterDraw(chart) {
        const {ctx, chartArea} = chart;
        if (!chartArea) return;

        const x = (chartArea.left + chartArea.right) / 2;
        const y = (chartArea.top + chartArea.bottom) / 2;

        ctx.save();
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';

        ctx.font = '14px Arial';
        ctx.fillStyle = '#777';
        ctx.fillText('Total Expenses', x, y - 14);

        ctx.font = 'bold 21px Arial';
        ctx.fillStyle = '#172554';
        ctx.fillText(
            '₹' + totalExpense.toLocaleString('en-IN', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }),
            x, y + 15
        );

        ctx.restore();
    }
};

new Chart(document.getElementById('expenseChart'), {
    type: 'doughnut',

    data: {
        labels: chartLabels,
        datasets: [{
            data: chartValues,
            backgroundColor: [
                '#4361ee', '#f72585', '#4cc9f0',
                '#f9c74f', '#43aa8b', '#9b5de5',
                '#f9844a', '#577590'
            ],
            borderWidth: 3,
            borderColor: '#ffffff',
            hoverOffset: 10
        }]
    },

    options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '65%',

        plugins: {
            legend: {
                display: false
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        const amount = Number(context.raw);
                        const percentage = (amount / totalExpense) * 100;

                        return context.label + ': ₹' +
                            amount.toLocaleString('en-IN', {
                                minimumFractionDigits: 2,
                                maximumFractionDigits: 2
                            }) + ' (' + percentage.toFixed(1) + '%)';
                    }
                }
            }
        }
    },

    plugins: [centerText]
});
</script>


<?php endif; ?>


<script>
const monthlyLabels = <?= json_encode(
    $monthlyLabels,
    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
) ?>;

const monthlyValues = <?= json_encode($monthlyValues) ?>;

new Chart(document.getElementById('monthlyExpenseChart'), {
    type: 'bar',

    data: {
        labels: monthlyLabels,

        datasets: [{
            label: 'Monthly Expenses (₹)',
            data: monthlyValues,
            backgroundColor: '#4361ee',
            borderRadius: 8,
            barThickness: 35
        }]
    },

    options: {
        responsive: true,
        maintainAspectRatio: false,

        plugins: {
            legend: {
                display: false
            },

            tooltip: {
                callbacks: {
                    label: function(context) {
                        return '₹' + Number(context.raw)
                            .toLocaleString('en-IN', {
                                minimumFractionDigits: 2,
                                maximumFractionDigits: 2
                            });
                    }
                }
            }
        },

        scales: {
            y: {
                beginAtZero: true,

                ticks: {
                    color: '#cbd5e1',
                    callback: function(value) {
                        return '₹' + value;
                    }
                },

                grid: {
                    color: 'rgba(255,255,255,0.1)'
                }
            },

            x: {
                ticks: {
                    color: '#cbd5e1'
                },

                grid: {
                    display: false
                }
            }
        }
    }
});
</script>

<?php include "includes/footer.php"; ?>
