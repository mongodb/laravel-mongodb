# Installation

## Prerequisites

The `mongodb/laravel-mongodb` package requires the `ext-mongodb` PHP extension.

### Install the PHP extension

**Ubuntu / Debian (recommended):**

```bash
sudo apt install php-mongodb
```

**macOS (Homebrew):**

```bash
brew install php && brew install --build-from-source php-mongodb
```

**pie (cross-platform PHP extension installer):**

```bash
pie install mongodb/mongodb-extension
```

**PECL (last resort):**

```bash
pecl install mongodb
```

**Docker** — add to your `Dockerfile`:

```dockerfile
RUN pecl install mongodb && docker-php-ext-enable mongodb
```

### Install the Composer package

```bash
composer require mongodb/laravel-mongodb
```

The Laravel service provider is auto-discovered. No manual registration needed.

### Configure the connection

Add a `mongodb` entry in `config/database.php`:

```php
'connections' => [
    'mongodb' => [
        'driver'   => 'mongodb',
        'dsn'      => env('MONGODB_URI', 'mongodb://localhost:27017'),
        'database' => env('MONGODB_DATABASE', 'laravel'),
    ],
],
```

Set the environment variables in `.env`:

```bash
MONGODB_URI=mongodb://localhost:27017
MONGODB_DATABASE=laravel
```
