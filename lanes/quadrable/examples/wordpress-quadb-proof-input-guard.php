<?php

declare(strict_types=1);

use PortLibs\Quadrable\Proof;
use PortLibs\Quadrable\QuadbStore;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$missingDir = sys_get_temp_dir() . '/quadrable-wp-proof-missing-' . bin2hex(random_bytes(6));
$sourceDir = sys_get_temp_dir() . '/quadrable-wp-proof-source-' . bin2hex(random_bytes(6));
$targetDir = sys_get_temp_dir() . '/quadrable-wp-proof-target-' . bin2hex(random_bytes(6));
$uppercaseRootDir = sys_get_temp_dir() . '/quadrable-wp-proof-uppercase-root-' . bin2hex(random_bytes(6));
$emptyRootDir = sys_get_temp_dir() . '/quadrable-wp-proof-empty-root-' . bin2hex(random_bytes(6));

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
    $missingImport = QuadbStore::importProofHexCommandOutput($missingDir, 'zz');

    $source = QuadbStore::init($sourceDir);
    $source->put('wp_options:siteurl', 'https://example.test');
    $trustedRoot = $source->tree()->rootHash();
    $proofHex = $source->exportProofHex(['wp_options:siteurl'], Proof::ENCODING_FULL_KEYS);
    $badFormat = QuadbStore::exportProofCommandOutput(
        $sourceDir,
        ['wp_options:siteurl'],
        'Bad',
        true
    );

    if (!mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
        throw new RuntimeException('unable to create WordPress proof target directory');
    }

    $oddHex = QuadbStore::importProofHexCommandOutput($targetDir, 'abc');
    $uppercasePrefix = QuadbStore::importProofHexCommandOutput($targetDir, '0X00');
    $emptyProof = QuadbStore::importProofHexCommandOutput($targetDir, '0001');
    $uppercaseRootPrefix = QuadbStore::importProofHexCommandOutput(
        $targetDir,
        $proofHex,
        '0X' . $trustedRoot
    );
    $shortRoot = QuadbStore::importProofHexCommandOutput($targetDir, $proofHex, '0x00');

    if (!mkdir($uppercaseRootDir, 0755, true) && !is_dir($uppercaseRootDir)) {
        throw new RuntimeException('unable to create WordPress uppercase-root proof target directory');
    }
    $uppercaseRoot = QuadbStore::importProofHexCommandOutput(
        $uppercaseRootDir,
        $proofHex,
        '0x' . strtoupper($trustedRoot)
    );

    if (!mkdir($emptyRootDir, 0755, true) && !is_dir($emptyRootDir)) {
        throw new RuntimeException('unable to create WordPress empty-root proof target directory');
    }
    $emptyRoot = QuadbStore::importProofHexCommandOutput($emptyRootDir, $proofHex, '0x');

    $trustedImport = QuadbStore::importProofHexCommandOutput($targetDir, $proofHex, $trustedRoot);

    echo json_encode([
        'scenario' => 'guard WordPress delegated proof and root input with upstream-shaped quadb proof command output',
        'missingImportExitCode' => $missingImport['exitCode'],
        'missingStoreCreatedDirectory' => is_dir($missingDir),
        'badFormatStderr' => rtrim($badFormat['stderr'], "\r\n"),
        'oddHexStderr' => rtrim($oddHex['stderr'], "\r\n"),
        'uppercasePrefixStderr' => rtrim($uppercasePrefix['stderr'], "\r\n"),
        'emptyProofStderr' => rtrim($emptyProof['stderr'], "\r\n"),
        'uppercaseRootPrefixStderr' => rtrim($uppercaseRootPrefix['stderr'], "\r\n"),
        'shortRootStderr' => rtrim($shortRoot['stderr'], "\r\n"),
        'uppercaseRootImportExitCode' => $uppercaseRoot['exitCode'],
        'emptyRootImportHasNoWarning' => $emptyRoot['stdout'] === '' && $emptyRoot['stderr'] === '',
        'trustedImportExitCode' => $trustedImport['exitCode'],
        'trustedSiteUrl' => QuadbStore::open($targetDir)->get('wp_options:siteurl'),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
} finally {
    $cleanup($missingDir);
    $cleanup($sourceDir);
    $cleanup($targetDir);
    $cleanup($uppercaseRootDir);
    $cleanup($emptyRootDir);
}
