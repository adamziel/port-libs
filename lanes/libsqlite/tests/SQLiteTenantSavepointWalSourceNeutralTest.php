<?php

declare(strict_types=1);

$sourceFile = dirname(__DIR__) . '/src/SQLiteTenantSavepointWalPlan.php';

$legacyTenantSavepointMatches = static function () use ($sourceFile): array {
    $contents = file_get_contents($sourceFile);
    if ($contents === false) {
        throw new RuntimeException("Unable to read {$sourceFile}");
    }

    $legacyTerms = [
        'blog' . '_id',
        'multi' . 'site',
        'site' . '_count',
        'rolled_back_' . 'site' . '_count',
        'stable_' . 'site' . '_count',
        'site' . 's',
        'rollbackToAcross' . 'Sites',
    ];
    $pattern = '/\b(?:' . implode('|', array_map(static fn (string $term): string => preg_quote($term, '/'), $legacyTerms)) . ')\b/';

    preg_match_all($pattern, $contents, $matches);

    return $matches[0] ?? [];
};

return [
    'tenant savepoint wal source uses tenant neutral surfaces' => static fn (TestRunner $t) => $t->same([], $legacyTenantSavepointMatches()),
];
