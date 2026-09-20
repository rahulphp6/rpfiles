<?php
include_once "../core/config.php";

$table = $_REQUEST['table'] ?? '';
$columns = $_REQUEST['columns'] ?? [];
$action = $_REQUEST['action'] ?? '';
$uid = $_SESSION['id'] ?? null;
$user_id = $_SESSION['user_id'] ?? '';
// ==========================================
// 1. GENERIC OPTIONS BUILDERS
// ==========================================
$monthOptions = [];
$yearOptions = [];

for ($i = (int) date('Y') - 3; $i <= (int) date('Y') + 5; $i++) {
    $yearOptions[] = ['value' => (string) $i, 'label' => (string) $i];
}
for ($i = 1; $i <= 12; $i++) {
    $monthOptions[] = ['value' => (string) $i, 'label' => date('F', mktime(0, 0, 0, $i, 10))];
}
$order_status = $order_status ?? [];
$apiStatus = $apiStatus ?? [];
$lastQt = secureDbAction('quotes', 4, ['columns' => 'id', 'orderBy' => 'id DESC', 'limit' => 1]);
$nextId = (isset($lastQt['data'][0]['id']) ? $lastQt['data'][0]['id'] : 0) + 1;
$quoteNo = invoiceNo($nextId, 5, 'QT-');

$commonFields = [
    'status' => [
        'name' => 'status',
        'label' => 'Status',
        'type' => 'select',
        'options' => [
            ['value' => '1', 'label' => 'Active'],
            ['value' => '0', 'label' => 'Inactive']
        ],
        'filterable' => true
    ],
    'date' => [
        'name' => 'date',
        'label' => 'Date',
        'type' => 'date',
        'value' => date('Y-m-d'),
        'filterable' => false
    ],
];

// Generic helper for foreign key relationship mappings
function foreignKeyField($label, $sourceTable, $key = 'id', $display = 'name', $extra = [])
{
    return array_merge([
        'label' => $label,
        'type' => 'select',
        'source' => $sourceTable,
        'valueKey' => $key,
        'labelKey' => $display,
        'map' => [
            'table' => $sourceTable,
            'key' => $key,
            'label' => $display
        ],
    ], $extra);
}

// Data Type Defaults
$defaults = [
    'text' => ['type' => 'text', 'filterable' => true],
    'number' => ['type' => 'number', 'filterable' => false],
    'date' => ['type' => 'date', 'filterable' => false],
    'datetime' => ['type' => 'datetime', 'filterable' => false],
    'select' => ['type' => 'select', 'filterable' => true],
    'hidden' => ['type' => 'hidden', 'filterable' => false],
];
$createdBy = ['label' => 'created_by', 'value' => $uid ?? 0, "type" => 'hidden'];
if (isAdmin()) {
    $createdBy = foreignKeyField('Created By', 'users', 'id', 'name');
}
// ==========================================
// 3. GENERIC FIELD REGISTRY (Fixed user_id & role_id)
// ==========================================
$fieldRegistry = [
    'created_by' => $createdBy,
    'amount' => ['label' => 'Amount'] + $defaults['number'] + ['step' => '0.01'],
    'total' => ['label' => 'Total'] + $defaults['number'] + ['step' => '0.01'],
    'date' => $commonFields['date'],
    'created_at' => ['label' => 'Created At'] + $defaults['datetime'],
    'role_id' => foreignKeyField('Role', 'roles', 'id', 'name'),
    'dep_id' => foreignKeyField('dep_id', 'departments', 'id', 'name'),
    'parent' => foreignKeyField('parent', 'menu', 'id', 'name'),
    'client_id' => foreignKeyField('lead_id', 'leads', 'id'),
    'project_id' => foreignKeyField('project_id', 'projects', 'id'),
    'assigned_to' => foreignKeyField('Assigned To', 'users', 'id', 'name'),
    'task_history' => foreignKeyField('task_id', 'task_history', 'id', 'name'),
    // Common fields
    'name' => ['label' => 'Name'] + $defaults['text'],
    'title' => ['label' => 'Title'] + $defaults['text'],
    'email' => ['label' => 'Email', 'type' => 'email', 'filterable' => true],
    'phone' => ['label' => 'Phone', 'type' => 'tel', 'filterable' => true],
    'description' => ['label' => 'Description', 'type' => 'textarea'],
    // Leads
    'client_name' => ['label' => 'Client Name'] + $defaults['text'],
    'website' => ['label' => 'Website', 'type' => 'url'],
    'project_name' => ['label' => 'Project Name'] + $defaults['text'],
    'lead_status' => ['label' => 'Lead Status'] + $defaults['select'] + [
        'options' => [
            ['value' => 'new', 'label' => 'New'],
            ['value' => 'contacted', 'label' => 'Contacted'],
            ['value' => 'converted', 'label' => 'Converted'],
            ['value' => 'lost', 'label' => 'Lost'],
        ]
    ],

    // Projects
    'payment_terms' => ['label' => 'Payment Terms', 'type' => 'textarea'],
    'policy' => ['label' => 'Policy', 'type' => 'textarea'],
    'file' => ['label' => 'File', 'type' => 'file'],
    'reference' => ['label' => 'Reference'] + $defaults['text'],
    'reference_link' => ['label' => 'Reference Link'] + $defaults['text'],
    'project_status' => ['label' => 'Project Status'] + $defaults['select'] + [
        'options' => [
            ['value' => 'active', 'label' => 'Active'],
            ['value' => 'on_hold', 'label' => 'On Hold'],
            ['value' => 'completed', 'label' => 'Completed'],
            ['value' => 'archived', 'label' => 'Archived'],
        ]
    ],
    // payments
    'installment_no' => ['label' => 'Installment No'] + $defaults['number'],
    'paid_date' => ['label' => 'Paid Date'] + $defaults['date'],
    'due_date' => ['label' => 'Due Date'] + $defaults['date'],
    'paid_status' => ['label' => 'Paid Status'] + $defaults['select'] + [
        'options' => [
            ['value' => 'pending', 'label' => 'Pending'],
            ['value' => 'paid', 'label' => 'Paid'],
            ['value' => 'overdue', 'label' => 'Overdue'],
        ]
    ],
    'notes' => ['label' => 'Notes', 'type' => 'textarea'],
    // Tasks
    'priority' => ['label' => 'Priority'] + $defaults['select'] + [
        'options' => [
            ['value' => 'low', 'label' => 'Low'],
            ['value' => 'medium', 'label' => 'Medium'],
            ['value' => 'high', 'label' => 'High'],
        ]
    ],
    'task_status' => ['label' => 'Task Status'] + $defaults['select'] + [
        'options' => [
            ['value' => 'todo', 'label' => 'To Do'],
            ['value' => 'in_progress', 'label' => 'In Progress'],
            ['value' => 'completed', 'label' => 'Completed'],
            ['value' => 'testing', 'label' => 'Testing'],
            ['value' => 'deployed', 'label' => 'Deployed'],
        ]
    ],
    'progress' => ['label' => 'Progress'] + $defaults['number'] + ['step' => '1'],
    'deadline' => ['label' => 'Deadline'] + $defaults['date'],

    // Bugs
    'task_id' => foreignKeyField('Task', 'tasks', 'id', 'name'),
    'screenshot' => ['label' => 'Screenshot', 'type' => 'file'],
    'severity' => ['label' => 'Severity'] + $defaults['select'] + [
        'options' => [
            ['value' => 'low', 'label' => 'Low'],
            ['value' => 'medium', 'label' => 'Medium'],
            ['value' => 'high', 'label' => 'High'],
        ]
    ],
    'bugs_status' => ['label' => 'Bug Status'] + $defaults['select'] + [
        'options' => [
            ['value' => 'open', 'label' => 'Open'],
            ['value' => 'fixed', 'label' => 'Fixed'],
            ['value' => 'closed', 'label' => 'Closed'],
        ]
    ],
    'reported_by' => foreignKeyField('Reported By', 'users', 'id', 'name'),
    'fixed_by' => foreignKeyField('Fixed By', 'users', 'id', 'name'),

    // Testing
    'test_case' => ['label' => 'Test Case', 'type' => 'textarea'],
    'result' => ['label' => 'Result'] + $defaults['select'] + [
        'options' => [
            ['value' => 'pass', 'label' => 'Pass'],
            ['value' => 'fail', 'label' => 'Fail'],
        ]
    ],
    'tested_by' => foreignKeyField('Tested By', 'users', 'id', 'name'),

    // Deployments
    'deployed_by' => foreignKeyField('Deployed By', 'users', 'id', 'name'),
];


function fields(...$names)
{
    global $fieldRegistry, $commonFields;

    $opts = ['includeStatus' => true, 'includeDate' => true];

    // Check if optional config parameters are passed at the end
    if (is_array(end($names)) && isset(end($names)['__config'])) {
        $opts = array_merge($opts, array_pop($names)['__config']);
    }

    $base = [];
    foreach ($names as $entry) {
        if (is_array($entry)) {
            $name = $entry['name'];
            $base[] = array_merge($fieldRegistry[$name] ?? ['label' => ucfirst(str_replace('_', ' ', $name))], $entry);
        } else {
            $base[] = ['name' => $entry] + ($fieldRegistry[$entry] ?? ['label' => ucfirst(str_replace('_', ' ', $entry))]);
        }
    }

    if ($opts['includeStatus']) {
        $base[] = $commonFields['status'];
    }
    if ($opts['includeDate']) {
        $base[] = $commonFields['date'];
    }

    return $base;
}

$config = [
    'users' => ['fields' => fields('name', 'email', 'phone', 'role_id', 'dep_id')],
    'menu' => [
        'fields' => array_merge(fields('name', 'icon', 'link', 'addnew', 'parent'), [
            [
                'name' => 'sort',
                'label' => 'Sort Order',
                'type' => 'number',
                'filterable' => false
            ],
            [
                'name' => 'is_active',
                'label' => 'Enable',
                'type' => 'select',
                'options' => [
                    ['value' => '0', 'label' => 'ALL'],
                    ['value' => '1', 'label' => 'Admin'],
                    ['value' => '2', 'label' => 'Users'],
                    ['value' => '3', 'label' => 'Super Admin'],
                ],
            ],

        ])
    ],
    'ticket' => [
        'fields' => array_merge(
            fields('user_id', 'customer_id', 'location_id'),
            [
                ['name' => 'title', 'label' => 'Title', 'type' => 'text'],
                ['name' => 'query', 'label' => 'Query', 'type' => 'textarea']
            ]
        )
    ],
    'roles' => [
        'fields' => array_merge(
            fields('name'),
            [['name' => 'description', 'label' => 'Description', 'type' => 'textarea']]
        )
    ],
    'departments' => [
        'fields' => fields('name')
    ],

    'theme' => ['fields' => fields('name')],
    'permissions' => ['fields' => fields('name', 'description')],
    'leads' => ['fields' => fields('name', 'phone', 'website', 'project_name', 'lead_status')],
    'projects' => ['fields' => fields('client_id', 'name', 'amount', 'payment_terms', 'policy', 'file', 'reference', 'reference_link', 'project_status')],
    'payments' => ['fields' => fields('project_id', 'task_id', 'installment_no', 'amount', 'paid_date', 'due_date', 'paid_status', 'notes')],
    'tasks' => ['fields' => fields('project_id', 'name', 'description', 'assigned_to', 'priority', 'task_status', 'progress', 'task_history', 'deadline')],
    'bugs' => ['fields' => fields('task_id', 'description', 'screenshot', 'severity', 'bugs_status', 'reported_by', 'fixed_by')],
    'testing' => ['fields' => fields('task_id', 'test_case', 'result', 'tested_by')],
    'deployments' => ['fields' => fields('project_id', 'task_id', 'deployed_by', 'notes')],
];
// ==========================================
// 6. UNIFIED FILTER ACTION & DYNAMIC FALLBACK
// ==========================================
if ($action === 'filter') {
    $static = $config[$table]['fields'] ?? [];

    if (!empty($static)) {
        header('Content-Type: application/json');
        echo json_encode(['fields' => $static]);
        exit;
    }

    $metaRes = function_exists('secureDbAction') ? secureDbAction($table, 5) : ['success' => false];
    $allCols = $metaRes['data'] ?? [];

    if (!$metaRes['success'] || empty($allCols)) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'No columns found for table: ' . htmlspecialchars($table)]);
        exit;
    }

    $fields = [];
    foreach ($allCols as $name) {
        if (!empty($columns) && !in_array($name, $columns)) {
            continue;
        }

        $type = 'text';
        if (str_contains($name, 'date') || str_contains($name, '_at')) {
            $type = 'date';
        } elseif (str_contains($name, 'status') || str_contains($name, 'is_')) {
            $type = 'select';
        } elseif (str_contains($name, 'id') || str_contains($name, 'amount') || str_contains($name, 'qty') || str_contains($name, 'count')) {
            $type = 'number';
        } elseif (str_contains($name, 'email')) {
            $type = 'email';
        } elseif (str_contains($name, 'phone') || str_contains($name, 'mobile')) {
            $type = 'tel';
        }

        $fields[] = [
            'name' => $name,
            'label' => ucfirst(str_replace('_', ' ', $name)),
            'type' => $type,
            'required' => false,
            'filterable' => true,
            'placeholder' => "Enter " . ucfirst(str_replace('_', ' ', $name))
        ];
    }

    header('Content-Type: application/json');
    echo json_encode(['fields' => $fields]);
    exit;
}