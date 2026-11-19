
<?php
require_once "../includes/auth.php";
require_once "../includes/functions.php";

$userId = $_SESSION['user_id'];

$categories = [
    "Food", "Travel", "Shopping", "Bills",
    "Entertainment", "Health", "Education", "Other"
];

// Get existing search and filter values
$search = trim($_GET['search'] ?? '');
$filterCategory = $_GET['category'] ?? '';
$fromDate = $_GET['from_date'] ?? '';
$toDate = $_GET['to_date'] ?? '';

// Fetch only the logged-in user's expenses
$sql = "SELECT title, amount, category, expense_date, notes
        FROM expenses WHERE user_id = ?";
$params = [$userId];

if ($search !== '') {
    $sql .= " AND (title LIKE ? OR notes LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (in_array($filterCategory, $categories, true)) {
    $sql .= " AND category = ?";
    $params[] = $filterCategory;
}

if ($fromDate !== '') {
    $date = DateTime::createFromFormat('!Y-m-d', $fromDate);
    if ($date && $date->format('Y-m-d') === $fromDate) {
        $sql .= " AND expense_date >= ?";
        $params[] = $fromDate;
    }
}

if ($toDate !== '') {
    $date = DateTime::createFromFormat('!Y-m-d', $toDate);
    if ($date && $date->format('Y-m-d') === $toDate) {
        $sql .= " AND expense_date <= ?";
        $params[] = $toDate;
    }
}

$sql .= " ORDER BY expense_date DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Prepare CSV download
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="my_expenses_' . date('Y-m-d') . '.csv"');

$output = fopen('php://output', 'w');

// UTF-8 BOM for Excel compatibility
fwrite($output, "\xEF\xBB\xBF");

// CSV headings
fputcsv($output, [
    'Expense Title',
    'Amount (INR)',
    'Category',
    'Date',
    'Notes'
]);

// Prevent spreadsheet formula injection in text fields
function safeCsvText($value) {
    $value = (string)($value ?? '');

    if (preg_match('/^[\s]*[=+\-@]/u', $value)) {
        return "'" . $value;
    }

    return $value;
}

// Export expense records
foreach ($expenses as $expense) {
    fputcsv($output, [
        safeCsvText($expense['title']),
        $expense['amount'],
        safeCsvText($expense['category']),
        $expense['expense_date'],
        safeCsvText($expense['notes'])
    ]);
}

fclose($output);
exit;
