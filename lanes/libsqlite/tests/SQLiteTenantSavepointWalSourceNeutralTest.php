<?php

declare(strict_types=1);

$sourceRoot = dirname(__DIR__) . '/src';
$sourceFiles = [
    $sourceRoot . '/SQLiteTenantSavepointWalPlan.php',
    $sourceRoot . '/SQLiteTenantJsonWalSavepointPlan.php',
];

$legacyTenantSavepointMatches = static function () use ($sourceFiles, $sourceRoot): array {
    $legacyPatterns = [
        'blog' . '_id',
        'multi' . 'site',
        'site' . '_count',
        'rolled_back_' . 'site' . '_count',
        'stable_' . 'site' . '_count',
        'rollbackToAcross' . 'Sites',
        'continue_on_' . 'site' . '_error',
        'rollback_' . 'network' . '_on_error',
        'network' . '_rollback',
        'network' . '_wal',
        'network' . '_frame_index',
        'network' . '_page_number',
        '\$' . 'site' . 's\b',
        '\$' . 'site' . '\b',
        'site' . 'Plans',
        'site' . 'Plan',
        "\\['" . 'site' . "s'\\]",
    ];
    $pattern = '/(?:' . implode('|', $legacyPatterns) . ')/';
    $matches = [];

    foreach ($sourceFiles as $file) {
        $contents = file_get_contents($file);
        if ($contents === false) {
            throw new RuntimeException("Unable to read {$file}");
        }
        if (preg_match_all($pattern, $contents, $fileMatches) < 1) {
            continue;
        }
        $relative = str_replace($sourceRoot . '/', 'src/', $file);
        foreach ($fileMatches[0] as $match) {
            $matches[] = "{$relative}: {$match}";
        }
    }

    return $matches;
};

return [
    'tenant savepoint wal source uses tenant neutral surfaces' => static fn (TestRunner $t) => $t->same([], $legacyTenantSavepointMatches()),
];
