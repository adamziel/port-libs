<?php

declare(strict_types=1);

use PortLibs\Quadrable\Proof;
use PortLibs\Quadrable\QuadbStore;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$sourceDir = sys_get_temp_dir() . '/quadrable-wp-binary-proof-source-' . bin2hex(random_bytes(6));
$targetDir = sys_get_temp_dir() . '/quadrable-wp-binary-proof-target-' . bin2hex(random_bytes(6));
$integerDir = sys_get_temp_dir() . '/quadrable-wp-binary-proof-integer-' . bin2hex(random_bytes(6));

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
    $source = QuadbStore::init($sourceDir);
    $source->importLines(
        "wp_options:siteurl|https://example.test\n"
        . "wp_options:home|https://example.test\n"
        . "wp_posts:1|Published post\n",
        '|'
    );

    $trustedRoot = $source->tree()->rootHash();
    $keyInput = "wp_options:siteurl\nwp_posts:1\nwp_posts:404\n";
    $proofCommand = QuadbStore::exportProofStdinCommandOutput(
        $sourceDir,
        $keyInput,
        'FullKeys',
        hex: false
    );
    if ($proofCommand['exitCode'] !== 0) {
        throw new RuntimeException($proofCommand['stderr']);
    }
    $proofBytes = $proofCommand['stdout'];
    $homeProofBytes = $source->exportProofBytesFromKeyLines("wp_options:home\n", Proof::ENCODING_FULL_KEYS);

    $target = QuadbStore::init($targetDir);
    $target->checkout('delegated-binary-preview');
    $target->importProofBytes($proofBytes, $trustedRoot);
    $target->mergeProofBytes($homeProofBytes);

    $integer = QuadbStore::init($integerDir);
    $integer->importIntegerLines(
        "2,wp_options:home=https://example.test\n"
        . "4,wp_posts:1=Published post\n"
    );
    $integerProofCommand = QuadbStore::exportProofStdinCommandOutput(
        $integerDir,
        "2\n4\n99\n",
        integerKeys: true
    );
    if ($integerProofCommand['exitCode'] !== 0) {
        throw new RuntimeException($integerProofCommand['stderr']);
    }
    $integerProofBytes = $integerProofCommand['stdout'];
    $directIntegerProofCommand = QuadbStore::exportProofCommandOutput(
        $integerDir,
        ['2', '4', '99'],
        integerKeys: true
    );
    $integerHexProofCommand = QuadbStore::exportProofStdinCommandOutput(
        $integerDir,
        "2\n4\n99\n",
        hex: true,
        integerKeys: true
    );
    $directIntegerHexProofCommand = QuadbStore::exportProofCommandOutput(
        $integerDir,
        ['2', '4', '99'],
        hex: true,
        integerKeys: true
    );
    $directIntegerDumpWithBadFormat = QuadbStore::exportProofCommandOutput(
        $integerDir,
        ['2', '4', '99'],
        'BadFormat',
        dump: true,
        integerKeys: true
    );
    $numericPrefixProofCommand = QuadbStore::exportProofCommandOutput(
        $integerDir,
        ['2suffix', '4'],
        integerKeys: true
    );
    $spacedPlusProofCommand = QuadbStore::exportProofCommandOutput(
        $integerDir,
        ['  +2suffix', ' +4'],
        integerKeys: true
    );
    $badIntegerProofCommand = QuadbStore::exportProofStdinCommandOutput(
        $integerDir,
        "2\nnot-an-int\n",
        integerKeys: true
    );
    $negativeIntegerProofCommand = QuadbStore::exportProofCommandOutput(
        $integerDir,
        ['-1'],
        integerKeys: true
    );

    echo json_encode([
        'scenario' => 'quadb exportProof --stdin binary proof input for delegated WordPress preview reads',
        'trustedRoot' => $trustedRoot,
        'stdinKeys' => explode("\n", rtrim($keyInput, "\n")),
        'exportProofStdinExitCode' => $proofCommand['exitCode'],
        'binaryProofBytes' => strlen($proofBytes),
        'encodingType' => ord($proofBytes[0]),
        'siteUrl' => $target->get('wp_options:siteurl'),
        'post' => $target->get('wp_posts:1'),
        'mergedHome' => $target->get('wp_options:home'),
        'integerStdinKeys' => [2, 4, 99],
        'integerExportProofStdinExitCode' => $integerProofCommand['exitCode'],
        'integerBinaryProofBytes' => strlen($integerProofBytes),
        'directIntegerExportProofExitCode' => $directIntegerProofCommand['exitCode'],
        'directIntegerBinaryProofMatchesStdin' => $directIntegerProofCommand['stdout'] === $integerProofBytes,
        'directIntegerHexProofMatchesStdin' => $directIntegerHexProofCommand === $integerHexProofCommand,
        'directIntegerDumpIgnoresBadFormat' => $directIntegerDumpWithBadFormat['exitCode'] === 0
            && str_contains($directIntegerDumpWithBadFormat['stdout'], 'ITEMS (2):')
            && $directIntegerDumpWithBadFormat['stderr'] === '',
        'numericPrefixIntegerExportProofExitCode' => $numericPrefixProofCommand['exitCode'],
        'numericPrefixIntegerBinaryProofBytes' => strlen($numericPrefixProofCommand['stdout']),
        'spacedPlusIntegerExportProofExitCode' => $spacedPlusProofCommand['exitCode'],
        'spacedPlusIntegerProofMatchesNumericPrefix' => $spacedPlusProofCommand['stdout'] === $numericPrefixProofCommand['stdout'],
        'badIntegerProofStdin' => $badIntegerProofCommand,
        'negativeIntegerProofDirect' => $negativeIntegerProofCommand,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
} finally {
    $cleanup($sourceDir);
    $cleanup($targetDir);
    $cleanup($integerDir);
}
