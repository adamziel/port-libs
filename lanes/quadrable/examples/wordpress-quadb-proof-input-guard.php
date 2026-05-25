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
$hexDumpDir = sys_get_temp_dir() . '/quadrable-wp-proof-hex-dump-' . bin2hex(random_bytes(6));
$binaryDumpDir = sys_get_temp_dir() . '/quadrable-wp-proof-binary-dump-' . bin2hex(random_bytes(6));
$binaryTrustedDir = sys_get_temp_dir() . '/quadrable-wp-proof-binary-trusted-' . bin2hex(random_bytes(6));
$binaryMergeDir = sys_get_temp_dir() . '/quadrable-wp-proof-binary-merge-' . bin2hex(random_bytes(6));

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
    $source->put('wp_options:home', 'https://example.test');
    $trustedRoot = $source->tree()->rootHash();
    $proofHex = $source->exportProofHex(['wp_options:siteurl'], Proof::ENCODING_FULL_KEYS);
    $proofBytes = $source->exportProofBytes(['wp_options:siteurl'], Proof::ENCODING_FULL_KEYS);
    $mergeProofBytes = $source->exportProofBytes(['wp_options:home'], Proof::ENCODING_FULL_KEYS);
    $badFormat = QuadbStore::exportProofCommandOutput(
        $sourceDir,
        ['wp_options:siteurl'],
        'Bad',
        true
    );
    $dumpWithBadFormat = QuadbStore::exportProofCommandOutput(
        $sourceDir,
        ['wp_options:siteurl'],
        'Bad',
        false,
        true
    );
    $stdinDumpWithBadFormat = QuadbStore::exportProofStdinCommandOutput(
        $sourceDir,
        "wp_options:siteurl\n",
        'Bad',
        dump: true
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

    if (!mkdir($hexDumpDir, 0755, true) && !is_dir($hexDumpDir)) {
        throw new RuntimeException('unable to create WordPress hex-dump proof target directory');
    }
    $hexDumpWithInvalidRoot = QuadbStore::importProofHexCommandOutput(
        $hexDumpDir,
        $proofHex,
        '0X' . $trustedRoot,
        true
    );

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

    if (!mkdir($binaryDumpDir, 0755, true) && !is_dir($binaryDumpDir)) {
        throw new RuntimeException('unable to create WordPress binary-dump proof target directory');
    }
    $binaryDumpWithInvalidRoot = QuadbStore::importProofCommandOutput(
        $binaryDumpDir,
        $proofBytes,
        '0X' . $trustedRoot,
        true
    );

    if (!mkdir($binaryTrustedDir, 0755, true) && !is_dir($binaryTrustedDir)) {
        throw new RuntimeException('unable to create WordPress binary proof target directory');
    }
    $binaryTrustedImport = QuadbStore::importProofCommandOutput(
        $binaryTrustedDir,
        $proofBytes,
        $trustedRoot
    );

    if (!mkdir($binaryMergeDir, 0755, true) && !is_dir($binaryMergeDir)) {
        throw new RuntimeException('unable to create WordPress binary mergeProof target directory');
    }
    QuadbStore::init($binaryMergeDir)->importProofBytes($proofBytes, $trustedRoot);
    $binaryMerge = QuadbStore::mergeProofCommandOutput($binaryMergeDir, $mergeProofBytes);

    $trustedImport = QuadbStore::importProofHexCommandOutput($targetDir, $proofHex, $trustedRoot);

    echo json_encode([
        'scenario' => 'guard WordPress delegated proof and root input with upstream-shaped quadb proof command output',
        'missingImportExitCode' => $missingImport['exitCode'],
        'missingStoreCreatedDirectory' => is_dir($missingDir),
        'badFormatStderr' => rtrim($badFormat['stderr'], "\r\n"),
        'dumpWithBadFormatStillDumps' => $dumpWithBadFormat['exitCode'] === 0
            && str_starts_with($dumpWithBadFormat['stdout'], 'ITEMS (')
            && str_contains($dumpWithBadFormat['stdout'], 'wp_options:siteurl')
            && $dumpWithBadFormat['stderr'] === '',
        'stdinDumpWithBadFormatStillDumps' => $stdinDumpWithBadFormat['exitCode'] === 0
            && str_starts_with($stdinDumpWithBadFormat['stdout'], 'ITEMS (')
            && str_contains($stdinDumpWithBadFormat['stdout'], 'wp_options:siteurl')
            && $stdinDumpWithBadFormat['stderr'] === '',
        'oddHexStderr' => rtrim($oddHex['stderr'], "\r\n"),
        'uppercasePrefixStderr' => rtrim($uppercasePrefix['stderr'], "\r\n"),
        'emptyProofStderr' => rtrim($emptyProof['stderr'], "\r\n"),
        'uppercaseRootPrefixStderr' => rtrim($uppercaseRootPrefix['stderr'], "\r\n"),
        'shortRootStderr' => rtrim($shortRoot['stderr'], "\r\n"),
        'hexDumpIgnoresInvalidRoot' => $hexDumpWithInvalidRoot['exitCode'] === 0
            && str_starts_with($hexDumpWithInvalidRoot['stdout'], 'ITEMS (')
            && str_contains($hexDumpWithInvalidRoot['stdout'], 'wp_options:siteurl')
            && $hexDumpWithInvalidRoot['stderr'] === '',
        'uppercaseRootImportExitCode' => $uppercaseRoot['exitCode'],
        'emptyRootImportHasNoWarning' => $emptyRoot['stdout'] === '' && $emptyRoot['stderr'] === '',
        'binaryDumpIgnoresInvalidRoot' => $binaryDumpWithInvalidRoot['exitCode'] === 0
            && str_starts_with($binaryDumpWithInvalidRoot['stdout'], 'ITEMS (')
            && $binaryDumpWithInvalidRoot['stderr'] === '',
        'binaryTrustedImportExitCode' => $binaryTrustedImport['exitCode'],
        'binaryTrustedSiteUrl' => QuadbStore::open($binaryTrustedDir)->get('wp_options:siteurl'),
        'binaryMergeExitCode' => $binaryMerge['exitCode'],
        'binaryMergedHome' => QuadbStore::open($binaryMergeDir)->get('wp_options:home'),
        'trustedImportExitCode' => $trustedImport['exitCode'],
        'trustedSiteUrl' => QuadbStore::open($targetDir)->get('wp_options:siteurl'),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
} finally {
    $cleanup($missingDir);
    $cleanup($sourceDir);
    $cleanup($targetDir);
    $cleanup($uppercaseRootDir);
    $cleanup($emptyRootDir);
    $cleanup($hexDumpDir);
    $cleanup($binaryDumpDir);
    $cleanup($binaryTrustedDir);
    $cleanup($binaryMergeDir);
}
