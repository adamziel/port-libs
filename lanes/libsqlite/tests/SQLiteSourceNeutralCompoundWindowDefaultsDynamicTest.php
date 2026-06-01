<?php

declare(strict_types=1);

$libsqliteRoot = dirname(__DIR__);
$sourceFile = $libsqliteRoot . '/src/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan.php';

$compoundWindowSourceMatches = static function () use ($sourceFile, $libsqliteRoot): array {
    $contents = file_get_contents($sourceFile);
    if ($contents === false) {
        throw new RuntimeException("Unable to read {$sourceFile}");
    }

    $terms = [
        'wp' . '_',
        'wp' . '_options',
        'opt' . 'ion_id',
        'opt' . 'ion_name',
        'auto' . 'load',
        'Auto' . 'load',
        'application-' . 'option',
        'Application ' . 'option',
    ];
    $pattern = '/(?:\bwp\b|' . implode('|', array_map(static fn (string $term): string => preg_quote($term, '/'), $terms)) . ')/';
    if (preg_match_all($pattern, $contents, $matches, PREG_OFFSET_CAPTURE) < 1) {
        return [];
    }

    $relative = str_replace($libsqliteRoot . '/', '', $sourceFile);

    return array_values(array_map(
        static fn (array $match): string => "{$relative}: {$match[0]}",
        $matches[0],
    ));
};

return [
    'source-neutral compound window defaults dynamic source has no legacy setting table terms' => static fn (TestRunner $t) => $t->same([], $compoundWindowSourceMatches()),
];
