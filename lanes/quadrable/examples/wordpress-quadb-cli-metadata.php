<?php

declare(strict_types=1);

use PortLibs\Quadrable\QuadbStore;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$help = QuadbStore::helpCommandOutput();
$version = QuadbStore::versionCommandOutput();

echo json_encode([
    'scenario' => 'preflight native quadb metadata output for a WordPress snapshot CLI without opening a store',
    'versionExitCode' => $version['exitCode'],
    'versionStdout' => rtrim($version['stdout'], "\r\n"),
    'versionStderr' => $version['stderr'],
    'helpExitCode' => $help['exitCode'],
    'helpHasNoStderr' => $help['stderr'] === '',
    'helpMentionsSnapshotDirectoryOption' => str_contains($help['stdout'], '--db=<dir>'),
    'helpMentionsPrivateSnapshotMode' => str_contains($help['stdout'], '--noTrackKeys'),
    'helpMentionsDelegatedProofImport' => str_contains($help['stdout'], 'importProof [--root=<root>]'),
    'helpDoesNotCreateStore' => !is_dir(sys_get_temp_dir() . '/quadrable-wp-cli-metadata'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
