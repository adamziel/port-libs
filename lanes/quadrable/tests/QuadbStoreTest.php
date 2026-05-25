<?php

declare(strict_types=1);

use PortLibs\Quadrable\Blake2s;
use PortLibs\Quadrable\HashTree;
use PortLibs\Quadrable\Key;
use PortLibs\Quadrable\Proof;
use PortLibs\Quadrable\QuadbStore;
use PortLibs\Quadrable\SparseTree;

return [
    'native quadb store maps help and version metadata command output' => static function (TestRunner $t): void {
        $help = QuadbStore::helpCommandOutput();

        $t->same(0, $help['exitCode']);
        $t->same('', $help['stderr']);
        $t->true(str_starts_with($help['stdout'], "\n    Usage:\n"));
        $t->true(str_ends_with($help['stdout'], "      --version      Show version.\n\n"));
        $t->contains('quadb [options] exportProof [--format=(HashedKeys|FullKeys)]', $help['stdout']);
        $t->contains('Database directory (default $ENV{QUADB_DIR} || "./quadb-dir/")', $help['stdout']);
        $t->contains('quadb [options] mineHash <prefix>', $help['stdout']);

        $t->same([
            'exitCode' => 0,
            'stdout' => "quadb \n",
            'stderr' => '',
        ], QuadbStore::versionCommandOutput());
        $t->same([
            'exitCode' => 0,
            'stdout' => "quadb v1.2.3\n",
            'stderr' => '',
        ], QuadbStore::versionCommandOutput('v1.2.3'));
    },
    'native quadb store maps no-argument docopt and get command output' => static function (TestRunner $t): void {
        $noArgs = QuadbStore::noArgumentCommandOutput();
        $t->same(255, $noArgs['exitCode']);
        $t->same('Arguments did not match expected patterns', $noArgs['stderr']);
        $t->true(str_starts_with($noArgs['stdout'], "\n\n    Usage:\n"));
        $t->true(str_ends_with($noArgs['stdout'], "      --version      Show version.\n\n"));

        $missingDir = quadrableQuadbTempDir();
        $storeDir = quadrableQuadbTempDir();

        try {
            $missingStore = QuadbStore::getCommandOutput($missingDir, 'wp_options:siteurl');
            $t->same([
                'exitCode' => 1,
                'stdout' => '',
                'stderr' => "quadb error: Could not access directory '{$missingDir}/': No such file or directory\n",
            ], $missingStore);
            $t->true(!is_dir($missingDir), 'missing get command should not create the database directory');

            if (!mkdir($storeDir, 0755, true) && !is_dir($storeDir)) {
                throw new RuntimeException('unable to create quadrable temp directory');
            }

            $missingKey = QuadbStore::getCommandOutput($storeDir, 'wp_options:home');
            $t->same([
                'exitCode' => 1,
                'stdout' => '',
                'stderr' => "quadb error: key not found in db\n",
            ], $missingKey);
            $t->same('0x' . HashTree::EMPTY_HASH . "\n", QuadbStore::open($storeDir)->rootText());

            $repo = QuadbStore::open($storeDir);
            $repo->put('wp_options:siteurl', 'https://example.test');
            $repo->put('wp_posts:1', 'Published post');

            $t->same([
                'exitCode' => 0,
                'stdout' => "https://example.test\n",
                'stderr' => '',
            ], QuadbStore::getCommandOutput($storeDir, 'wp_options:siteurl'));
            $t->same([
                'exitCode' => 1,
                'stdout' => '',
                'stderr' => "quadb error: key not found in db\n",
            ], QuadbStore::getCommandOutput($storeDir, 'wp_options:home'));
        } finally {
            quadrableQuadbRemoveDir($missingDir);
            quadrableQuadbRemoveDir($storeDir);
        }
    },
    'native quadb store maps put and del command output' => static function (TestRunner $t): void {
        $missingDir = quadrableQuadbTempDir();
        $storeDir = quadrableQuadbTempDir();

        try {
            $missingPut = QuadbStore::putCommandOutput(
                $missingDir,
                'wp_options:siteurl',
                'https://example.test'
            );
            $t->same([
                'exitCode' => 1,
                'stdout' => '',
                'stderr' => "quadb error: Could not access directory '{$missingDir}/': No such file or directory\n",
            ], $missingPut);
            $t->true(!is_dir($missingDir), 'missing put command should not create the database directory');

            $missingDelete = QuadbStore::deleteCommandOutput($missingDir, 'wp_options:siteurl');
            $t->same([
                'exitCode' => 1,
                'stdout' => '',
                'stderr' => "quadb error: Could not access directory '{$missingDir}/': No such file or directory\n",
            ], $missingDelete);
            $t->true(!is_dir($missingDir), 'missing del command should not create the database directory');

            if (!mkdir($storeDir, 0755, true) && !is_dir($storeDir)) {
                throw new RuntimeException('unable to create quadrable temp directory');
            }

            $t->same([
                'exitCode' => 0,
                'stdout' => '',
                'stderr' => '',
            ], QuadbStore::putCommandOutput($storeDir, 'wp_options:siteurl', 'https://example.test'));
            $t->same([
                'exitCode' => 0,
                'stdout' => "https://example.test\n",
                'stderr' => '',
            ], QuadbStore::getCommandOutput($storeDir, 'wp_options:siteurl'));

            $t->same([
                'exitCode' => 0,
                'stdout' => '',
                'stderr' => '',
            ], QuadbStore::putCommandOutput($storeDir, 'wp_options:siteurl', 'https://preview.example.test'));
            $t->same([
                'exitCode' => 0,
                'stdout' => "https://preview.example.test\n",
                'stderr' => '',
            ], QuadbStore::getCommandOutput($storeDir, 'wp_options:siteurl'));

            $rootBeforeMissingDelete = QuadbStore::open($storeDir)->rootText();
            $t->same([
                'exitCode' => 0,
                'stdout' => '',
                'stderr' => '',
            ], QuadbStore::deleteCommandOutput($storeDir, 'wp_options:home'));
            $t->same($rootBeforeMissingDelete, QuadbStore::open($storeDir)->rootText());

            $t->same([
                'exitCode' => 0,
                'stdout' => '',
                'stderr' => '',
            ], QuadbStore::deleteCommandOutput($storeDir, 'wp_options:siteurl'));
            $t->same([
                'exitCode' => 1,
                'stdout' => '',
                'stderr' => "quadb error: key not found in db\n",
            ], QuadbStore::getCommandOutput($storeDir, 'wp_options:siteurl'));
        } finally {
            quadrableQuadbRemoveDir($missingDir);
            quadrableQuadbRemoveDir($storeDir);
        }
    },
    'native quadb store maps get put and del int command output' => static function (TestRunner $t): void {
        $missingDir = quadrableQuadbTempDir();
        $storeDir = quadrableQuadbTempDir();

        try {
            $missingPut = QuadbStore::putIntegerCommandOutput(
                $missingDir,
                '1',
                'wp_options:siteurl=https://example.test'
            );
            $t->same([
                'exitCode' => 1,
                'stdout' => '',
                'stderr' => "quadb error: Could not access directory '{$missingDir}/': No such file or directory\n",
            ], $missingPut);
            $t->true(!is_dir($missingDir), 'missing put --int command should not create the database directory');

            $missingGet = QuadbStore::getIntegerCommandOutput($missingDir, '1');
            $t->same([
                'exitCode' => 1,
                'stdout' => '',
                'stderr' => "quadb error: Could not access directory '{$missingDir}/': No such file or directory\n",
            ], $missingGet);
            $t->true(!is_dir($missingDir), 'missing get --int command should not create the database directory');

            if (!mkdir($storeDir, 0755, true) && !is_dir($storeDir)) {
                throw new RuntimeException('unable to create quadrable temp directory');
            }

            $t->same([
                'exitCode' => 1,
                'stdout' => '',
                'stderr' => "quadb error: stoi\n",
            ], QuadbStore::putIntegerCommandOutput($storeDir, 'abc', 'ignored'));
            $t->same([
                'exitCode' => 1,
                'stdout' => '',
                'stderr' => "quadb error: int range exceeded\n",
            ], QuadbStore::getIntegerCommandOutput($storeDir, '-1'));
            $t->same([
                'exitCode' => 1,
                'stdout' => '',
                'stderr' => "quadb error: stoi\n",
            ], QuadbStore::deleteIntegerCommandOutput($storeDir, '2147483648'));

            $t->same([
                'exitCode' => 0,
                'stdout' => '',
                'stderr' => '',
            ], QuadbStore::putIntegerCommandOutput(
                $storeDir,
                '1x',
                'wp_options:siteurl=https://example.test'
            ));
            $t->same([
                'exitCode' => 0,
                'stdout' => "wp_options:siteurl=https://example.test\n",
                'stderr' => '',
            ], QuadbStore::getIntegerCommandOutput($storeDir, '1'));

            $rootBeforeMissingDelete = QuadbStore::open($storeDir)->rootText();
            $t->same([
                'exitCode' => 0,
                'stdout' => '',
                'stderr' => '',
            ], QuadbStore::deleteIntegerCommandOutput($storeDir, '2'));
            $t->same($rootBeforeMissingDelete, QuadbStore::open($storeDir)->rootText());

            $t->same([
                'exitCode' => 0,
                'stdout' => '',
                'stderr' => '',
            ], QuadbStore::deleteIntegerCommandOutput($storeDir, '1'));
            $t->same([
                'exitCode' => 1,
                'stdout' => '',
                'stderr' => "quadb error: key not found in db\n",
            ], QuadbStore::getIntegerCommandOutput($storeDir, '1'));
        } finally {
            quadrableQuadbRemoveDir($missingDir);
            quadrableQuadbRemoveDir($storeDir);
        }
    },
    'native quadb store maps mineHash and length command output' => static function (TestRunner $t): void {
        $missingDir = quadrableQuadbTempDir();
        $storeDir = quadrableQuadbTempDir();

        try {
            $mined = QuadbStore::mineHashCommandOutput('101010', 1, 200);
            $t->same(0, $mined['exitCode']);
            $t->same('', $mined['stderr']);
            $t->same(QuadbStore::mineHashText('101010', 1, 200), $mined['stdout']);
            $t->true(str_starts_with($mined['stdout'], '146 -> '));

            $t->same([
                'exitCode' => 1,
                'stdout' => '',
                'stderr' => "quadb error: bit prefix must contain only 0 and 1\n",
            ], QuadbStore::mineHashCommandOutput('10x'));

            $missingLength = QuadbStore::lengthCommandOutput($missingDir);
            $t->same([
                'exitCode' => 1,
                'stdout' => '',
                'stderr' => "quadb error: Could not access directory '{$missingDir}/': No such file or directory\n",
            ], $missingLength);
            $t->true(!is_dir($missingDir), 'missing length command should not create the database directory');

            if (!mkdir($storeDir, 0755, true) && !is_dir($storeDir)) {
                throw new RuntimeException('unable to create quadrable temp directory');
            }

            $t->same([
                'exitCode' => 0,
                'stdout' => '',
                'stderr' => '',
            ], QuadbStore::lengthCommandOutput($storeDir));

            QuadbStore::open($storeDir)->importLines(
                "wp_options:siteurl|https://example.test\n"
                . "wp_posts:1|Published post\n",
                '|'
            );
            $t->same([
                'exitCode' => 0,
                'stdout' => '',
                'stderr' => '',
            ], QuadbStore::lengthCommandOutput($storeDir));
        } finally {
            quadrableQuadbRemoveDir($missingDir);
            quadrableQuadbRemoveDir($storeDir);
        }
    },
    'native quadb store maps import int command output and stoi-style input' => static function (TestRunner $t): void {
        $missingDir = quadrableQuadbTempDir();
        $storeDir = quadrableQuadbTempDir();

        try {
            $missingImport = QuadbStore::importIntegerCommandOutput(
                $missingDir,
                "1,wp_options:siteurl=https://example.test\n"
            );
            $t->same([
                'exitCode' => 1,
                'stdout' => '',
                'stderr' => "quadb error: Could not access directory '{$missingDir}/': No such file or directory\n",
            ], $missingImport);
            $t->true(!is_dir($missingDir), 'missing import --int command should not create the database directory');

            if (!mkdir($storeDir, 0755, true) && !is_dir($storeDir)) {
                throw new RuntimeException('unable to create quadrable temp directory');
            }

            $t->same([
                'exitCode' => 0,
                'stdout' => '',
                'stderr' => '',
            ], QuadbStore::importIntegerCommandOutput(
                $storeDir,
                "1x,wp_options:siteurl=https://example.test\n"
            ));
            $t->same(
                "1,wp_options:siteurl=https://example.test\n",
                QuadbStore::open($storeDir)->exportIntegerLines()
            );

            $t->same([
                'exitCode' => 1,
                'stdout' => '',
                'stderr' => "quadb error: couldn't find separator in input line\n",
            ], QuadbStore::importIntegerCommandOutput($storeDir, "missing separator\n"));
            $t->same([
                'exitCode' => 1,
                'stdout' => '',
                'stderr' => "quadb error: stoi\n",
            ], QuadbStore::importIntegerCommandOutput($storeDir, "abc,value\n"));
            $t->same([
                'exitCode' => 1,
                'stdout' => '',
                'stderr' => "quadb error: int range exceeded\n",
            ], QuadbStore::importIntegerCommandOutput($storeDir, "-1,value\n"));
            $t->same([
                'exitCode' => 1,
                'stdout' => '',
                'stderr' => "quadb error: stoi\n",
            ], QuadbStore::importIntegerCommandOutput($storeDir, "2147483648,value\n"));
            $t->same(
                "1,wp_options:siteurl=https://example.test\n",
                QuadbStore::open($storeDir)->exportIntegerLines()
            );
        } finally {
            quadrableQuadbRemoveDir($missingDir);
            quadrableQuadbRemoveDir($storeDir);
        }
    },
    'native quadb store maps export int command output' => static function (TestRunner $t): void {
        $missingDir = quadrableQuadbTempDir();
        $storeDir = quadrableQuadbTempDir();

        try {
            $missingExport = QuadbStore::exportIntegerCommandOutput($missingDir, '|');
            $t->same([
                'exitCode' => 1,
                'stdout' => '',
                'stderr' => "quadb error: Could not access directory '{$missingDir}/': No such file or directory\n",
            ], $missingExport);
            $t->true(!is_dir($missingDir), 'missing export --int command should not create the database directory');

            if (!mkdir($storeDir, 0755, true) && !is_dir($storeDir)) {
                throw new RuntimeException('unable to create quadrable temp directory');
            }

            $t->same([
                'exitCode' => 0,
                'stdout' => '',
                'stderr' => '',
            ], QuadbStore::exportIntegerCommandOutput($storeDir, '|'));

            $t->same([
                'exitCode' => 0,
                'stdout' => '',
                'stderr' => '',
            ], QuadbStore::importIntegerCommandOutput(
                $storeDir,
                "10|wp_options:siteurl=https://example.test\n"
                . "2|wp_posts:1=Published post\n",
                '|'
            ));
            $t->same([
                'exitCode' => 0,
                'stdout' => "2|wp_posts:1=Published post\n10|wp_options:siteurl=https://example.test\n",
                'stderr' => '',
            ], QuadbStore::exportIntegerCommandOutput($storeDir, '|'));
            $t->same([
                'exitCode' => 1,
                'stdout' => '',
                'stderr' => "quadb error: separator must be non-empty\n",
            ], QuadbStore::exportIntegerCommandOutput($storeDir, ''));
        } finally {
            quadrableQuadbRemoveDir($missingDir);
            quadrableQuadbRemoveDir($storeDir);
        }
    },
    'native quadb store maps plain import and export command output' => static function (TestRunner $t): void {
        $missingDir = quadrableQuadbTempDir();
        $storeDir = quadrableQuadbTempDir();

        try {
            $missingImport = QuadbStore::importCommandOutput(
                $missingDir,
                "wp_options:siteurl=https://example.test\n",
                '='
            );
            $t->same([
                'exitCode' => 1,
                'stdout' => '',
                'stderr' => "quadb error: Could not access directory '{$missingDir}/': No such file or directory\n",
            ], $missingImport);
            $t->true(!is_dir($missingDir), 'missing import command should not create the database directory');

            $missingExport = QuadbStore::exportCommandOutput($missingDir, '=');
            $t->same([
                'exitCode' => 1,
                'stdout' => '',
                'stderr' => "quadb error: Could not access directory '{$missingDir}/': No such file or directory\n",
            ], $missingExport);
            $t->true(!is_dir($missingDir), 'missing export command should not create the database directory');

            if (!mkdir($storeDir, 0755, true) && !is_dir($storeDir)) {
                throw new RuntimeException('unable to create quadrable temp directory');
            }

            $t->same([
                'exitCode' => 0,
                'stdout' => '',
                'stderr' => '',
            ], QuadbStore::exportCommandOutput($storeDir, '='));

            $t->same([
                'exitCode' => 0,
                'stdout' => '',
                'stderr' => '',
            ], QuadbStore::importCommandOutput(
                $storeDir,
                "wp_options:siteurl=https://example.test\nwp_posts:1=Published post\n",
                '='
            ));
            $t->same([
                'exitCode' => 0,
                'stdout' => "wp_posts:1=Published post\nwp_options:siteurl=https://example.test\n",
                'stderr' => '',
            ], QuadbStore::exportCommandOutput($storeDir, '='));

            $t->same([
                'exitCode' => 1,
                'stdout' => '',
                'stderr' => "quadb error: couldn't find separator in input line\n",
            ], QuadbStore::importCommandOutput($storeDir, "missing separator\n", '='));
            $t->same([
                'exitCode' => 0,
                'stdout' => "wp_posts:1=Published post\nwp_options:siteurl=https://example.test\n",
                'stderr' => '',
            ], QuadbStore::exportCommandOutput($storeDir, '='));
        } finally {
            quadrableQuadbRemoveDir($missingDir);
            quadrableQuadbRemoveDir($storeDir);
        }
    },
    'native quadb store maps plain import and export empty separator command output' => static function (TestRunner $t): void {
        $storeDir = quadrableQuadbTempDir();

        try {
            if (!mkdir($storeDir, 0755, true) && !is_dir($storeDir)) {
                throw new RuntimeException('unable to create quadrable temp directory');
            }

            $t->same([
                'exitCode' => 1,
                'stdout' => '',
                'stderr' => "quadb error: separator must be non-empty\n",
            ], QuadbStore::importCommandOutput($storeDir, "wp_options:siteurl=https://example.test\n", ''));

            $t->same([
                'exitCode' => 1,
                'stdout' => '',
                'stderr' => "quadb error: separator must be non-empty\n",
            ], QuadbStore::exportCommandOutput($storeDir, ''));

            $t->same([
                'exitCode' => 0,
                'stdout' => '',
                'stderr' => '',
            ], QuadbStore::exportCommandOutput($storeDir, '='));
        } finally {
            quadrableQuadbRemoveDir($storeDir);
        }
    },
    'native quadb store maps proof command output for invalid format and hex input' => static function (TestRunner $t): void {
        $missingDir = quadrableQuadbTempDir();
        $sourceDir = quadrableQuadbTempDir();
        $targetDir = quadrableQuadbTempDir();
        $uppercaseRootDir = quadrableQuadbTempDir();
        $emptyRootDir = quadrableQuadbTempDir();
        $emptyPrefixedRootDir = quadrableQuadbTempDir();
        $hexDumpDir = quadrableQuadbTempDir();

        try {
            $missingImport = QuadbStore::importProofHexCommandOutput($missingDir, 'zz');
            $t->same([
                'exitCode' => 1,
                'stdout' => '',
                'stderr' => "quadb error: Could not access directory '{$missingDir}/': No such file or directory\n",
            ], $missingImport);
            $t->true(!is_dir($missingDir), 'missing importProof command should not create the database directory');

            $source = QuadbStore::init($sourceDir);
            $source->put('wp_options:siteurl', 'https://example.test');
            $proofHex = $source->exportProofHex(['wp_options:siteurl'], Proof::ENCODING_FULL_KEYS);

            $t->same([
                'exitCode' => 1,
                'stdout' => '',
                'stderr' => "quadb error: unknown proof format\n",
            ], QuadbStore::exportProofCommandOutput($sourceDir, ['wp_options:siteurl'], 'Bad', true));
            $dumpWithBadFormat = QuadbStore::exportProofCommandOutput(
                $sourceDir,
                ['wp_options:siteurl'],
                'Bad',
                false,
                true
            );
            $t->same(0, $dumpWithBadFormat['exitCode']);
            $t->contains('ITEMS (1):', $dumpWithBadFormat['stdout']);
            $t->contains('wp_options:siteurl', $dumpWithBadFormat['stdout']);
            $t->same('', $dumpWithBadFormat['stderr']);
            $t->same([
                'exitCode' => 0,
                'stdout' => $proofHex,
                'stderr' => '',
            ], QuadbStore::exportProofCommandOutput($sourceDir, ['wp_options:siteurl'], 'FullKeys', true));

            if (!mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
                throw new RuntimeException('unable to create quadrable temp directory');
            }

            foreach ([
                '' => 'proof ends prematurely',
                '0x' => 'proof ends prematurely',
                'abc' => 'unexpected proof encoding type: 10',
                'zz' => 'unexpected character in from_hex: 122',
                '00' => 'proof ends prematurely',
                '0001' => 'empty proof',
                '0X00' => 'unexpected character in from_hex: 88',
                '01000080' => 'premature end of varint',
            ] as $input => $message) {
                $t->same([
                    'exitCode' => 1,
                    'stdout' => '',
                    'stderr' => "quadb error: {$message}\n",
                ], QuadbStore::importProofHexCommandOutput($targetDir, $input));
                $t->same([
                    'exitCode' => 1,
                    'stdout' => '',
                    'stderr' => "quadb error: {$message}\n",
                ], QuadbStore::mergeProofHexCommandOutput($targetDir, $input));
            }

            foreach ([
                '0X' . $source->tree()->rootHash() => 'unexpected character in from_hex: 88',
                'zz' => 'unexpected character in from_hex: 122',
                '0x00' => 'proof invalid',
                'abc' => 'proof invalid',
            ] as $rootInput => $message) {
                $t->same([
                    'exitCode' => 1,
                    'stdout' => '',
                    'stderr' => "quadb error: {$message}\n",
                ], QuadbStore::importProofHexCommandOutput($targetDir, $proofHex, $rootInput));
            }
            $t->same([
                'exitCode' => 1,
                'stdout' => '',
                'stderr' => "quadb error: empty proof\n",
            ], QuadbStore::importProofHexCommandOutput($targetDir, '0001', '0x00'));
            $t->same('0x' . HashTree::EMPTY_HASH . "\n", QuadbStore::open($targetDir)->rootText());

            if (!mkdir($hexDumpDir, 0755, true) && !is_dir($hexDumpDir)) {
                throw new RuntimeException('unable to create quadrable temp directory');
            }
            $hexDumpWithInvalidRoot = QuadbStore::importProofHexCommandOutput(
                $hexDumpDir,
                $proofHex,
                '0X' . $source->tree()->rootHash(),
                true
            );
            $t->same(0, $hexDumpWithInvalidRoot['exitCode']);
            $t->contains('ITEMS (1):', $hexDumpWithInvalidRoot['stdout']);
            $t->contains('wp_options:siteurl', $hexDumpWithInvalidRoot['stdout']);
            $t->same('', $hexDumpWithInvalidRoot['stderr']);
            $t->same('0x' . HashTree::EMPTY_HASH . "\n", QuadbStore::open($hexDumpDir)->rootText());

            if (!mkdir($uppercaseRootDir, 0755, true) && !is_dir($uppercaseRootDir)) {
                throw new RuntimeException('unable to create quadrable temp directory');
            }
            $uppercaseRoot = QuadbStore::importProofHexCommandOutput(
                $uppercaseRootDir,
                $proofHex,
                '0x' . strtoupper($source->tree()->rootHash())
            );
            $t->same(0, $uppercaseRoot['exitCode']);
            $t->same('', $uppercaseRoot['stdout']);
            $t->same('', $uppercaseRoot['stderr']);
            $t->same('https://example.test', QuadbStore::open($uppercaseRootDir)->get('wp_options:siteurl'));

            if (!mkdir($emptyRootDir, 0755, true) && !is_dir($emptyRootDir)) {
                throw new RuntimeException('unable to create quadrable temp directory');
            }
            $emptyRoot = QuadbStore::importProofHexCommandOutput($emptyRootDir, $proofHex, '');
            $t->same(0, $emptyRoot['exitCode']);
            $t->same('', $emptyRoot['stdout']);
            $t->same('', $emptyRoot['stderr']);
            $t->same('https://example.test', QuadbStore::open($emptyRootDir)->get('wp_options:siteurl'));

            if (!mkdir($emptyPrefixedRootDir, 0755, true) && !is_dir($emptyPrefixedRootDir)) {
                throw new RuntimeException('unable to create quadrable temp directory');
            }
            $emptyPrefixedRoot = QuadbStore::importProofHexCommandOutput($emptyPrefixedRootDir, $proofHex, '0x');
            $t->same(0, $emptyPrefixedRoot['exitCode']);
            $t->same('', $emptyPrefixedRoot['stdout']);
            $t->same('', $emptyPrefixedRoot['stderr']);
            $t->same('https://example.test', QuadbStore::open($emptyPrefixedRootDir)->get('wp_options:siteurl'));

            $trustedImport = QuadbStore::importProofHexCommandOutput($targetDir, $proofHex, $source->tree()->rootHash());
            $t->same(0, $trustedImport['exitCode']);
            $t->same('', $trustedImport['stdout']);
            $t->same('', $trustedImport['stderr']);
            $t->same('https://example.test', QuadbStore::open($targetDir)->get('wp_options:siteurl'));
        } finally {
            quadrableQuadbRemoveDir($missingDir);
            quadrableQuadbRemoveDir($sourceDir);
            quadrableQuadbRemoveDir($targetDir);
            quadrableQuadbRemoveDir($uppercaseRootDir);
            quadrableQuadbRemoveDir($emptyRootDir);
            quadrableQuadbRemoveDir($emptyPrefixedRootDir);
            quadrableQuadbRemoveDir($hexDumpDir);
        }
    },
    'native quadb store maps binary importProof command root and dump precedence' => static function (TestRunner $t): void {
        $missingDir = quadrableQuadbTempDir();
        $sourceDir = quadrableQuadbTempDir();
        $targetDir = quadrableQuadbTempDir();
        $dumpDir = quadrableQuadbTempDir();
        $trustedDir = quadrableQuadbTempDir();
        $emptyRootDir = quadrableQuadbTempDir();
        $unauthenticatedDir = quadrableQuadbTempDir();

        try {
            $missingImport = QuadbStore::importProofCommandOutput($missingDir, 'not a proof');
            $t->same([
                'exitCode' => 1,
                'stdout' => '',
                'stderr' => "quadb error: Could not access directory '{$missingDir}/': No such file or directory\n",
            ], $missingImport);
            $t->true(!is_dir($missingDir), 'missing binary importProof command should not create the database directory');

            $source = QuadbStore::init($sourceDir);
            $source->put('wp_options:siteurl', 'https://example.test');
            $proofBytes = $source->exportProofBytes(['wp_options:siteurl'], Proof::ENCODING_FULL_KEYS);
            $root = $source->tree()->rootHash();
            $expectedDump = Proof::decode($proofBytes)->dumpText();

            if (!mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
                throw new RuntimeException('unable to create quadrable temp directory');
            }
            $t->same([
                'exitCode' => 1,
                'stdout' => '',
                'stderr' => "quadb error: unexpected character in from_hex: 88\n",
            ], QuadbStore::importProofCommandOutput($targetDir, $proofBytes, '0X' . $root));
            $t->same('0x' . HashTree::EMPTY_HASH . "\n", QuadbStore::open($targetDir)->rootText());

            if (!mkdir($dumpDir, 0755, true) && !is_dir($dumpDir)) {
                throw new RuntimeException('unable to create quadrable temp directory');
            }
            $dumpWithInvalidRoot = QuadbStore::importProofCommandOutput($dumpDir, $proofBytes, '0X' . $root, true);
            $t->same(0, $dumpWithInvalidRoot['exitCode']);
            $t->same($expectedDump, $dumpWithInvalidRoot['stdout']);
            $t->same('', $dumpWithInvalidRoot['stderr']);
            $t->same('0x' . HashTree::EMPTY_HASH . "\n", QuadbStore::open($dumpDir)->rootText());

            if (!mkdir($trustedDir, 0755, true) && !is_dir($trustedDir)) {
                throw new RuntimeException('unable to create quadrable temp directory');
            }
            $trustedImport = QuadbStore::importProofCommandOutput($trustedDir, $proofBytes, $root);
            $t->same(0, $trustedImport['exitCode']);
            $t->same('', $trustedImport['stdout']);
            $t->same('', $trustedImport['stderr']);
            $t->same('https://example.test', QuadbStore::open($trustedDir)->get('wp_options:siteurl'));

            if (!mkdir($emptyRootDir, 0755, true) && !is_dir($emptyRootDir)) {
                throw new RuntimeException('unable to create quadrable temp directory');
            }
            $emptyRootImport = QuadbStore::importProofCommandOutput($emptyRootDir, $proofBytes, '0x');
            $t->same(0, $emptyRootImport['exitCode']);
            $t->same('', $emptyRootImport['stdout']);
            $t->same('', $emptyRootImport['stderr']);
            $t->same('https://example.test', QuadbStore::open($emptyRootDir)->get('wp_options:siteurl'));

            if (!mkdir($unauthenticatedDir, 0755, true) && !is_dir($unauthenticatedDir)) {
                throw new RuntimeException('unable to create quadrable temp directory');
            }
            $unauthenticatedImport = QuadbStore::importProofCommandOutput($unauthenticatedDir, $proofBytes);
            $t->same(0, $unauthenticatedImport['exitCode']);
            $t->same('Imported UNAUTHENTICATED proof. Root: 0x' . $root . "\n", $unauthenticatedImport['stdout']);
            $t->same('', $unauthenticatedImport['stderr']);
            $t->same('https://example.test', QuadbStore::open($unauthenticatedDir)->get('wp_options:siteurl'));
        } finally {
            quadrableQuadbRemoveDir($missingDir);
            quadrableQuadbRemoveDir($sourceDir);
            quadrableQuadbRemoveDir($targetDir);
            quadrableQuadbRemoveDir($dumpDir);
            quadrableQuadbRemoveDir($trustedDir);
            quadrableQuadbRemoveDir($emptyRootDir);
            quadrableQuadbRemoveDir($unauthenticatedDir);
        }
    },
    'native quadb store maps binary mergeProof command output' => static function (TestRunner $t): void {
        $missingDir = quadrableQuadbTempDir();
        $sourceDir = quadrableQuadbTempDir();
        $invalidDir = quadrableQuadbTempDir();
        $fullDir = quadrableQuadbTempDir();
        $targetDir = quadrableQuadbTempDir();

        try {
            $missingMerge = QuadbStore::mergeProofCommandOutput($missingDir, 'not a proof');
            $t->same([
                'exitCode' => 1,
                'stdout' => '',
                'stderr' => "quadb error: Could not access directory '{$missingDir}/': No such file or directory\n",
            ], $missingMerge);
            $t->true(!is_dir($missingDir), 'missing binary mergeProof command should not create the database directory');

            if (!mkdir($invalidDir, 0755, true) && !is_dir($invalidDir)) {
                throw new RuntimeException('unable to create quadrable temp directory');
            }
            $t->same([
                'exitCode' => 1,
                'stdout' => '',
                'stderr' => "quadb error: unexpected proof encoding type: 110\n",
            ], QuadbStore::mergeProofCommandOutput($invalidDir, 'not a proof'));
            $t->same('0x' . HashTree::EMPTY_HASH . "\n", QuadbStore::open($invalidDir)->rootText());

            $source = QuadbStore::init($sourceDir);
            $source->put('wp_options:siteurl', 'https://example.test');
            $source->put('wp_options:home', 'https://example.test');
            $root = $source->tree()->rootHash();
            $siteProofBytes = $source->exportProofBytes(['wp_options:siteurl'], Proof::ENCODING_FULL_KEYS);
            $homeProofBytes = $source->exportProofBytes(['wp_options:home'], Proof::ENCODING_FULL_KEYS);

            QuadbStore::init($fullDir);
            $t->same([
                'exitCode' => 1,
                'stdout' => '',
                'stderr' => "quadb error: different roots, unable to merge proofs\n",
            ], QuadbStore::mergeProofCommandOutput($fullDir, $siteProofBytes));
            $t->same('0x' . HashTree::EMPTY_HASH . "\n", QuadbStore::open($fullDir)->rootText());

            $target = QuadbStore::init($targetDir);
            $target->importProofBytes($siteProofBytes, $root);
            $t->same([
                'exitCode' => 0,
                'stdout' => '',
                'stderr' => '',
            ], QuadbStore::mergeProofCommandOutput($targetDir, $homeProofBytes));
            $merged = QuadbStore::open($targetDir);
            $t->same('https://example.test', $merged->get('wp_options:siteurl'));
            $t->same('https://example.test', $merged->get('wp_options:home'));
        } finally {
            quadrableQuadbRemoveDir($missingDir);
            quadrableQuadbRemoveDir($sourceDir);
            quadrableQuadbRemoveDir($invalidDir);
            quadrableQuadbRemoveDir($fullDir);
            quadrableQuadbRemoveDir($targetDir);
        }
    },
    'native quadb store maps init streams and advertised length no-op output' => static function (TestRunner $t): void {
        $dir = quadrableQuadbTempDir();

        try {
            $t->same([
                'stdout' => "quadb: init'ing directory: {$dir}/\n",
                'stderr' => '',
            ], QuadbStore::initCommandOutput($dir));

            $repo = QuadbStore::open($dir);
            $t->same('', $repo->lengthText());
            $t->same("Head: master\nRoot: 0x" . HashTree::EMPTY_HASH . " (0)\n", $repo->statusText());

            $repo->importLines(
                "wp_options:siteurl|https://example.test\n"
                . "wp_posts:1|Published post\n",
                '|'
            );
            $rootAfterImport = $repo->tree()->rootHash();

            $t->same('', $repo->lengthText());
            $t->same([
                'stdout' => '',
                'stderr' => "quadb: Directory '{$dir}/' already init'ed. Doing nothing.\n",
            ], QuadbStore::initCommandOutput($dir));
            $t->same($rootAfterImport, QuadbStore::open($dir)->tree()->rootHash());
            $t->same('', QuadbStore::open($dir)->lengthText());
        } finally {
            quadrableQuadbRemoveDir($dir);
        }
    },
    'native quadb root command maps missing and empty directory startup behavior' => static function (TestRunner $t): void {
        $missingDir = quadrableQuadbTempDir();
        $emptyDir = quadrableQuadbTempDir();

        try {
            $missing = QuadbStore::rootCommandOutput($missingDir);
            $t->same([
                'exitCode' => 1,
                'stdout' => '',
                'stderr' => "quadb error: Could not access directory '{$missingDir}/': No such file or directory\n",
            ], $missing);
            $t->true(!is_dir($missingDir), 'missing root command should not create the database directory');

            if (!mkdir($emptyDir, 0755, true) && !is_dir($emptyDir)) {
                throw new RuntimeException('unable to create empty quadrable temp directory');
            }

            $empty = QuadbStore::rootCommandOutput($emptyDir);
            $t->same([
                'exitCode' => 0,
                'stdout' => '0x' . HashTree::EMPTY_HASH . "\n",
                'stderr' => '',
            ], $empty);
            $t->same(HashTree::EMPTY_HASH, QuadbStore::open($emptyDir)->tree()->rootHash());

            $repo = QuadbStore::open($emptyDir);
            $repo->importLines(
                "wp_options:siteurl|https://example.test\n"
                . "wp_posts:1|Published post\n",
                '|'
            );
            $rootAfterImport = $repo->tree()->rootHash();

            $t->same([
                'exitCode' => 0,
                'stdout' => '0x' . $rootAfterImport . "\n",
                'stderr' => '',
            ], QuadbStore::rootCommandOutput($emptyDir));
        } finally {
            quadrableQuadbRemoveDir($missingDir);
            quadrableQuadbRemoveDir($emptyDir);
        }
    },
    'native quadb store maps status head stats gc and dumpTree command output' => static function (TestRunner $t): void {
        $missingDir = quadrableQuadbTempDir();
        $emptyDir = quadrableQuadbTempDir();
        $storeDir = quadrableQuadbTempDir();

        try {
            $missingError = "quadb error: Could not access directory '{$missingDir}/': No such file or directory\n";
            $t->same([
                'exitCode' => 1,
                'stdout' => '',
                'stderr' => $missingError,
            ], QuadbStore::statusCommandOutput($missingDir));
            $t->same([
                'exitCode' => 1,
                'stdout' => '',
                'stderr' => $missingError,
            ], QuadbStore::headCommandOutput($missingDir));
            $t->same([
                'exitCode' => 1,
                'stdout' => '',
                'stderr' => $missingError,
            ], QuadbStore::headRemoveCommandOutput($missingDir, 'wp-preview'));
            $t->same([
                'exitCode' => 1,
                'stdout' => '',
                'stderr' => $missingError,
            ], QuadbStore::statsCommandOutput($missingDir));
            $t->same([
                'exitCode' => 1,
                'stdout' => '',
                'stderr' => $missingError,
            ], QuadbStore::garbageCollectCommandOutput($missingDir));
            $t->same([
                'exitCode' => 1,
                'stdout' => '',
                'stderr' => $missingError,
            ], QuadbStore::dumpTreeCommandOutput($missingDir));
            $t->true(!is_dir($missingDir), 'missing inspection commands should not create the database directory');

            if (!mkdir($emptyDir, 0755, true) && !is_dir($emptyDir)) {
                throw new RuntimeException('unable to create empty quadrable temp directory');
            }

            $emptyStatus = "Head: master\nRoot: 0x" . HashTree::EMPTY_HASH . " (0)\n";
            $emptyDump = "-----------------\n"
                . "0x00000000... (0) empty\n"
                . "-----------------\n";
            $t->same([
                'exitCode' => 0,
                'stdout' => $emptyStatus,
                'stderr' => '',
            ], QuadbStore::statusCommandOutput($emptyDir));
            $t->same([
                'exitCode' => 0,
                'stdout' => '',
                'stderr' => '',
            ], QuadbStore::headCommandOutput($emptyDir));
            $t->same([
                'exitCode' => 0,
                'stdout' => "numNodes:        0\n"
                    . "numLeafNodes:    0\n"
                    . "numBranchNodes:  0\n"
                    . "numWitnessNodes: 0\n"
                    . "maxDepth:        0\n"
                    . "numBytes:        0\n",
                'stderr' => '',
            ], QuadbStore::statsCommandOutput($emptyDir));
            $t->same([
                'exitCode' => 0,
                'stdout' => "Collected 0/0 nodes\n",
                'stderr' => '',
            ], QuadbStore::garbageCollectCommandOutput($emptyDir));
            $t->same([
                'exitCode' => 0,
                'stdout' => $emptyDump,
                'stderr' => '',
            ], QuadbStore::dumpTreeCommandOutput($emptyDir));

            if (!mkdir($storeDir, 0755, true) && !is_dir($storeDir)) {
                throw new RuntimeException('unable to create quadrable temp directory');
            }

            $t->same([
                'exitCode' => 0,
                'stdout' => '',
                'stderr' => '',
            ], QuadbStore::putCommandOutput($storeDir, 'wp_options:siteurl', 'https://example.test'));
            $t->same([
                'exitCode' => 0,
                'stdout' => '',
                'stderr' => '',
            ], QuadbStore::forkCommandOutput($storeDir, 'preview'));
            $t->same([
                'exitCode' => 0,
                'stdout' => '',
                'stderr' => '',
            ], QuadbStore::putCommandOutput($storeDir, 'wp_options:home', 'https://preview.example.test'));

            $repo = QuadbStore::open($storeDir);
            $t->same([
                'exitCode' => 0,
                'stdout' => $repo->statusText(),
                'stderr' => '',
            ], QuadbStore::statusCommandOutput($storeDir));
            $t->same([
                'exitCode' => 0,
                'stdout' => $repo->headText(),
                'stderr' => '',
            ], QuadbStore::headCommandOutput($storeDir));
            $t->same([
                'exitCode' => 0,
                'stdout' => $repo->statsText(),
                'stderr' => '',
            ], QuadbStore::statsCommandOutput($storeDir));
            $t->same([
                'exitCode' => 0,
                'stdout' => $repo->dumpTreeText(),
                'stderr' => '',
            ], QuadbStore::dumpTreeCommandOutput($storeDir));

            $storedBeforeRemove = quadrableQuadbStoredNodeCount($repo);
            $t->same([
                'exitCode' => 0,
                'stdout' => '',
                'stderr' => '',
            ], QuadbStore::headRemoveCommandOutput($storeDir));

            $afterRemove = QuadbStore::open($storeDir);
            $t->same("Head: preview\nRoot: 0x" . HashTree::EMPTY_HASH . " (0)\n", $afterRemove->statusText());
            $t->contains('   master : ', $afterRemove->headText());
            $t->true(!str_contains($afterRemove->headText(), 'preview :'), 'removed current head should disappear from head output');

            $t->same([
                'exitCode' => 0,
                'stdout' => '',
                'stderr' => '',
            ], QuadbStore::forkCommandOutput($storeDir, 'discarded-preview', 'master'));
            $t->same([
                'exitCode' => 0,
                'stdout' => '',
                'stderr' => '',
            ], QuadbStore::putCommandOutput($storeDir, 'wp_posts:1', 'Discarded preview'));
            $discarded = QuadbStore::open($storeDir);
            $t->contains('=> discarded-preview : ', $discarded->headText());
            $t->same([
                'exitCode' => 0,
                'stdout' => '',
                'stderr' => '',
            ], QuadbStore::headRemoveCommandOutput($storeDir, 'discarded-preview'));
            $afterNamedRemove = QuadbStore::open($storeDir);
            $t->same('discarded-preview', $afterNamedRemove->currentHeadName());
            $t->same(HashTree::EMPTY_HASH, $afterNamedRemove->tree()->rootHash());
            $t->true(!str_contains($afterNamedRemove->headText(), 'discarded-preview :'), 'named head rm should remove the requested head');

            $t->same([
                'exitCode' => 0,
                'stdout' => '',
                'stderr' => '',
            ], QuadbStore::checkoutCommandOutput($storeDir));
            $t->same([
                'exitCode' => 0,
                'stdout' => '',
                'stderr' => '',
            ], QuadbStore::putCommandOutput($storeDir, 'wp_posts:2', 'Detached draft'));
            $detachedBeforeRemove = QuadbStore::open($storeDir);
            $t->true($detachedBeforeRemove->isDetachedHead());
            $t->contains('D> [detached] : ', $detachedBeforeRemove->headText());
            $t->same([
                'exitCode' => 0,
                'stdout' => '',
                'stderr' => '',
            ], QuadbStore::headRemoveCommandOutput($storeDir));
            $afterDetachedRemove = QuadbStore::open($storeDir);
            $t->true($afterDetachedRemove->isDetachedHead());
            $t->same(HashTree::EMPTY_HASH, $afterDetachedRemove->tree()->rootHash());
            $t->contains('D> [detached] : 0x' . HashTree::EMPTY_HASH . ' (0)', $afterDetachedRemove->headText());

            $gcCommand = QuadbStore::garbageCollectCommandOutput($storeDir);
            $t->same(0, $gcCommand['exitCode']);
            $t->same('', $gcCommand['stderr']);
            $gc = quadrableQuadbParseGcText($gcCommand['stdout']);
            $t->true($gc['garbage'] > 0);
        } finally {
            quadrableQuadbRemoveDir($missingDir);
            quadrableQuadbRemoveDir($emptyDir);
            quadrableQuadbRemoveDir($storeDir);
        }
    },
    'native quadb store maps checkout and fork command output' => static function (TestRunner $t): void {
        $missingDir = quadrableQuadbTempDir();
        $storeDir = quadrableQuadbTempDir();

        try {
            $missingCheckout = QuadbStore::checkoutCommandOutput($missingDir, 'wp-preview');
            $t->same([
                'exitCode' => 1,
                'stdout' => '',
                'stderr' => "quadb error: Could not access directory '{$missingDir}/': No such file or directory\n",
            ], $missingCheckout);
            $t->true(!is_dir($missingDir), 'missing checkout command should not create the database directory');

            $missingFork = QuadbStore::forkCommandOutput($missingDir, 'wp-approved');
            $t->same([
                'exitCode' => 1,
                'stdout' => '',
                'stderr' => "quadb error: Could not access directory '{$missingDir}/': No such file or directory\n",
            ], $missingFork);
            $t->true(!is_dir($missingDir), 'missing fork command should not create the database directory');

            if (!mkdir($storeDir, 0755, true) && !is_dir($storeDir)) {
                throw new RuntimeException('unable to create quadrable temp directory');
            }

            $t->same([
                'exitCode' => 0,
                'stdout' => '',
                'stderr' => '',
            ], QuadbStore::checkoutCommandOutput($storeDir, 'wp-preview'));
            $t->same("Head: wp-preview\nRoot: 0x" . HashTree::EMPTY_HASH . " (0)\n", QuadbStore::open($storeDir)->statusText());

            $t->same([
                'exitCode' => 0,
                'stdout' => '',
                'stderr' => '',
            ], QuadbStore::putCommandOutput($storeDir, 'wp_options:siteurl', 'https://preview.example.test'));
            $preview = QuadbStore::open($storeDir);
            $previewRoot = $preview->tree()->rootHash();
            $previewHeadNodeId = $preview->tree()->headNodeId();

            $t->same([
                'exitCode' => 0,
                'stdout' => '',
                'stderr' => '',
            ], QuadbStore::forkCommandOutput($storeDir, 'wp-approved'));
            $approved = QuadbStore::open($storeDir);
            $t->same('wp-approved', $approved->currentHeadName());
            $t->same($previewRoot, $approved->tree()->rootHash());
            $t->same($previewHeadNodeId, $approved->tree()->headNodeId());

            $t->same([
                'exitCode' => 0,
                'stdout' => '',
                'stderr' => '',
            ], QuadbStore::checkoutCommandOutput($storeDir));
            $detached = QuadbStore::open($storeDir);
            $t->true($detached->isDetachedHead());
            $t->same("Detached head\nRoot: 0x" . HashTree::EMPTY_HASH . " (0)\n", $detached->statusText());
            $t->contains("   wp-approved : 0x{$previewRoot} ({$previewHeadNodeId})\n", $detached->headText());

            $t->same([
                'exitCode' => 0,
                'stdout' => '',
                'stderr' => '',
            ], QuadbStore::forkCommandOutput($storeDir, 'wp-empty', 'missing-head'));
            $emptyFork = QuadbStore::open($storeDir);
            $t->same('wp-empty', $emptyFork->currentHeadName());
            $t->same("Head: wp-empty\nRoot: 0x" . HashTree::EMPTY_HASH . " (0)\n", $emptyFork->statusText());
            $t->contains("=> wp-empty : 0x" . HashTree::EMPTY_HASH . " (0)\n", $emptyFork->headText());
        } finally {
            quadrableQuadbRemoveDir($missingDir);
            quadrableQuadbRemoveDir($storeDir);
        }
    },
    'native quadb store reopens the current named head and integer import export lines' => static function (TestRunner $t): void {
        $dir = quadrableQuadbTempDir();

        try {
            $repo = QuadbStore::init($dir);
            $t->same('master', $repo->currentHeadName());
            $t->same(2, $repo->importIntegerLines("1,wp_options:siteurl=https://example.test\n3,wp_posts:1=Hello world\n"));

            $master = $repo->tree();
            $masterRoot = $master->rootHash();
            $masterHeadNodeId = $master->headNodeId();

            $reopened = QuadbStore::open($dir);
            $t->same('master', $reopened->currentHeadName());
            $t->same($masterRoot, $reopened->tree()->rootHash());
            $t->same($masterHeadNodeId, $reopened->tree()->headNodeId());
            $t->same([
                '1,wp_options:siteurl=https://example.test',
                '3,wp_posts:1=Hello world',
            ], quadrableQuadbSortedLines($reopened->exportIntegerLines()));

            $preview = $reopened->checkout('wp-preview');
            $t->same(HashTree::EMPTY_HASH, $preview->rootHash());
            $t->same(2, $reopened->importIntegerLines("3,wp_posts:1=Preview edit\n4,wp_postmeta:1:_thumbnail_id=42\n"));

            $again = QuadbStore::open($dir);
            $t->same('wp-preview', $again->currentHeadName());
            $t->same('wp_posts:1=Preview edit', $again->tree()->getKey(Key::fromInteger(3)));
            $t->same('wp_postmeta:1:_thumbnail_id=42', $again->tree()->getKey(Key::fromInteger(4)));

            $restoredMaster = $again->checkout('master');
            $t->same($masterRoot, $restoredMaster->rootHash());
            $t->same($masterHeadNodeId, $restoredMaster->headNodeId());
            $t->same('wp_posts:1=Hello world', $restoredMaster->getKey(Key::fromInteger(3)));
            $t->same(null, $restoredMaster->getKey(Key::fromInteger(4)));

            $t->throws(RuntimeException::class, static fn () => $again->importIntegerLines("missing separator\n"));
        } finally {
            quadrableQuadbRemoveDir($dir);
        }
    },
    'native quadb store forks named heads across reopen without copying unchanged leaves' => static function (TestRunner $t): void {
        $dir = quadrableQuadbTempDir();

        try {
            $repo = QuadbStore::init($dir);
            $repo->importIntegerLines("1,wp_options:siteurl=https://example.test\n2,wp_options:home=https://example.test\n3,wp_posts:1=Hello world\n");

            $master = $repo->tree();
            $masterSiteUrlNodeId = 0;
            $t->same('wp_options:siteurl=https://example.test', $master->getKey(Key::fromInteger(1), $masterSiteUrlNodeId));
            $masterHeadNodeId = $master->headNodeId();
            $masterRoot = $master->rootHash();

            $snapshot = $repo->fork('wp-snapshot');
            $t->same($masterHeadNodeId, $snapshot->headNodeId());
            $t->same($masterRoot, $snapshot->rootHash());

            $repo->importIntegerLines("3,wp_posts:1=Published update\n5,wp_posts:2=New page\n");
            $updatedSnapshot = $repo->tree();
            $snapshotSiteUrlNodeId = 0;
            $t->same('wp_options:siteurl=https://example.test', $updatedSnapshot->getKey(Key::fromInteger(1), $snapshotSiteUrlNodeId));
            $t->same($masterSiteUrlNodeId, $snapshotSiteUrlNodeId);

            $reopened = QuadbStore::open($dir);
            $t->same('wp-snapshot', $reopened->currentHeadName());
            $t->same('wp_posts:1=Published update', $reopened->tree()->getKey(Key::fromInteger(3)));
            $t->same('wp_posts:2=New page', $reopened->tree()->getKey(Key::fromInteger(5)));

            $restoredMaster = $reopened->checkout('master');
            $t->same($masterRoot, $restoredMaster->rootHash());
            $t->same('wp_posts:1=Hello world', $restoredMaster->getKey(Key::fromInteger(3)));
            $t->same(null, $restoredMaster->getKey(Key::fromInteger(5)));

            $releaseCopy = $reopened->fork('wp-release-copy', 'master');
            $t->same($masterHeadNodeId, $releaseCopy->headNodeId());
            $t->same($masterRoot, $releaseCopy->rootHash());
        } finally {
            quadrableQuadbRemoveDir($dir);
        }
    },
    'native quadb store persists detached fork state across reopen' => static function (TestRunner $t): void {
        $dir = quadrableQuadbTempDir();

        try {
            $repo = QuadbStore::init($dir);
            $repo->importIntegerLines("1,wp_options:siteurl=https://example.test\n3,wp_posts:1=Hello world\n");
            $masterRoot = $repo->tree()->rootHash();

            $detached = $repo->fork();
            $t->true($repo->isDetachedHead());
            $t->same(null, $repo->currentHeadName());
            $t->same($masterRoot, $detached->rootHash());

            $detached->putKey(Key::fromInteger(3), 'wp_posts:1=Detached preview edit');
            $detached->putKey(Key::fromInteger(4), 'wp_postmeta:1:_thumbnail_id=42');
            $repo->save($detached);

            $reopened = QuadbStore::open($dir);
            $t->true($reopened->isDetachedHead());
            $t->same(null, $reopened->currentHeadName());
            $t->same('wp_posts:1=Detached preview edit', $reopened->tree()->getKey(Key::fromInteger(3)));
            $t->same('wp_postmeta:1:_thumbnail_id=42', $reopened->tree()->getKey(Key::fromInteger(4)));

            $master = $reopened->checkout('master');
            $t->same($masterRoot, $master->rootHash());
            $t->same('wp_posts:1=Hello world', $master->getKey(Key::fromInteger(3)));
            $t->same(null, $master->getKey(Key::fromInteger(4)));
        } finally {
            quadrableQuadbRemoveDir($dir);
        }
    },
    'native quadb store emits and applies tracked string-key patch lines' => static function (TestRunner $t): void {
        $dir = quadrableQuadbTempDir();

        try {
            $repo = QuadbStore::init($dir);
            $t->same(3, $repo->importLines(
                "wp_options:siteurl|https://example.test\n"
                . "wp_options:home|https://example.test\n"
                . "wp_posts:1|Hello world\n",
                '|'
            ));

            $masterRoot = $repo->tree()->rootHash();
            $masterHeadNodeId = $repo->tree()->headNodeId();

            $repo->fork('wp-preview');
            $repo->put('wp_posts:1', 'Preview edit');
            $repo->put('wp_posts:2', 'New page');
            $repo->delete('wp_options:home');

            $previewRoot = $repo->tree()->rootHash();
            $patch = $repo->diffLines('master', '|');
            $t->same([
                '+wp_posts:1|Preview edit',
                '+wp_posts:2|New page',
                '-wp_options:home|https://example.test',
                '-wp_posts:1|Hello world',
            ], quadrableQuadbSortedLines($patch));

            $reopened = QuadbStore::open($dir);
            $t->same($patch, $reopened->diffLines('master', '|'));

            $replica = $reopened->fork('wp-replica', 'master');
            $t->same($masterRoot, $replica->rootHash());
            $t->same($masterHeadNodeId, $replica->headNodeId());

            $t->same(4, $reopened->applyPatchLines("# preview patch\n" . $patch, '|'));
            $t->same($previewRoot, $reopened->tree()->rootHash());
            $t->same('Preview edit', $reopened->get('wp_posts:1'));
            $t->same('New page', $reopened->get('wp_posts:2'));
            $t->throws(RuntimeException::class, static fn () => $reopened->get('wp_options:home'));

            $t->same([
                'wp_options:siteurl|https://example.test',
                'wp_posts:1|Preview edit',
                'wp_posts:2|New page',
            ], quadrableQuadbSortedLines($reopened->exportLines('|')));

            $t->throws(RuntimeException::class, static fn () => $reopened->applyPatchLines("\n", '|'));
            $t->throws(RuntimeException::class, static fn () => $reopened->applyPatchLines("~wp_posts:1|bad\n", '|'));
            $t->throws(RuntimeException::class, static fn () => $reopened->applyPatchLines("+wp_posts:1 missing separator\n", '|'));
        } finally {
            quadrableQuadbRemoveDir($dir);
        }
    },
    'native quadb store maps diff and patch command output' => static function (TestRunner $t): void {
        $missingDir = quadrableQuadbTempDir();
        $emptyDir = quadrableQuadbTempDir();
        $dir = quadrableQuadbTempDir();

        try {
            $missingError = "quadb error: Could not access directory '{$missingDir}/': No such file or directory\n";
            $t->same([
                'exitCode' => 1,
                'stdout' => '',
                'stderr' => $missingError,
            ], QuadbStore::diffCommandOutput($missingDir, 'master', '|'));
            $t->same([
                'exitCode' => 1,
                'stdout' => '',
                'stderr' => $missingError,
            ], QuadbStore::patchCommandOutput($missingDir, "+wp_posts:1|Preview edit\n", '|'));
            $t->true(!is_dir($missingDir), 'missing diff/patch commands should not create the database directory');

            if (!mkdir($emptyDir, 0755, true) && !is_dir($emptyDir)) {
                throw new RuntimeException('unable to create quadrable temp directory');
            }
            $t->same([
                'exitCode' => 0,
                'stdout' => '',
                'stderr' => '',
            ], QuadbStore::diffCommandOutput($emptyDir, 'missing-head', '|'));
            $t->same('0x' . HashTree::EMPTY_HASH . "\n", QuadbStore::open($emptyDir)->rootText());

            $repo = QuadbStore::init($dir);
            $repo->importLines(
                "wp_options:siteurl|https://example.test\n"
                . "wp_options:home|https://example.test\n"
                . "wp_posts:1|Hello world\n",
                '|'
            );
            $repo->fork('wp-preview');
            $repo->put('wp_posts:1', 'Preview edit');
            $repo->put('wp_posts:2', 'New page');
            $repo->delete('wp_options:home');
            $previewRoot = $repo->tree()->rootHash();

            $expectedPatch = "-wp_posts:1|Hello world\n"
                . "+wp_posts:1|Preview edit\n"
                . "-wp_options:home|https://example.test\n"
                . "+wp_posts:2|New page\n";
            $t->same([
                'exitCode' => 0,
                'stdout' => $expectedPatch,
                'stderr' => '',
            ], QuadbStore::diffCommandOutput($dir, 'master', '|'));
            $t->same([
                'exitCode' => 1,
                'stdout' => '',
                'stderr' => "quadb error: separator must be non-empty\n",
            ], QuadbStore::diffCommandOutput($dir, 'master', ''));

            $t->same([
                'exitCode' => 0,
                'stdout' => '',
                'stderr' => '',
            ], QuadbStore::forkCommandOutput($dir, 'wp-replica', 'master'));
            $t->same([
                'exitCode' => 0,
                'stdout' => '',
                'stderr' => '',
            ], QuadbStore::patchCommandOutput($dir, "# preview patch\n" . $expectedPatch, '|'));

            $replica = QuadbStore::open($dir);
            $t->same($previewRoot, $replica->tree()->rootHash());
            $t->same('Preview edit', $replica->get('wp_posts:1'));
            $t->same('New page', $replica->get('wp_posts:2'));
            $t->throws(RuntimeException::class, static fn () => $replica->get('wp_options:home'));

            $t->same([
                'exitCode' => 1,
                'stdout' => '',
                'stderr' => "quadb error: empty line in patch\n",
            ], QuadbStore::patchCommandOutput($dir, "\n", '|'));
            $t->same([
                'exitCode' => 1,
                'stdout' => '',
                'stderr' => "quadb error: unexpected line in patch\n",
            ], QuadbStore::patchCommandOutput($dir, "~wp_posts:1|bad\n", '|'));
            $t->same([
                'exitCode' => 1,
                'stdout' => '',
                'stderr' => "quadb error: couldn't find separator in input line\n",
            ], QuadbStore::patchCommandOutput($dir, "+wp_posts:1 missing separator\n", '|'));
            $t->same([
                'exitCode' => 1,
                'stdout' => '',
                'stderr' => "quadb error: separator must be non-empty\n",
            ], QuadbStore::patchCommandOutput($dir, "# preview patch\n" . $expectedPatch, ''));
        } finally {
            quadrableQuadbRemoveDir($missingDir);
            quadrableQuadbRemoveDir($emptyDir);
            quadrableQuadbRemoveDir($dir);
        }
    },
    'native quadb store honors noTrackKeys for export diff dump and full-key proofs' => static function (TestRunner $t): void {
        $privateDir = quadrableQuadbTempDir();
        $trackedDir = quadrableQuadbTempDir();

        try {
            $siteUrlUnknown = quadrableQuadbUnknownStringKey('wp_options:siteurl');
            $homeUnknown = quadrableQuadbUnknownStringKey('wp_options:home');
            $postUnknown = quadrableQuadbUnknownStringKey('wp_posts:1');

            $private = QuadbStore::init($privateDir, false);
            $private->importLines(
                "wp_options:siteurl|https://example.test\n"
                . "wp_options:home|https://example.test\n"
                . "wp_posts:1|Published post\n",
                '|'
            );

            $t->same([
                $postUnknown . '|Published post',
                $homeUnknown . '|https://example.test',
                $siteUrlUnknown . '|https://example.test',
            ], quadrableQuadbSortedLines($private->exportLines('|')));

            $root = $private->tree()->rootHash();
            $proofBytes = $private->exportProofBytes(['wp_options:siteurl']);
            $partial = SparseTree::importProof(Proof::decode($proofBytes), $root);

            $t->same('https://example.test', $partial->get('wp_options:siteurl'));
            $t->throws(RuntimeException::class, static fn () => $private->exportProofBytes(
                ['wp_options:siteurl'],
                Proof::ENCODING_FULL_KEYS
            ));
            $t->same([
                'exitCode' => 1,
                'stdout' => '',
                'stderr' => "quadb error: FullKeys specified in proof encoding, but key not available\n",
            ], QuadbStore::exportProofCommandOutput(
                $privateDir,
                ['wp_options:siteurl'],
                'FullKeys',
                true,
                false,
                false,
                false
            ));
            $t->same([
                'exitCode' => 0,
                'stdout' => $private->exportProofHex(['wp_options:siteurl']),
                'stderr' => '',
            ], QuadbStore::exportProofCommandOutput(
                $privateDir,
                ['wp_options:siteurl'],
                'HashedKeys',
                true,
                false,
                false,
                false
            ));

            $private->fork('preview');
            $private->put('wp_posts:1', 'Preview edit');
            $t->same([
                '+' . $postUnknown . '|Preview edit',
                '-' . $postUnknown . '|Published post',
            ], quadrableQuadbSortedLines($private->diffLines('master', '|')));
            $t->contains('leaf: ' . $postUnknown . " = Preview edit\n", $private->dumpTreeText());
            $t->contains('leaf: ' . $postUnknown . " = Preview edit\n", QuadbStore::open($privateDir)->dumpTreeText());

            $tracked = QuadbStore::init($trackedDir);
            $tracked->importLines(
                "wp_options:siteurl|https://example.test\n"
                . "wp_options:home|https://example.test\n",
                '|'
            );
            $t->same([
                'wp_options:home|https://example.test',
                'wp_options:siteurl|https://example.test',
            ], quadrableQuadbSortedLines($tracked->exportLines('|')));

            $masked = QuadbStore::open($trackedDir, false);
            $t->same([
                $homeUnknown . '|https://example.test',
                $siteUrlUnknown . '|https://example.test',
            ], quadrableQuadbSortedLines($masked->exportLines('|')));
            $t->throws(RuntimeException::class, static fn () => $masked->exportProofBytes(
                ['wp_options:siteurl'],
                Proof::ENCODING_FULL_KEYS
            ));

            $masked->put('wp_options:siteurl', 'https://private.example.test');
            $visibleAgain = QuadbStore::open($trackedDir);
            $t->same([
                $siteUrlUnknown . '|https://private.example.test',
                'wp_options:home|https://example.test',
            ], quadrableQuadbSortedLines($visibleAgain->exportLines('|')));
        } finally {
            quadrableQuadbRemoveDir($privateDir);
            quadrableQuadbRemoveDir($trackedDir);
        }
    },
    'native quadb store exports hex full-key proofs like quadb exportProof' => static function (TestRunner $t): void {
        $dir = quadrableQuadbTempDir();

        try {
            $repo = QuadbStore::init($dir);
            $repo->importLines(
                "wp_options:siteurl|https://example.test\n"
                . "wp_options:home|https://example.test\n"
                . "wp_posts:1|Published post\n",
                '|'
            );

            $root = $repo->tree()->rootHash();
            $proofHex = $repo->exportProofHex([
                'wp_options:siteurl',
                'wp_posts:1',
                'wp_posts:404',
            ], Proof::ENCODING_FULL_KEYS);

            $proofBytes = quadrableQuadbDecodeHexProof($proofHex);
            $proof = Proof::decode($proofBytes);
            $partial = SparseTree::importProof($proof, $root);

            $t->same(Proof::ENCODING_FULL_KEYS, ord($proofBytes[0]));
            $t->true(str_starts_with($proofHex, '0x'));
            $t->true(str_ends_with($proofHex, "\n"));
            $t->same($root, $partial->rootHash());
            $t->same('https://example.test', $partial->get('wp_options:siteurl'));
            $t->same('Published post', $partial->get('wp_posts:1'));
            $t->same(null, $partial->get('wp_posts:404'));
            $t->throws(RuntimeException::class, static fn () => $partial->get('wp_options:home'));

            $entries = [];
            foreach ($partial->orderedEntries() as $entry) {
                $entries[$entry->stringKey() ?? $entry->keyHex()] = $entry->value();
            }
            ksort($entries, SORT_STRING);

            $t->same([
                'wp_options:siteurl' => 'https://example.test',
                'wp_posts:1' => 'Published post',
            ], $entries);
            $t->same($proofHex, QuadbStore::open($dir)->exportProofHex([
                'wp_options:siteurl',
                'wp_posts:1',
                'wp_posts:404',
            ], Proof::ENCODING_FULL_KEYS));
        } finally {
            quadrableQuadbRemoveDir($dir);
        }
    },
    'native quadb store exports raw integer proofs like quadb exportProof int' => static function (TestRunner $t): void {
        $dir = quadrableQuadbTempDir();

        try {
            $repo = QuadbStore::init($dir);
            $repo->importIntegerLines(
                "0,wp_options:blog_public=1\n"
                . "1,wp_options:siteurl=https://example.test\n"
                . "2,wp_options:home=https://example.test\n"
                . "4,wp_posts:1=Published post\n"
                . "2147483647,wp_posts:max=Boundary post\n"
            );

            $root = $repo->tree()->rootHash();
            $proofHex = $repo->exportIntegerProofHex([2, 4, 99]);
            $proofBytes = quadrableQuadbDecodeHexProof($proofHex);
            $proof = Proof::decode(quadrableQuadbDecodeHexProof($proofHex));
            $partial = SparseTree::importProof($proof, $root);

            $t->same($root, $partial->rootHash());
            $t->same('wp_options:home=https://example.test', $partial->getKey(Key::fromInteger(2)));
            $t->same('wp_posts:1=Published post', $partial->getKey(Key::fromInteger(4)));
            $t->same(null, $partial->getKey(Key::fromInteger(99)));
            $t->throws(RuntimeException::class, static fn () => $partial->getKey(Key::fromInteger(1)));
            $t->throws(RuntimeException::class, static fn () => $repo->exportIntegerProofHex([2], Proof::ENCODING_FULL_KEYS));
            $t->throws(InvalidArgumentException::class, static fn () => $repo->exportIntegerProof([2, '3']));

            $t->same([
                'exitCode' => 0,
                'stdout' => $proofBytes,
                'stderr' => '',
            ], QuadbStore::exportProofCommandOutput($dir, ['2', '4', '99'], integerKeys: true));
            $t->same([
                'exitCode' => 0,
                'stdout' => $proofHex,
                'stderr' => '',
            ], QuadbStore::exportProofCommandOutput($dir, ['2', '4', '99'], hex: true, integerKeys: true));
            $t->same([
                'exitCode' => 0,
                'stdout' => $proofHex,
                'stderr' => '',
            ], QuadbStore::exportProofStdinCommandOutput($dir, "2\n4\n99\n", hex: true, integerKeys: true));
            $t->same([
                'exitCode' => 0,
                'stdout' => $proofBytes,
                'stderr' => '',
            ], QuadbStore::exportProofStdinCommandOutput($dir, "2\n4\n99", integerKeys: true));
            $t->same([
                'exitCode' => 0,
                'stdout' => $proofBytes,
                'stderr' => '',
            ], QuadbStore::exportProofStdinCommandOutput($dir, "2\r\n4\r\n99\r\n", integerKeys: true));
            $emptyStdinIntegerProof = QuadbStore::exportProofStdinCommandOutput($dir, '', integerKeys: true);
            $t->same(0, $emptyStdinIntegerProof['exitCode']);
            $t->same($repo->exportIntegerProofBytes([]), $emptyStdinIntegerProof['stdout']);
            $t->same('', $emptyStdinIntegerProof['stderr']);
            $t->same([
                'exitCode' => 1,
                'stdout' => '',
                'stderr' => "quadb error: stoi\n",
            ], QuadbStore::exportProofStdinCommandOutput($dir, "\n", integerKeys: true));
            $t->same([
                'exitCode' => 1,
                'stdout' => '',
                'stderr' => "quadb error: stoi\n",
            ], QuadbStore::exportProofStdinCommandOutput($dir, " \t\r\n", integerKeys: true));
            $integerDump = QuadbStore::exportProofCommandOutput($dir, ['2', '4', '99'], dump: true, integerKeys: true);
            $t->same(0, $integerDump['exitCode']);
            $t->contains('ITEMS (2):', $integerDump['stdout']);
            $t->same('', $integerDump['stderr']);
            $integerDumpWithBadFormat = QuadbStore::exportProofCommandOutput(
                $dir,
                ['2', '4', '99'],
                'BadFormat',
                dump: true,
                integerKeys: true
            );
            $t->same(0, $integerDumpWithBadFormat['exitCode']);
            $t->contains('ITEMS (2):', $integerDumpWithBadFormat['stdout']);
            $t->same('', $integerDumpWithBadFormat['stderr']);
            $stdinIntegerDumpWithBadFormat = QuadbStore::exportProofStdinCommandOutput(
                $dir,
                "2\n4\n99\n",
                'BadFormat',
                dump: true,
                integerKeys: true
            );
            $t->same($integerDumpWithBadFormat, $stdinIntegerDumpWithBadFormat);
            $t->same([
                'exitCode' => 1,
                'stdout' => '',
                'stderr' => "quadb error: stoi\n",
            ], QuadbStore::exportProofCommandOutput($dir, ['2', 'not-an-int'], integerKeys: true));
            $numericPrefixProof = QuadbStore::exportProofCommandOutput($dir, ['2suffix', '4'], integerKeys: true);
            $t->same(0, $numericPrefixProof['exitCode']);
            $t->same($repo->exportIntegerProofBytes([2, 4]), $numericPrefixProof['stdout']);
            $t->same('', $numericPrefixProof['stderr']);
            $trailingWhitespaceProof = QuadbStore::exportProofCommandOutput(
                $dir,
                ["2 \tignored", "4\nignored"],
                integerKeys: true
            );
            $t->same(0, $trailingWhitespaceProof['exitCode']);
            $t->same($repo->exportIntegerProofBytes([2, 4]), $trailingWhitespaceProof['stdout']);
            $t->same('', $trailingWhitespaceProof['stderr']);
            $spacedPlusProof = QuadbStore::exportProofCommandOutput($dir, ['  +2suffix', ' +4'], integerKeys: true);
            $t->same(0, $spacedPlusProof['exitCode']);
            $t->same($repo->exportIntegerProofBytes([2, 4]), $spacedPlusProof['stdout']);
            $t->same('', $spacedPlusProof['stderr']);
            $verticalWhitespaceProof = QuadbStore::exportProofCommandOutput(
                $dir,
                ["\t+2suffix", "\n4"],
                integerKeys: true
            );
            $t->same(0, $verticalWhitespaceProof['exitCode']);
            $t->same($repo->exportIntegerProofBytes([2, 4]), $verticalWhitespaceProof['stdout']);
            $t->same('', $verticalWhitespaceProof['stderr']);
            $formFeedWhitespaceProof = QuadbStore::exportProofCommandOutput(
                $dir,
                ["\f+2suffix", "\v4"],
                integerKeys: true
            );
            $t->same(0, $formFeedWhitespaceProof['exitCode']);
            $t->same($repo->exportIntegerProofBytes([2, 4]), $formFeedWhitespaceProof['stdout']);
            $t->same('', $formFeedWhitespaceProof['stderr']);
            $carriageReturnWhitespaceProof = QuadbStore::exportProofCommandOutput(
                $dir,
                ["\r+2suffix", "\r4"],
                integerKeys: true
            );
            $t->same(0, $carriageReturnWhitespaceProof['exitCode']);
            $t->same($repo->exportIntegerProofBytes([2, 4]), $carriageReturnWhitespaceProof['stdout']);
            $t->same('', $carriageReturnWhitespaceProof['stderr']);
            $t->same([
                'exitCode' => 1,
                'stdout' => '',
                'stderr' => "quadb error: stoi\n",
            ], QuadbStore::exportProofCommandOutput($dir, ["\0+2"], integerKeys: true));
            $t->same([
                'exitCode' => 1,
                'stdout' => '',
                'stderr' => "quadb error: stoi\n",
            ], QuadbStore::exportProofStdinCommandOutput($dir, "\0+2\n", integerKeys: true));
            $t->same([
                'exitCode' => 1,
                'stdout' => '',
                'stderr' => "quadb error: stoi\n",
            ], QuadbStore::exportProofCommandOutput($dir, ['+', '-'], integerKeys: true));
            $t->same([
                'exitCode' => 1,
                'stdout' => '',
                'stderr' => "quadb error: stoi\n",
            ], QuadbStore::exportProofStdinCommandOutput($dir, "+\n-\n", integerKeys: true));
            $maxIntegerProof = QuadbStore::exportProofCommandOutput(
                $dir,
                ['0002147483647suffix', '+0000000002'],
                integerKeys: true
            );
            $t->same(0, $maxIntegerProof['exitCode']);
            $t->same($repo->exportIntegerProofBytes([2147483647, 2]), $maxIntegerProof['stdout']);
            $t->same('', $maxIntegerProof['stderr']);
            $signedZeroProof = QuadbStore::exportProofCommandOutput($dir, ['-0suffix', '+0'], integerKeys: true);
            $t->same(0, $signedZeroProof['exitCode']);
            $t->same($repo->exportIntegerProofBytes([0]), $signedZeroProof['stdout']);
            $t->same('', $signedZeroProof['stderr']);
            $emptyIntegerProof = QuadbStore::exportProofCommandOutput($dir, [], integerKeys: true);
            $t->same(0, $emptyIntegerProof['exitCode']);
            $t->same($repo->exportIntegerProofBytes([]), $emptyIntegerProof['stdout']);
            $t->same('', $emptyIntegerProof['stderr']);
            $emptyIntegerHexProof = QuadbStore::exportProofCommandOutput($dir, [], hex: true, integerKeys: true);
            $t->same([
                'exitCode' => 0,
                'stdout' => $repo->exportIntegerProofHex([]),
                'stderr' => '',
            ], $emptyIntegerHexProof);
            $t->same([
                'exitCode' => 1,
                'stdout' => '',
                'stderr' => "quadb error: stoi\n",
            ], QuadbStore::exportProofCommandOutput($dir, ['2147483648'], integerKeys: true));
            $t->same([
                'exitCode' => 1,
                'stdout' => '',
                'stderr' => "quadb error: stoi\n",
            ], QuadbStore::exportProofCommandOutput($dir, ['+2147483648suffix'], integerKeys: true));
            $t->same([
                'exitCode' => 1,
                'stdout' => '',
                'stderr' => "quadb error: stoi\n",
            ], QuadbStore::exportProofStdinCommandOutput($dir, "+2147483648suffix\n", integerKeys: true));
            $t->same([
                'exitCode' => 1,
                'stdout' => '',
                'stderr' => "quadb error: int range exceeded\n",
            ], QuadbStore::exportProofCommandOutput($dir, ['-1'], integerKeys: true));
            $t->same([
                'exitCode' => 1,
                'stdout' => '',
                'stderr' => "quadb error: int range exceeded\n",
            ], QuadbStore::exportProofCommandOutput($dir, ['-2147483648'], integerKeys: true));
            $t->same([
                'exitCode' => 1,
                'stdout' => '',
                'stderr' => "quadb error: stoi\n",
            ], QuadbStore::exportProofCommandOutput($dir, ['-2147483649suffix'], integerKeys: true));
            $t->same([
                'exitCode' => 1,
                'stdout' => '',
                'stderr' => "quadb error: FullKeys specified in proof encoding, but key not available\n",
            ], QuadbStore::exportProofCommandOutput($dir, ['2'], 'FullKeys', integerKeys: true));
        } finally {
            quadrableQuadbRemoveDir($dir);
        }
    },
    'native quadb store imports exports and proves composite integer hash keys' => static function (TestRunner $t): void {
        $sourceDir = quadrableQuadbTempDir();
        $targetDir = quadrableQuadbTempDir();

        try {
            $thumbnail = quadrableQuadbCompositeSuffix('_thumbnail_id');
            $editLock = quadrableQuadbCompositeSuffix('_edit_lock');
            $template = quadrableQuadbCompositeSuffix('_wp_page_template');
            $missing = quadrableQuadbCompositeSuffix('_missing_meta');

            $repo = QuadbStore::init($sourceDir);
            $t->same(3, $repo->importCompositeLines(
                "42|{$thumbnail}|wp_postmeta:42:_thumbnail_id=7\n"
                . "42|{$editLock}|wp_postmeta:42:_edit_lock=1716400000\n"
                . "42|{$template}|wp_postmeta:42:_wp_page_template=templates/full-width.html\n",
                '|'
            ));

            $root = $repo->tree()->rootHash();
            $t->same('wp_postmeta:42:_thumbnail_id=7', $repo->getCompositeKey(42, $thumbnail));
            $t->same('wp_postmeta:42:_edit_lock=1716400000', $repo->getCompositeKey(42, '0x' . strtoupper($editLock)));

            $reopened = QuadbStore::open($sourceDir);
            $t->same([
                "42|{$editLock}|wp_postmeta:42:_edit_lock=1716400000",
                "42|{$thumbnail}|wp_postmeta:42:_thumbnail_id=7",
                "42|{$template}|wp_postmeta:42:_wp_page_template=templates/full-width.html",
            ], quadrableQuadbSortedLines($reopened->exportCompositeLines('|')));

            $reopened->putCompositeKey(43, $thumbnail, 'wp_postmeta:43:_thumbnail_id=8');
            $t->same('wp_postmeta:43:_thumbnail_id=8', QuadbStore::open($sourceDir)->getCompositeKey(43, $thumbnail));
            $reopened->deleteCompositeKey(43, $thumbnail);
            $t->throws(RuntimeException::class, static fn () => $reopened->getCompositeKey(43, $thumbnail));

            $proofKeys = "42|{$thumbnail}\n42|{$missing}\n";
            $proofBytes = $reopened->exportCompositeProofBytesFromKeyLines($proofKeys, '|');
            $proofHex = $reopened->exportCompositeProofHexFromKeyLines($proofKeys, '|');
            $t->same($proofBytes, quadrableQuadbDecodeHexProof($proofHex));

            $target = QuadbStore::init($targetDir);
            $target->checkout('postmeta-proof');
            $t->same('', $target->importProofBytesOutputText($proofBytes, $root));
            $t->same('wp_postmeta:42:_thumbnail_id=7', $target->getCompositeKey(42, $thumbnail));
            $t->throws(RuntimeException::class, static fn () => $target->getCompositeKey(42, $missing));

            $t->throws(InvalidArgumentException::class, static fn () => $repo->importCompositeLines("42|bad|value\n", '|'));
            $t->throws(InvalidArgumentException::class, static fn () => $repo->importCompositeLines(((string) (Key::MAX_INTEGER + 1)) . "|{$thumbnail}|value\n", '|'));
            $t->throws(RuntimeException::class, static fn () => $repo->exportCompositeProofBytesFromKeyLines("42|{$thumbnail}|extra\n", '|'));
        } finally {
            quadrableQuadbRemoveDir($sourceDir);
            quadrableQuadbRemoveDir($targetDir);
        }
    },
    'native quadb store exports stdin key proofs and imports binary proof input like quadb' => static function (TestRunner $t): void {
        $sourceDir = quadrableQuadbTempDir();
        $targetDir = quadrableQuadbTempDir();
        $integerSourceDir = quadrableQuadbTempDir();
        $integerTargetDir = quadrableQuadbTempDir();

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
            $proofBytes = $source->exportProofBytesFromKeyLines($keyInput, Proof::ENCODING_FULL_KEYS);
            $eofKeyInput = "wp_options:siteurl\nwp_posts:1\nwp_posts:404";
            $proofCommand = QuadbStore::exportProofStdinCommandOutput(
                $sourceDir,
                $keyInput,
                'FullKeys',
                hex: false
            );

            $t->same(
                $source->exportProofBytes([
                    'wp_options:siteurl',
                    'wp_posts:1',
                    'wp_posts:404',
                ], Proof::ENCODING_FULL_KEYS),
                $proofBytes
            );
            $t->same(
                $source->exportProofHex([
                    'wp_options:siteurl',
                    'wp_posts:1',
                    'wp_posts:404',
                ], Proof::ENCODING_FULL_KEYS),
                QuadbStore::exportProofStdinCommandOutput(
                    $sourceDir,
                    $keyInput,
                    'FullKeys',
                    hex: true
                )['stdout']
            );
            $t->same(0, $proofCommand['exitCode']);
            $t->same($proofBytes, $proofCommand['stdout']);
            $t->same('', $proofCommand['stderr']);
            $t->same([
                'exitCode' => 0,
                'stdout' => $proofBytes,
                'stderr' => '',
            ], QuadbStore::exportProofStdinCommandOutput(
                $sourceDir,
                $eofKeyInput,
                'FullKeys',
                hex: false
            ));
            $t->same([
                'exitCode' => 0,
                'stdout' => $source->exportProofBytes([
                    "wp_options:siteurl\r",
                    "wp_posts:1\r",
                    "wp_posts:404\r",
                ], Proof::ENCODING_FULL_KEYS),
                'stderr' => '',
            ], QuadbStore::exportProofStdinCommandOutput(
                $sourceDir,
                "wp_options:siteurl\r\nwp_posts:1\r\nwp_posts:404\r\n",
                'FullKeys',
                hex: false
            ));
            $t->same(
                $source->exportProofFromKeyLines($keyInput)->dumpText(),
                QuadbStore::exportProofStdinCommandOutput(
                    $sourceDir,
                    $keyInput,
                    dump: true
                )['stdout']
            );
            $stdinDumpWithBadFormat = QuadbStore::exportProofStdinCommandOutput(
                $sourceDir,
                $keyInput,
                'BadFormat',
                dump: true
            );
            $t->same(0, $stdinDumpWithBadFormat['exitCode']);
            $t->contains('ITEMS (3):', $stdinDumpWithBadFormat['stdout']);
            $t->contains('wp_options:siteurl', $stdinDumpWithBadFormat['stdout']);
            $t->same('', $stdinDumpWithBadFormat['stderr']);
            $t->same([
                'exitCode' => 1,
                'stdout' => '',
                'stderr' => "quadb error: unknown proof format\n",
            ], QuadbStore::exportProofStdinCommandOutput($sourceDir, $keyInput, 'BadFormat'));
            $t->same([
                'exitCode' => 1,
                'stdout' => '',
                'stderr' => "quadb error: Could not access directory '{$sourceDir}-missing/': No such file or directory\n",
            ], QuadbStore::exportProofStdinCommandOutput($sourceDir . '-missing', $keyInput));
            $t->same(Proof::ENCODING_FULL_KEYS, ord($proofBytes[0]));

            $target = QuadbStore::init($targetDir);
            $target->checkout('wp-binary-proof');
            $t->same('', $target->importProofBytesOutputText($proofBytes, $trustedRoot));
            $t->same('https://example.test', $target->get('wp_options:siteurl'));
            $t->same('Published post', $target->get('wp_posts:1'));
            $t->throws(RuntimeException::class, static fn () => $target->get('wp_options:home'));
            $t->throws(RuntimeException::class, static fn () => $target->get('wp_posts:404'));

            $homeProofBytes = $source->exportProofBytesFromKeyLines("wp_options:home\n", Proof::ENCODING_FULL_KEYS);
            $t->same($trustedRoot, $target->mergeProofBytes($homeProofBytes));
            $t->same('https://example.test', $target->get('wp_options:home'));

            $integerSource = QuadbStore::init($integerSourceDir);
            $integerSource->importIntegerLines(
                "2,wp_options:home=https://example.test\n"
                . "4,wp_posts:1=Published post\n"
            );

            $integerRoot = $integerSource->tree()->rootHash();
            $integerKeyInput = "2\n4\n99";
            $integerProofBytes = $integerSource->exportIntegerProofBytesFromKeyLines($integerKeyInput);
            $integerProofCommand = QuadbStore::exportProofStdinCommandOutput(
                $integerSourceDir,
                $integerKeyInput,
                integerKeys: true
            );
            $t->same($integerSource->exportIntegerProofBytes([2, 4, 99]), $integerProofBytes);
            $t->same(0, $integerProofCommand['exitCode']);
            $t->same($integerProofBytes, $integerProofCommand['stdout']);
            $t->same('', $integerProofCommand['stderr']);
            $t->same([
                'exitCode' => 1,
                'stdout' => '',
                'stderr' => "quadb error: stoi\n",
            ], QuadbStore::exportProofStdinCommandOutput($integerSourceDir, "2\nnot-an-int\n", integerKeys: true));
            $stdinNumericPrefixProof = QuadbStore::exportProofStdinCommandOutput(
                $integerSourceDir,
                "  +2suffix\n-0suffix\n0000000004ignored\n",
                integerKeys: true
            );
            $t->same(0, $stdinNumericPrefixProof['exitCode']);
            $t->same($integerSource->exportIntegerProofBytes([2, 0, 4]), $stdinNumericPrefixProof['stdout']);
            $t->same('', $stdinNumericPrefixProof['stderr']);
            $t->same([
                'exitCode' => 1,
                'stdout' => '',
                'stderr' => "quadb error: stoi\n",
            ], QuadbStore::exportProofStdinCommandOutput($integerSourceDir, "-2147483649suffix\n", integerKeys: true));

            $integerTarget = QuadbStore::init($integerTargetDir);
            $integerTarget->checkout('wp-integer-binary-proof');
            $integerTarget->importProofBytes($integerProofBytes, $integerRoot);
            $t->same('wp_options:home=https://example.test', $integerTarget->getInteger(2));
            $t->same('wp_posts:1=Published post', $integerTarget->getInteger(4));
            $t->throws(RuntimeException::class, static fn () => $integerTarget->getInteger(99));
            $t->throws(InvalidArgumentException::class, static fn () => $integerSource->exportIntegerProofBytesFromKeyLines("2\nnot-an-int\n"));
            $t->throws(InvalidArgumentException::class, static fn () => $integerSource->exportIntegerProofBytesFromKeyLines(((string) PHP_INT_MAX) . "\n"));
        } finally {
            quadrableQuadbRemoveDir($sourceDir);
            quadrableQuadbRemoveDir($targetDir);
            quadrableQuadbRemoveDir($integerSourceDir);
            quadrableQuadbRemoveDir($integerTargetDir);
        }
    },
    'native quadb store dumps proofs and reports unauthenticated proof imports like quadb' => static function (TestRunner $t): void {
        $sourceDir = quadrableQuadbTempDir();
        $targetDir = quadrableQuadbTempDir();
        $trustedDir = quadrableQuadbTempDir();

        try {
            $source = QuadbStore::init($sourceDir);
            $source->importLines(
                "wp_options:siteurl|https://example.test\n"
                . "wp_options:home|https://example.test\n"
                . "wp_posts:1|Published post\n",
                '|'
            );

            $trustedRoot = $source->tree()->rootHash();
            $keys = [
                'wp_options:siteurl',
                'wp_posts:404',
            ];
            $dump = $source->exportProofDumpText($keys);
            $fullKeyProofHex = $source->exportProofHex($keys, Proof::ENCODING_FULL_KEYS);

            $t->same($source->exportProof($keys)->dumpText(), $dump);
            $t->contains('ITEMS (', $dump);
            $t->contains('  ITEM 0: 0x', $dump);
            $t->contains("    Leaf  depth=", $dump);
            $t->contains("    Key: wp_options:siteurl\n", $dump);
            $t->contains("    Val: https://example.test\n", $dump);
            $t->contains("    WitnessLeaf  depth=", $dump);
            $t->contains('CMDS (', $dump);

            $target = QuadbStore::init($targetDir);
            $t->same($source->exportProof($keys)->dumpText(), $target->importProofHexDumpText($fullKeyProofHex));
            $t->same(HashTree::EMPTY_HASH, $target->tree()->rootHash());

            $t->same(
                "Imported UNAUTHENTICATED proof. Root: 0x{$trustedRoot}\n",
                $target->importProofHexOutputText($fullKeyProofHex)
            );
            $t->same($trustedRoot, $target->status()['rootHash']);
            $t->same('https://example.test', $target->get('wp_options:siteurl'));
            $t->throws(RuntimeException::class, static fn () => $target->get('wp_options:home'));
            $t->same('https://example.test', QuadbStore::open($targetDir)->get('wp_options:siteurl'));

            $trusted = QuadbStore::init($trustedDir);
            $trusted->checkout('wp-trusted-partial');
            $t->same('', $trusted->importProofHexOutputText($fullKeyProofHex, '0x' . $trustedRoot));
            $t->same($trustedRoot, $trusted->status()['rootHash']);
        } finally {
            quadrableQuadbRemoveDir($sourceDir);
            quadrableQuadbRemoveDir($targetDir);
            quadrableQuadbRemoveDir($trustedDir);
        }
    },
    'native quadb store updates raw integer proof-backed heads' => static function (TestRunner $t): void {
        $sourceDir = quadrableQuadbTempDir();
        $targetDir = quadrableQuadbTempDir();

        try {
            $source = QuadbStore::init($sourceDir);
            $source->importIntegerLines(
                "1,wp_options:siteurl=https://example.test\n"
                . "2,wp_options:home=https://example.test\n"
                . "3,wp_posts:1=Published post\n"
            );

            $trustedRoot = $source->tree()->rootHash();
            $proofHex = $source->exportIntegerProofHex([2, 4]);

            $source->importIntegerLines(
                "2,wp_options:home=https://preview.example.test\n"
                . "4,wp_postmeta:1:_thumbnail_id=42\n"
            );
            $updatedRoot = $source->tree()->rootHash();
            $updatedSiteProofHex = $source->exportIntegerProofHex([1]);

            $target = QuadbStore::init($targetDir);
            $target->checkout('wp-integer-delegated');
            $target->importProofHex($proofHex, $trustedRoot);
            $t->same(2, $target->importIntegerLines(
                "2,wp_options:home=https://preview.example.test\n"
                . "4,wp_postmeta:1:_thumbnail_id=42\n"
            ));
            $t->same($updatedRoot, $target->status()['rootHash']);
            $t->same('wp_options:home=https://preview.example.test', $target->getInteger(2));
            $t->same('wp_postmeta:1:_thumbnail_id=42', $target->getInteger(4));
            $t->throws(RuntimeException::class, static fn () => $target->getInteger(1));

            $reopened = QuadbStore::open($targetDir);
            $t->same($updatedRoot, $reopened->status()['rootHash']);
            $t->same('wp_postmeta:1:_thumbnail_id=42', $reopened->getKey(Key::fromInteger(4)));
            $t->same($updatedRoot, $reopened->mergeProofHex($updatedSiteProofHex));
            $t->same('wp_options:siteurl=https://example.test', $reopened->getInteger(1));

            $source->deleteInteger(4);
            $reopened->deleteInteger(4);
            $t->same($source->status()['rootHash'], $reopened->status()['rootHash']);
            $t->throws(RuntimeException::class, static fn () => $reopened->getInteger(4));

            $delegatedProofHex = $reopened->exportIntegerProofHex([1, 2, 4]);
            $delegated = SparseTree::importProof(
                Proof::decode(quadrableQuadbDecodeHexProof($delegatedProofHex)),
                $reopened->status()['rootHash']
            );
            $t->same('wp_options:siteurl=https://example.test', $delegated->getKey(Key::fromInteger(1)));
            $t->same('wp_options:home=https://preview.example.test', $delegated->getKey(Key::fromInteger(2)));
            $t->same(null, $delegated->getKey(Key::fromInteger(4)));
        } finally {
            quadrableQuadbRemoveDir($sourceDir);
            quadrableQuadbRemoveDir($targetDir);
        }
    },
    'native quadb store imports and merges proof-backed heads across reopen' => static function (TestRunner $t): void {
        $sourceDir = quadrableQuadbTempDir();
        $targetDir = quadrableQuadbTempDir();

        try {
            $source = QuadbStore::init($sourceDir);
            $source->importLines(
                "wp_options:siteurl|https://example.test\n"
                . "wp_options:home|https://example.test\n"
                . "wp_posts:1|Published post\n",
                '|'
            );

            $trustedRoot = $source->tree()->rootHash();
            $optionProofHex = $source->exportProofHex([
                'wp_options:siteurl',
                'wp_posts:404',
            ], Proof::ENCODING_FULL_KEYS);
            $postProofHex = $source->exportProofHex([
                'wp_posts:1',
            ], Proof::ENCODING_FULL_KEYS);

            $target = QuadbStore::init($targetDir);
            $target->checkout('wp-delegated');
            $t->same($trustedRoot, $target->importProofHex($optionProofHex, '0x' . $trustedRoot));
            $t->same('https://example.test', $target->get('wp_options:siteurl'));
            $t->throws(RuntimeException::class, static fn () => $target->get('wp_posts:1'));
            $t->throws(RuntimeException::class, static fn () => $target->get('wp_posts:404'));
            $t->throws(RuntimeException::class, static fn () => $target->get('wp_options:home'));
            $t->same([
                quadrableQuadbUnknownStringKey('wp_options:home') . '|' . quadrableQuadbUnknownHash((new HashTree())->valueHash('https://example.test')),
                'wp_options:siteurl|https://example.test',
            ], quadrableQuadbOutputLines($target->exportLines('|')));

            $status = $target->status();
            $t->same(false, $status['detached']);
            $t->same('wp-delegated', $status['head']);
            $t->same($trustedRoot, $status['rootHash']);
            $t->true($status['headNodeId'] >= 576460752303423488);
            $t->contains("=> wp-delegated : 0x{$trustedRoot} (", $target->headText());

            $reopened = QuadbStore::open($targetDir);
            $t->same('https://example.test', $reopened->get('wp_options:siteurl'));
            $t->same($trustedRoot, $reopened->mergeProofHex($postProofHex));
            $t->same('Published post', $reopened->get('wp_posts:1'));
            $t->same([
                'wp_posts:1|Published post',
                quadrableQuadbUnknownStringKey('wp_options:home') . '|' . quadrableQuadbUnknownHash((new HashTree())->valueHash('https://example.test')),
                'wp_options:siteurl|https://example.test',
            ], quadrableQuadbOutputLines($reopened->exportLines('|')));
            $t->same($trustedRoot, QuadbStore::open($targetDir)->status()['rootHash']);

            $delegatedProofHex = $reopened->exportProofHex([
                'wp_options:siteurl',
                'wp_posts:1',
            ], Proof::ENCODING_FULL_KEYS);
            $delegated = SparseTree::importProof(
                Proof::decode(quadrableQuadbDecodeHexProof($delegatedProofHex)),
                $trustedRoot
            );

            $t->same('https://example.test', $delegated->get('wp_options:siteurl'));
            $t->same('Published post', $delegated->get('wp_posts:1'));

            $source->put('wp_posts:1', 'Changed after proof');
            $wrongRootProofHex = $source->exportProofHex(['wp_posts:1'], Proof::ENCODING_FULL_KEYS);
            $t->throws(RuntimeException::class, static fn () => $reopened->mergeProofHex($wrongRootProofHex));
            $t->same('Published post', QuadbStore::open($targetDir)->get('wp_posts:1'));
        } finally {
            quadrableQuadbRemoveDir($sourceDir);
            quadrableQuadbRemoveDir($targetDir);
        }
    },
    'native quadb store exports integer proof-backed partial heads like quadb export int' => static function (TestRunner $t): void {
        $sourceDir = quadrableQuadbTempDir();
        $targetDir = quadrableQuadbTempDir();

        try {
            $source = QuadbStore::init($sourceDir);
            $source->importIntegerLines("1,wp_options:siteurl=https://example.test\n2,wp_options:home=https://example.test\n");

            $trustedRoot = $source->tree()->rootHash();
            $proofHex = $source->exportIntegerProofHex([1, 3]);

            $target = QuadbStore::init($targetDir);
            $target->checkout('wp-delegated-integer-export');
            $target->importProofHex($proofHex, $trustedRoot);

            $t->same([
                '1,wp_options:siteurl=https://example.test',
                '2,' . quadrableQuadbUnknownHash((new HashTree())->valueHash('wp_options:home=https://example.test')),
            ], quadrableQuadbOutputLines($target->exportIntegerLines(',')));
            $t->same([
                'H(?)=0x020000000000...,wp_options:siteurl=https://example.test',
                'H(?)=0x040000000000...,' . quadrableQuadbUnknownHash((new HashTree())->valueHash('wp_options:home=https://example.test')),
            ], quadrableQuadbOutputLines($target->exportLines(',')));
        } finally {
            quadrableQuadbRemoveDir($sourceDir);
            quadrableQuadbRemoveDir($targetDir);
        }
    },
    'native quadb store retains mergeProof import garbage until quadb gc' => static function (TestRunner $t): void {
        $sourceDir = quadrableQuadbTempDir();
        $targetDir = quadrableQuadbTempDir();

        try {
            $source = QuadbStore::init($sourceDir);
            $source->importLines(
                "wp_options:siteurl|https://example.test\n"
                . "wp_options:home|https://example.test\n"
                . "wp_posts:1|Published post\n"
                . "wp_posts:2|Second post\n",
                '|'
            );

            $trustedRoot = $source->tree()->rootHash();
            $siteProofHex = $source->exportProofHex(['wp_options:siteurl'], Proof::ENCODING_FULL_KEYS);
            $postProofHex = $source->exportProofHex(['wp_posts:1'], Proof::ENCODING_FULL_KEYS);

            $target = QuadbStore::init($targetDir);
            $target->checkout('wp-delegated-merge-gc');
            $target->importProofHex($siteProofHex, $trustedRoot);
            $target->mergeProofHex($postProofHex);

            $before = $target->lmdbBucketSnapshot();
            $beforeNodeCount = count($before['quadrable_nodesLeaf']) + count($before['quadrable_nodesInterior']);
            $t->same('Published post', $target->get('wp_posts:1'));
            $t->true(
                quadrableQuadbRawBucketBytes($before) > $target->stats()['numBytes'],
                'mergeProof import should leave unreferenced projected LMDB nodes before gc'
            );

            $gc = quadrableQuadbParseGcText($target->garbageCollectText());
            $after = $target->lmdbBucketSnapshot();
            $afterNodeCount = count($after['quadrable_nodesLeaf']) + count($after['quadrable_nodesInterior']);

            $t->same($beforeNodeCount, $gc['total']);
            $t->true($gc['garbage'] > 0);
            $t->same($beforeNodeCount - $gc['garbage'], $afterNodeCount);
            $t->same($target->stats()['numBytes'], quadrableQuadbRawBucketBytes($after));
            $t->same('https://example.test', $target->get('wp_options:siteurl'));
            $t->same('Published post', $target->get('wp_posts:1'));

            $reopened = QuadbStore::open($targetDir);
            $t->same($after, $reopened->lmdbBucketSnapshot());
            $t->same("Collected 0/{$afterNodeCount} nodes\n", $reopened->garbageCollectText());
        } finally {
            quadrableQuadbRemoveDir($sourceDir);
            quadrableQuadbRemoveDir($targetDir);
        }
    },
    'native quadb store persists proof-backed partial-head writes across reopen' => static function (TestRunner $t): void {
        $sourceDir = quadrableQuadbTempDir();
        $targetDir = quadrableQuadbTempDir();

        try {
            $source = QuadbStore::init($sourceDir);
            $source->importLines(
                "wp_options:siteurl|https://example.test\n"
                . "wp_options:home|https://example.test\n"
                . "wp_posts:1|Published post\n",
                '|'
            );

            $trustedRoot = $source->tree()->rootHash();
            $proofHex = $source->exportProofHex([
                'wp_options:siteurl',
                'wp_posts:404',
            ], Proof::ENCODING_FULL_KEYS);
            $staleHomeProofHex = $source->exportProofHex(['wp_options:home'], Proof::ENCODING_FULL_KEYS);

            $source->put('wp_options:siteurl', 'https://preview.example.test');
            $updatedRoot = $source->tree()->rootHash();
            $updatedHomeProofHex = $source->exportProofHex(['wp_options:home'], Proof::ENCODING_FULL_KEYS);

            $target = QuadbStore::init($targetDir);
            $target->checkout('wp-delegated');
            $target->importProofHex($proofHex, $trustedRoot);
            $target->put('wp_options:siteurl', 'https://preview.example.test');

            $t->same($updatedRoot, $target->status()['rootHash']);
            $t->same('https://preview.example.test', $target->get('wp_options:siteurl'));
            $t->throws(RuntimeException::class, static fn () => $target->put('wp_posts:1', 'Unproved edit'));
            $t->same($updatedRoot, $target->status()['rootHash']);
            $t->throws(RuntimeException::class, static fn () => $target->mergeProofHex($staleHomeProofHex));
            $t->same($updatedRoot, $target->mergeProofHex($updatedHomeProofHex));
            $t->same('https://example.test', $target->get('wp_options:home'));

            $reopened = QuadbStore::open($targetDir);
            $t->same($updatedRoot, $reopened->status()['rootHash']);
            $t->same('https://preview.example.test', $reopened->get('wp_options:siteurl'));
            $t->same('https://example.test', $reopened->get('wp_options:home'));

            $delegatedProofHex = $reopened->exportProofHex([
                'wp_options:siteurl',
                'wp_options:home',
                'wp_posts:404',
            ], Proof::ENCODING_FULL_KEYS);
            $delegated = SparseTree::importProof(
                Proof::decode(quadrableQuadbDecodeHexProof($delegatedProofHex)),
                $updatedRoot
            );
            $t->same('https://preview.example.test', $delegated->get('wp_options:siteurl'));
            $t->same('https://example.test', $delegated->get('wp_options:home'));
            $t->same(null, $delegated->get('wp_posts:404'));
        } finally {
            quadrableQuadbRemoveDir($sourceDir);
            quadrableQuadbRemoveDir($targetDir);
        }
    },
    'native quadb store merges updated-root proofs after persisted proof-backed writes' => static function (TestRunner $t): void {
        $sourceDir = quadrableQuadbTempDir();
        $targetDir = quadrableQuadbTempDir();

        try {
            $source = QuadbStore::init($sourceDir);
            $source->importLines(
                "wp_options:siteurl|https://example.test\n"
                . "wp_options:home|https://example.test\n"
                . "wp_posts:1|Published post\n",
                '|'
            );

            $trustedRoot = $source->tree()->rootHash();
            $siteUrlProofHex = $source->exportProofHex(['wp_options:siteurl'], Proof::ENCODING_FULL_KEYS);
            $stalePostProofHex = $source->exportProofHex(['wp_posts:1'], Proof::ENCODING_FULL_KEYS);

            $source->put('wp_options:siteurl', 'https://preview.example.test');
            $updatedRoot = $source->tree()->rootHash();
            $updatedPostProofHex = $source->exportProofHex(['wp_posts:1'], Proof::ENCODING_FULL_KEYS);

            $target = QuadbStore::init($targetDir);
            $target->checkout('wp-delegated-edit');
            $target->importProofHex($siteUrlProofHex, $trustedRoot);
            $target->put('wp_options:siteurl', 'https://preview.example.test');

            $reopened = QuadbStore::open($targetDir);
            $t->same($updatedRoot, $reopened->status()['rootHash']);
            $t->throws(RuntimeException::class, static fn () => $reopened->get('wp_posts:1'));
            $t->throws(RuntimeException::class, static fn () => $reopened->mergeProofHex($stalePostProofHex));
            $t->same($updatedRoot, $reopened->status()['rootHash']);

            $t->same($updatedRoot, $reopened->mergeProofHex($updatedPostProofHex));
            $t->same('Published post', $reopened->get('wp_posts:1'));
            $t->same('Published post', QuadbStore::open($targetDir)->get('wp_posts:1'));

            $delegatedProofHex = $reopened->exportProofHex([
                'wp_options:siteurl',
                'wp_posts:1',
            ], Proof::ENCODING_FULL_KEYS);
            $delegated = SparseTree::importProof(
                Proof::decode(quadrableQuadbDecodeHexProof($delegatedProofHex)),
                $updatedRoot
            );

            $t->same('https://preview.example.test', $delegated->get('wp_options:siteurl'));
            $t->same('Published post', $delegated->get('wp_posts:1'));
        } finally {
            quadrableQuadbRemoveDir($sourceDir);
            quadrableQuadbRemoveDir($targetDir);
        }
    },
    'native quadb store deletes and forks proof-backed partial heads' => static function (TestRunner $t): void {
        $sourceDir = quadrableQuadbTempDir();
        $targetDir = quadrableQuadbTempDir();

        try {
            $source = QuadbStore::init($sourceDir);
            $source->importLines(
                "731156037546|one\n"
                . "925458752084|two\n",
                '|'
            );

            $trustedRoot = $source->tree()->rootHash();
            $proofHex = $source->exportProofHex([
                '731156037546',
                '925458752084',
            ], Proof::ENCODING_FULL_KEYS);

            $source->delete('731156037546');
            $deleteRoot = $source->tree()->rootHash();

            $target = QuadbStore::init($targetDir);
            $target->checkout('upstream-partial');
            $target->importProofHex($proofHex, $trustedRoot);

            $forked = $target->fork('wp-preview-partial');
            $t->true($forked instanceof SparseTree);
            $target->put('925458752084', 'two-preview');
            $previewRoot = $target->status()['rootHash'];

            $original = $target->checkout('upstream-partial');
            $t->true($original instanceof SparseTree);
            $t->same($trustedRoot, $target->status()['rootHash']);
            $t->same('two', $target->get('925458752084'));

            $target->delete('731156037546');
            $t->same($deleteRoot, $target->status()['rootHash']);
            $t->throws(RuntimeException::class, static fn () => $target->get('731156037546'));
            $t->same('two', $target->get('925458752084'));

            $reopened = QuadbStore::open($targetDir);
            $preview = $reopened->checkout('wp-preview-partial');
            $t->true($preview instanceof SparseTree);
            $t->same($previewRoot, $preview->rootHash());
            $t->same('two-preview', $reopened->get('925458752084'));
        } finally {
            quadrableQuadbRemoveDir($sourceDir);
            quadrableQuadbRemoveDir($targetDir);
        }
    },
    'native quadb store shares imported proof storage across divergent proof-backed forks' => static function (TestRunner $t): void {
        $sourceDir = quadrableQuadbTempDir();
        $targetDir = quadrableQuadbTempDir();

        try {
            $source = QuadbStore::init($sourceDir);
            $source->importLines(
                "wp_options:siteurl|https://example.test\n"
                . "wp_options:home|https://example.test\n"
                . "wp_posts:1|Published post\n",
                '|'
            );

            $trustedRoot = $source->tree()->rootHash();
            $proofHex = $source->exportProofHex([
                'wp_options:siteurl',
                'wp_options:home',
            ], Proof::ENCODING_FULL_KEYS);

            $source->put('wp_options:siteurl', 'https://preview.example.test');
            $updatedRoot = $source->tree()->rootHash();

            $target = QuadbStore::init($targetDir);
            $target->checkout('wp-delegated-base');
            $target->importProofHex($proofHex, $trustedRoot);
            $target->fork('wp-delegated-preview');
            $target->put('wp_options:siteurl', 'https://preview.example.test');

            $previewRoot = $target->status()['rootHash'];
            $t->same($updatedRoot, $previewRoot);
            $t->same('https://preview.example.test', $target->get('wp_options:siteurl'));
            $t->same('https://example.test', $target->get('wp_options:home'));

            $base = $target->checkout('wp-delegated-base');
            $t->true($base instanceof SparseTree);
            $t->same($trustedRoot, $target->status()['rootHash']);
            $t->same('https://example.test', $target->get('wp_options:siteurl'));
            $t->same('https://example.test', $target->get('wp_options:home'));

            $lmdb = $target->lmdbBucketSnapshot();
            $headNodeIds = [];
            foreach ($lmdb['quadrable_head'] as $head => $rawNodeId) {
                $headNodeIds[$head] = quadrableQuadbUnpackUint64Le($rawNodeId);
            }
            $keyCounts = array_count_values(array_values($lmdb['quadrable_key']));

            $t->true($headNodeIds['wp-delegated-base'] !== $headNodeIds['wp-delegated-preview']);
            $t->same(1, $keyCounts['wp_options:home'] ?? 0);
            $t->same(2, $keyCounts['wp_options:siteurl'] ?? 0);
            $t->same($lmdb, QuadbStore::open($targetDir)->lmdbBucketSnapshot());

            $reopened = QuadbStore::open($targetDir);
            $reopened->checkout('wp-delegated-preview');
            $t->same($previewRoot, $reopened->status()['rootHash']);
            $t->same('https://preview.example.test', $reopened->get('wp_options:siteurl'));
            $reopened->checkout('wp-delegated-base');
            $t->same($trustedRoot, $reopened->status()['rootHash']);
            $t->same('https://example.test', $reopened->get('wp_options:siteurl'));
        } finally {
            quadrableQuadbRemoveDir($sourceDir);
            quadrableQuadbRemoveDir($targetDir);
        }
    },
    'native quadb store rejects proof imports unless the current head is empty' => static function (TestRunner $t): void {
        $sourceDir = quadrableQuadbTempDir();
        $targetDir = quadrableQuadbTempDir();

        try {
            $source = QuadbStore::init($sourceDir);
            $source->importLines("wp_posts:1|Published post\n", '|');
            $trustedRoot = $source->tree()->rootHash();
            $proofHex = $source->exportProofHex(['wp_posts:1'], Proof::ENCODING_FULL_KEYS);

            $target = QuadbStore::init($targetDir);
            $target->importLines("wp_options:siteurl|https://example.test\n", '|');
            $t->throws(RuntimeException::class, static fn () => $target->importProofHex($proofHex, $trustedRoot));

            $target->checkout('wp-partial');
            $t->throws(RuntimeException::class, static fn () => $target->importProofHex($proofHex, str_repeat('f', 64)));
            $t->same(HashTree::EMPTY_HASH, $target->status()['rootHash']);
            $t->throws(InvalidArgumentException::class, static fn () => $target->importProofHex('0xabc', $trustedRoot));
        } finally {
            quadrableQuadbRemoveDir($sourceDir);
            quadrableQuadbRemoveDir($targetDir);
        }
    },
    'native quadb store formats root status and sorted head output' => static function (TestRunner $t): void {
        $dir = quadrableQuadbTempDir();

        try {
            $repo = QuadbStore::init($dir);
            $t->same('0x' . HashTree::EMPTY_HASH . "\n", $repo->rootText());
            $t->same("Head: master\nRoot: 0x" . HashTree::EMPTY_HASH . " (0)\n", $repo->statusText());
            $t->same('', $repo->headText());

            $repo->importLines(
                "wp_options:siteurl|https://example.test\n"
                . "wp_posts:1|Published post\n",
                '|'
            );
            $masterRoot = $repo->tree()->rootHash();
            $masterHeadNodeId = $repo->tree()->headNodeId();

            $repo->fork('wp-release');
            $repo->put('wp_posts:2', 'Released page');
            $releaseRoot = $repo->tree()->rootHash();
            $releaseHeadNodeId = $repo->tree()->headNodeId();

            $repo->fork('wp-preview', 'master');
            $repo->put('wp_posts:1', 'Preview edit');
            $previewRoot = $repo->tree()->rootHash();
            $previewHeadNodeId = $repo->tree()->headNodeId();

            $t->same('0x' . $previewRoot . "\n", $repo->rootText());
            $t->same("Head: wp-preview\nRoot: 0x{$previewRoot} ({$previewHeadNodeId})\n", $repo->statusText());
            $t->same([
                "=> wp-preview : 0x{$previewRoot} ({$previewHeadNodeId})",
                "   wp-release : 0x{$releaseRoot} ({$releaseHeadNodeId})",
                "   master : 0x{$masterRoot} ({$masterHeadNodeId})",
            ], quadrableQuadbOutputLines($repo->headText()));

            $reopened = QuadbStore::open($dir);
            $t->same($repo->headText(), $reopened->headText());
        } finally {
            quadrableQuadbRemoveDir($dir);
        }
    },
    'native quadb store formats stats output like quadb stats' => static function (TestRunner $t): void {
        $sourceDir = quadrableQuadbTempDir();
        $targetDir = quadrableQuadbTempDir();

        try {
            $repo = QuadbStore::init($sourceDir);
            $t->same([
                'numNodes:        0',
                'numLeafNodes:    0',
                'numBranchNodes:  0',
                'numWitnessNodes: 0',
                'maxDepth:        0',
                'numBytes:        0',
            ], quadrableQuadbOutputLines($repo->statsText()));

            $repo->importLines(
                "wp_options:siteurl|https://example.test\n"
                . "wp_options:home|https://example.test\n"
                . "wp_posts:1|Published post\n",
                '|'
            );

            $stats = $repo->stats();
            $t->same(3, $stats['numLeafNodes']);
            $t->same(0, $stats['numWitnessNodes']);
            $t->same($stats['numLeafNodes'] + $stats['numBranchNodes'], $stats['numNodes']);
            $t->same(
                (72 * 3)
                    + strlen('https://example.test')
                    + strlen('https://example.test')
                    + strlen('Published post')
                    + (48 * $stats['numBranchNodes']),
                $stats['numBytes']
            );
            $t->same(quadrableQuadbStatsLines($stats), quadrableQuadbOutputLines($repo->statsText()));
            $t->same($repo->statsText(), QuadbStore::open($sourceDir)->statsText());

            $trustedRoot = $repo->tree()->rootHash();
            $proofHex = $repo->exportProofHex(['wp_options:siteurl'], Proof::ENCODING_FULL_KEYS);
            $target = QuadbStore::init($targetDir);
            $target->checkout('wp-delegated-stats');
            $target->importProofHex($proofHex, $trustedRoot);

            $partialStats = $target->stats();
            $t->same(1, $partialStats['numLeafNodes']);
            $t->true($partialStats['numBranchNodes'] > 0);
            $t->true($partialStats['numWitnessNodes'] > 0);
            $t->same(
                $partialStats['numLeafNodes'] + $partialStats['numBranchNodes'] + $partialStats['numWitnessNodes'],
                $partialStats['numNodes']
            );
            $t->same(quadrableQuadbStatsLines($partialStats), quadrableQuadbOutputLines($target->statsText()));
            $t->same($target->statsText(), QuadbStore::open($targetDir)->statsText());
        } finally {
            quadrableQuadbRemoveDir($sourceDir);
            quadrableQuadbRemoveDir($targetDir);
        }
    },
    'native quadb store exposes full-head LMDB bucket layout like upstream storage' => static function (TestRunner $t): void {
        $dir = quadrableQuadbTempDir();

        try {
            $repo = QuadbStore::init($dir);
            $repo->importLines(
                "wp_options:siteurl|https://example.test\n"
                . "wp_options:home|https://example.test\n"
                . "wp_posts:1|Published post\n",
                '|'
            );

            $tree = $repo->tree();
            $headNodeId = $tree->headNodeId();
            $storeSnapshot = $repo->nodeStore()->exportSnapshot();
            $lmdb = $repo->lmdbBucketSnapshot();

            $t->same(['master'], array_keys($lmdb['quadrable_head']));
            $t->same($headNodeId, quadrableQuadbUnpackUint64Le($lmdb['quadrable_head']['master']));
            $t->same('master', $lmdb['quadrable_quadb_state']['currHead']);
            $t->same(count($storeSnapshot['leaves']), count($lmdb['quadrable_nodesLeaf']));
            $t->same(count($storeSnapshot['branches']), count($lmdb['quadrable_nodesInterior']));
            $t->same(count($storeSnapshot['leaves']), count($lmdb['quadrable_key']));
            $t->same($repo->stats()['numBytes'], quadrableQuadbRawBucketBytes($lmdb));

            $leafNodeId = array_key_first($lmdb['quadrable_nodesLeaf']);
            $leaf = $storeSnapshot['leaves'][$leafNodeId];
            $leafRaw = $lmdb['quadrable_nodesLeaf'][$leafNodeId];
            $t->same(72 + strlen($leaf['value']), strlen($leafRaw));
            $t->same(4, quadrableQuadbUnpackUint64Le(substr($leafRaw, 0, 8)));
            $t->same($leaf['hash'], bin2hex(substr($leafRaw, 8, 32)));
            $t->same($leaf['keyHash'], bin2hex(substr($leafRaw, 40, 32)));
            $t->same($leaf['value'], substr($leafRaw, 72));
            $t->true(in_array($lmdb['quadrable_key'][$leafNodeId], [
                'wp_options:siteurl',
                'wp_options:home',
                'wp_posts:1',
            ], true));

            $branchNodeId = array_key_first($lmdb['quadrable_nodesInterior']);
            $branch = $storeSnapshot['branches'][$branchNodeId];
            $branchRaw = $lmdb['quadrable_nodesInterior'][$branchNodeId];
            $branchWord = quadrableQuadbUnpackUint64Le(substr($branchRaw, 0, 8));
            $branchType = $branchWord % 16;
            $firstChild = intdiv($branchWord, 16);
            $t->same(48, strlen($branchRaw));
            $t->same($branch['hash'], bin2hex(substr($branchRaw, 8, 32)));

            if ($branch['rightNodeId'] === 0) {
                $t->same(1, $branchType);
                $t->same($branch['leftNodeId'], $firstChild);
                $t->same(0, quadrableQuadbUnpackUint64Le(substr($branchRaw, 40, 8)));
            } elseif ($branch['leftNodeId'] === 0) {
                $t->same(2, $branchType);
                $t->same($branch['rightNodeId'], $firstChild);
                $t->same(0, quadrableQuadbUnpackUint64Le(substr($branchRaw, 40, 8)));
            } else {
                $t->same(3, $branchType);
                $t->same($branch['leftNodeId'], $firstChild);
                $t->same($branch['rightNodeId'], quadrableQuadbUnpackUint64Le(substr($branchRaw, 40, 8)));
            }

            $detached = $repo->fork();
            $detachedBuckets = $repo->lmdbBucketSnapshot();
            $t->true(!isset($detachedBuckets['quadrable_quadb_state']['currHead']));
            $t->same($detached->headNodeId(), quadrableQuadbUnpackUint64Le($detachedBuckets['quadrable_quadb_state']['detachedHead']));
            $t->same($lmdb['quadrable_nodesLeaf'], $detachedBuckets['quadrable_nodesLeaf']);
            $t->same($lmdb['quadrable_nodesInterior'], $detachedBuckets['quadrable_nodesInterior']);
        } finally {
            quadrableQuadbRemoveDir($dir);
        }
    },
    'native quadb store exposes raw LMDB entry bytes for backup tooling' => static function (TestRunner $t): void {
        $dir = quadrableQuadbTempDir();
        $proofDir = quadrableQuadbTempDir();

        try {
            $repo = QuadbStore::init($dir);
            $repo->importLines(
                "wp_options:siteurl|https://example.test\n"
                . "wp_options:home|https://example.test\n"
                . "wp_posts:1|Published post\n",
                '|'
            );

            $tree = $repo->tree();
            $lmdb = $repo->lmdbBucketSnapshot();
            $raw = $repo->lmdbRawEntrySnapshot();

            $t->same([
                [
                    'key' => 'master',
                    'value' => quadrableQuadbPackUint64Le($tree->headNodeId()),
                ],
            ], $raw['quadrable_head']);
            $t->same([
                [
                    'key' => 'currHead',
                    'value' => 'master',
                ],
            ], $raw['quadrable_quadb_state']);

            $leafNodeIds = array_keys($lmdb['quadrable_nodesLeaf']);
            $interiorNodeIds = array_keys($lmdb['quadrable_nodesInterior']);
            $trackedKeyNodeIds = array_keys($lmdb['quadrable_key']);

            $t->same($leafNodeIds, array_map(
                static fn (array $entry): int => quadrableQuadbUnpackUint64Le($entry['key']),
                $raw['quadrable_nodesLeaf']
            ));
            $t->same($interiorNodeIds, array_map(
                static fn (array $entry): int => quadrableQuadbUnpackUint64Le($entry['key']),
                $raw['quadrable_nodesInterior']
            ));
            $t->same($trackedKeyNodeIds, array_map(
                static fn (array $entry): int => quadrableQuadbUnpackUint64Le($entry['key']),
                $raw['quadrable_key']
            ));

            $firstLeafNodeId = $leafNodeIds[0];
            $leafEntriesByKeyHex = quadrableQuadbRawEntriesByKeyHex($raw['quadrable_nodesLeaf']);
            $trackedKeyEntriesByKeyHex = quadrableQuadbRawEntriesByKeyHex($raw['quadrable_key']);
            $firstLeafKeyHex = bin2hex(quadrableQuadbPackUint64Le($firstLeafNodeId));

            $t->same($lmdb['quadrable_nodesLeaf'][$firstLeafNodeId], $leafEntriesByKeyHex[$firstLeafKeyHex]);
            $t->same($lmdb['quadrable_key'][$firstLeafNodeId], $trackedKeyEntriesByKeyHex[$firstLeafKeyHex]);

            $detached = $repo->fork();
            $detachedRaw = $repo->lmdbRawEntrySnapshot();
            $t->same([
                [
                    'key' => 'detachedHead',
                    'value' => quadrableQuadbPackUint64Le($detached->headNodeId()),
                ],
            ], $detachedRaw['quadrable_quadb_state']);
            $t->same($raw['quadrable_nodesLeaf'], $detachedRaw['quadrable_nodesLeaf']);
            $t->same($raw['quadrable_nodesInterior'], $detachedRaw['quadrable_nodesInterior']);

            $trustedRoot = $repo->tree()->rootHash();
            $proofHex = $repo->exportProofHex([
                'wp_options:siteurl',
                'wp_posts:404',
            ], Proof::ENCODING_FULL_KEYS);

            $proofRepo = QuadbStore::init($proofDir);
            $proofRepo->checkout('wp-delegated-raw-backup');
            $proofRepo->importProofHex($proofHex, $trustedRoot);

            $proofLmdb = $proofRepo->lmdbBucketSnapshot();
            $proofRaw = $proofRepo->lmdbRawEntrySnapshot();
            $proofHeadNodeId = quadrableQuadbUnpackUint64Le($proofLmdb['quadrable_head']['wp-delegated-raw-backup']);
            $interiorEntriesByKeyHex = quadrableQuadbRawEntriesByKeyHex($proofRaw['quadrable_nodesInterior']);

            $t->same([
                [
                    'key' => 'currHead',
                    'value' => 'wp-delegated-raw-backup',
                ],
            ], $proofRaw['quadrable_quadb_state']);
            $t->same(quadrableQuadbPackUint64Le($proofHeadNodeId), $proofRaw['quadrable_head'][0]['value']);
            $t->true($proofHeadNodeId >= 288230376151711744);
            $t->same(
                $proofLmdb['quadrable_nodesInterior'][$proofHeadNodeId],
                $interiorEntriesByKeyHex[bin2hex(quadrableQuadbPackUint64Le($proofHeadNodeId))]
            );
            $t->same($proofRaw, QuadbStore::open($proofDir)->lmdbRawEntrySnapshot());
        } finally {
            quadrableQuadbRemoveDir($dir);
            quadrableQuadbRemoveDir($proofDir);
        }
    },
    'native quadb store preserves binary LMDB values and raw cursor ordering' => static function (TestRunner $t): void {
        $dir = quadrableQuadbTempDir();
        $proofDir = quadrableQuadbTempDir();

        $leafValues = static function (array $rawEntries): array {
            $values = [];
            foreach ($rawEntries as $entry) {
                $nodeType = quadrableQuadbUnpackUint64Le(substr($entry['value'], 0, 8)) % 16;
                if ($nodeType === 4) {
                    $values[] = substr($entry['value'], 72);
                }
            }

            return $values;
        };

        try {
            $binaryKey = "wp_options:serialized-\xff";
            $binaryValue = "autoload\0\xffserialized:site-option\x80";
            $previewValue = "preview\0\xffpost-bytes\x81";

            $repo = QuadbStore::init($dir);
            $repo->put('wp_options:plain', 'plain');
            $repo->put($binaryKey, $binaryValue);
            $repo->fork('2');
            $repo->put('wp_posts:2', $previewValue);
            $repo->fork('10', 'master');
            $repo->fork('a-preview', '2');

            $raw = $repo->lmdbRawEntrySnapshot();
            $t->same(['10', '2', 'a-preview', 'master'], array_column($raw['quadrable_head'], 'key'));
            $t->true(in_array($binaryValue, $leafValues($raw['quadrable_nodesLeaf']), true));
            $t->true(in_array($previewValue, $leafValues($raw['quadrable_nodesLeaf']), true));
            $t->true(in_array(
                bin2hex($binaryKey),
                array_map(static fn (array $entry): string => bin2hex($entry['value']), $raw['quadrable_key']),
                true
            ));

            $stateJson = (string) file_get_contents($dir . '/quadb-state.json');
            $t->contains(base64_encode($binaryKey), $stateJson);
            $t->contains(base64_encode($binaryValue), $stateJson);

            $reopened = QuadbStore::open($dir);
            $t->same('a-preview', $reopened->currentHeadName());
            $t->same($binaryValue, $reopened->get($binaryKey));
            $t->same($previewValue, $reopened->get('wp_posts:2'));
            $t->same(['10', '2', 'a-preview', 'master'], array_column($reopened->lmdbRawEntrySnapshot()['quadrable_head'], 'key'));

            $reopened->checkout('master');
            $trustedRoot = $reopened->tree()->rootHash();
            $proofHex = $reopened->exportProofHex([$binaryKey], Proof::ENCODING_FULL_KEYS);

            $proofRepo = QuadbStore::init($proofDir);
            $proofRepo->checkout('binary-proof');
            $proofRepo->importProofHex($proofHex, $trustedRoot);
            $updatedBinaryValue = "delegated\0\xffpreview-update\x82";
            $proofRepo->put($binaryKey, $updatedBinaryValue);

            $proofStateJson = (string) file_get_contents($proofDir . '/quadb-state.json');
            $t->contains(base64_encode($binaryKey), $proofStateJson);
            $t->contains(base64_encode($updatedBinaryValue), $proofStateJson);

            $proofReopened = QuadbStore::open($proofDir);
            $t->same($updatedBinaryValue, $proofReopened->get($binaryKey));
            $t->true(in_array(
                $updatedBinaryValue,
                $leafValues($proofReopened->lmdbRawEntrySnapshot()['quadrable_nodesLeaf']),
                true
            ));
        } finally {
            quadrableQuadbRemoveDir($dir);
            quadrableQuadbRemoveDir($proofDir);
        }
    },
    'native quadb store matches upstream LMDB cursor oracle for binary and detached proof heads' => static function (TestRunner $t): void {
        $dir = quadrableQuadbTempDir();
        $proofDir = quadrableQuadbTempDir();
        $detachedProofDir = quadrableQuadbTempDir();
        $mergeGcDir = quadrableQuadbTempDir();
        $noTrackDir = quadrableQuadbTempDir();
        $noTrackProofDir = quadrableQuadbTempDir();

        try {
            $oraclePath = dirname(__DIR__) . '/fixtures/upstream-lmdb-cursor-oracle.json';
            $oracle = json_decode((string) file_get_contents($oraclePath), true, flags: JSON_THROW_ON_ERROR);
            if (!is_array($oracle)
                || !isset($oracle['binaryFixture'], $oracle['fullHead'], $oracle['proofHead'], $oracle['detachedProofHead'], $oracle['mergeGcProofHead'], $oracle['noTrackHead'], $oracle['noTrackProofHead'])
                || !is_array($oracle['binaryFixture'])
                || !is_array($oracle['fullHead'])
                || !is_array($oracle['proofHead'])
                || !is_array($oracle['detachedProofHead'])
                || !is_array($oracle['mergeGcProofHead'])
                || !is_array($oracle['noTrackHead'])
                || !is_array($oracle['noTrackProofHead'])
            ) {
                throw new RuntimeException('malformed upstream LMDB cursor oracle fixture');
            }

            $binaryKey = quadrableQuadbOracleBytes($oracle['binaryFixture']['keyHex']);
            $binaryValue = quadrableQuadbOracleBytes($oracle['binaryFixture']['valueHex']);
            $previewValue = quadrableQuadbOracleBytes($oracle['binaryFixture']['previewValueHex']);
            $delegatedValue = quadrableQuadbOracleBytes($oracle['binaryFixture']['delegatedValueHex']);
            $detachedValue = quadrableQuadbOracleBytes($oracle['binaryFixture']['detachedValueHex']);
            $noTrackDelegatedValue = quadrableQuadbOracleBytes($oracle['binaryFixture']['noTrackDelegatedValueHex']);

            $repo = QuadbStore::init($dir);
            $repo->put('wp_options:plain', 'plain');
            $repo->put($binaryKey, $binaryValue);
            $masterRoot = $repo->tree()->rootHash();
            $proofHex = $repo->exportProofHex([$binaryKey, 'wp_posts:404'], Proof::ENCODING_FULL_KEYS);
            $plainProofHex = $repo->exportProofHex(['wp_options:plain'], Proof::ENCODING_FULL_KEYS);

            $repo->fork('2');
            $repo->put('wp_posts:2', $previewValue);
            $repo->fork('10', 'master');
            $repo->fork('a-preview', '2');

            $t->same($oracle['fullHead']['rootHex'], $repo->tree()->rootHash());
            $t->same($oracle['fullHead']['entries'], quadrableQuadbRawSnapshotHex($repo->lmdbRawEntrySnapshot()));

            $proofRepo = QuadbStore::init($proofDir);
            $proofRepo->checkout('binary-proof');
            $t->same($oracle['proofHead']['sourceRootHex'], $masterRoot);
            $proofRepo->importProofHex($proofHex, $masterRoot);
            $proofRepo->put($binaryKey, $delegatedValue);

            $t->same($oracle['proofHead']['rootHex'], $proofRepo->status()['rootHash']);
            $t->same($oracle['proofHead']['entries'], quadrableQuadbRawSnapshotHex($proofRepo->lmdbRawEntrySnapshot()));

            $detachedOracle = $oracle['detachedProofHead'];
            if (!isset($detachedOracle['sourceRootHex'], $detachedOracle['rootHex'], $detachedOracle['entries'])
                || !is_array($detachedOracle['entries'])
            ) {
                throw new RuntimeException('malformed upstream detached proof-head LMDB cursor oracle fixture');
            }

            $detachedRepo = QuadbStore::init($detachedProofDir);
            $detachedRepo->checkout();
            $t->same($detachedOracle['sourceRootHex'], $masterRoot);
            $detachedRepo->importProofHex($proofHex, $masterRoot);
            $detachedRepo->put($binaryKey, $detachedValue);

            $detachedStatus = $detachedRepo->status();
            $t->true($detachedStatus['detached']);
            $t->same($detachedOracle['rootHex'], $detachedStatus['rootHash']);
            $t->same(
                $detachedOracle['entries'],
                quadrableQuadbRawSnapshotHex($detachedRepo->lmdbRawEntrySnapshot())
            );
            $t->same(
                $detachedOracle['entries'],
                quadrableQuadbRawSnapshotHex(QuadbStore::open($detachedProofDir)->lmdbRawEntrySnapshot())
            );

            $noTrackOracle = $oracle['noTrackHead'];
            if (!isset($noTrackOracle['rootHex'], $noTrackOracle['entries'])
                || !is_array($noTrackOracle['entries'])
            ) {
                throw new RuntimeException('malformed upstream noTrackKeys LMDB cursor oracle fixture');
            }

            $noTrackRepo = QuadbStore::init($noTrackDir, false);
            $noTrackRepo->put('wp_options:plain', 'plain');
            $noTrackRepo->put($binaryKey, $binaryValue);
            $noTrackMasterRoot = $noTrackRepo->tree()->rootHash();
            $noTrackProofHex = $noTrackRepo->exportProofHex([$binaryKey, 'wp_posts:404']);

            $noTrackRepo->fork('2');
            $noTrackRepo->put('wp_posts:2', $previewValue);
            $noTrackRepo->fork('10', 'master');
            $noTrackRepo->fork('a-preview', '2');

            $t->same($noTrackOracle['rootHex'], $noTrackRepo->tree()->rootHash());
            $t->same([], $noTrackOracle['entries']['quadrable_key']);
            $t->same(
                $noTrackOracle['entries'],
                quadrableQuadbRawSnapshotHex($noTrackRepo->lmdbRawEntrySnapshot())
            );
            $t->same(
                $noTrackOracle['entries'],
                quadrableQuadbRawSnapshotHex(QuadbStore::open($noTrackDir, false)->lmdbRawEntrySnapshot())
            );

            $noTrackProofOracle = $oracle['noTrackProofHead'];
            if (!isset($noTrackProofOracle['sourceRootHex'], $noTrackProofOracle['rootHex'], $noTrackProofOracle['entries'])
                || !is_array($noTrackProofOracle['entries'])
            ) {
                throw new RuntimeException('malformed upstream noTrackKeys proof-head LMDB cursor oracle fixture');
            }

            $noTrackProofRepo = QuadbStore::init($noTrackProofDir, false);
            $noTrackProofRepo->checkout('private-proof');
            $t->same($noTrackProofOracle['sourceRootHex'], $noTrackMasterRoot);
            $noTrackProofRepo->importProofHex($noTrackProofHex, $noTrackMasterRoot);
            $noTrackProofRepo->put($binaryKey, $noTrackDelegatedValue);

            $t->same($noTrackProofOracle['rootHex'], $noTrackProofRepo->status()['rootHash']);
            $t->same([], $noTrackProofOracle['entries']['quadrable_key']);
            $t->same(
                $noTrackProofOracle['entries'],
                quadrableQuadbRawSnapshotHex($noTrackProofRepo->lmdbRawEntrySnapshot())
            );
            $t->same(
                $noTrackProofOracle['entries'],
                quadrableQuadbRawSnapshotHex(QuadbStore::open($noTrackProofDir, false)->lmdbRawEntrySnapshot())
            );

            $mergeGcOracle = $oracle['mergeGcProofHead'];
            if (!isset($mergeGcOracle['sourceRootHex'], $mergeGcOracle['rootHex'], $mergeGcOracle['beforeGc'], $mergeGcOracle['afterGc'], $mergeGcOracle['gc'])
                || !is_array($mergeGcOracle['beforeGc'])
                || !is_array($mergeGcOracle['afterGc'])
                || !is_array($mergeGcOracle['gc'])
            ) {
                throw new RuntimeException('malformed upstream merge/gc LMDB cursor oracle fixture');
            }

            $mergeRepo = QuadbStore::init($mergeGcDir);
            $mergeRepo->checkout('merge-gc-proof');
            $t->same($mergeGcOracle['sourceRootHex'], $masterRoot);
            $mergeRepo->importProofHex($proofHex, $masterRoot);
            $t->same($mergeGcOracle['rootHex'], $mergeRepo->mergeProofHex($plainProofHex));
            $t->same(
                $mergeGcOracle['beforeGc']['entries'],
                quadrableQuadbRawSnapshotHex($mergeRepo->lmdbRawEntrySnapshot())
            );

            $t->same(
                'Collected ' . $mergeGcOracle['gc']['garbage'] . '/' . $mergeGcOracle['gc']['total'] . " nodes\n",
                $mergeRepo->garbageCollectText()
            );
            $t->same(
                $mergeGcOracle['afterGc']['entries'],
                quadrableQuadbRawSnapshotHex($mergeRepo->lmdbRawEntrySnapshot())
            );
            $t->same(
                $mergeGcOracle['afterGc']['entries'],
                quadrableQuadbRawSnapshotHex(QuadbStore::open($mergeGcDir)->lmdbRawEntrySnapshot())
            );
        } finally {
            quadrableQuadbRemoveDir($dir);
            quadrableQuadbRemoveDir($proofDir);
            quadrableQuadbRemoveDir($detachedProofDir);
            quadrableQuadbRemoveDir($mergeGcDir);
            quadrableQuadbRemoveDir($noTrackDir);
            quadrableQuadbRemoveDir($noTrackProofDir);
        }
    },
    'native quadb store matches upstream LMDB dump load oracle for a mixed WordPress store' => static function (TestRunner $t): void {
        $dir = quadrableQuadbTempDir();

        try {
            $oraclePath = dirname(__DIR__) . '/fixtures/upstream-lmdb-dump-restore-oracle.json';
            $oracle = json_decode((string) file_get_contents($oraclePath), true, flags: JSON_THROW_ON_ERROR);
            if (!is_array($oracle)
                || !isset($oracle['fixtureValues'], $oracle['source'], $oracle['restored'], $oracle['dumpLoad'])
                || !is_array($oracle['fixtureValues'])
                || !is_array($oracle['source'])
                || !is_array($oracle['restored'])
                || !is_array($oracle['dumpLoad'])
                || !isset($oracle['source']['entries'], $oracle['restored']['entries'])
                || !is_array($oracle['source']['entries'])
                || !is_array($oracle['restored']['entries'])
            ) {
                throw new RuntimeException('malformed upstream LMDB dump/restore oracle fixture');
            }

            $t->true($oracle['dumpLoad']['entriesMatch']);
            $t->same($oracle['source']['entries'], $oracle['restored']['entries']);
            $t->same($oracle['source']['rawEntryDigest'], QuadbStore::portableRawEntryDigest($oracle['source']['entries']));
            $t->same($oracle['restored']['rawEntryDigest'], QuadbStore::portableRawEntryDigest($oracle['restored']['entries']));

            $values = $oracle['fixtureValues'];
            $binaryKey = quadrableQuadbOracleBytes($values['binaryKeyHex']);
            $binaryValue = quadrableQuadbOracleBytes($values['binaryValueHex']);
            $previewValue = quadrableQuadbOracleBytes($values['previewValueHex']);
            $delegatedValue = quadrableQuadbOracleBytes($values['delegatedValueHex']);
            $detachedValue = quadrableQuadbOracleBytes($values['detachedValueHex']);
            $privateValue = quadrableQuadbOracleBytes($values['privateValueHex']);
            $privatePostValue = quadrableQuadbOracleBytes($values['privatePostValueHex']);
            $privateDelegatedValue = quadrableQuadbOracleBytes($values['privateDelegatedValueHex']);

            $repo = QuadbStore::init($dir);
            $repo->put('wp_options:plain', 'plain');
            $repo->put($binaryKey, $binaryValue);
            $repo->put('wp_posts:1', 'Published post');
            $repo->put('wp_postmeta:1:_thumbnail_id', '42');
            $masterRoot = $repo->tree()->rootHash();
            $binaryProofHex = $repo->exportProofHex([
                $binaryKey,
                'wp_posts:404',
            ], Proof::ENCODING_FULL_KEYS);

            $repo->fork('2');
            $repo->put('wp_posts:2', $previewValue);
            $repo->put('wp_postmeta:2:_edit_lock', '1716400000:1');
            $repo->fork('10', 'master');
            $repo->fork('a-preview', '2');

            $private = QuadbStore::open($dir, false);
            $private->checkout('private-full');
            $private->put('wp_options:private', $privateValue);
            $private->put('wp_posts:private', $privatePostValue);
            $privateRoot = $private->tree()->rootHash();
            $privateProofHex = $private->exportProofHex([
                'wp_options:private',
                'wp_posts:missing',
            ]);

            $repo = QuadbStore::open($dir);
            $repo->checkout('binary-proof');
            $repo->importProofHex($binaryProofHex, $masterRoot);
            $repo->put($binaryKey, $delegatedValue);

            $private = QuadbStore::open($dir, false);
            $private->checkout('private-proof');
            $private->importProofHex($privateProofHex, $privateRoot);
            $private->put('wp_options:private', $privateDelegatedValue);

            $repo = QuadbStore::open($dir);
            $repo->checkout();
            $repo->importProofHex($binaryProofHex, $masterRoot);
            $repo->put($binaryKey, $detachedValue);

            $actualEntries = quadrableQuadbRawSnapshotHex($repo->lmdbRawEntrySnapshot());
            $t->same($oracle['restored']['entries'], $actualEntries);

            $dump = $repo->exportPortableDump();
            $t->same($oracle['restored']['rawEntryDigest'], $dump['rawEntryDigest']);
            $t->same($oracle['restored']['entries'], $dump['rawEntries']);
            $t->same($dump['rawEntryDigest'], QuadbStore::portableRawEntryDigest($dump['rawEntries']));
        } finally {
            quadrableQuadbRemoveDir($dir);
        }
    },
    'native quadb store restores full-head raw LMDB cursor dumps without portable state' => static function (TestRunner $t): void {
        $dir = quadrableQuadbTempDir();
        $restoreDir = quadrableQuadbTempDir();
        $corruptDir = quadrableQuadbTempDir();

        try {
            $repo = QuadbStore::init($dir);
            $repo->importLines(
                "wp_options:siteurl|https://example.test\n"
                . "wp_options:home|https://example.test\n"
                . "wp_posts:1|Published post\n",
                '|'
            );
            $masterRoot = $repo->tree()->rootHash();

            $repo->fork('wp-preview', 'master');
            $repo->put('wp_posts:1', 'Preview post');
            $repo->put('wp_posts:2', "Preview page\0serialized");
            $previewRoot = $repo->tree()->rootHash();

            $repo->checkout();
            $repo->put('wp_posts:2', "Detached page\0serialized");
            $repo->put('wp_postmeta:2:_edit_lock', '1716400000:1');
            $detachedRoot = $repo->tree()->rootHash();

            $rawEntries = quadrableQuadbRawSnapshotHex($repo->lmdbRawEntrySnapshot());
            $t->same('detachedHead', hex2bin($rawEntries['quadrable_quadb_state'][0]['keyHex']));

            $restored = QuadbStore::restoreRawEntryDump($restoreDir, $rawEntries);
            $t->same($rawEntries, quadrableQuadbRawSnapshotHex($restored->lmdbRawEntrySnapshot()));
            $t->true($restored->isDetachedHead());
            $t->same($detachedRoot, $restored->status()['rootHash']);
            $t->same("Detached page\0serialized", $restored->get('wp_posts:2'));
            $t->same('1716400000:1', $restored->get('wp_postmeta:2:_edit_lock'));

            $restored->checkout('wp-preview');
            $t->same($previewRoot, $restored->status()['rootHash']);
            $t->same('Preview post', $restored->get('wp_posts:1'));
            $t->same("Preview page\0serialized", $restored->get('wp_posts:2'));

            $restored->checkout('master');
            $t->same($masterRoot, $restored->status()['rootHash']);
            $t->same('Published post', $restored->get('wp_posts:1'));
            $t->same('https://example.test', $restored->get('wp_options:home'));

            $corrupt = $rawEntries;
            $corrupt['quadrable_nodesLeaf'][0]['valueHex'] .= '00';
            $t->throws(InvalidArgumentException::class, static fn () => QuadbStore::restoreRawEntryDump($corruptDir, $corrupt));
        } finally {
            quadrableQuadbRemoveDir($dir);
            quadrableQuadbRemoveDir($restoreDir);
            quadrableQuadbRemoveDir($corruptDir);
        }
    },
    'native quadb store restores upstream full and proof-backed LMDB cursor dumps without portable state' => static function (TestRunner $t): void {
        $restoreDir = quadrableQuadbTempDir();
        $mixedRestoreDir = quadrableQuadbTempDir();

        try {
            $oraclePath = dirname(__DIR__) . '/fixtures/upstream-lmdb-dump-restore-oracle.json';
            $oracle = json_decode((string) file_get_contents($oraclePath), true, flags: JSON_THROW_ON_ERROR);
            if (!is_array($oracle)
                || !isset($oracle['fixtureValues'], $oracle['restored']['entries'])
                || !is_array($oracle['fixtureValues'])
                || !is_array($oracle['restored']['entries'])
            ) {
                throw new RuntimeException('malformed upstream LMDB dump/restore oracle fixture');
            }

            $fullRawEntries = quadrableQuadbFullHeadRawEntries(
                $oracle['restored']['entries'],
                ['10', '2', 'a-preview', 'master', 'private-full'],
                'a-preview'
            );
            $restored = QuadbStore::restoreRawEntryDump($restoreDir, $fullRawEntries);
            $t->same($fullRawEntries, quadrableQuadbRawSnapshotHex($restored->lmdbRawEntrySnapshot()));
            $t->same(64, strlen(QuadbStore::portableRawEntryDigest($fullRawEntries)));

            $values = $oracle['fixtureValues'];
            $binaryKey = quadrableQuadbOracleBytes($values['binaryKeyHex']);
            $binaryValue = quadrableQuadbOracleBytes($values['binaryValueHex']);
            $previewValue = quadrableQuadbOracleBytes($values['previewValueHex']);
            $delegatedValue = quadrableQuadbOracleBytes($values['delegatedValueHex']);
            $detachedValue = quadrableQuadbOracleBytes($values['detachedValueHex']);
            $privateValue = quadrableQuadbOracleBytes($values['privateValueHex']);
            $privatePostValue = quadrableQuadbOracleBytes($values['privatePostValueHex']);
            $privateDelegatedValue = quadrableQuadbOracleBytes($values['privateDelegatedValueHex']);

            $t->same('a-preview', $restored->currentHeadName());
            $t->same($previewValue, $restored->get('wp_posts:2'));
            $t->same('1716400000:1', $restored->get('wp_postmeta:2:_edit_lock'));

            $restored->checkout('master');
            $t->same('plain', $restored->get('wp_options:plain'));
            $t->same($binaryValue, $restored->get($binaryKey));
            $t->same('Published post', $restored->get('wp_posts:1'));

            $restored->checkout('private-full');
            $t->same($privateValue, $restored->get('wp_options:private'));
            $t->same($privatePostValue, $restored->get('wp_posts:private'));

            $mixed = QuadbStore::restoreRawEntryDump($mixedRestoreDir, $oracle['restored']['entries']);
            $t->same($oracle['restored']['entries'], quadrableQuadbRawSnapshotHex($mixed->lmdbRawEntrySnapshot()));
            $t->same($oracle['restored']['rawEntryDigest'], QuadbStore::portableRawEntryDigest(
                quadrableQuadbRawSnapshotHex($mixed->lmdbRawEntrySnapshot())
            ));
            $t->true($mixed->isDetachedHead());
            $t->same($detachedValue, $mixed->get($binaryKey));

            $mixed->checkout('binary-proof');
            $t->same($delegatedValue, $mixed->get($binaryKey));
            $rootBeforeRawUpdate = $mixed->status()['rootHash'];
            $updatedDelegatedValue = "Delegated raw restore edit\0serialized";
            $mixed->put($binaryKey, $updatedDelegatedValue);
            $updatedRoot = $mixed->status()['rootHash'];
            $t->true($updatedRoot !== $rootBeforeRawUpdate);
            $t->same($updatedDelegatedValue, $mixed->get($binaryKey));

            $reopenedMixed = QuadbStore::open($mixedRestoreDir);
            $t->same('binary-proof', $reopenedMixed->currentHeadName());
            $t->same($updatedRoot, $reopenedMixed->status()['rootHash']);
            $t->same($updatedDelegatedValue, $reopenedMixed->get($binaryKey));
            $updatedProof = Proof::decode($reopenedMixed->exportProofBytes([$binaryKey], Proof::ENCODING_FULL_KEYS));
            $updatedPartial = SparseTree::importProof($updatedProof, $updatedRoot);
            $t->same($updatedDelegatedValue, $updatedPartial->get($binaryKey));

            $mixed->checkout('private-proof');
            $t->same($privateDelegatedValue, $mixed->get('wp_options:private'));

            $mixed->checkout('master');
            $t->same('plain', $mixed->get('wp_options:plain'));
            $t->same($binaryValue, $mixed->get($binaryKey));
        } finally {
            quadrableQuadbRemoveDir($restoreDir);
            quadrableQuadbRemoveDir($mixedRestoreDir);
        }
    },
    'native quadb store merges proofs on raw-restored proof-backed heads after delegated writes' => static function (TestRunner $t): void {
        $sourceDir = quadrableQuadbTempDir();
        $proofDir = quadrableQuadbTempDir();
        $restoreDir = quadrableQuadbTempDir();

        try {
            $source = QuadbStore::init($sourceDir);
            $source->importLines(
                "wp_options:siteurl|https://example.test\n"
                . "wp_options:home|https://example.test\n"
                . "wp_posts:1|Published post\n",
                '|'
            );

            $trustedRoot = $source->tree()->rootHash();
            $siteUrlProofHex = $source->exportProofHex(
                ['wp_options:siteurl'],
                Proof::ENCODING_FULL_KEYS
            );

            $proofRepo = QuadbStore::init($proofDir);
            $proofRepo->checkout('wp-delegated-raw');
            $proofRepo->importProofHex($siteUrlProofHex, $trustedRoot);

            $rawEntries = quadrableQuadbRawSnapshotHex($proofRepo->lmdbRawEntrySnapshot());
            $restored = QuadbStore::restoreRawEntryDump($restoreDir, $rawEntries);
            $t->same($rawEntries, quadrableQuadbRawSnapshotHex($restored->lmdbRawEntrySnapshot()));
            $t->same('wp-delegated-raw', $restored->currentHeadName());
            $t->same('https://example.test', $restored->get('wp_options:siteurl'));
            $t->throws(RuntimeException::class, static fn () => $restored->get('wp_options:home'));

            $updatedSiteUrl = 'https://preview.example.test';
            $restored->put('wp_options:siteurl', $updatedSiteUrl);
            $updatedRoot = $restored->status()['rootHash'];

            $authoritative = new SparseTree();
            $authoritative->change()
                ->put('wp_options:siteurl', $updatedSiteUrl)
                ->put('wp_options:home', 'https://example.test')
                ->put('wp_posts:1', 'Published post')
                ->apply();

            $t->same($updatedRoot, $authoritative->rootHash());

            $rawBeforeMerge = quadrableQuadbRawSnapshotHex($restored->lmdbRawEntrySnapshot());
            $nodeCountBeforeMerge = count($rawBeforeMerge['quadrable_nodesLeaf'])
                + count($rawBeforeMerge['quadrable_nodesInterior']);
            $homeProofBytes = $authoritative->exportProof(['wp_options:home'])
                ->encode(Proof::ENCODING_FULL_KEYS);

            $t->same($updatedRoot, $restored->mergeProofBytes($homeProofBytes));
            $t->same($updatedSiteUrl, $restored->get('wp_options:siteurl'));
            $t->same('https://example.test', $restored->get('wp_options:home'));

            $rawAfterMerge = quadrableQuadbRawSnapshotHex($restored->lmdbRawEntrySnapshot());
            $nodeCountAfterMerge = count($rawAfterMerge['quadrable_nodesLeaf'])
                + count($rawAfterMerge['quadrable_nodesInterior']);
            $t->true($nodeCountAfterMerge > $nodeCountBeforeMerge);

            $gc = quadrableQuadbParseGcText($restored->garbageCollectText());
            $t->true($gc['garbage'] > 0);
            $t->same($updatedRoot, $restored->status()['rootHash']);

            $reopened = QuadbStore::open($restoreDir);
            $t->same('wp-delegated-raw', $reopened->currentHeadName());
            $t->same($updatedRoot, $reopened->status()['rootHash']);
            $t->same($updatedSiteUrl, $reopened->get('wp_options:siteurl'));
            $t->same('https://example.test', $reopened->get('wp_options:home'));
        } finally {
            quadrableQuadbRemoveDir($sourceDir);
            quadrableQuadbRemoveDir($proofDir);
            quadrableQuadbRemoveDir($restoreDir);
        }
    },
    'native quadb store matches upstream raw-restored mergeProof LMDB cursor oracle' => static function (TestRunner $t): void {
        $restoreDir = quadrableQuadbTempDir();

        try {
            $oraclePath = dirname(__DIR__) . '/fixtures/upstream-lmdb-raw-restored-merge-oracle.json';
            $oracle = json_decode((string) file_get_contents($oraclePath), true, flags: JSON_THROW_ON_ERROR);
            if (!is_array($oracle)
                || !isset(
                    $oracle['fixtureValues'],
                    $oracle['roots'],
                    $oracle['beforeUpdate']['entries'],
                    $oracle['afterUpdateBeforeMerge']['entries'],
                    $oracle['afterMergeBeforeGc']['entries'],
                    $oracle['afterGc']['entries'],
                    $oracle['gc'],
                    $oracle['updatedRootHex'],
                    $oracle['mergedRootHex']
                )
                || !is_array($oracle['fixtureValues'])
                || !is_array($oracle['roots'])
                || !is_array($oracle['beforeUpdate']['entries'])
                || !is_array($oracle['afterUpdateBeforeMerge']['entries'])
                || !is_array($oracle['afterMergeBeforeGc']['entries'])
                || !is_array($oracle['afterGc']['entries'])
                || !is_array($oracle['gc'])
            ) {
                throw new RuntimeException('malformed upstream raw-restored mergeProof oracle fixture');
            }

            $values = $oracle['fixtureValues'];
            foreach (['siteUrlKey', 'homeKey', 'postKey', 'originalUrl', 'updatedUrl', 'postValue', 'head'] as $key) {
                if (!isset($values[$key]) || !is_string($values[$key])) {
                    throw new RuntimeException('malformed upstream raw-restored mergeProof fixture value');
                }
            }

            $restored = QuadbStore::restoreRawEntryDump($restoreDir, $oracle['beforeUpdate']['entries']);
            $t->same($oracle['beforeUpdate']['entries'], quadrableQuadbRawSnapshotHex($restored->lmdbRawEntrySnapshot()));
            $t->same($values['head'], $restored->currentHeadName());
            $t->same($oracle['roots']['restoredRootHex'], $restored->status()['rootHash']);
            $t->same($values['originalUrl'], $restored->get($values['siteUrlKey']));
            $t->throws(RuntimeException::class, static fn () => $restored->get($values['homeKey']));

            $restored->put($values['siteUrlKey'], $values['updatedUrl']);
            $t->same($oracle['roots']['authoritativeUpdatedRootHex'], $restored->status()['rootHash']);
            $t->same($oracle['updatedRootHex'], $restored->status()['rootHash']);
            $t->same($oracle['afterUpdateBeforeMerge']['entries'], quadrableQuadbRawSnapshotHex($restored->lmdbRawEntrySnapshot()));

            $authoritative = new SparseTree();
            $authoritative->change()
                ->put($values['siteUrlKey'], $values['updatedUrl'])
                ->put($values['homeKey'], $values['originalUrl'])
                ->put($values['postKey'], $values['postValue'])
                ->apply();

            $t->same($oracle['updatedRootHex'], $authoritative->rootHash());
            $homeProofBytes = $authoritative->exportProof([$values['homeKey']])
                ->encode(Proof::ENCODING_FULL_KEYS);

            $t->same($oracle['mergedRootHex'], $restored->mergeProofBytes($homeProofBytes));
            $t->same($oracle['afterMergeBeforeGc']['entries'], quadrableQuadbRawSnapshotHex($restored->lmdbRawEntrySnapshot()));
            $t->same($values['updatedUrl'], $restored->get($values['siteUrlKey']));
            $t->same($values['originalUrl'], $restored->get($values['homeKey']));

            $t->same(
                'Collected ' . $oracle['gc']['garbage'] . '/' . $oracle['gc']['total'] . " nodes\n",
                $restored->garbageCollectText()
            );
            $t->same($oracle['afterGc']['entries'], quadrableQuadbRawSnapshotHex($restored->lmdbRawEntrySnapshot()));

            $reopened = QuadbStore::open($restoreDir);
            $t->same($values['head'], $reopened->currentHeadName());
            $t->same($oracle['mergedRootHex'], $reopened->status()['rootHash']);
            $t->same($values['updatedUrl'], $reopened->get($values['siteUrlKey']));
            $t->same($values['originalUrl'], $reopened->get($values['homeKey']));
            $t->same($oracle['afterGc']['entries'], quadrableQuadbRawSnapshotHex($reopened->lmdbRawEntrySnapshot()));
        } finally {
            quadrableQuadbRemoveDir($restoreDir);
        }
    },
    'native quadb store matches upstream detached raw-restored mergeProof LMDB cursor oracle' => static function (TestRunner $t): void {
        $restoreDir = quadrableQuadbTempDir();

        try {
            $oraclePath = dirname(__DIR__) . '/fixtures/upstream-lmdb-detached-raw-restored-merge-oracle.json';
            $oracle = json_decode((string) file_get_contents($oraclePath), true, flags: JSON_THROW_ON_ERROR);
            if (!is_array($oracle)
                || !isset(
                    $oracle['fixtureValues'],
                    $oracle['roots'],
                    $oracle['beforeUpdate']['entries'],
                    $oracle['afterUpdateBeforeMerge']['entries'],
                    $oracle['afterMergeBeforeGc']['entries'],
                    $oracle['afterGc']['entries'],
                    $oracle['gc'],
                    $oracle['updatedRootHex'],
                    $oracle['mergedRootHex']
                )
                || !is_array($oracle['fixtureValues'])
                || !is_array($oracle['roots'])
                || !is_array($oracle['beforeUpdate']['entries'])
                || !is_array($oracle['afterUpdateBeforeMerge']['entries'])
                || !is_array($oracle['afterMergeBeforeGc']['entries'])
                || !is_array($oracle['afterGc']['entries'])
                || !is_array($oracle['gc'])
            ) {
                throw new RuntimeException('malformed upstream detached raw-restored mergeProof oracle fixture');
            }

            $values = $oracle['fixtureValues'];
            foreach (['siteUrlKey', 'homeKey', 'postKey', 'originalUrl', 'updatedUrl', 'postValue'] as $key) {
                if (!isset($values[$key]) || !is_string($values[$key])) {
                    throw new RuntimeException('malformed upstream detached raw-restored mergeProof fixture value');
                }
            }
            if (($values['detached'] ?? null) !== true || ($values['head'] ?? null) !== null) {
                throw new RuntimeException('upstream detached raw-restored mergeProof fixture is not detached');
            }

            $restored = QuadbStore::restoreRawEntryDump($restoreDir, $oracle['beforeUpdate']['entries']);
            $t->same($oracle['beforeUpdate']['entries'], quadrableQuadbRawSnapshotHex($restored->lmdbRawEntrySnapshot()));
            $t->true($restored->isDetachedHead());
            $t->same(null, $restored->currentHeadName());
            $t->same($oracle['roots']['restoredRootHex'], $restored->status()['rootHash']);
            $t->same($values['originalUrl'], $restored->get($values['siteUrlKey']));
            $t->throws(RuntimeException::class, static fn () => $restored->get($values['homeKey']));

            $restored->put($values['siteUrlKey'], $values['updatedUrl']);
            $t->true($restored->isDetachedHead());
            $t->same($oracle['roots']['authoritativeUpdatedRootHex'], $restored->status()['rootHash']);
            $t->same($oracle['updatedRootHex'], $restored->status()['rootHash']);
            $t->same($oracle['afterUpdateBeforeMerge']['entries'], quadrableQuadbRawSnapshotHex($restored->lmdbRawEntrySnapshot()));

            $authoritative = new SparseTree();
            $authoritative->change()
                ->put($values['siteUrlKey'], $values['updatedUrl'])
                ->put($values['homeKey'], $values['originalUrl'])
                ->put($values['postKey'], $values['postValue'])
                ->apply();

            $t->same($oracle['updatedRootHex'], $authoritative->rootHash());
            $homeProofBytes = $authoritative->exportProof([$values['homeKey']])
                ->encode(Proof::ENCODING_FULL_KEYS);

            $t->same($oracle['mergedRootHex'], $restored->mergeProofBytes($homeProofBytes));
            $t->true($restored->isDetachedHead());
            $t->same($oracle['afterMergeBeforeGc']['entries'], quadrableQuadbRawSnapshotHex($restored->lmdbRawEntrySnapshot()));
            $t->same($values['updatedUrl'], $restored->get($values['siteUrlKey']));
            $t->same($values['originalUrl'], $restored->get($values['homeKey']));

            $t->same(
                'Collected ' . $oracle['gc']['garbage'] . '/' . $oracle['gc']['total'] . " nodes\n",
                $restored->garbageCollectText()
            );
            $t->same($oracle['afterGc']['entries'], quadrableQuadbRawSnapshotHex($restored->lmdbRawEntrySnapshot()));

            $reopened = QuadbStore::open($restoreDir);
            $t->true($reopened->isDetachedHead());
            $t->same(null, $reopened->currentHeadName());
            $t->same($oracle['mergedRootHex'], $reopened->status()['rootHash']);
            $t->same($values['updatedUrl'], $reopened->get($values['siteUrlKey']));
            $t->same($values['originalUrl'], $reopened->get($values['homeKey']));
            $t->same($oracle['afterGc']['entries'], quadrableQuadbRawSnapshotHex($reopened->lmdbRawEntrySnapshot()));
        } finally {
            quadrableQuadbRemoveDir($restoreDir);
        }
    },
    'native quadb store matches upstream noTrack raw-restored mergeProof LMDB cursor oracle' => static function (TestRunner $t): void {
        $restoreDir = quadrableQuadbTempDir();

        try {
            $oraclePath = dirname(__DIR__) . '/fixtures/upstream-lmdb-notrack-raw-restored-merge-oracle.json';
            $oracle = json_decode((string) file_get_contents($oraclePath), true, flags: JSON_THROW_ON_ERROR);
            if (!is_array($oracle)
                || !isset(
                    $oracle['fixtureValues'],
                    $oracle['roots'],
                    $oracle['beforeUpdate']['entries'],
                    $oracle['afterUpdateBeforeMerge']['entries'],
                    $oracle['afterMergeBeforeGc']['entries'],
                    $oracle['afterGc']['entries'],
                    $oracle['gc'],
                    $oracle['updatedRootHex'],
                    $oracle['mergedRootHex']
                )
                || !is_array($oracle['fixtureValues'])
                || !is_array($oracle['roots'])
                || !is_array($oracle['beforeUpdate']['entries'])
                || !is_array($oracle['afterUpdateBeforeMerge']['entries'])
                || !is_array($oracle['afterMergeBeforeGc']['entries'])
                || !is_array($oracle['afterGc']['entries'])
                || !is_array($oracle['gc'])
            ) {
                throw new RuntimeException('malformed upstream noTrack raw-restored mergeProof oracle fixture');
            }

            $values = $oracle['fixtureValues'];
            foreach (['siteUrlKey', 'homeKey', 'postKey', 'originalUrl', 'updatedUrl', 'postValue', 'head'] as $key) {
                if (!isset($values[$key]) || !is_string($values[$key])) {
                    throw new RuntimeException('malformed upstream noTrack raw-restored mergeProof fixture value');
                }
            }
            if (($values['noTrackKeys'] ?? null) !== true || ($values['detached'] ?? null) !== false) {
                throw new RuntimeException('upstream noTrack raw-restored mergeProof fixture is not a named noTrack proof head');
            }

            $restored = QuadbStore::restoreRawEntryDump($restoreDir, $oracle['beforeUpdate']['entries'], false);
            $t->same($oracle['beforeUpdate']['entries'], quadrableQuadbRawSnapshotHex($restored->lmdbRawEntrySnapshot()));
            $t->same([], $restored->lmdbRawEntrySnapshot()['quadrable_key']);
            $t->same($values['head'], $restored->currentHeadName());
            $t->same($oracle['roots']['restoredRootHex'], $restored->status()['rootHash']);
            $t->same($values['originalUrl'], $restored->get($values['siteUrlKey']));
            $t->throws(RuntimeException::class, static fn () => $restored->get($values['homeKey']));

            $restored->put($values['siteUrlKey'], $values['updatedUrl']);
            $t->same($oracle['roots']['authoritativeUpdatedRootHex'], $restored->status()['rootHash']);
            $t->same($oracle['updatedRootHex'], $restored->status()['rootHash']);
            $t->same($oracle['afterUpdateBeforeMerge']['entries'], quadrableQuadbRawSnapshotHex($restored->lmdbRawEntrySnapshot()));
            $t->same([], $restored->lmdbRawEntrySnapshot()['quadrable_key']);

            $authoritative = new SparseTree();
            $authoritative->change()
                ->put($values['siteUrlKey'], $values['updatedUrl'])
                ->put($values['homeKey'], $values['originalUrl'])
                ->put($values['postKey'], $values['postValue'])
                ->apply();

            $t->same($oracle['updatedRootHex'], $authoritative->rootHash());
            $homeProofBytes = $authoritative->exportProof([$values['homeKey']])->encode();

            $t->same($oracle['mergedRootHex'], $restored->mergeProofBytes($homeProofBytes));
            $t->same($oracle['afterMergeBeforeGc']['entries'], quadrableQuadbRawSnapshotHex($restored->lmdbRawEntrySnapshot()));
            $t->same([], $restored->lmdbRawEntrySnapshot()['quadrable_key']);
            $t->same($values['updatedUrl'], $restored->get($values['siteUrlKey']));
            $t->same($values['originalUrl'], $restored->get($values['homeKey']));
            $t->throws(RuntimeException::class, static fn () => $restored->exportProofBytes(
                [$values['siteUrlKey']],
                Proof::ENCODING_FULL_KEYS
            ));

            $t->same(
                'Collected ' . $oracle['gc']['garbage'] . '/' . $oracle['gc']['total'] . " nodes\n",
                $restored->garbageCollectText()
            );
            $t->same($oracle['afterGc']['entries'], quadrableQuadbRawSnapshotHex($restored->lmdbRawEntrySnapshot()));
            $t->same([], $restored->lmdbRawEntrySnapshot()['quadrable_key']);

            $reopened = QuadbStore::open($restoreDir, false);
            $t->same($values['head'], $reopened->currentHeadName());
            $t->same($oracle['mergedRootHex'], $reopened->status()['rootHash']);
            $t->same($values['updatedUrl'], $reopened->get($values['siteUrlKey']));
            $t->same($values['originalUrl'], $reopened->get($values['homeKey']));
            $t->same($oracle['afterGc']['entries'], quadrableQuadbRawSnapshotHex($reopened->lmdbRawEntrySnapshot()));
            $t->same([], $reopened->lmdbRawEntrySnapshot()['quadrable_key']);
        } finally {
            quadrableQuadbRemoveDir($restoreDir);
        }
    },
    'native quadb store matches upstream sequential raw-restored mergeProof LMDB cursor oracle' => static function (TestRunner $t): void {
        $restoreDir = quadrableQuadbTempDir();

        try {
            $oraclePath = dirname(__DIR__) . '/fixtures/upstream-lmdb-sequential-raw-restored-merge-oracle.json';
            $oracle = json_decode((string) file_get_contents($oraclePath), true, flags: JSON_THROW_ON_ERROR);
            if (!is_array($oracle)
                || !isset(
                    $oracle['fixtureValues'],
                    $oracle['roots'],
                    $oracle['beforeUpdate']['entries'],
                    $oracle['afterUpdateBeforeFirstMerge']['entries'],
                    $oracle['afterFirstMergeBeforeSecond']['entries'],
                    $oracle['afterSecondMergeBeforeGc']['entries'],
                    $oracle['afterGc']['entries'],
                    $oracle['gc'],
                    $oracle['updatedRootHex'],
                    $oracle['firstMergedRootHex'],
                    $oracle['secondMergedRootHex']
                )
                || !is_array($oracle['fixtureValues'])
                || !is_array($oracle['roots'])
                || !is_array($oracle['beforeUpdate']['entries'])
                || !is_array($oracle['afterUpdateBeforeFirstMerge']['entries'])
                || !is_array($oracle['afterFirstMergeBeforeSecond']['entries'])
                || !is_array($oracle['afterSecondMergeBeforeGc']['entries'])
                || !is_array($oracle['afterGc']['entries'])
                || !is_array($oracle['gc'])
            ) {
                throw new RuntimeException('malformed upstream sequential raw-restored mergeProof oracle fixture');
            }

            $values = $oracle['fixtureValues'];
            foreach (['siteUrlKey', 'homeKey', 'postKey', 'originalUrl', 'updatedUrl', 'postValue', 'head'] as $key) {
                if (!isset($values[$key]) || !is_string($values[$key])) {
                    throw new RuntimeException('malformed upstream sequential raw-restored mergeProof fixture value');
                }
            }

            $restored = QuadbStore::restoreRawEntryDump($restoreDir, $oracle['beforeUpdate']['entries']);
            $t->same($oracle['beforeUpdate']['entries'], quadrableQuadbRawSnapshotHex($restored->lmdbRawEntrySnapshot()));
            $t->same($values['head'], $restored->currentHeadName());
            $t->same($oracle['roots']['restoredRootHex'], $restored->status()['rootHash']);
            $t->same($values['originalUrl'], $restored->get($values['siteUrlKey']));
            $t->throws(RuntimeException::class, static fn () => $restored->get($values['homeKey']));
            $t->throws(RuntimeException::class, static fn () => $restored->get($values['postKey']));

            $restored->put($values['siteUrlKey'], $values['updatedUrl']);
            $t->same($oracle['roots']['authoritativeUpdatedRootHex'], $restored->status()['rootHash']);
            $t->same($oracle['updatedRootHex'], $restored->status()['rootHash']);
            $t->same(
                $oracle['afterUpdateBeforeFirstMerge']['entries'],
                quadrableQuadbRawSnapshotHex($restored->lmdbRawEntrySnapshot())
            );

            $authoritative = new SparseTree();
            $authoritative->change()
                ->put($values['siteUrlKey'], $values['updatedUrl'])
                ->put($values['homeKey'], $values['originalUrl'])
                ->put($values['postKey'], $values['postValue'])
                ->apply();

            $t->same($oracle['updatedRootHex'], $authoritative->rootHash());
            $homeProofBytes = $authoritative->exportProof([$values['homeKey']])
                ->encode(Proof::ENCODING_FULL_KEYS);
            $postProofBytes = $authoritative->exportProof([$values['postKey']])
                ->encode(Proof::ENCODING_FULL_KEYS);

            $t->same($oracle['firstMergedRootHex'], $restored->mergeProofBytes($homeProofBytes));
            $t->same($oracle['updatedRootHex'], $restored->status()['rootHash']);
            $t->same(
                $oracle['afterFirstMergeBeforeSecond']['entries'],
                quadrableQuadbRawSnapshotHex($restored->lmdbRawEntrySnapshot())
            );
            $t->same($values['updatedUrl'], $restored->get($values['siteUrlKey']));
            $t->same($values['originalUrl'], $restored->get($values['homeKey']));
            $t->throws(RuntimeException::class, static fn () => $restored->get($values['postKey']));

            $t->same($oracle['secondMergedRootHex'], $restored->mergeProofBytes($postProofBytes));
            $t->same($oracle['updatedRootHex'], $restored->status()['rootHash']);
            $t->same(
                $oracle['afterSecondMergeBeforeGc']['entries'],
                quadrableQuadbRawSnapshotHex($restored->lmdbRawEntrySnapshot())
            );
            $t->same($values['updatedUrl'], $restored->get($values['siteUrlKey']));
            $t->same($values['originalUrl'], $restored->get($values['homeKey']));
            $t->same($values['postValue'], $restored->get($values['postKey']));

            $t->same(
                'Collected ' . $oracle['gc']['garbage'] . '/' . $oracle['gc']['total'] . " nodes\n",
                $restored->garbageCollectText()
            );
            $t->same($oracle['afterGc']['entries'], quadrableQuadbRawSnapshotHex($restored->lmdbRawEntrySnapshot()));

            $reopened = QuadbStore::open($restoreDir);
            $t->same($values['head'], $reopened->currentHeadName());
            $t->same($oracle['secondMergedRootHex'], $reopened->status()['rootHash']);
            $t->same($values['updatedUrl'], $reopened->get($values['siteUrlKey']));
            $t->same($values['originalUrl'], $reopened->get($values['homeKey']));
            $t->same($values['postValue'], $reopened->get($values['postKey']));
            $t->same($oracle['afterGc']['entries'], quadrableQuadbRawSnapshotHex($reopened->lmdbRawEntrySnapshot()));
        } finally {
            quadrableQuadbRemoveDir($restoreDir);
        }
    },
    'native quadb store matches upstream mixed raw-restored mergeProof LMDB cursor oracle' => static function (TestRunner $t): void {
        $restoreDir = quadrableQuadbTempDir();

        try {
            $oraclePath = dirname(__DIR__) . '/fixtures/upstream-lmdb-mixed-raw-restored-merge-oracle.json';
            $oracle = json_decode((string) file_get_contents($oraclePath), true, flags: JSON_THROW_ON_ERROR);
            if (!is_array($oracle)
                || !isset(
                    $oracle['fixtureValues'],
                    $oracle['roots'],
                    $oracle['beforeUpdate']['entries'],
                    $oracle['afterUpdateBeforeMerge']['entries'],
                    $oracle['afterMergeBeforeGc']['entries'],
                    $oracle['afterGc']['entries'],
                    $oracle['gc'],
                    $oracle['updatedRootHex'],
                    $oracle['mergedRootHex']
                )
                || !is_array($oracle['fixtureValues'])
                || !is_array($oracle['roots'])
                || !is_array($oracle['beforeUpdate']['entries'])
                || !is_array($oracle['afterUpdateBeforeMerge']['entries'])
                || !is_array($oracle['afterMergeBeforeGc']['entries'])
                || !is_array($oracle['afterGc']['entries'])
                || !is_array($oracle['gc'])
            ) {
                throw new RuntimeException('malformed upstream mixed raw-restored mergeProof oracle fixture');
            }

            $values = $oracle['fixtureValues'];
            foreach ([
                'binaryKeyHex',
                'binaryValueHex',
                'delegatedValueHex',
                'detachedValueHex',
                'detachedMergedValueHex',
                'privateDelegatedValueHex',
            ] as $key) {
                if (!isset($values[$key]) || !is_string($values[$key])) {
                    throw new RuntimeException('malformed upstream mixed raw-restored mergeProof fixture value');
                }
            }

            $binaryKey = quadrableQuadbOracleBytes($values['binaryKeyHex']);
            $binaryValue = quadrableQuadbOracleBytes($values['binaryValueHex']);
            $delegatedValue = quadrableQuadbOracleBytes($values['delegatedValueHex']);
            $detachedValue = quadrableQuadbOracleBytes($values['detachedValueHex']);
            $detachedMergedValue = quadrableQuadbOracleBytes($values['detachedMergedValueHex']);
            $privateDelegatedValue = quadrableQuadbOracleBytes($values['privateDelegatedValueHex']);

            $restored = QuadbStore::restoreRawEntryDump($restoreDir, $oracle['beforeUpdate']['entries']);
            $t->same($oracle['beforeUpdate']['entries'], quadrableQuadbRawSnapshotHex($restored->lmdbRawEntrySnapshot()));
            $t->true($restored->isDetachedHead());
            $t->same(null, $restored->currentHeadName());
            $t->same($oracle['roots']['restoredRootHex'], $restored->status()['rootHash']);
            $t->same($detachedValue, $restored->get($binaryKey));

            $restored->put($binaryKey, $detachedMergedValue);
            $t->true($restored->isDetachedHead());
            $t->same($oracle['roots']['authoritativeUpdatedRootHex'], $restored->status()['rootHash']);
            $t->same($oracle['updatedRootHex'], $restored->status()['rootHash']);
            $t->same(
                $oracle['afterUpdateBeforeMerge']['entries'],
                quadrableQuadbRawSnapshotHex($restored->lmdbRawEntrySnapshot())
            );

            $authoritative = new SparseTree();
            $authoritative->change()
                ->put('wp_options:plain', 'plain')
                ->put($binaryKey, $detachedMergedValue)
                ->put('wp_posts:1', 'Published post')
                ->put('wp_postmeta:1:_thumbnail_id', '42')
                ->apply();

            $t->same($oracle['updatedRootHex'], $authoritative->rootHash());
            $postProofBytes = $authoritative->exportProof(['wp_posts:1'])
                ->encode(Proof::ENCODING_FULL_KEYS);

            $t->same($oracle['mergedRootHex'], $restored->mergeProofBytes($postProofBytes));
            $t->true($restored->isDetachedHead());
            $t->same(
                $oracle['afterMergeBeforeGc']['entries'],
                quadrableQuadbRawSnapshotHex($restored->lmdbRawEntrySnapshot())
            );
            $t->same($detachedMergedValue, $restored->get($binaryKey));
            $t->same('Published post', $restored->get('wp_posts:1'));

            $t->same(
                'Collected ' . $oracle['gc']['garbage'] . '/' . $oracle['gc']['total'] . " nodes\n",
                $restored->garbageCollectText()
            );
            $t->same($oracle['afterGc']['entries'], quadrableQuadbRawSnapshotHex($restored->lmdbRawEntrySnapshot()));

            $reopened = QuadbStore::open($restoreDir);
            $t->true($reopened->isDetachedHead());
            $t->same(null, $reopened->currentHeadName());
            $t->same($oracle['mergedRootHex'], $reopened->status()['rootHash']);
            $t->same($detachedMergedValue, $reopened->get($binaryKey));
            $t->same('Published post', $reopened->get('wp_posts:1'));
            $t->same($oracle['afterGc']['entries'], quadrableQuadbRawSnapshotHex($reopened->lmdbRawEntrySnapshot()));

            $reopened->checkout('binary-proof');
            $t->same($delegatedValue, $reopened->get($binaryKey));
            $reopened->checkout('private-proof');
            $t->same($privateDelegatedValue, $reopened->get('wp_options:private'));
            $reopened->checkout('master');
            $t->same($binaryValue, $reopened->get($binaryKey));
            $t->same('plain', $reopened->get('wp_options:plain'));
        } finally {
            quadrableQuadbRemoveDir($restoreDir);
        }
    },
    'native quadb store matches upstream mixed named raw-restored mergeProof LMDB cursor oracle' => static function (TestRunner $t): void {
        $restoreDir = quadrableQuadbTempDir();

        try {
            $oraclePath = dirname(__DIR__) . '/fixtures/upstream-lmdb-mixed-named-raw-restored-merge-oracle.json';
            $oracle = json_decode((string) file_get_contents($oraclePath), true, flags: JSON_THROW_ON_ERROR);
            if (!is_array($oracle)
                || !isset(
                    $oracle['fixtureValues'],
                    $oracle['roots'],
                    $oracle['beforeUpdate']['entries'],
                    $oracle['afterUpdateBeforeMerge']['entries'],
                    $oracle['afterMergeBeforeGc']['entries'],
                    $oracle['afterGc']['entries'],
                    $oracle['gc'],
                    $oracle['updatedRootHex'],
                    $oracle['mergedRootHex']
                )
                || !is_array($oracle['fixtureValues'])
                || !is_array($oracle['roots'])
                || !is_array($oracle['beforeUpdate']['entries'])
                || !is_array($oracle['afterUpdateBeforeMerge']['entries'])
                || !is_array($oracle['afterMergeBeforeGc']['entries'])
                || !is_array($oracle['afterGc']['entries'])
                || !is_array($oracle['gc'])
            ) {
                throw new RuntimeException('malformed upstream mixed named raw-restored mergeProof oracle fixture');
            }

            $values = $oracle['fixtureValues'];
            foreach ([
                'binaryKeyHex',
                'binaryValueHex',
                'delegatedValueHex',
                'detachedMergedValueHex',
                'privateDelegatedValueHex',
            ] as $key) {
                if (!isset($values[$key]) || !is_string($values[$key])) {
                    throw new RuntimeException('malformed upstream mixed named raw-restored mergeProof fixture value');
                }
            }

            $binaryKey = quadrableQuadbOracleBytes($values['binaryKeyHex']);
            $binaryValue = quadrableQuadbOracleBytes($values['binaryValueHex']);
            $delegatedValue = quadrableQuadbOracleBytes($values['delegatedValueHex']);
            $detachedMergedValue = quadrableQuadbOracleBytes($values['detachedMergedValueHex']);
            $privateDelegatedValue = quadrableQuadbOracleBytes($values['privateDelegatedValueHex']);

            $restored = QuadbStore::restoreRawEntryDump($restoreDir, $oracle['beforeUpdate']['entries']);
            $t->same($oracle['beforeUpdate']['entries'], quadrableQuadbRawSnapshotHex($restored->lmdbRawEntrySnapshot()));
            $t->same(false, $restored->isDetachedHead());
            $t->same('binary-proof', $restored->currentHeadName());
            $t->same($oracle['roots']['restoredRootHex'], $restored->status()['rootHash']);
            $t->same($delegatedValue, $restored->get($binaryKey));

            $restored->put($binaryKey, $detachedMergedValue);
            $t->same(false, $restored->isDetachedHead());
            $t->same($oracle['roots']['authoritativeUpdatedRootHex'], $restored->status()['rootHash']);
            $t->same($oracle['updatedRootHex'], $restored->status()['rootHash']);
            $t->same(
                $oracle['afterUpdateBeforeMerge']['entries'],
                quadrableQuadbRawSnapshotHex($restored->lmdbRawEntrySnapshot())
            );

            $authoritative = new SparseTree();
            $authoritative->change()
                ->put('wp_options:plain', 'plain')
                ->put($binaryKey, $detachedMergedValue)
                ->put('wp_posts:1', 'Published post')
                ->put('wp_postmeta:1:_thumbnail_id', '42')
                ->apply();

            $t->same($oracle['updatedRootHex'], $authoritative->rootHash());
            $postProofBytes = $authoritative->exportProof(['wp_posts:1'])
                ->encode(Proof::ENCODING_FULL_KEYS);

            $t->same($oracle['mergedRootHex'], $restored->mergeProofBytes($postProofBytes));
            $t->same(false, $restored->isDetachedHead());
            $t->same(
                $oracle['afterMergeBeforeGc']['entries'],
                quadrableQuadbRawSnapshotHex($restored->lmdbRawEntrySnapshot())
            );
            $t->same($detachedMergedValue, $restored->get($binaryKey));
            $t->same('Published post', $restored->get('wp_posts:1'));

            $t->same(
                'Collected ' . $oracle['gc']['garbage'] . '/' . $oracle['gc']['total'] . " nodes\n",
                $restored->garbageCollectText()
            );
            $t->same($oracle['afterGc']['entries'], quadrableQuadbRawSnapshotHex($restored->lmdbRawEntrySnapshot()));

            $reopened = QuadbStore::open($restoreDir);
            $t->same(false, $reopened->isDetachedHead());
            $t->same('binary-proof', $reopened->currentHeadName());
            $t->same($oracle['mergedRootHex'], $reopened->status()['rootHash']);
            $t->same($detachedMergedValue, $reopened->get($binaryKey));
            $t->same('Published post', $reopened->get('wp_posts:1'));
            $t->same($oracle['afterGc']['entries'], quadrableQuadbRawSnapshotHex($reopened->lmdbRawEntrySnapshot()));

            $reopened->checkout('private-proof');
            $t->same($privateDelegatedValue, $reopened->get('wp_options:private'));
            $reopened->checkout('master');
            $t->same($binaryValue, $reopened->get($binaryKey));
            $t->same('plain', $reopened->get('wp_options:plain'));
        } finally {
            quadrableQuadbRemoveDir($restoreDir);
        }
    },
    'native quadb store matches upstream mixed noTrack raw-restored mergeProof LMDB cursor oracle' => static function (TestRunner $t): void {
        $restoreDir = quadrableQuadbTempDir();

        try {
            $oraclePath = dirname(__DIR__) . '/fixtures/upstream-lmdb-mixed-notrack-raw-restored-merge-oracle.json';
            $oracle = json_decode((string) file_get_contents($oraclePath), true, flags: JSON_THROW_ON_ERROR);
            if (!is_array($oracle)
                || !isset(
                    $oracle['fixtureValues'],
                    $oracle['roots'],
                    $oracle['beforeUpdate']['entries'],
                    $oracle['afterUpdateBeforeMerge']['entries'],
                    $oracle['afterMergeBeforeGc']['entries'],
                    $oracle['afterGc']['entries'],
                    $oracle['gc'],
                    $oracle['updatedRootHex'],
                    $oracle['mergedRootHex']
                )
                || !is_array($oracle['fixtureValues'])
                || !is_array($oracle['roots'])
                || !is_array($oracle['beforeUpdate']['entries'])
                || !is_array($oracle['afterUpdateBeforeMerge']['entries'])
                || !is_array($oracle['afterMergeBeforeGc']['entries'])
                || !is_array($oracle['afterGc']['entries'])
                || !is_array($oracle['gc'])
            ) {
                throw new RuntimeException('malformed upstream mixed noTrack raw-restored mergeProof oracle fixture');
            }

            $values = $oracle['fixtureValues'];
            foreach ([
                'binaryKeyHex',
                'binaryValueHex',
                'delegatedValueHex',
                'privateValueHex',
                'privatePostValueHex',
                'privateDelegatedValueHex',
                'privateMergedValueHex',
            ] as $key) {
                if (!isset($values[$key]) || !is_string($values[$key])) {
                    throw new RuntimeException('malformed upstream mixed noTrack raw-restored mergeProof fixture value');
                }
            }

            $binaryKey = quadrableQuadbOracleBytes($values['binaryKeyHex']);
            $binaryValue = quadrableQuadbOracleBytes($values['binaryValueHex']);
            $delegatedValue = quadrableQuadbOracleBytes($values['delegatedValueHex']);
            $privateValue = quadrableQuadbOracleBytes($values['privateValueHex']);
            $privatePostValue = quadrableQuadbOracleBytes($values['privatePostValueHex']);
            $privateDelegatedValue = quadrableQuadbOracleBytes($values['privateDelegatedValueHex']);
            $privateMergedValue = quadrableQuadbOracleBytes($values['privateMergedValueHex']);
            $privateKeyHex = bin2hex('wp_options:private');

            $restored = QuadbStore::restoreRawEntryDump($restoreDir, $oracle['beforeUpdate']['entries']);
            $t->same($oracle['beforeUpdate']['entries'], quadrableQuadbRawSnapshotHex($restored->lmdbRawEntrySnapshot()));
            $t->same(false, $restored->isDetachedHead());
            $t->same('private-proof', $restored->currentHeadName());
            $t->same($oracle['roots']['restoredRootHex'], $restored->status()['rootHash']);
            $t->same($privateDelegatedValue, $restored->get('wp_options:private'));
            $t->same(10, count($restored->lmdbRawEntrySnapshot()['quadrable_key']));
            $t->same(false, in_array(
                $privateKeyHex,
                array_column($oracle['beforeUpdate']['entries']['quadrable_key'], 'valueHex'),
                true
            ));

            $restored->put('wp_options:private', $privateMergedValue);
            $t->same(false, $restored->isDetachedHead());
            $t->same($oracle['roots']['authoritativeUpdatedRootHex'], $restored->status()['rootHash']);
            $t->same($oracle['updatedRootHex'], $restored->status()['rootHash']);
            $t->same(
                $oracle['afterUpdateBeforeMerge']['entries'],
                quadrableQuadbRawSnapshotHex($restored->lmdbRawEntrySnapshot())
            );
            $t->same(10, count($restored->lmdbRawEntrySnapshot()['quadrable_key']));
            $t->same(false, in_array(
                $privateKeyHex,
                array_column($oracle['afterUpdateBeforeMerge']['entries']['quadrable_key'], 'valueHex'),
                true
            ));

            $authoritative = new SparseTree();
            $authoritative->change()
                ->put('wp_options:private', $privateMergedValue)
                ->put('wp_posts:private', $privatePostValue)
                ->apply();

            $t->same($oracle['updatedRootHex'], $authoritative->rootHash());
            $privatePostProofBytes = $authoritative->exportProof(['wp_posts:private'])->encode();

            $t->same($oracle['mergedRootHex'], $restored->mergeProofBytes($privatePostProofBytes));
            $t->same(false, $restored->isDetachedHead());
            $t->same(
                $oracle['afterMergeBeforeGc']['entries'],
                quadrableQuadbRawSnapshotHex($restored->lmdbRawEntrySnapshot())
            );
            $t->same(10, count($restored->lmdbRawEntrySnapshot()['quadrable_key']));
            $t->same($privateMergedValue, $restored->get('wp_options:private'));
            $t->same($privatePostValue, $restored->get('wp_posts:private'));
            $t->throws(RuntimeException::class, static fn () => $restored->exportProofBytes(
                ['wp_options:private'],
                Proof::ENCODING_FULL_KEYS
            ));

            $t->same(
                'Collected ' . $oracle['gc']['garbage'] . '/' . $oracle['gc']['total'] . " nodes\n",
                $restored->garbageCollectText()
            );
            $t->same($oracle['afterGc']['entries'], quadrableQuadbRawSnapshotHex($restored->lmdbRawEntrySnapshot()));
            $t->same(10, count($restored->lmdbRawEntrySnapshot()['quadrable_key']));
            $t->same(false, in_array(
                $privateKeyHex,
                array_column($oracle['afterGc']['entries']['quadrable_key'], 'valueHex'),
                true
            ));

            $reopened = QuadbStore::open($restoreDir);
            $t->same(false, $reopened->isDetachedHead());
            $t->same('private-proof', $reopened->currentHeadName());
            $t->same($oracle['mergedRootHex'], $reopened->status()['rootHash']);
            $t->same($privateMergedValue, $reopened->get('wp_options:private'));
            $t->same($privatePostValue, $reopened->get('wp_posts:private'));
            $t->same($oracle['afterGc']['entries'], quadrableQuadbRawSnapshotHex($reopened->lmdbRawEntrySnapshot()));

            $reopened->checkout('private-full');
            $t->same($privateValue, $reopened->get('wp_options:private'));
            $reopened->checkout('binary-proof');
            $t->same($delegatedValue, $reopened->get($binaryKey));
            $reopened->checkout('master');
            $t->same($binaryValue, $reopened->get($binaryKey));
            $t->same('plain', $reopened->get('wp_options:plain'));
        } finally {
            quadrableQuadbRemoveDir($restoreDir);
        }
    },
    'native quadb store restores portable dumps for mixed full proof detached and noTrack heads' => static function (TestRunner $t): void {
        $dir = quadrableQuadbTempDir();
        $restoreDir = quadrableQuadbTempDir();
        $corruptRestoreDir = quadrableQuadbTempDir();
        $corruptDigestRestoreDir = quadrableQuadbTempDir();
        $noTrackDir = quadrableQuadbTempDir();
        $noTrackRestoreDir = quadrableQuadbTempDir();

        try {
            $repo = QuadbStore::init($dir);
            $repo->importLines(
                "wp_options:siteurl|https://example.test\n"
                . "wp_options:home|https://example.test\n"
                . "wp_posts:1|Published post\n"
                . "wp_posts:2|Second post\n",
                '|'
            );
            $masterRoot = $repo->tree()->rootHash();
            $proofHex = $repo->exportProofHex([
                'wp_options:siteurl',
                'wp_posts:1',
            ], Proof::ENCODING_FULL_KEYS);

            $repo->fork('wp-preview', 'master');
            $repo->put('wp_posts:1', 'Preview post');
            $previewRoot = $repo->tree()->rootHash();

            $repo->checkout('delegated-proof');
            $repo->importProofHex($proofHex, $masterRoot);
            $repo->put('wp_options:siteurl', 'https://delegated.example.test');
            $delegatedRoot = $repo->status()['rootHash'];

            $repo->checkout();
            $repo->importProofHex($proofHex, $masterRoot);
            $repo->put('wp_posts:1', 'Detached delegated post');
            $detachedRoot = $repo->status()['rootHash'];

            $dump = $repo->exportPortableDump();
            $t->same('quadrable-quadb-portable-dump', $dump['format']);
            $t->true($dump['trackKeys']);
            $t->true($dump['current']['detached']);
            $t->same($detachedRoot, $dump['current']['rootHash']);
            $t->same($dump['rawEntries'], quadrableQuadbRawSnapshotHex($repo->lmdbRawEntrySnapshot()));
            $t->same($dump['rawEntryDigest'], QuadbStore::portableRawEntryDigest($dump['rawEntries']));
            $t->same(64, strlen($dump['rawEntryDigest']));
            $t->true(count($dump['rawEntries']['quadrable_head']) >= 3);
            $t->true(count($dump['rawEntries']['quadrable_key']) >= 2);

            $restored = QuadbStore::restorePortableDump($restoreDir, $dump);
            $t->same($dump['rawEntries'], quadrableQuadbRawSnapshotHex($restored->lmdbRawEntrySnapshot()));
            $t->same($dump['current'], $restored->status());
            $t->same('Detached delegated post', $restored->get('wp_posts:1'));
            $t->throws(RuntimeException::class, static fn () => $restored->get('wp_options:home'));

            $restored->checkout('delegated-proof');
            $t->same($delegatedRoot, $restored->status()['rootHash']);
            $t->same('https://delegated.example.test', $restored->get('wp_options:siteurl'));
            $t->same('Published post', $restored->get('wp_posts:1'));

            $restored->checkout('wp-preview');
            $t->same($previewRoot, $restored->status()['rootHash']);
            $t->same('Preview post', $restored->get('wp_posts:1'));

            $restored->checkout('master');
            $t->same($masterRoot, $restored->status()['rootHash']);
            $t->same('Published post', $restored->get('wp_posts:1'));
            $t->same('https://example.test', $restored->get('wp_options:home'));

            $corrupt = $dump;
            $corrupt['rawEntries']['quadrable_quadb_state'][0]['valueHex'] = '00';
            $t->throws(RuntimeException::class, static fn () => QuadbStore::restorePortableDump($corruptRestoreDir, $corrupt));

            $corruptDigest = $dump;
            $corruptDigest['rawEntryDigest'] = str_repeat('0', 64);
            $t->throws(RuntimeException::class, static fn () => QuadbStore::restorePortableDump($corruptDigestRestoreDir, $corruptDigest));

            $private = QuadbStore::init($noTrackDir, false);
            $private->put('wp_options:private', "secret\0value");
            $private->put('wp_posts:1', 'Private post');
            $privateRoot = $private->tree()->rootHash();
            $privateProofHex = $private->exportProofHex([
                'wp_options:private',
                'wp_posts:404',
            ]);

            $private->checkout('private-proof');
            $private->importProofHex($privateProofHex, $privateRoot);
            $private->put('wp_options:private', "delegated\0secret");

            $privateDump = $private->exportPortableDump();
            $t->same(false, $privateDump['trackKeys']);
            $t->same([], $privateDump['rawEntries']['quadrable_key']);

            $privateRestored = QuadbStore::restorePortableDump($noTrackRestoreDir, $privateDump);
            $t->same($privateDump['rawEntries'], quadrableQuadbRawSnapshotHex($privateRestored->lmdbRawEntrySnapshot()));
            $t->same("delegated\0secret", $privateRestored->get('wp_options:private'));
            $t->same([], $privateRestored->lmdbRawEntrySnapshot()['quadrable_key']);
            $t->throws(RuntimeException::class, static fn () => $privateRestored->exportProofBytes(
                ['wp_options:private'],
                Proof::ENCODING_FULL_KEYS
            ));
        } finally {
            quadrableQuadbRemoveDir($dir);
            quadrableQuadbRemoveDir($restoreDir);
            quadrableQuadbRemoveDir($corruptRestoreDir);
            quadrableQuadbRemoveDir($corruptDigestRestoreDir);
            quadrableQuadbRemoveDir($noTrackDir);
            quadrableQuadbRemoveDir($noTrackRestoreDir);
        }
    },
    'native quadb store preserves numeric head names as LMDB string keys' => static function (TestRunner $t): void {
        $dir = quadrableQuadbTempDir();
        $proofDir = quadrableQuadbTempDir();

        try {
            $repo = QuadbStore::init($dir);
            $repo->importLines(
                "wp_options:siteurl|https://example.test\n"
                . "wp_posts:1|Published post\n",
                '|'
            );
            $masterRoot = $repo->tree()->rootHash();
            $masterHeadNodeId = $repo->tree()->headNodeId();

            $repo->fork('20260523');
            $repo->put('wp_posts:1', 'Numeric preview edit');
            $previewRoot = $repo->tree()->rootHash();
            $previewHeadNodeId = $repo->tree()->headNodeId();
            $raw = $repo->lmdbRawEntrySnapshot();

            $t->same('20260523', $repo->currentHeadName());
            $t->same([
                [
                    'key' => 'currHead',
                    'value' => '20260523',
                ],
            ], $raw['quadrable_quadb_state']);
            $t->same(
                [
                    '20260523' => quadrableQuadbPackUint64Le($previewHeadNodeId),
                    'master' => quadrableQuadbPackUint64Le($masterHeadNodeId),
                ],
                quadrableQuadbRawStringEntriesByKey($raw['quadrable_head'])
            );

            $reopened = QuadbStore::open($dir);
            $t->same('20260523', $reopened->currentHeadName());
            $t->same('Numeric preview edit', $reopened->get('wp_posts:1'));
            $t->same([
                "=> 20260523 : 0x{$previewRoot} ({$previewHeadNodeId})",
                "   master : 0x{$masterRoot} ({$masterHeadNodeId})",
            ], quadrableQuadbOutputLines($reopened->headText()));

            $reopened->checkout('master');
            $sourceProofHex = $reopened->exportProofHex(
                ['wp_options:siteurl'],
                Proof::ENCODING_FULL_KEYS
            );
            $proofRepo = QuadbStore::init($proofDir);
            $proofRepo->checkout('404');
            $proofRepo->importProofHex($sourceProofHex, $masterRoot);

            $proofRaw = $proofRepo->lmdbRawEntrySnapshot();
            $t->same('404', $proofRepo->currentHeadName());
            $t->same('404', $proofRaw['quadrable_head'][0]['key']);
            $t->same([
                [
                    'key' => 'currHead',
                    'value' => '404',
                ],
            ], $proofRaw['quadrable_quadb_state']);

            $proofReopened = QuadbStore::open($proofDir);
            $t->same('404', $proofReopened->currentHeadName());
            $t->same('https://example.test', $proofReopened->get('wp_options:siteurl'));
            $t->contains("=> 404 : 0x{$masterRoot} (", $proofReopened->headText());
        } finally {
            quadrableQuadbRemoveDir($dir);
            quadrableQuadbRemoveDir($proofDir);
        }
    },
    'native quadb store exposes proof-backed LMDB bucket layout like upstream importProof' => static function (TestRunner $t): void {
        $sourceDir = quadrableQuadbTempDir();
        $targetDir = quadrableQuadbTempDir();

        try {
            $source = QuadbStore::init($sourceDir);
            $source->importLines(
                "wp_options:siteurl|https://example.test\n"
                . "wp_options:home|https://example.test\n"
                . "wp_posts:1|Published post\n",
                '|'
            );

            $trustedRoot = $source->tree()->rootHash();
            $proofHex = $source->exportProofHex([
                'wp_options:siteurl',
                'wp_posts:404',
            ], Proof::ENCODING_FULL_KEYS);

            $target = QuadbStore::init($targetDir);
            $target->checkout('wp-delegated-layout');
            $target->importProofHex($proofHex, $trustedRoot);

            $status = $target->status();
            $lmdb = $target->lmdbBucketSnapshot();
            $projectedHeadNodeId = quadrableQuadbUnpackUint64Le($lmdb['quadrable_head']['wp-delegated-layout']);

            $t->same('wp-delegated-layout', $lmdb['quadrable_quadb_state']['currHead']);
            $t->same($target->stats()['numBytes'], quadrableQuadbRawBucketBytes($lmdb));
            $t->true($status['headNodeId'] >= 576460752303423488);
            $t->true($projectedHeadNodeId >= 288230376151711744);
            $t->true($projectedHeadNodeId < 576460752303423488);
            $t->contains('wp_options:siteurl', implode("\n", $lmdb['quadrable_key']));

            $leafTypes = [];
            foreach ($lmdb['quadrable_nodesLeaf'] as $nodeId => $raw) {
                $t->true($nodeId > 0);
                $t->true($nodeId < 288230376151711744);

                $type = quadrableQuadbUnpackUint64Le(substr($raw, 0, 8)) % 16;
                $leafTypes[] = $type;

                if ($type === 4) {
                    $t->true(strlen($raw) > 72);
                    $t->same(64, strlen(bin2hex(substr($raw, 8, 32))));
                    $t->same(64, strlen(bin2hex(substr($raw, 40, 32))));
                } elseif ($type === 6) {
                    $t->same(104, strlen($raw));
                    $t->same(64, strlen(bin2hex(substr($raw, 72, 32))));
                }
            }

            $interiorTypes = [];
            foreach ($lmdb['quadrable_nodesInterior'] as $nodeId => $raw) {
                $t->true($nodeId >= 288230376151711744);
                $t->true($nodeId < 576460752303423488);
                $t->same(48, strlen($raw));

                $word = quadrableQuadbUnpackUint64Le(substr($raw, 0, 8));
                $type = $word % 16;
                $interiorTypes[] = $type;

                if ($type === 5) {
                    $t->same(0, quadrableQuadbUnpackUint64Le(substr($raw, 40, 8)));
                } else {
                    $t->true(in_array($type, [1, 2, 3], true));
                }
            }

            $t->true(in_array(4, $leafTypes, true));
            $t->true(in_array(6, $leafTypes, true));
            $t->true(in_array(5, $interiorTypes, true));
            $t->true(count(array_intersect([1, 2, 3], $interiorTypes)) > 0);
            $t->same($lmdb, QuadbStore::open($targetDir)->lmdbBucketSnapshot());
        } finally {
            quadrableQuadbRemoveDir($sourceDir);
            quadrableQuadbRemoveDir($targetDir);
        }
    },
    'native quadb store projects independent proof imports in upstream LMDB allocation order' => static function (TestRunner $t): void {
        $sourceDir = quadrableQuadbTempDir();
        $targetDir = quadrableQuadbTempDir();

        try {
            $source = QuadbStore::init($sourceDir);
            $source->importLines(
                "wp_options:siteurl|https://example.test\n"
                . "wp_options:home|https://example.test\n"
                . "wp_posts:1|Published post\n",
                '|'
            );

            $trustedRoot = $source->tree()->rootHash();
            $proofHex = $source->exportProofHex([
                'wp_options:siteurl',
                'wp_posts:404',
            ], Proof::ENCODING_FULL_KEYS);

            $target = QuadbStore::init($targetDir);
            $target->checkout('z-first-import');
            $target->importProofHex($proofHex, $trustedRoot);
            $target->checkout('a-second-import');
            $target->importProofHex($proofHex, $trustedRoot);
            $target->checkout('z-first-import');
            $target->fork('z-first-fork');

            $lmdb = $target->lmdbBucketSnapshot();
            $heads = [];
            foreach ($lmdb['quadrable_head'] as $head => $rawNodeId) {
                $heads[$head] = quadrableQuadbUnpackUint64Le($rawNodeId);
            }

            $t->true($heads['z-first-import'] < $heads['a-second-import']);
            $t->same($heads['z-first-import'], $heads['z-first-fork']);
            $t->true($heads['a-second-import'] !== $heads['z-first-import']);

            $leafIds = array_keys($lmdb['quadrable_nodesLeaf']);
            $interiorIds = array_keys($lmdb['quadrable_nodesInterior']);
            $t->same(range(min($leafIds), max($leafIds)), $leafIds);
            $t->same(range(min($interiorIds), max($interiorIds)), $interiorIds);
            $t->same($lmdb, QuadbStore::open($targetDir)->lmdbBucketSnapshot());
        } finally {
            quadrableQuadbRemoveDir($sourceDir);
            quadrableQuadbRemoveDir($targetDir);
        }
    },
    'native quadb store dumps full and proof-backed trees like quadb dumpTree' => static function (TestRunner $t): void {
        $sourceDir = quadrableQuadbTempDir();
        $targetDir = quadrableQuadbTempDir();
        $integerDir = quadrableQuadbTempDir();

        try {
            $source = QuadbStore::init($sourceDir);
            $t->same(
                "-----------------\n"
                . '0x00000000... (0) empty' . "\n"
                . "-----------------\n",
                $source->dumpTreeText()
            );

            $source->importLines(
                "wp_options:siteurl|https://example.test\n"
                . "wp_options:home|https://example.test\n"
                . "wp_posts:1|Published post\n",
                '|'
            );

            $t->same(
                "-----------------\n"
                . "0x34dfb816... (288230376151711745) branch:\n"
                . "  0x3025435e... (288230376151711744) branch:\n"
                . "    0xa4da3a8b... (1) leaf: wp_posts:1 = Published post\n"
                . "    0xa1e166a4... (2) leaf: wp_options:home = https://example.test\n"
                . "  0x2c115121... (3) leaf: wp_options:siteurl = https://example.test\n"
                . "-----------------\n",
                $source->dumpTreeText()
            );
            $t->same($source->dumpTreeText(), QuadbStore::open($sourceDir)->dumpTreeText());

            $trustedRoot = $source->tree()->rootHash();
            $proofHex = $source->exportProofHex([
                'wp_options:siteurl',
                'wp_posts:404',
            ], Proof::ENCODING_FULL_KEYS);

            $target = QuadbStore::init($targetDir);
            $target->checkout('wp-delegated-dump');
            $target->importProofHex($proofHex, $trustedRoot);
            $partialDump = $target->dumpTreeText();

            $t->contains("0x34dfb816... (576460752303423492) branch:\n", $partialDump);
            $t->contains("witness\n", $partialDump);
            $t->contains(
                "witness leaf: 0x7b52fb0f1f4a77fb1dc7cb8188132a04f7b57e0b54f41cbdd20df89c098ef985 hash(val) = 0x0a62a7127118b2347eea44eb95cd06211ded305b934d459bf64f3ac9db5038d1\n",
                $partialDump
            );
            $t->contains("leaf: wp_options:siteurl = https://example.test\n", $partialDump);
            $t->same($partialDump, QuadbStore::open($targetDir)->dumpTreeText());

            $integer = QuadbStore::init($integerDir);
            $integer->importIntegerLines(
                "1,wp_options:siteurl=https://example.test\n"
                . "3,wp_posts:1=Hello\n"
            );
            $integerDump = $integer->dumpTreeText();

            $t->contains('leaf: H(?)=0x020000000000... = wp_options:siteurl=https://example.test', $integerDump);
            $t->contains('leaf: H(?)=0x050000000000... = wp_posts:1=Hello', $integerDump);
            $t->contains("0x00000000... (0) empty\n", $integerDump);
        } finally {
            quadrableQuadbRemoveDir($sourceDir);
            quadrableQuadbRemoveDir($targetDir);
            quadrableQuadbRemoveDir($integerDir);
        }
    },
    'native quadb store removes named and current heads like quadb head rm' => static function (TestRunner $t): void {
        $dir = quadrableQuadbTempDir();

        try {
            $repo = QuadbStore::init($dir);
            $repo->importLines(
                "wp_options:siteurl|https://example.test\n"
                . "wp_posts:1|Published post\n",
                '|'
            );
            $masterRoot = $repo->tree()->rootHash();
            $masterHeadNodeId = $repo->tree()->headNodeId();

            $repo->fork('wp-preview');
            $repo->put('wp_posts:1', 'Preview edit');
            $previewRoot = $repo->tree()->rootHash();
            $previewHeadNodeId = $repo->tree()->headNodeId();

            $repo->fork('wp-throwaway', 'master');
            $repo->put('wp_posts:2', 'Throwaway preview');
            $repo->removeHead('wp-throwaway');
            $repo->checkout('wp-preview');

            $t->same([
                "=> wp-preview : 0x{$previewRoot} ({$previewHeadNodeId})",
                "   master : 0x{$masterRoot} ({$masterHeadNodeId})",
            ], quadrableQuadbOutputLines($repo->headText()));

            $repo->removeHead();
            $t->same('wp-preview', $repo->currentHeadName());
            $t->same(HashTree::EMPTY_HASH, $repo->tree()->rootHash());
            $t->same("Head: wp-preview\nRoot: 0x" . HashTree::EMPTY_HASH . " (0)\n", $repo->statusText());
            $t->same([
                "   master : 0x{$masterRoot} ({$masterHeadNodeId})",
            ], quadrableQuadbOutputLines($repo->headText()));

            $repo->put('wp_posts:3', 'Recreated preview head');
            $recreatedRoot = $repo->tree()->rootHash();
            $recreatedHeadNodeId = $repo->tree()->headNodeId();

            $t->same([
                "   master : 0x{$masterRoot} ({$masterHeadNodeId})",
                "=> wp-preview : 0x{$recreatedRoot} ({$recreatedHeadNodeId})",
            ], quadrableQuadbOutputLines(QuadbStore::open($dir)->headText()));
        } finally {
            quadrableQuadbRemoveDir($dir);
        }
    },
    'native quadb store head rm resets detached head to an empty tree' => static function (TestRunner $t): void {
        $dir = quadrableQuadbTempDir();

        try {
            $repo = QuadbStore::init($dir);
            $repo->importLines("wp_posts:1|Published post\n", '|');
            $masterRoot = $repo->tree()->rootHash();
            $masterHeadNodeId = $repo->tree()->headNodeId();

            $detached = $repo->fork();
            $t->true($repo->isDetachedHead());
            $t->same($masterRoot, $detached->rootHash());
            $t->same([
                "D> [detached] : 0x{$masterRoot} ({$masterHeadNodeId})",
                "   master : 0x{$masterRoot} ({$masterHeadNodeId})",
            ], quadrableQuadbOutputLines($repo->headText()));

            $repo->removeHead();
            $t->true($repo->isDetachedHead());
            $t->same(HashTree::EMPTY_HASH, $repo->tree()->rootHash());
            $t->same([
                'D> [detached] : 0x' . HashTree::EMPTY_HASH . ' (0)',
                "   master : 0x{$masterRoot} ({$masterHeadNodeId})",
            ], quadrableQuadbOutputLines(QuadbStore::open($dir)->headText()));
        } finally {
            quadrableQuadbRemoveDir($dir);
        }
    },
    'native quadb store garbage collects discarded full heads like quadb gc' => static function (TestRunner $t): void {
        $dir = quadrableQuadbTempDir();
        $detachedDir = quadrableQuadbTempDir();

        try {
            $repo = QuadbStore::init($dir);
            $repo->importLines(
                "wp_options:siteurl|https://example.test\n"
                . "wp_posts:1|Published post\n",
                '|'
            );
            $masterRoot = $repo->tree()->rootHash();

            $repo->fork('preview-discard');
            $repo->put('wp_posts:1', 'Discarded preview edit');

            $repo->fork('preview-approved', 'master');
            $repo->put('wp_posts:2', 'Approved page');
            $approvedRoot = $repo->tree()->rootHash();

            $repo->removeHead('preview-discard');
            $storedBeforeGc = quadrableQuadbStoredNodeCount($repo);
            $gc = quadrableQuadbParseGcText($repo->garbageCollectText());
            $storedAfterGc = quadrableQuadbStoredNodeCount($repo);

            $t->same($storedBeforeGc, $gc['total']);
            $t->true($gc['garbage'] > 0);
            $t->same($storedBeforeGc - $gc['garbage'], $storedAfterGc);
            $t->same('preview-approved', $repo->currentHeadName());
            $t->same($approvedRoot, $repo->tree()->rootHash());
            $t->same('Approved page', $repo->get('wp_posts:2'));
            $t->same($masterRoot, $repo->checkout('master')->rootHash());
            $t->same('Published post', $repo->get('wp_posts:1'));

            $reopened = QuadbStore::open($dir);
            $t->same("Collected 0/{$storedAfterGc} nodes\n", $reopened->garbageCollectText());
            $t->same($storedAfterGc, quadrableQuadbStoredNodeCount($reopened));

            $detachedRepo = QuadbStore::init($detachedDir);
            $detachedRepo->importLines(
                "wp_options:siteurl|https://example.test\n"
                . "wp_posts:1|Published post\n",
                '|'
            );
            $detached = $detachedRepo->fork();
            $detached->put('wp_posts:1', 'Detached preview edit');
            $detached->put('wp_posts:2', 'Detached only page');
            $detachedRepo->save($detached);
            $detachedRepo->removeHead('master');

            $detachedBeforeGc = quadrableQuadbStoredNodeCount($detachedRepo);
            $detachedGc = quadrableQuadbParseGcText($detachedRepo->garbageCollectText());
            $detachedAfterGc = quadrableQuadbStoredNodeCount($detachedRepo);

            $t->same($detachedBeforeGc, $detachedGc['total']);
            $t->true($detachedGc['garbage'] > 0);
            $t->same($detachedBeforeGc - $detachedGc['garbage'], $detachedAfterGc);
            $t->true($detachedRepo->isDetachedHead());
            $t->same('Detached preview edit', $detachedRepo->get('wp_posts:1'));
            $t->same('Detached only page', $detachedRepo->get('wp_posts:2'));
            $t->contains('D> [detached] : ', $detachedRepo->headText());
            $t->same("Collected 0/{$detachedAfterGc} nodes\n", QuadbStore::open($detachedDir)->garbageCollectText());
        } finally {
            quadrableQuadbRemoveDir($dir);
            quadrableQuadbRemoveDir($detachedDir);
        }
    },
];

function quadrableQuadbTempDir(): string
{
    return sys_get_temp_dir() . '/quadrable-quadb-' . bin2hex(random_bytes(6));
}

function quadrableQuadbRemoveDir(string $dir): void
{
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
}

/**
 * @return list<string>
 */
function quadrableQuadbSortedLines(string $lines): array
{
    $trimmed = trim($lines);
    if ($trimmed === '') {
        return [];
    }

    $output = explode("\n", $trimmed);
    sort($output, SORT_STRING);

    return $output;
}

/**
 * @return list<string>
 */
function quadrableQuadbOutputLines(string $lines): array
{
    $trimmed = rtrim($lines, "\r\n");
    if ($trimmed === '') {
        return [];
    }

    return explode("\n", $trimmed);
}

function quadrableQuadbDecodeHexProof(string $proofHex): string
{
    $trimmed = trim($proofHex);
    if (!str_starts_with($trimmed, '0x')) {
        throw new InvalidArgumentException('expected 0x-prefixed proof');
    }

    $decoded = hex2bin(substr($trimmed, 2));
    if ($decoded === false) {
        throw new InvalidArgumentException('expected hexadecimal proof');
    }

    return $decoded;
}

function quadrableQuadbUnknownStringKey(string $key): string
{
    return quadrableQuadbUnknownHash((new HashTree())->keyHash($key));
}

function quadrableQuadbUnknownHash(string $hashHex): string
{
    return 'H(?)=0x' . substr($hashHex, 0, 12) . '...';
}

function quadrableQuadbCompositeSuffix(string $label): string
{
    return bin2hex(substr(Blake2s::hash($label), -23));
}

function quadrableQuadbStoredNodeCount(QuadbStore $repo): int
{
    $snapshot = $repo->nodeStore()->exportSnapshot();

    return count($snapshot['leaves']) + count($snapshot['branches']);
}

function quadrableQuadbUnpackUint64Le(string $bytes): int
{
    if (strlen($bytes) !== 8) {
        throw new RuntimeException('expected exactly eight bytes');
    }

    $parts = unpack('Vlow/Vhigh', $bytes);
    if (!is_array($parts)) {
        throw new RuntimeException('unable to unpack uint64');
    }

    return $parts['low'] + ($parts['high'] * 4294967296);
}

function quadrableQuadbPackUint64Le(int $value): string
{
    if ($value < 0) {
        throw new InvalidArgumentException('uint64 value must be non-negative');
    }

    return pack('V2', $value % 4294967296, intdiv($value, 4294967296));
}

/**
 * @param list<array{key: string, value: string}> $entries
 *
 * @return array<string, string>
 */
function quadrableQuadbRawEntriesByKeyHex(array $entries): array
{
    $indexed = [];
    foreach ($entries as $entry) {
        $indexed[bin2hex($entry['key'])] = $entry['value'];
    }

    return $indexed;
}

function quadrableQuadbOracleBytes(mixed $hex): string
{
    if (!is_string($hex) || !preg_match('/^(?:[0-9a-f]{2})*$/', $hex)) {
        throw new RuntimeException('malformed upstream oracle byte hex');
    }

    $bytes = hex2bin($hex);
    if ($bytes === false) {
        throw new RuntimeException('malformed upstream oracle byte hex');
    }

    return $bytes;
}

/**
 * @param array<string, list<array{key: string, value: string}>> $snapshot
 *
 * @return array<string, list<array{keyHex: string, valueHex: string}>>
 */
function quadrableQuadbRawSnapshotHex(array $snapshot): array
{
    $out = [];
    foreach ([
        'quadrable_head',
        'quadrable_nodesLeaf',
        'quadrable_nodesInterior',
        'quadrable_key',
        'quadrable_quadb_state',
    ] as $bucket) {
        $out[$bucket] = [];
        foreach ($snapshot[$bucket] ?? [] as $entry) {
            $out[$bucket][] = [
                'keyHex' => bin2hex($entry['key']),
                'valueHex' => bin2hex($entry['value']),
            ];
        }
    }

    return $out;
}

/**
 * Extracts a full-head-only raw cursor slice from the mixed upstream LMDB
 * oracle. The selected heads, reachable full nodes, tracked keys, and a
 * synthetic `currHead` state entry remain byte-for-byte upstream bucket values.
 *
 * @param array<string, list<array{keyHex: string, valueHex: string}>> $entries
 * @param list<string> $heads
 *
 * @return array<string, list<array{keyHex: string, valueHex: string}>>
 */
function quadrableQuadbFullHeadRawEntries(array $entries, array $heads, string $currentHead): array
{
    $headSet = array_fill_keys($heads, true);
    if (!isset($headSet[$currentHead])) {
        throw new RuntimeException('current head must be included in full-head raw entry slice');
    }

    $headNodeIds = [];
    $selectedHeads = [];
    foreach ($entries['quadrable_head'] as $entry) {
        $head = (string) hex2bin($entry['keyHex']);
        if (!isset($headSet[$head])) {
            continue;
        }

        $selectedHeads[] = $entry;
        $headNodeIds[$head] = quadrableQuadbUnpackUint64Le((string) hex2bin($entry['valueHex']));
    }
    if (count($selectedHeads) !== count($headSet)) {
        throw new RuntimeException('upstream full-head raw entry slice is missing a requested head');
    }

    $leavesById = [];
    foreach ($entries['quadrable_nodesLeaf'] as $entry) {
        $leavesById[quadrableQuadbUnpackUint64Le((string) hex2bin($entry['keyHex']))] = $entry;
    }

    $branchesById = [];
    foreach ($entries['quadrable_nodesInterior'] as $entry) {
        $branchesById[quadrableQuadbUnpackUint64Le((string) hex2bin($entry['keyHex']))] = $entry;
    }

    $reachableLeaves = [];
    $reachableBranches = [];
    $walk = static function (int $nodeId) use (&$walk, &$reachableLeaves, &$reachableBranches, $leavesById, $branchesById): void {
        if ($nodeId === 0) {
            return;
        }
        if (isset($reachableLeaves[$nodeId]) || isset($reachableBranches[$nodeId])) {
            return;
        }
        if (isset($leavesById[$nodeId])) {
            $leafValue = (string) hex2bin($leavesById[$nodeId]['valueHex']);
            if (quadrableQuadbUnpackUint64Le(substr($leafValue, 0, 8)) !== 4) {
                throw new RuntimeException('selected upstream raw slice includes a proof-backed leaf');
            }
            $reachableLeaves[$nodeId] = true;
            return;
        }
        if (!isset($branchesById[$nodeId])) {
            throw new RuntimeException('selected upstream raw slice references an unknown node');
        }

        $branchValue = (string) hex2bin($branchesById[$nodeId]['valueHex']);
        [$leftNodeId, $rightNodeId] = quadrableQuadbRawBranchChildren($branchValue);
        $reachableBranches[$nodeId] = true;
        $walk($leftNodeId);
        $walk($rightNodeId);
    };

    foreach ($headNodeIds as $nodeId) {
        $walk($nodeId);
    }

    $selectedLeaves = [];
    foreach ($entries['quadrable_nodesLeaf'] as $entry) {
        if (isset($reachableLeaves[quadrableQuadbUnpackUint64Le((string) hex2bin($entry['keyHex']))])) {
            $selectedLeaves[] = $entry;
        }
    }

    $selectedBranches = [];
    foreach ($entries['quadrable_nodesInterior'] as $entry) {
        if (isset($reachableBranches[quadrableQuadbUnpackUint64Le((string) hex2bin($entry['keyHex']))])) {
            $selectedBranches[] = $entry;
        }
    }

    $selectedKeys = [];
    foreach ($entries['quadrable_key'] as $entry) {
        if (isset($reachableLeaves[quadrableQuadbUnpackUint64Le((string) hex2bin($entry['keyHex']))])) {
            $selectedKeys[] = $entry;
        }
    }

    return [
        'quadrable_head' => $selectedHeads,
        'quadrable_nodesLeaf' => $selectedLeaves,
        'quadrable_nodesInterior' => $selectedBranches,
        'quadrable_key' => $selectedKeys,
        'quadrable_quadb_state' => [[
            'keyHex' => bin2hex('currHead'),
            'valueHex' => bin2hex($currentHead),
        ]],
    ];
}

/**
 * @return array{int, int}
 */
function quadrableQuadbRawBranchChildren(string $branchValue): array
{
    if (strlen($branchValue) !== 48) {
        throw new RuntimeException('expected a 48-byte raw branch value');
    }

    $firstWord = quadrableQuadbUnpackUint64Le(substr($branchValue, 0, 8));
    $nodeType = $firstWord & 0xf;
    $firstNodeId = intdiv($firstWord, 16);
    $secondNodeId = quadrableQuadbUnpackUint64Le(substr($branchValue, 40, 8));

    if ($nodeType === 1) {
        return [$firstNodeId, 0];
    }
    if ($nodeType === 2) {
        return [0, $firstNodeId];
    }
    if ($nodeType === 3) {
        return [$firstNodeId, $secondNodeId];
    }

    throw new RuntimeException('selected upstream raw slice includes a proof-backed or unknown branch');
}

/**
 * @param list<array{key: string, value: string}> $entries
 *
 * @return array<string, string>
 */
function quadrableQuadbRawStringEntriesByKey(array $entries): array
{
    $indexed = [];
    foreach ($entries as $entry) {
        $indexed[$entry['key']] = $entry['value'];
    }

    ksort($indexed, SORT_STRING);

    return $indexed;
}

/**
 * @param array{
 *     quadrable_nodesLeaf: array<int, string>,
 *     quadrable_nodesInterior: array<int, string>
 * } $lmdb
 */
function quadrableQuadbRawBucketBytes(array $lmdb): int
{
    $bytes = 0;
    foreach ($lmdb['quadrable_nodesLeaf'] as $raw) {
        $bytes += strlen($raw);
    }
    foreach ($lmdb['quadrable_nodesInterior'] as $raw) {
        $bytes += strlen($raw);
    }

    return $bytes;
}

/**
 * @param array{numNodes: int, numLeafNodes: int, numBranchNodes: int, numWitnessNodes: int, maxDepth: int, numBytes: int} $stats
 *
 * @return list<string>
 */
function quadrableQuadbStatsLines(array $stats): array
{
    return [
        'numNodes:        ' . $stats['numNodes'],
        'numLeafNodes:    ' . $stats['numLeafNodes'],
        'numBranchNodes:  ' . $stats['numBranchNodes'],
        'numWitnessNodes: ' . $stats['numWitnessNodes'],
        'maxDepth:        ' . $stats['maxDepth'],
        'numBytes:        ' . $stats['numBytes'],
    ];
}

/**
 * @return array{garbage: int, total: int}
 */
function quadrableQuadbParseGcText(string $text): array
{
    if (!preg_match('/^Collected ([0-9]+)\/([0-9]+) nodes\n$/', $text, $matches)) {
        throw new RuntimeException('unexpected garbage collection output: ' . $text);
    }

    return [
        'garbage' => (int) $matches[1],
        'total' => (int) $matches[2],
    ];
}
