<?php

declare(strict_types=1);

$autoload = dirname(__DIR__) . '/vendor/autoload.php';

if (!is_file($autoload)) {
    http_response_code(500);
    exit('Composer autoloader not found. Run composer install from the project root.');
}

require $autoload;

$checks = [
    'RpAction autoloaded' => class_exists(Rahul\Helpers\RpAction::class),
    'ZohoBooks autoloaded' => class_exists(Rahul\Helpers\ZohoBooks::class),
    'safePost default works' => Rahul\Helpers\RpAction::safePost('__rpfiles_example_missing__', 'Guest') === 'Guest',
];

$allPassed = !in_array(false, $checks, true);
$customers = [];
$customerColumns = [];
$customerError = null;

try {
    $customerResult = Rahul\Helpers\RpAction::secureDbAction(
        'customers',
        4,
        ['columns' => '*', 'limit' => 20]
    );

    if (!$customerResult['success']) {
        $customerError = $customerResult['error'] ?? 'The customer query failed.';
    } else {
        $customers = $customerResult['data'];
        if ($customers !== []) {
            $displayableColumns = [
                'id',
                'contact_id',
                'contact_name',
                'name',
                'company_name',
                'email',
                'phone',
                'mobile',
            ];
            $customerColumns = array_values(array_intersect(
                $displayableColumns,
                array_keys($customers[0])
            ));
        }
    }
} catch (Throwable $exception) {
    $customerError = $exception->getMessage();
}

$escape = static fn($value): string => htmlspecialchars(
    is_scalar($value) ? (string) $value : '',
    ENT_QUOTES,
    'UTF-8'
);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>rpfiles local test</title>
</head>
<body>
    <h1>rpfiles local test</h1>
    <p><?= $allPassed ? 'All local checks passed.' : 'One or more local checks failed.' ?></p>
    <ul>
        <?php foreach ($checks as $check => $passed): ?>
            <li><?= htmlspecialchars($check, ENT_QUOTES, 'UTF-8') ?>: <?= $passed ? 'PASS' : 'FAIL' ?></li>
        <?php endforeach; ?>
    </ul>

    <h2>Customers table (first 20 rows)</h2>
    <?php if ($customerError !== null): ?>
        <p role="alert">Could not load customers: <?= $escape($customerError) ?></p>
        <p>Check that DB_HOST, DB_USER, DB_PASS, and DB_NAME are configured and the database has a customers table.</p>
    <?php elseif ($customers === []): ?>
        <p>No customer records found.</p>
    <?php elseif ($customerColumns === []): ?>
        <p>Customer rows were found, but no recognized display columns are available. Expected one of: id, contact_id, contact_name, name, company_name, email, phone, mobile.</p>
    <?php else: ?>
        <table border="1" cellpadding="6">
            <thead>
                <tr>
                    <?php foreach ($customerColumns as $column): ?>
                        <th><?= $escape($column) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($customers as $customer): ?>
                    <tr>
                        <?php foreach ($customerColumns as $column): ?>
                            <td><?= $escape($customer[$column] ?? '') ?></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <p>This page reads at most 20 rows and does not call Zoho. Run it only on your local machine.</p>
</body>
</html>
