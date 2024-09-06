<?php

use Rector\Config\RectorConfig;
use Rector\Php71\Rector\FuncCall\RemoveExtraParametersRector;
use Rector\Php74\Rector\Closure\ClosureToArrowFunctionRector;
use Rector\Php80\Rector\FunctionLike\MixedTypeRector;
use Rector\Php81\Rector\FuncCall\NullToStrictStringFuncCallArgRector;
use Rector\TypeDeclaration\Rector\Closure\AddClosureVoidReturnTypeWhereNoReturnRector;

return RectorConfig::configure()
    ->withPaths([
        __FILE__,
        __DIR__ . '/docs',
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->withPhpSets()
    ->withTypeCoverageLevel(0)
    ->withSkip([
        RemoveExtraParametersRector::class,
        ClosureToArrowFunctionRector::class,
        NullToStrictStringFuncCallArgRector::class,
        MixedTypeRector::class,
        AddClosureVoidReturnTypeWhereNoReturnRector::class,
    ]);
