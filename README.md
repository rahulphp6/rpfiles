# rpfiles

`rahulphp6/rpfiles` is a PHP helper library with MySQL utilities and a Zoho
Books API client.

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

### Database helpers

```php
use Rahul\Helpers\RpAction;

$connection = RpAction::OpenCon();

$username = RpAction::safePost('username', 'Guest');
$user = RpAction::getRowById('users', 1);
```

`secureDbAction()` supports insert, update, delete, select, and table-metadata
operations using action codes `1` through `5`. Bind data values through its
`$params` argument. Table/column names and SQL fragments such as `where` and
`orderBy` are not values and must only come from trusted application code.
Legacy helpers such as `dbAction()` and `queryRun()` also accept raw SQL
fragments; never pass untrusted request data into those fragments.

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

Keep database and OAuth credentials private. Validate inputs and authorize
requests in the consuming application. Review SQL-fragment parameters and
HTML/JavaScript output carefully; escaping and parameterization depend on the
output context and the specific helper used.

## License

This project is available under the [MIT License](LICENSE).
