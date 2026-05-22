<?php

declare(strict_types=1);

use PortLibs\Quadrable\QuadbStore;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$dir = sys_get_temp_dir() . '/quadrable-wp-quadb-patch-' . bin2hex(random_bytes(6));

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
    $patch = $repo->diffLines('master', '|');

    $replica = $repo->fork('replica', 'master');
    $repo->applyPatchLines("# patch generated from preview\n" . $patch, '|');

    echo json_encode([
        'scenario' => 'apply quadb-style tracked string-key patch lines to a WordPress preview snapshot',
        'patchLines' => array_values(array_filter(explode("\n", trim($patch)), static fn (string $line): bool => $line !== '')),
        'replicaStartedAtMaster' => $replica->get('wp_posts:1') === 'Published post',
        'replicaMatchesPreviewRoot' => $repo->tree()->rootHash() === $previewRoot,
        'previewPost' => $repo->get('wp_posts:1'),
        'newDraft' => $repo->get('wp_posts:2'),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
} finally {
    $cleanup($dir);
}
