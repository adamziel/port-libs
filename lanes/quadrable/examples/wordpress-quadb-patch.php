<?php

declare(strict_types=1);

use PortLibs\Quadrable\QuadbStore;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$dir = sys_get_temp_dir() . '/quadrable-wp-quadb-patch-' . bin2hex(random_bytes(6));
$missingDir = sys_get_temp_dir() . '/quadrable-wp-quadb-patch-missing-' . bin2hex(random_bytes(6));

$cleanup = static function (string $dir): void {
    if (!is_dir($dir)) {
        return;
    }

    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($items as $item) {
        if ($item->isDir()) {
            rmdir($item->getPathname());
        } else {
            unlink($item->getPathname());
        }
    }
    rmdir($dir);
};

try {
    $repo = QuadbStore::init($dir);
    $repo->importLines(
        "wp_options:siteurl|https://example.test\n"
        . "wp_options:home|https://example.test\n"
        . "wp_posts:1|Published post\n",
        '|'
    );

    $repo->fork('preview');
    $repo->put('wp_posts:1', 'Preview edit');
    $repo->put('wp_posts:2', 'Imported draft');
    $repo->delete('wp_options:home');

    $previewRoot = $repo->tree()->rootHash();
    $diffCommand = QuadbStore::diffCommandOutput($dir, 'master', '|');
    if ($diffCommand['exitCode'] !== 0) {
        throw new RuntimeException($diffCommand['stderr']);
    }
    $patch = $diffCommand['stdout'];

    $forkCommand = QuadbStore::forkCommandOutput($dir, 'replica', 'master');
    if ($forkCommand['exitCode'] !== 0) {
        throw new RuntimeException($forkCommand['stderr']);
    }
    $patchCommand = QuadbStore::patchCommandOutput($dir, "# patch generated from preview\n" . $patch, '|');
    if ($patchCommand['exitCode'] !== 0) {
        throw new RuntimeException($patchCommand['stderr']);
    }

    $replica = QuadbStore::open($dir);
    $missingDiff = QuadbStore::diffCommandOutput($missingDir, 'master', '|');
    $emptySeparatorDiff = QuadbStore::diffCommandOutput($dir, 'master', '');
    $emptySeparatorPatch = QuadbStore::patchCommandOutput($dir, $patch, '');

    echo json_encode([
        'scenario' => 'apply quadb command-style tracked string-key patch lines to a WordPress preview snapshot',
        'missingStoreFailsClosed' => $missingDiff['exitCode'] === 1 && !is_dir($missingDir),
        'emptySeparatorFailsClosed' => $emptySeparatorDiff['exitCode'] === 1
            && $emptySeparatorPatch['exitCode'] === 1
            && $emptySeparatorDiff['stderr'] === "quadb error: separator must be non-empty\n"
            && $emptySeparatorPatch['stderr'] === "quadb error: separator must be non-empty\n",
        'diffCommandClean' => $diffCommand['stderr'] === '',
        'patchCommandClean' => $patchCommand['stderr'] === '',
        'patchLines' => array_values(array_filter(explode("\n", trim($patch)), static fn (string $line): bool => $line !== '')),
        'replicaMatchesPreviewRoot' => $replica->tree()->rootHash() === $previewRoot,
        'previewPost' => $replica->get('wp_posts:1'),
        'newDraft' => $replica->get('wp_posts:2'),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
} finally {
    $cleanup($dir);
    $cleanup($missingDir);
}
