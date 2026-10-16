<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Ticket;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use MongoDB\Laravel\Eloquent\SoftDeletes;
use MongoDB\Laravel\Tests\TestCase;

use function dirname;
use function proc_close;
use function proc_open;
use function restore_error_handler;
use function set_error_handler;
use function stream_get_contents;
use function var_export;

use const E_USER_DEPRECATED;
use const PHP_BINARY;

/** @see https://github.com/mongodb/laravel-mongodb/issues/3449 */
class GH3449Test extends TestCase
{
    public function testRequiringTheTraitFileDoesNotDeprecate(): void
    {
        $code = 'require ' . var_export(dirname(__DIR__, 2) . '/vendor/autoload.php', true) . ';'
            . 'set_error_handler(static function (int $severity, string $message): bool {'
            . ' if ($severity === E_USER_DEPRECATED) { fwrite(STDERR, $message); exit(2); }'
            . ' return false; });'
            . 'require ' . var_export(dirname(__DIR__, 2) . '/src/Eloquent/SoftDeletes.php', true) . ';';

        $pipes = [];
        $process = proc_open(
            [PHP_BINARY, '-d', 'display_errors=stderr', '-r', $code],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );

        $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
        $exitCode = proc_close($process);

        self::assertSame(0, $exitCode, $output);
        self::assertSame('', $output);
    }

    public function testTraitDeprecatesWhenTheModelBoots(): void
    {
        $deprecations = [];
        set_error_handler(static function (int $severity, string $message) use (&$deprecations): bool {
            if ($severity === E_USER_DEPRECATED) {
                $deprecations[] = $message;

                return true;
            }

            return false;
        });

        try {
            $model = new DeprecatedSoftDeletesModel();
        } finally {
            restore_error_handler();
        }

        self::assertSame(['Since mongodb/laravel-mongodb:5.5, trait "MongoDB\Laravel\Eloquent\SoftDeletes" is deprecated, use "Illuminate\Database\Eloquent\SoftDeletes" instead.'], $deprecations);
        self::assertSame('deleted_at', $model->getQualifiedDeletedAtColumn());
        self::assertTrue(DeprecatedSoftDeletesModel::hasGlobalScope(SoftDeletingScope::class));
    }
}

class DeprecatedSoftDeletesModel extends Model
{
    use SoftDeletes;

    protected $table = 'deprecated_soft_deletes';
}
