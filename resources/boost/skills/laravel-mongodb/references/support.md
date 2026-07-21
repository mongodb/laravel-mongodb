# Support and Issue Reporting

Before opening an issue, search existing issues and pull requests in the relevant repository — the bug may already be reported or fixed.

Report issues to the repository that owns the layer where the bug occurs:

| Layer                                            | Repository |
|--------------------------------------------------|---|
| Eloquent integration, Laravel-specific behaviour | [mongodb/laravel-mongodb](https://github.com/mongodb/laravel-mongodb/issues) |
| PHP query API, BSON, GridFS                      | [mongodb/mongo-php-library](https://github.com/mongodb/mongo-php-library/issues) |
| C driver, connection, PHP extension              | [mongodb/mongo-php-driver](https://github.com/mongodb/mongo-php-driver/issues) |
| Documentation                                    | Submit feedback using the **Rate this page** tab on the relevant [docs](https://www.mongodb.com/docs/php-library/current/) page |

When filing a new issue, include a minimal reproducer: the smallest possible code that demonstrates the bug, the PHP and package versions, and the error message or unexpected output.

If an existing issue matches, add a comment with your reproducer — it helps maintainers confirm the scope and prioritise a fix.

## Diagnosing the layer

- **`MongoDB\Laravel\*` class or Eloquent method** → `laravel-mongodb`
- **`MongoDB\Driver\*` or `ext-mongodb` error** → `mongo-php-driver`
- **`MongoDB\*` (no `Laravel`) class** → `mongo-php-library`
- **Wrong page in the docs** → `docs`
