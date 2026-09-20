<?php
include_once "../core/config.php";
include_once "crud-config.php"; // ✅ Loads $config array

// ==========================================
// 1. REUSABLE FORMATTERS & HELPERS
// ==========================================
$statusBadge = fn($row) => "<span class='badge bg-" . (!empty($row['status']) ? 'success' : 'secondary') . "'>" . (!empty($row['status']) ? 'Active' : 'Blocked') . "</span>";
$AutoNumber = fn($row) => "<a href='?page=enquiries&id={$row['id']}'>" . invoiceNo($row['id'], 5, 'ENQ-') . "</a>";
$dpPic = fn($row) => (!empty($row['avatar']) ? $row['avatar'] : 'upload/avatar.jpg');
$uid = $_SESSION['id'] ?? null;
// Dynamic Closure: Kisi bhi status text par dynamic badge render karega
$otherStatus = function ($row, $key = 'quote_status') {
  $val = $row[$key] ?? 'NA';
  // Status text ke hisab se automatic color pick karega
  $colorMap = [
    'draft' => 'secondary',
    'active' => 'success',
    'approved' => 'success',
    'processing' => 'info',
    'queued' => 'warning',
    'pending' => 'secondary',
    'sent' => 'primary',
    'cancel' => 'danger',
    'cancelled' => 'danger',
    'failed' => 'danger',
    'blocked' => 'danger',
    'completed' => 'success',
    'inactive' => 'secondary',
  ];

  $color = $colorMap[strtolower($val)] ?? 'primary'; // Fallback to 'primary' if unknown
  $label = ucfirst($val);

  return "<span class='badge bg-{$color}'>{$label}</span>";
};
function payment_status($paid_status = '')
{
  return match (strtolower($paid_status ?? '')) {
    'pending' => '<span class="badge bg-warning text-dark">Pending</span>',
    'paid' => '<span class="badge bg-success">Paid</span>',
    'overdue' => '<span class="badge bg-danger">Overdue</span>',
    default => '<span class="badge bg-light text-dark">Unknown</span>'
  };
}
$table = $_POST['table'] ?? '';
$columns = $_POST['columns'] ?? [];
$filters = $_POST['filters'] ?? [];

// Default ordering logic (Fallback to 'id' or 'date')
$sortby = $filters['sortby'] ?? 'desc';
$limit = isset($filters['limit']) ? (int) $filters['limit'] : 100;
$orderby = $filters['orderby'] ?? 'id';
// Config array setup for current table
$tableConfig = $config[$table] ?? ['fields' => [], 'custom_actions' => null];

// Global default formatting rules
$formatRules = [

  // Leads
  'leads' => [
    'name' => fn($row) =>
      "<a href='?page=lead&id={$row['id']}'>" . htmlspecialchars($row['name']) . "</a>",
    'lead_status' => fn($row) => match (strtolower($row['lead_status'] ?? '')) {
      'new' => '<span class="badge bg-secondary">New</span>',
      'contacted' => '<span class="badge bg-info text-dark">Contacted</span>',
      'converted' => '<span class="badge bg-success">Converted</span>',
      'lost' => '<span class="badge bg-danger">Lost</span>',
      default => '<span class="badge bg-light text-dark">Unknown</span>'
    },
    'status' => $statusBadge,
  ],

  // Projects
  'projects' => [
    'name' => fn($row) =>
      "<a href='?page=projects&id={$row['id']}'>" . htmlspecialchars($row['name']) . "</a>",
    'amount' => fn($row) => '₹ ' . number_format($row['amount'] ?? 0, 2),
    'project_status' => fn($row) => match (strtolower($row['project_status'] ?? '')) {
      'active' => '<span class="badge bg-success">Active</span>',
      'on_hold' => '<span class="badge bg-warning text-dark">On Hold</span>',
      'completed' => '<span class="badge bg-primary">Completed</span>',
      'archived' => '<span class="badge bg-secondary">Archived</span>',
      default => '<span class="badge bg-light text-dark">Unknown</span>'
    },
    'status' => $statusBadge,
  ],
  'payments' => [

    'installment_no' => fn($row) =>
      "<span class='badge bg-secondary'>#" . htmlspecialchars($row['installment_no']) . "</span>",

    'amount' => fn($row) =>
      '₹ ' . number_format($row['amount'] ?? 0, 2),

    'due_date' => fn($row) =>
      "<span class='text-muted'>" . htmlspecialchars($row['due_date']) . "</span>",

    'paid_status' => fn($row) => payment_status($row['paid_status']),

    'status' => $statusBadge,
  ],
  // Tasks
  'tasks' => [
    'name' => fn($row) => "<a href='?page=task&id={$row['id']}'>" . htmlspecialchars($row['name']) . "</a>",
    'task_status' => fn($row) => match (strtolower($row['task_status'] ?? '')) {
      'todo' => '<span class="badge bg-secondary">To Do</span>',
      'in_progress' => '<span class="badge bg-info text-dark">In Progress</span>',
      'completed' => '<span class="badge bg-success">Completed</span>',
      'testing' => '<span class="badge bg-warning text-dark">Testing</span>',
      'deployed' => '<span class="badge bg-primary">Deployed</span>',
      default => '<span class="badge bg-light text-dark">Unknown</span>'
    },
    'progress' => fn($row) =>
      "<div class='progress' style='height: 8px;'>
         <div class='progress-bar' role='progressbar' style='width: {$row['progress']}%;'></div>
       </div>",
    'status' => $statusBadge,
  ],

  // Bugs
  'bugs' => [
    'description' => fn($row) =>
      "<a href='?page=bug&id={$row['id']}'>" . htmlspecialchars($row['description']) . "</a>",
    'bugs_status' => fn($row) => match (strtolower($row['bugs_status'] ?? '')) {
      'open' => '<span class="badge bg-danger">Open</span>',
      'fixed' => '<span class="badge bg-success">Fixed</span>',
      'closed' => '<span class="badge bg-secondary">Closed</span>',
      default => '<span class="badge bg-light text-dark">Unknown</span>'
    },
    'screenshot' => fn($row) =>
      !empty($row['screenshot'])
      ? "<img src='" . htmlspecialchars($row['screenshot']) . "' class='img-thumbnail' style='width:50px;height:50px;'>"
      : "<span class='text-muted'>No Screenshot</span>",
    'status' => $statusBadge,
  ],

  // Testing
  'testing' => [
    'test_case' => fn($row) =>
      "<a href='?page=testing&id={$row['id']}'>" . htmlspecialchars($row['test_case']) . "</a>",
    'result' => fn($row) => match (strtolower($row['result'] ?? '')) {
      'pass' => '<span class="badge bg-success">Pass</span>',
      'fail' => '<span class="badge bg-danger">Fail</span>',
      default => '<span class="badge bg-light text-dark">Unknown</span>'
    },
    'status' => $statusBadge,
  ],

  // Deployments
  'deployments' => [
    'notes' => fn($row) =>
      "<a href='?page=deployment&id={$row['id']}'>" . htmlspecialchars($row['notes']) . "</a>",
    'status' => $statusBadge,
  ],
];

if (in_array('status', $columns)) {
  $formatRules[$table]['status'] ??= $otherStatus;
}

if (in_array('date', $columns)) {
  $formatRules[$table]['date'] ??= fn($row) => isset($row['date']) && function_exists('dateFormate') ? dateFormate($row['date'], 'd M Y', 1) : ($row['date'] ?? '');
}

// ==========================================
// 2. DYNAMIC FILTER & WHERE CLAUSE BUILDER
// ==========================================
$whereParts = [];
$bindParams = [];

foreach ($filters as $key => $val) {
  // Standard system parameters aur query parameters ignore karein
  if (in_array($key, ['page', 'limit', 'sortby', 'orderby', 'module', 'table']) || $val === '') {
    continue;
  }

  $safeKey = strtolower(preg_replace('/[^a-zA-Z0-9_]/', '', $key));
  $safeVal = trim(urldecode($val));

  // LIKE filter (e.g., name_like)
  if (str_ends_with($safeKey, 'like')) {
    $col = str_replace('_like', '', $safeKey);
    $whereParts[] = "`$col` LIKE ?";
    $bindParams[] = "%$safeVal%";
    continue;
  }

  // Date range filters
  if ($safeKey === 'from_date') {
    $whereParts[] = "`date` >= ?";
    $bindParams[] = $safeVal;
    continue;
  }

  if ($safeKey === 'to_date') {
    $whereParts[] = "`date` <= ?";
    $bindParams[] = $safeVal;
    continue;
  }

  // Operator-based filters (e.g. amount>=, qty!=)
  if (preg_match('/(.+?)([!<>=]+)$/', $safeKey, $match)) {
    $col = preg_replace('/[^a-zA-Z0-9_]/', '', $match[1]);
    $op = $match[2];
    $whereParts[] = "`$col` $op ?";
    $bindParams[] = $safeVal;
    continue;
  }

  // Default equality filter
  $whereParts[] = "`$safeKey` = ?";
  $bindParams[] = $safeVal;
}
$checkPages = ['projects', 'tasks', 'payments', 'leads'];
if (in_array($table, $checkPages)) {
  $whereParts[] = "`uid` = ?";
  $bindParams[] = $uid ?? '';
}
if (isAdmin()) { // Sub-admin role check

}
$whereClause = implode(' AND ', $whereParts);

// ==========================================
// 3. FETCH DATA FROM DATABASE
// ==========================================
$response = secureDbAction($table, 4, [
  'columns' => '*',
  'orderBy' => "$orderby $sortby",
  'limit' => $limit
], $whereClause, $bindParams);
// testing area
// print_r($whereClause);
// Table configuration setup
$numericCols = $tableConfig['numeric_cols'] ?? ['total', 'balance', 'adjustment', 'amount'];
$totals = array_fill_keys($numericCols, 0);

$colBgClass = [
  'total' => 'bg-light  text-center',
  'balance' => 'bg-warning text-center',
  'adjustment' => 'bg-info text-center',
  'amount' => 'bg-success text-center'
];

echo "<table class='table table-sm table-hover' id='dynamicTable' title='" . htmlspecialchars($whereClause) . "'><thead class='bg-light'><tr>";

if (!$response['success'] || empty($response['data'])) {
  echo "<tr><th colspan='100%'>No data found</th></tr></thead><tbody></tbody></table>";
  exit;
}

$rows = $response['data'];
$allCols = array_keys($rows[0]);
$showCols = $columns ?: $allCols;

// Table Headers
echo "<th style='width:5%;text-align:center'><input type='checkbox' id='checkAll'></th>";
echo "<th>Actions</th>";
foreach ($showCols as $col) {
  $bgClass = $colBgClass[$col] ?? '';
  echo "<th class='$bgClass text-dark'>" . ucfirst(str_replace('_', ' ', $col)) . "</th>";
}
echo "</tr></thead><tbody>";

// ==========================================
// 4. MAP DATA CACHING (RELATIONSHIPS)
// ==========================================
$mapCache = [];
$mapTables = [];
if (!empty($tableConfig['fields'])) {
  foreach ($tableConfig['fields'] as $field) {
    if (!isset($field['map']))
      continue;

    $colName = $field['name'];
    $mapTable = $field['map']['table'];
    $mapTables[$colName] = $mapTable;
    $mapKey = $field['map']['key'];
    $mapLabel = $field['map']['label'];

    $mapRes = secureDbAction($mapTable, 4, [
      'columns' => "$mapKey, $mapLabel",
      'orderBy' => "$mapLabel ASC"
    ]);

    $mapCache[$colName] = [];
    foreach ($mapRes['data'] ?? [] as $m) {
      $mapCache[$colName][$m[$mapKey]] = $m[$mapLabel];
    }
  }
}

// ==========================================
// 5. RENDER TABLE ROWS
// ==========================================
foreach ($rows as $row) {
  echo "<tr>";
  echo "<td style='width:5%;text-align:center;padding-right:15px'><input type='checkbox' class='row-check' value='{$row['id']}'></td>";

  $id = intval($row['id'] ?? 0);
  $status = intval($row['status'] ?? 0);

  // Default Action Flags (Can be configured in crud-config.php)
  $editAction = $tableConfig['actions']['edit'] ?? 1;
  $deleteAction = $tableConfig['actions']['delete'] ?? 1;
  $blockAction = $tableConfig['actions']['block'] ?? 1;
  echo "<td>";

  // Optional: Render custom action buttons dynamically via callback/config if defined
  if (isset($tableConfig['custom_actions']) && is_callable($tableConfig['custom_actions'])) {
    echo $tableConfig['custom_actions']($row);
  }
  if ($table == 'roles') {
    echo '<a href="?page=permissions-view&rId=' . htmlspecialchars($row['id']) . '" class="btn btn-warning btn-xs"><i class="fa fa-lock nav-icon"></i></a>';

  }
  if ($table == 'tasks') {
    echo '<a href="?page=payments&task_id=' . htmlspecialchars($row['id']) . '" class="btn btn-info me-1"><i class="fa fa-rupee nav-icon"></i></a>';

  }
  // Default CRUD actions call
  if (function_exists('LogisticActions')) {
    LogisticActions($id, $editAction, $deleteAction, $blockAction, $status, $row);
  }

  echo "</td>";

  // Render Data Columns
  foreach ($showCols as $col) {
    $rawValue = $row[$col] ?? null;
    $rawKey = (string) $rawValue;
    $bgClass = $colBgClass[$col] ?? '';

    if (in_array($col, $numericCols) && is_numeric($rawValue)) {
      $totals[$col] += $rawValue;
    }

    if (isset($mapCache[$col]) && isset($mapCache[$col][$rawKey])) {
      // Priority 1: Mapped lookup value
      $targetPage = $mapTables[$col] ?? $table;
      echo "<td class='$bgClass'><a href='?page={$targetPage}&id={$rawKey}'>" . htmlspecialchars($mapCache[$col][$rawKey]) . "</a></td>";
    } elseif (isset($formatRules[$table][$col])) {
      // Priority 2: Custom format function
      echo "<td class='$bgClass'>" . $formatRules[$table][$col]($row) . "</td>";
    } elseif ($rawValue !== null) {
      // Priority 3: Raw value
      echo "<td class='$bgClass text-dark'>" . htmlspecialchars($rawValue) . "</td>";
    } else {
      // Priority 4: Empty cell
      echo "<td></td>";
    }
  }

  echo "</tr>";
}

// ==========================================
// 6. RENDER FOOTER (TOTALS)
// ==========================================
echo "</tbody>";
echo "<tfoot><tr>";
echo "<td></td><td></td>";

foreach ($showCols as $col) {
  $bgClass = $colBgClass[$col] ?? '';
  if (in_array($col, $numericCols)) {
    $label = strtoupper(str_replace('_', ' ', $col));
    $formattedValue = function_exists('num_formate_deci') ? num_formate_deci($totals[$col]) : number_format($totals[$col], 2);
    echo "<td class='text-center $bgClass text-dark'><b>$label</b><br>" . $formattedValue . "</td>";
  } else {
    echo "<td></td>";
  }
}

echo "</tr></tfoot>";
echo "</table>";
function LogisticActions($id, $isE = 1, $isD = 1, $isB = 1, $status = 1, $data = [])
{
  $status = intval($status ?? 0);
  $statusLabel = $status ? 'eye-slash' : 'eye';
  $statusClass = $status ? 'warning' : 'success';

  // Encode $data for JS (safe JSON string)
  $jsonData = json_encode($data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

  echo "<div class='btn-group'>";

  // 🔒 Block/Unblock — Only Admins
  if ($isB && function_exists('isAdmin') && isAdmin()) {
    echo "<button class='btn btn-xs btn-light text-$statusClass' onclick='toggleStatus($id, $status)' title='Toggle status'>
                <i class='fa fa-$statusLabel nav-icon'></i>
              </button>";
  }

  // ✏️ Edit — Admins and Users
  if ($isE && (function_exists('isAdmin') && isAdmin() || function_exists('isUser') && isUser())) {
    echo "<button class='btn btn-xs btn-light' onclick='openModal($id, $jsonData)' title='Edit'>
                <i class='fa fa-pencil-square nav-icon text-info'></i>
              </button>";
  }

  // 🗑️ Delete — Only Admins
  if ($isD && function_exists('isAdmin') && isAdmin()) {
    echo "<button class='btn btn-sm btn-light' onclick='deleteCrud($id)' title='Delete'>
                <i class='fa fa-trash nav-icon text-danger'></i>
              </button>";
  }

  echo "</div>";
}