<?php
declare(strict_types=1);

use ComposerUnused\ComposerUnused\Configuration\Configuration;
use ComposerUnused\ComposerUnused\Configuration\NamedFilter;

// These are all false positives
// @see https://github.com/composer-unused/composer-unused/issues/326

return static fn(Configuration $config): Configuration => $config
    ->addNamedFilter(NamedFilter::fromString('chimera/routing'));
