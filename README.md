# rpfiles

`rahulphp6/rpfiles` is a PHP helper library with MySQL utilities and a Zoho
Books API client.

For a more visual, browser-friendly guide, open the
[HTML documentation](docs/index.html).

## Requirements

- PHP 8.0 or later
- PHP extensions: `curl` and `mysqli`

## Installation

If the package is not published on Packagist, register its GitHub repository
with Composer before requiring it:

```sh
composer config repositories.rpfiles vcs https://github.com/rahulphp6/rpfiles
composer require rahulphp6/rpfiles:dev-main
```

Load Composer's autoloader in your application:

```php
require __DIR__ . '/vendor/autoload.php';
```

## Configuration

`RpAction::OpenCon()` reads these values from `$_ENV`:

| Variable | Description | Default |
| --- | --- | --- |
| `DB_HOST` | MySQL server host | `localhost` |
| `DB_USER` | MySQL username | `root` |
| `DB_PASS` | MySQL password | Empty |
| `DB_NAME` | Default database name | `logistic_db` |

Set these values in your application bootstrap or load them with your
application's environment-variable loader. This library does not load `.env`
files itself. You can also pass a database name to `OpenCon($databaseName)`.

Zoho Books methods additionally need valid Zoho OAuth credentials and
organization configuration. The token helpers currently read application
constants such as `client_idb`, `client_secretb`, `BASE`, `organizationId`,
and `common_db`, plus token data from the application's `token` table. Define
and protect these values in the consuming application; do not commit secrets.

## Usage

### Simple PHP and database CRUD (no MVC framework required)

```php
use Rahul\Helpers\RpAction;

// Configure DB_HOST, DB_USER, DB_PASS, and DB_NAME as described above.
// OpenCon() is optional when using secureDbAction(); it opens its own connection.
$connection = RpAction::OpenCon();
```

`RpAction` is a helper library, not an MVC framework. A normal PHP page can
read form data, call a helper, and render the result. It does not create tables
or migrations: create the database and table first. For example, the snippets
below assume a `customers` table with `id` (auto-increment), `name`, and `email`
columns.

```sql
CREATE TABLE customers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(254) NOT NULL
);
```

`secureDbAction($table, $action, $data, $where, $params, $db)` performs
insert/update/delete/select with prepared statements. The action codes are:

| Code | Action | Result |
| --- | --- | --- |
| `1` | Insert | `['success' => true, 'insert_id' => ...]` |
| `2` | Update | Success status and affected-row count |
| `3` | Delete | Success status and affected-row count |
| `4` | Select | `['success' => true, 'data' => [...]]` |
| `5` | List table column names | `['success' => true, 'data' => [...]]` |

Pass data values and values for `?` placeholders through the arguments; do not
put request values directly into SQL. Table/column names and SQL fragments
(`$where`, `orderBy`, and selected columns) are query structure, not bindable
values. Keep those fragments fixed or allow-list them in your application.
`$where` is written without the `WHERE` keyword.

#### Create a record

```php
$name = RpAction::safePost('name', '', false);
$email = RpAction::safePost('email', '', false);

if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    // Show a validation message; validation rules belong to the application.
} else {
    $result = RpAction::secureDbAction('customers', 1, [
        'name' => $name,
        'email' => $email,
    ]);

    if ($result['success']) {
        $newCustomerId = $result['insert_id'];
    } else {
        // Log/report the failure safely; do not show database details to visitors.
    }
}
```

#### Read records

```php
$result = RpAction::secureDbAction(
    'customers',
    4,
    ['columns' => 'id, name, email', 'orderBy' => 'id DESC', 'limit' => 20],
    'email = ?',
    ['person@example.com']
);

$customers = $result['success'] ? $result['data'] : [];
foreach ($customers as $customer) {
    echo htmlspecialchars($customer['name'], ENT_QUOTES, 'UTF-8');
}
```

The `columns`, `orderBy`, and `limit` options are optional. `offset` can be
provided for pagination. Escape values when rendering HTML, even if they came
from the database.

#### Update a record

```php
$customerId = RpAction::safePostInt('id');
$name = RpAction::safePost('name', '', false);

if ($customerId > 0 && $name !== '') {
    $result = RpAction::secureDbAction(
        'customers',
        2,
        ['name' => $name],
        'id = ?',
        [$customerId]
    );
}
```

#### Delete a record

```php
$customerId = RpAction::safePostInt('id');

if ($customerId > 0) {
    $result = RpAction::secureDbAction(
        'customers',
        3,
        [],
        'id = ?',
        [$customerId]
    );
}
```

For delete, `$data` is unused (pass `[]`); the condition and its bound values
are passed as `$where` and `$params`. Use a POST form and confirm destructive
actions in your application.

#### Reading request values

```php
$search = RpAction::safeGet('search', '');
$page = RpAction::safeGetInt('page', 1);
$active = RpAction::safePostBool('active');
```

`safePost()` and `safeGet()` read a request field, trim strings, and by default
HTML-escape string values. Pass `false` as the third argument when you need the
raw string for validation/storage; then continue to use prepared statements
for database values and context-appropriate escaping for output. The `*Int`
helpers cast to integers. The `*Bool` helpers return true only for the string
`'1'` or `'true'`.

Other useful helpers include `OpenCon($databaseName)` for a MySQL connection,
`getRowById($table, $id)` for a single row, and `redirectTo($path)` for a
redirect. Older helpers such as `dbAction()`, `queryRun()`, `getRow()`,
`getName()`, `getTotal()`, and `table_sum()` accept SQL fragments or build
queries from identifiers; treat them as legacy convenience helpers and pass
only trusted, application-controlled SQL fragments to them.

#### Other `RpAction` helpers

| Helper(s) | Purpose |
| --- | --- |
| `isAdmin()`, `isUser()`, `checkUser()`, `isShop()`, `isSuper()` | Boolean role checks based on the current session's `role`; `isUser('location')` looks up and returns a user's location ID. |
| `isPermMode($role, $moduleKey)`, `isModCheck($name)` | Look up application permissions using the `permission_role` and `permissions` tables. These expect the library's application-specific schema. |
| `renderDropdown(...)`, `renderColName(...)` | Query and output a database-backed HTML select; the optional quick-add button expects matching front-end UI code. |
| `mgs(...)`, `alert(...)` | Output HTML or JavaScript alerts; only pass trusted or correctly escaped messages. |
| `flattenStdClass(...)`, `array_map_deep(...)`, `is_multi(...)`, `progress(...)` | General data and display utilities. |
| `filterThis(...)` | Legacy MySQL string escaping helper; prefer prepared statements for SQL values. |
| `lastId(...)`, `colSum(...)`, `getTotal(...)`, `table_sum(...)` | Legacy database lookup/aggregate helpers; use only with trusted table/column/condition fragments. |

The role and permission helpers rely on the consuming application's session
and database conventions. Establish and protect the session yourself, and
enforce authorization on the server for each action; hiding a button or
rendering a dropdown is not access control.

### Zoho Books

```php
use Rahul\Helpers\ZohoBooks;

$client = new ZohoBooks($accessToken);
$response = $client->request('contacts', 'GET');
```

`ZohoBooks` also provides OAuth token exchange/refresh and application-specific
helpers for retrieving and refreshing tokens and making Books API requests.
Those helpers depend on the constants and database schema described above.

## Development

```sh
composer install
composer test
```

The smoke test checks that Composer can autoload the library classes; it does
not connect to a database or call Zoho.

To try the browser example locally, configure your MySQL connection in
PowerShell and start the server from the project root:

```powershell
$env:DB_HOST = "127.0.0.1"
$env:DB_USER = "root"
$env:DB_PASS = "your-local-password"
$env:DB_NAME = "your-database-name"
php -S 127.0.0.1:8000 -t examples
```

Replace the example credentials with your local MySQL settings. `DB_NAME` must
be the database that contains your `customers` table. Open
`http://127.0.0.1:8000/` in your browser. The example checks Composer
autoloading and displays at most 20 customer rows. Stop the server with
`Ctrl+C` when finished.

## Security

`secureDbAction()` uses prepared statements for bound values, which helps
prevent SQL injection through those values. This protection does not extend to
table/column names or SQL fragments; keep those trusted and fixed. The
`safePost*`/`safeGet*` helpers are input-reading/convenience helpers, not a
complete security layer. They do not authenticate users, check permissions,
validate business rules, or provide CSRF protection. Add those controls in
your application for every write endpoint. Escape output for its context
(especially HTML), keep database/OAuth credentials private, and never expose
raw SQL, database errors, or sensitive query data to visitors. Legacy helpers
that accept raw SQL fragments are not a substitute for prepared statements.

## License

This project is available under the [MIT License](LICENSE).
