<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

/*
 * Rector configuration.
 *
 * withComposerBased() reads composer.lock and enables only the rules for
 * the installed versions of Symfony and PHPUnit. The rules then stay in step
 * with the dependencies, and nobody has to update a version here.
 *
 * The prepared sets give changes that are easy to review together: dead
 * code, code quality and type declarations. The sets that change structure,
 * for example naming, privatization and early return, are not used. Their
 * changes need a review of each line.
 *
 * config/ is not included, because it holds Symfony configuration and not
 * application logic.
 */
return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/src',
        __DIR__.'/tests',
    ])
    ->withPhpSets(php84: true)
    ->withComposerBased(
        phpunit: true,
        symfony: true,
    )
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        typeDeclarations: true,
    );
