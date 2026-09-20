<?php
include_once "../core/config.php";
header('Content-Type: application/json');

$table = trim($_POST['table'] ?? '');
$idCol = trim($_POST['id'] ?? 'id');
$textCol = trim($_POST['text'] ?? 'name');
$user_id = $_SESSION['user_id'] ?? null;

// 1. Sanitize column names to prevent SQL Injection
$idCol = preg_replace('/[^a-zA-Z0-9_]/', '', $idCol) ?: 'id';
$textCol = preg_replace('/[^a-zA-Z0-9_]/', '', $textCol) ?: 'name';

$actualTable = $tableMap[$table] ?? $table;
$actualTable = preg_replace('/[^a-zA-Z0-9_]/', '', $actualTable);

if (empty($actualTable)) {
    echo json_encode([]);
    exit;
}

$whereParts = [];
$bindParams = [];

// 3. Role-based Access Restrictions (Non-Admin checks)
if (function_exists('isAdmin') && !isAdmin()) {
    switch ($actualTable) {
        case 'users':
            // Only allow current logged-in user's record
            $userId = $user_id ?? 0;
            $whereParts[] = "`$idCol` = ?";
            $bindParams[] = $userId;
            break;
    }
}


$whereClause = !empty($whereParts) ? implode(' AND ', $whereParts) : '';

// 4. Secure DB Fetching
$response = secureDbAction(
    $actualTable,
    4,
    [
        'columns' => "`$idCol`, `$textCol`",
        'orderBy' => "`$textCol` ASC"
    ],
    $whereClause,
    $bindParams
);
// print_r($response);
// 5. Build Standard JSON Options Array
$options = [];
if (!empty($response['success']) && !empty($response['data'])) {
    foreach ($response['data'] as $row) {
        if (isset($row[$idCol], $row[$textCol])) {
            $options[] = [
                'value' => (string) $row[$idCol],
                'label' => (string) $row[$textCol]
            ];
        }
    }
}

echo json_encode($options);