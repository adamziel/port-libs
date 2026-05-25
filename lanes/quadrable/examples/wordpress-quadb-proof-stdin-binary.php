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
    $eofProofCommand = QuadbStore::exportProofStdinCommandOutput(
        $sourceDir,
        rtrim($keyInput, "\n"),
        'FullKeys',
        hex: false
    );
    $crlfProofCommand = QuadbStore::exportProofStdinCommandOutput(
        $sourceDir,
        "wp_options:siteurl\r\nwp_posts:1\r\nwp_posts:404\r\n",
        'FullKeys',
        hex: false
    );
    $homeProofBytes = $source->exportProofBytesFromKeyLines("wp_options:home\n", Proof::ENCODING_FULL_KEYS);

    $target = QuadbStore::init($targetDir);
    $target->checkout('delegated-binary-preview');
    $target->importProofBytes($proofBytes, $trustedRoot);
    $target->mergeProofBytes($homeProofBytes);

    $integer = QuadbStore::init($integerDir);
    $integer->importIntegerLines(
        "0,wp_options:blog_public=1\n"
        . "2,wp_options:home=https://example.test\n"
        . "4,wp_posts:1=Published post\n"
        . "2147483647,wp_posts:max=Boundary post\n"
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
    $integerCrlfProofCommand = QuadbStore::exportProofStdinCommandOutput(
        $integerDir,
        "2\r\n4\r\n99\r\n",
        integerKeys: true
    );
    $integerEofProofCommand = QuadbStore::exportProofStdinCommandOutput(
        $integerDir,
        "2\n4\n99",
        integerKeys: true
    );
    $emptyStdinIntegerProofCommand = QuadbStore::exportProofStdinCommandOutput(
        $integerDir,
        '',
        integerKeys: true
    );
    $blankLineStdinIntegerProofCommand = QuadbStore::exportProofStdinCommandOutput(
        $integerDir,
        "\n",
        integerKeys: true
    );
    $whitespaceOnlyStdinIntegerProofCommand = QuadbStore::exportProofStdinCommandOutput(
        $integerDir,
        " \t\r\n",
        integerKeys: true
    );
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
    $stdinIntegerDumpWithBadFormat = QuadbStore::exportProofStdinCommandOutput(
        $integerDir,
        "2\n4\n99\n",
        'BadFormat',
        dump: true,
        integerKeys: true
    );
    $numericPrefixProofCommand = QuadbStore::exportProofCommandOutput(
        $integerDir,
        ['2suffix', '4'],
        integerKeys: true
    );
    $trailingWhitespaceProofCommand = QuadbStore::exportProofCommandOutput(
        $integerDir,
        ["2 \tignored", "4\nignored"],
        integerKeys: true
    );
    $spacedPlusProofCommand = QuadbStore::exportProofCommandOutput(
        $integerDir,
        ['  +2suffix', ' +4'],
        integerKeys: true
    );
    $verticalWhitespaceProofCommand = QuadbStore::exportProofCommandOutput(
        $integerDir,
        ["\t+2suffix", "\n4"],
        integerKeys: true
    );
    $formFeedWhitespaceProofCommand = QuadbStore::exportProofCommandOutput(
        $integerDir,
        ["\f+2suffix", "\v4"],
        integerKeys: true
    );
    $carriageReturnWhitespaceProofCommand = QuadbStore::exportProofCommandOutput(
        $integerDir,
        ["\r+2suffix", "\r4"],
        integerKeys: true
    );
    $nulPrefixedProofCommand = QuadbStore::exportProofCommandOutput(
        $integerDir,
        ["\0+2"],
        integerKeys: true
    );
    $stdinNulPrefixedProofCommand = QuadbStore::exportProofStdinCommandOutput(
        $integerDir,
        "\0+2\n",
        integerKeys: true
    );
    $signOnlyProofCommand = QuadbStore::exportProofCommandOutput(
        $integerDir,
        ['+', '-'],
        integerKeys: true
    );
    $stdinSignOnlyProofCommand = QuadbStore::exportProofStdinCommandOutput(
        $integerDir,
        "+\n-\n",
        integerKeys: true
    );
    $maxIntegerProofCommand = QuadbStore::exportProofCommandOutput(
        $integerDir,
        ['0002147483647suffix', '+0000000002'],
        integerKeys: true
    );
    $signedZeroProofCommand = QuadbStore::exportProofCommandOutput(
        $integerDir,
        ['-0suffix', '+0'],
        integerKeys: true
    );
    $emptyIntegerProofCommand = QuadbStore::exportProofCommandOutput(
        $integerDir,
        [],
        integerKeys: true
    );
    $emptyIntegerHexProofCommand = QuadbStore::exportProofCommandOutput(
        $integerDir,
        [],
        hex: true,
        integerKeys: true
    );
    $badIntegerProofCommand = QuadbStore::exportProofStdinCommandOutput(
        $integerDir,
        "2\nnot-an-int\n",
        integerKeys: true
    );
    $stdinNumericPrefixProofCommand = QuadbStore::exportProofStdinCommandOutput(
        $integerDir,
        "  +2suffix\n-0suffix\n0000000004ignored\n",
        integerKeys: true
    );
    $stdinNegativeUnderflowProofCommand = QuadbStore::exportProofStdinCommandOutput(
        $integerDir,
        "-2147483649suffix\n",
        integerKeys: true
    );
    $negativeIntegerProofCommand = QuadbStore::exportProofCommandOutput(
        $integerDir,
        ['-1'],
        integerKeys: true
    );
    $plusOverflowIntegerProofCommand = QuadbStore::exportProofCommandOutput(
        $integerDir,
        ['+2147483648suffix'],
        integerKeys: true
    );
    $stdinPlusOverflowIntegerProofCommand = QuadbStore::exportProofStdinCommandOutput(
        $integerDir,
        "+2147483648suffix\n",
        integerKeys: true
    );
    $minSignedIntegerProofCommand = QuadbStore::exportProofCommandOutput(
        $integerDir,
        ['-2147483648'],
        integerKeys: true
    );
    $negativeUnderflowIntegerProofCommand = QuadbStore::exportProofCommandOutput(
        $integerDir,
        ['-2147483649suffix'],
        integerKeys: true
    );

    echo json_encode([
        'scenario' => 'quadb exportProof --stdin binary proof input for delegated WordPress preview reads',
        'trustedRoot' => $trustedRoot,
        'stdinKeys' => explode("\n", rtrim($keyInput, "\n")),
        'exportProofStdinExitCode' => $proofCommand['exitCode'],
        'binaryProofBytes' => strlen($proofBytes),
        'eofFinalKeyProofMatchesLf' => $eofProofCommand['exitCode'] === 0
            && $eofProofCommand['stdout'] === $proofBytes,
        'crlfStringProofPreservesCarriageReturnKeys' => $crlfProofCommand['exitCode'] === 0
            && $crlfProofCommand['stdout'] === $source->exportProofBytes([
                "wp_options:siteurl\r",
                "wp_posts:1\r",
                "wp_posts:404\r",
            ], Proof::ENCODING_FULL_KEYS),
        'crlfStringProofDiffersFromLf' => $crlfProofCommand['exitCode'] === 0
            && $crlfProofCommand['stdout'] !== $proofBytes,
        'encodingType' => ord($proofBytes[0]),
        'siteUrl' => $target->get('wp_options:siteurl'),
        'post' => $target->get('wp_posts:1'),
        'mergedHome' => $target->get('wp_options:home'),
        'integerStdinKeys' => [2, 4, 99],
        'integerExportProofStdinExitCode' => $integerProofCommand['exitCode'],
        'integerBinaryProofBytes' => strlen($integerProofBytes),
        'integerCrlfProofMatchesLf' => $integerCrlfProofCommand['stdout'] === $integerProofBytes
            && $integerCrlfProofCommand['exitCode'] === 0,
        'integerEofProofMatchesLf' => $integerEofProofCommand['stdout'] === $integerProofBytes
            && $integerEofProofCommand['exitCode'] === 0,
        'emptyStdinIntegerProofMatchesDirectEmpty' => $emptyStdinIntegerProofCommand['exitCode'] === 0
            && $emptyStdinIntegerProofCommand['stdout'] === QuadbStore::open($integerDir)->exportIntegerProofBytes([]),
        'blankLineStdinIntegerProofFailsStoi' => $blankLineStdinIntegerProofCommand['exitCode'] === 1
            && $blankLineStdinIntegerProofCommand['stderr'] === "quadb error: stoi\n",
        'whitespaceOnlyStdinIntegerProofFailsStoi' => $whitespaceOnlyStdinIntegerProofCommand['exitCode'] === 1
            && $whitespaceOnlyStdinIntegerProofCommand['stderr'] === "quadb error: stoi\n",
        'directIntegerExportProofExitCode' => $directIntegerProofCommand['exitCode'],
        'directIntegerBinaryProofMatchesStdin' => $directIntegerProofCommand['stdout'] === $integerProofBytes,
        'directIntegerHexProofMatchesStdin' => $directIntegerHexProofCommand === $integerHexProofCommand,
        'directIntegerDumpIgnoresBadFormat' => $directIntegerDumpWithBadFormat['exitCode'] === 0
            && str_contains($directIntegerDumpWithBadFormat['stdout'], 'ITEMS (2):')
            && $directIntegerDumpWithBadFormat['stderr'] === '',
        'stdinIntegerDumpMatchesDirect' => $stdinIntegerDumpWithBadFormat === $directIntegerDumpWithBadFormat,
        'numericPrefixIntegerExportProofExitCode' => $numericPrefixProofCommand['exitCode'],
        'numericPrefixIntegerBinaryProofBytes' => strlen($numericPrefixProofCommand['stdout']),
        'trailingWhitespaceIntegerProofMatchesNumericPrefix' => $trailingWhitespaceProofCommand['stdout'] === $numericPrefixProofCommand['stdout'],
        'spacedPlusIntegerExportProofExitCode' => $spacedPlusProofCommand['exitCode'],
        'spacedPlusIntegerProofMatchesNumericPrefix' => $spacedPlusProofCommand['stdout'] === $numericPrefixProofCommand['stdout'],
        'verticalWhitespaceIntegerProofMatchesNumericPrefix' => $verticalWhitespaceProofCommand['stdout'] === $numericPrefixProofCommand['stdout'],
        'formFeedWhitespaceIntegerProofMatchesNumericPrefix' => $formFeedWhitespaceProofCommand['stdout'] === $numericPrefixProofCommand['stdout'],
        'carriageReturnWhitespaceIntegerProofMatchesNumericPrefix' => $carriageReturnWhitespaceProofCommand['stdout'] === $numericPrefixProofCommand['stdout'],
        'nulPrefixedIntegerProofFailsStoi' => $nulPrefixedProofCommand['exitCode'] === 1
            && $nulPrefixedProofCommand['stderr'] === "quadb error: stoi\n",
        'stdinNulPrefixedIntegerProofFailsStoi' => $stdinNulPrefixedProofCommand['exitCode'] === 1
            && $stdinNulPrefixedProofCommand['stderr'] === "quadb error: stoi\n",
        'signOnlyIntegerProofFailsStoi' => $signOnlyProofCommand['exitCode'] === 1
            && $signOnlyProofCommand['stderr'] === "quadb error: stoi\n",
        'stdinSignOnlyIntegerProofFailsStoi' => $stdinSignOnlyProofCommand['exitCode'] === 1
            && $stdinSignOnlyProofCommand['stderr'] === "quadb error: stoi\n",
        'maxIntegerExportProofExitCode' => $maxIntegerProofCommand['exitCode'],
        'maxIntegerBinaryProofBytes' => strlen($maxIntegerProofCommand['stdout']),
        'signedZeroIntegerExportProofExitCode' => $signedZeroProofCommand['exitCode'],
        'signedZeroIntegerBinaryProofBytes' => strlen($signedZeroProofCommand['stdout']),
        'emptyIntegerExportProofExitCode' => $emptyIntegerProofCommand['exitCode'],
        'emptyIntegerHexProofHasPrefix' => str_starts_with($emptyIntegerHexProofCommand['stdout'], '0x'),
        'badIntegerProofStdin' => $badIntegerProofCommand,
        'stdinNumericPrefixIntegerProofExitCode' => $stdinNumericPrefixProofCommand['exitCode'],
        'stdinNumericPrefixIntegerProofMatchesDirect' => $stdinNumericPrefixProofCommand['stdout'] === QuadbStore::open($integerDir)
            ->exportIntegerProofBytes([2, 0, 4]),
        'stdinNegativeUnderflowIntegerProofFailsStoi' => $stdinNegativeUnderflowProofCommand['exitCode'] === 1
            && $stdinNegativeUnderflowProofCommand['stderr'] === "quadb error: stoi\n",
        'negativeIntegerProofDirect' => $negativeIntegerProofCommand,
        'plusOverflowIntegerProofFailsStoi' => $plusOverflowIntegerProofCommand['exitCode'] === 1
            && $plusOverflowIntegerProofCommand['stderr'] === "quadb error: stoi\n",
        'stdinPlusOverflowIntegerProofFailsStoi' => $stdinPlusOverflowIntegerProofCommand['exitCode'] === 1
            && $stdinPlusOverflowIntegerProofCommand['stderr'] === "quadb error: stoi\n",
        'minSignedIntegerProofFailsRange' => $minSignedIntegerProofCommand['exitCode'] === 1
            && $minSignedIntegerProofCommand['stderr'] === "quadb error: int range exceeded\n",
        'negativeUnderflowIntegerProofFailsStoi' => $negativeUnderflowIntegerProofCommand['exitCode'] === 1
            && $negativeUnderflowIntegerProofCommand['stderr'] === "quadb error: stoi\n",
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
} finally {
    $cleanup($sourceDir);
    $cleanup($targetDir);
    $cleanup($integerDir);
}
