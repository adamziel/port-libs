<?php

declare(strict_types=1);

namespace PortLibs\Quadrable;

final class QuadbStore
{
    private const STATE_FILE = 'quadb-state.json';
    private const NODE_TYPE_BRANCH_LEFT = 1;
    private const NODE_TYPE_BRANCH_RIGHT = 2;
    private const NODE_TYPE_BRANCH_BOTH = 3;
    private const NODE_TYPE_LEAF = 4;
    private const NODE_TYPE_WITNESS = 5;
    private const NODE_TYPE_WITNESS_LEAF = 6;

    private function __construct(
        private readonly string $directory,
        private readonly TrackedNodeStore $nodeStore,
        private ?string $currentHead,
        private int $detachedHeadNodeId,
        /** @var array<string, string> */
        private array $trackedKeys = [],
        /** @var array<string, array{integer: int, suffixHex: string}> */
        private array $compositeKeys = [],
        /** @var array<int|string, array<string, mixed>> */
        private array $partialProofHeads = [],
        /** @var array<string, mixed>|null */
        private ?array $partialDetachedHead = null,
        private readonly bool $trackKeys = true
    ) {
    }

    public static function init(string $directory, bool $trackKeys = true): self
    {
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new \RuntimeException("Unable to create directory '{$directory}'");
        }

        $statePath = self::statePath($directory);
        if (is_file($statePath)) {
            return self::open($directory, $trackKeys);
        }

        $store = new self($directory, new TrackedNodeStore(), 'master', 0, trackKeys: $trackKeys);
        $store->persist();

        return $store;
    }

    public static function usageText(): string
    {
        return <<<'USAGE'

    Usage:
      quadb [options] init
      quadb [options] put [--int] [--] <key> <val>
      quadb [options] del [--int] [--] <key>
      quadb [options] get [--int] [--] <key>
      quadb [options] length
      quadb [options] export [--sep=<sep>] [--int]
      quadb [options] import [--sep=<sep>] [--int]
      quadb [options] root
      quadb [options] stats
      quadb [options] status
      quadb [options] diff <head> [--sep=<sep>]
      quadb [options] patch [--sep=<sep>]
      quadb [options] head
      quadb [options] head rm [<head>]
      quadb [options] checkout [<head>]
      quadb [options] fork [<head>] [--from=<from>]
      quadb [options] gc
      quadb [options] exportProof [--format=(HashedKeys|FullKeys)] [--hex] [--dump] [--int] [--stdin] [--] [<keys>...]
      quadb [options] importProof [--root=<root>] [--hex] [--dump]
      quadb [options] mergeProof [--hex]
      quadb [options] dumpTree
      quadb [options] mineHash <prefix>

    Options:
      --db=<dir>     Database directory (default $ENV{QUADB_DIR} || "./quadb-dir/")
      --noTrackKeys  Don't store keys in DB (default $ENV{QUADB_NOTRACKKEYS} || false)
      --int          Keys are in integer format
      -h --help      Show this screen.
      --version      Show version.


USAGE;
    }

    /**
     * @return array{exitCode: int, stdout: string, stderr: string}
     */
    public static function helpCommandOutput(): array
    {
        return [
            'exitCode' => 0,
            'stdout' => self::usageText(),
            'stderr' => '',
        ];
    }

    /**
     * Native command-output shape for invoking `quadb` without a command.
     *
     * @return array{exitCode: int, stdout: string, stderr: string}
     */
    public static function noArgumentCommandOutput(): array
    {
        return [
            'exitCode' => 255,
            'stdout' => "\n" . self::usageText(),
            'stderr' => 'Arguments did not match expected patterns',
        ];
    }

    /**
     * Native command-output shape for `quadb --version`.
     *
     * @return array{exitCode: int, stdout: string, stderr: string}
     */
    public static function versionCommandOutput(string $version = ''): array
    {
        return [
            'exitCode' => 0,
            'stdout' => 'quadb ' . $version . "\n",
            'stderr' => '',
        ];
    }

    /**
     * Native command-output shape for `quadb init`. Upstream writes the
     * already-initialized notice to stderr, so both streams are returned.
     *
     * @return array{stdout: string, stderr: string}
     */
    public static function initCommandOutput(string $directory, bool $trackKeys = true): array
    {
        $alreadyInitialized = is_file(self::statePath($directory));
        self::init($directory, $trackKeys);

        $displayDirectory = $directory . '/';
        if ($alreadyInitialized) {
            return [
                'stdout' => '',
                'stderr' => "quadb: Directory '{$displayDirectory}' already init'ed. Doing nothing.\n",
            ];
        }

        return [
            'stdout' => "quadb: init'ing directory: {$displayDirectory}\n",
            'stderr' => '',
        ];
    }

    public static function open(string $directory, bool $trackKeys = true): self
    {
        if (!is_dir($directory)) {
            throw new \RuntimeException("Could not access directory '{$directory}'");
        }

        $statePath = self::statePath($directory);
        if (!is_file($statePath)) {
            throw new \RuntimeException("Quadrable store is not initialized: '{$directory}'");
        }

        $decoded = json_decode((string) file_get_contents($statePath), true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($decoded)
            || ($decoded['schemaVersion'] ?? null) !== 1
            || !isset($decoded['trackedNodeStore'], $decoded['quadbState'])
            || !is_array($decoded['trackedNodeStore'])
            || !is_array($decoded['quadbState'])
        ) {
            throw new \InvalidArgumentException('Malformed quadrable file-backed store state');
        }

        $quadbState = $decoded['quadbState'];
        $currentHead = array_key_exists('currentHead', $quadbState) ? $quadbState['currentHead'] : 'master';
        if ($currentHead !== null && !is_string($currentHead)) {
            throw new \InvalidArgumentException('quadb current head must be a string or null');
        }
        if ($currentHead === '') {
            throw new \InvalidArgumentException('head name must be non-empty');
        }

        $detachedHeadNodeId = self::parseNonNegativeNodeId(
            $quadbState['detachedHeadNodeId'] ?? 0,
            'detached head node id'
        );

        $nodeStore = TrackedNodeStore::fromSnapshot(
            self::decodeTrackedNodeStoreSnapshot($decoded['trackedNodeStore'])
        );
        if ($detachedHeadNodeId !== 0) {
            $nodeStore->nodeHash($detachedHeadNodeId);
        }

        $trackedKeys = [];
        foreach (($quadbState['trackedKeys'] ?? []) as $keyHashHex => $keyRaw) {
            if (!is_string($keyHashHex) || !preg_match('/^[0-9a-f]{64}$/', $keyHashHex)) {
                throw new \InvalidArgumentException('tracked key hash must be lowercase 32-byte hex');
            }
            $key = self::decodeStoredString($keyRaw, 'tracked key');
            if (!is_string($key) || $key === '') {
                throw new \InvalidArgumentException('tracked key must be a non-empty string');
            }

            $trackedKeys[$keyHashHex] = $key;
        }

        $compositeKeysRaw = $quadbState['compositeKeys'] ?? [];
        if (!is_array($compositeKeysRaw)) {
            throw new \InvalidArgumentException('composite keys must be an object');
        }

        $compositeKeys = [];
        foreach ($compositeKeysRaw as $keyHex => $composite) {
            if (!is_string($keyHex) || !preg_match('/^[0-9a-f]{64}$/', $keyHex) || !is_array($composite)) {
                throw new \InvalidArgumentException('composite key metadata is malformed');
            }

            $integer = self::parseCompositeIntegerValue($composite['integer'] ?? null, 'composite integer key');
            $suffixHex = self::normalizeCompositeSuffixHex($composite['suffixHex'] ?? null);
            if (self::compositeKey($integer, $suffixHex)->hex() !== $keyHex) {
                throw new \InvalidArgumentException('composite key metadata does not match key hash');
            }

            $compositeKeys[$keyHex] = [
                'integer' => $integer,
                'suffixHex' => $suffixHex,
            ];
        }
        ksort($compositeKeys, SORT_STRING);

        $partialProofHeadsRaw = $quadbState['partialProofHeads'] ?? [];
        if (!is_array($partialProofHeadsRaw)) {
            throw new \InvalidArgumentException('partial proof heads must be an object');
        }

        $partialProofHeads = [];
        foreach ($partialProofHeadsRaw as $head => $partialState) {
            if (!is_string($head) && !is_int($head)) {
                throw new \InvalidArgumentException('partial proof head names must be strings');
            }
            $headName = (string) $head;
            self::assertHeadNameValue($headName);
            $partialProofHeads[$headName] = self::parsePartialProofState(
                self::decodePartialProofState($partialState, 'partial proof head'),
                'partial proof head'
            );
        }
        ksort($partialProofHeads, SORT_STRING);

        $partialDetachedHead = null;
        if (array_key_exists('partialDetachedHead', $quadbState) && $quadbState['partialDetachedHead'] !== null) {
            $partialDetachedHead = self::parsePartialProofState(
                self::decodePartialProofState($quadbState['partialDetachedHead'], 'partial detached head'),
                'partial detached head'
            );
        }

        return new self(
            $directory,
            $nodeStore,
            $currentHead,
            $detachedHeadNodeId,
            $trackedKeys,
            $compositeKeys,
            $partialProofHeads,
            $partialDetachedHead,
            $trackKeys
        );
    }

    /**
     * Native equivalent of `quadb mineHash <prefix>` with a deterministic scan
     * instead of upstream's random-device loop.
     */
    public static function mineHashText(string $prefix, int $start = 1, int $maxAttempts = 1000000): string
    {
        $result = Key::mineHashPrefix($prefix, $start, $maxAttempts);

        return $result['input'] . ' -> ' . $result['hashHex'] . "\n";
    }

    /**
     * Native stdout/stderr/exit-code shape for `quadb mineHash <prefix>`.
     *
     * @return array{exitCode: int, stdout: string, stderr: string}
     */
    public static function mineHashCommandOutput(
        string $prefix,
        int $start = 1,
        int $maxAttempts = 1000000
    ): array {
        try {
            return [
                'exitCode' => 0,
                'stdout' => self::mineHashText($prefix, $start, $maxAttempts),
                'stderr' => '',
            ];
        } catch (\Throwable $throwable) {
            return [
                'exitCode' => 1,
                'stdout' => '',
                'stderr' => 'quadb error: ' . $throwable->getMessage() . "\n",
            ];
        }
    }

    /**
     * Opens a store with the startup behavior used by non-init `quadb`
     * commands. Upstream auto-creates the LMDB payload when the directory
     * already exists, but fails before opening LMDB when the directory itself
     * is missing.
     */
    public static function openForCommand(string $directory, bool $trackKeys = true): self
    {
        if (!is_dir($directory)) {
            throw new \RuntimeException(
                "Could not access directory '" . self::commandDisplayDirectory($directory) . "': No such file or directory"
            );
        }

        if (!is_file(self::statePath($directory))) {
            return self::init($directory, $trackKeys);
        }

        return self::open($directory, $trackKeys);
    }

    /**
     * Native stdout/stderr/exit-code shape for `quadb root`.
     *
     * @return array{exitCode: int, stdout: string, stderr: string}
     */
    public static function rootCommandOutput(string $directory, bool $trackKeys = true): array
    {
        try {
            return [
                'exitCode' => 0,
                'stdout' => self::openForCommand($directory, $trackKeys)->rootText(),
                'stderr' => '',
            ];
        } catch (\Throwable $throwable) {
            return [
                'exitCode' => 1,
                'stdout' => '',
                'stderr' => 'quadb error: ' . $throwable->getMessage() . "\n",
            ];
        }
    }

    /**
     * Native stdout/stderr/exit-code shape for `quadb length`.
     *
     * @return array{exitCode: int, stdout: string, stderr: string}
     */
    public static function lengthCommandOutput(string $directory, bool $trackKeys = true): array
    {
        try {
            return [
                'exitCode' => 0,
                'stdout' => self::openForCommand($directory, $trackKeys)->lengthText(),
                'stderr' => '',
            ];
        } catch (\Throwable $throwable) {
            return [
                'exitCode' => 1,
                'stdout' => '',
                'stderr' => 'quadb error: ' . $throwable->getMessage() . "\n",
            ];
        }
    }

    /**
     * Native stdout/stderr/exit-code shape for `quadb stats`.
     *
     * @return array{exitCode: int, stdout: string, stderr: string}
     */
    public static function statsCommandOutput(string $directory, bool $trackKeys = true): array
    {
        try {
            return [
                'exitCode' => 0,
                'stdout' => self::openForCommand($directory, $trackKeys)->statsText(),
                'stderr' => '',
            ];
        } catch (\Throwable $throwable) {
            return [
                'exitCode' => 1,
                'stdout' => '',
                'stderr' => 'quadb error: ' . $throwable->getMessage() . "\n",
            ];
        }
    }

    /**
     * Native stdout/stderr/exit-code shape for `quadb status`.
     *
     * @return array{exitCode: int, stdout: string, stderr: string}
     */
    public static function statusCommandOutput(string $directory, bool $trackKeys = true): array
    {
        try {
            return [
                'exitCode' => 0,
                'stdout' => self::openForCommand($directory, $trackKeys)->statusText(),
                'stderr' => '',
            ];
        } catch (\Throwable $throwable) {
            return [
                'exitCode' => 1,
                'stdout' => '',
                'stderr' => 'quadb error: ' . $throwable->getMessage() . "\n",
            ];
        }
    }

    /**
     * Native stdout/stderr/exit-code shape for `quadb head`.
     *
     * @return array{exitCode: int, stdout: string, stderr: string}
     */
    public static function headCommandOutput(string $directory, bool $trackKeys = true): array
    {
        try {
            return [
                'exitCode' => 0,
                'stdout' => self::openForCommand($directory, $trackKeys)->headText(),
                'stderr' => '',
            ];
        } catch (\Throwable $throwable) {
            return [
                'exitCode' => 1,
                'stdout' => '',
                'stderr' => 'quadb error: ' . $throwable->getMessage() . "\n",
            ];
        }
    }

    /**
     * Native stdout/stderr/exit-code shape for `quadb head rm [<head>]`.
     *
     * @return array{exitCode: int, stdout: string, stderr: string}
     */
    public static function headRemoveCommandOutput(
        string $directory,
        ?string $head = null,
        bool $trackKeys = true
    ): array {
        try {
            self::openForCommand($directory, $trackKeys)->removeHead($head);

            return [
                'exitCode' => 0,
                'stdout' => '',
                'stderr' => '',
            ];
        } catch (\Throwable $throwable) {
            return [
                'exitCode' => 1,
                'stdout' => '',
                'stderr' => 'quadb error: ' . $throwable->getMessage() . "\n",
            ];
        }
    }

    /**
     * Native stdout/stderr/exit-code shape for `quadb gc`.
     *
     * @return array{exitCode: int, stdout: string, stderr: string}
     */
    public static function garbageCollectCommandOutput(string $directory, bool $trackKeys = true): array
    {
        try {
            return [
                'exitCode' => 0,
                'stdout' => self::openForCommand($directory, $trackKeys)->garbageCollectText(),
                'stderr' => '',
            ];
        } catch (\Throwable $throwable) {
            return [
                'exitCode' => 1,
                'stdout' => '',
                'stderr' => 'quadb error: ' . $throwable->getMessage() . "\n",
            ];
        }
    }

    /**
     * Native stdout/stderr/exit-code shape for `quadb dumpTree`.
     *
     * @return array{exitCode: int, stdout: string, stderr: string}
     */
    public static function dumpTreeCommandOutput(string $directory, bool $trackKeys = true): array
    {
        try {
            return [
                'exitCode' => 0,
                'stdout' => self::openForCommand($directory, $trackKeys)->dumpTreeText(),
                'stderr' => '',
            ];
        } catch (\Throwable $throwable) {
            return [
                'exitCode' => 1,
                'stdout' => '',
                'stderr' => 'quadb error: ' . $throwable->getMessage() . "\n",
            ];
        }
    }

    /**
     * Native stdout/stderr/exit-code shape for `quadb get <key>`.
     *
     * @return array{exitCode: int, stdout: string, stderr: string}
     */
    public static function getCommandOutput(string $directory, string $key, bool $trackKeys = true): array
    {
        try {
            return [
                'exitCode' => 0,
                'stdout' => self::openForCommand($directory, $trackKeys)->get($key) . "\n",
                'stderr' => '',
            ];
        } catch (\Throwable $throwable) {
            return [
                'exitCode' => 1,
                'stdout' => '',
                'stderr' => 'quadb error: ' . $throwable->getMessage() . "\n",
            ];
        }
    }

    /**
     * Native stdout/stderr/exit-code shape for `quadb get --int <key>`.
     *
     * @return array{exitCode: int, stdout: string, stderr: string}
     */
    public static function getIntegerCommandOutput(string $directory, string $key, bool $trackKeys = true): array
    {
        try {
            return [
                'exitCode' => 0,
                'stdout' => self::openForCommand($directory, $trackKeys)
                    ->getInteger(self::parseQuadbCliIntegerKey($key)) . "\n",
                'stderr' => '',
            ];
        } catch (\Throwable $throwable) {
            return [
                'exitCode' => 1,
                'stdout' => '',
                'stderr' => 'quadb error: ' . $throwable->getMessage() . "\n",
            ];
        }
    }

    /**
     * Native stdout/stderr/exit-code shape for `quadb checkout [<head>]`.
     *
     * @return array{exitCode: int, stdout: string, stderr: string}
     */
    public static function checkoutCommandOutput(
        string $directory,
        ?string $head = null,
        bool $trackKeys = true
    ): array {
        try {
            self::openForCommand($directory, $trackKeys)->checkout($head);

            return [
                'exitCode' => 0,
                'stdout' => '',
                'stderr' => '',
            ];
        } catch (\Throwable $throwable) {
            return [
                'exitCode' => 1,
                'stdout' => '',
                'stderr' => 'quadb error: ' . $throwable->getMessage() . "\n",
            ];
        }
    }

    /**
     * Native stdout/stderr/exit-code shape for `quadb fork [<head>] [--from=<from>]`.
     *
     * @return array{exitCode: int, stdout: string, stderr: string}
     */
    public static function forkCommandOutput(
        string $directory,
        ?string $head = null,
        ?string $from = null,
        bool $trackKeys = true
    ): array {
        try {
            self::openForCommand($directory, $trackKeys)->fork($head, $from);

            return [
                'exitCode' => 0,
                'stdout' => '',
                'stderr' => '',
            ];
        } catch (\Throwable $throwable) {
            return [
                'exitCode' => 1,
                'stdout' => '',
                'stderr' => 'quadb error: ' . $throwable->getMessage() . "\n",
            ];
        }
    }

    /**
     * Native stdout/stderr/exit-code shape for `quadb put <key> <val>`.
     *
     * @return array{exitCode: int, stdout: string, stderr: string}
     */
    public static function putCommandOutput(
        string $directory,
        string $key,
        string $value,
        bool $trackKeys = true
    ): array {
        try {
            self::openForCommand($directory, $trackKeys)->put($key, $value);

            return [
                'exitCode' => 0,
                'stdout' => '',
                'stderr' => '',
            ];
        } catch (\Throwable $throwable) {
            return [
                'exitCode' => 1,
                'stdout' => '',
                'stderr' => 'quadb error: ' . $throwable->getMessage() . "\n",
            ];
        }
    }

    /**
     * Native stdout/stderr/exit-code shape for `quadb put --int <key> <val>`.
     *
     * @return array{exitCode: int, stdout: string, stderr: string}
     */
    public static function putIntegerCommandOutput(
        string $directory,
        string $key,
        string $value,
        bool $trackKeys = true
    ): array {
        try {
            self::openForCommand($directory, $trackKeys)->putInteger(
                self::parseQuadbCliIntegerKey($key),
                $value
            );

            return [
                'exitCode' => 0,
                'stdout' => '',
                'stderr' => '',
            ];
        } catch (\Throwable $throwable) {
            return [
                'exitCode' => 1,
                'stdout' => '',
                'stderr' => 'quadb error: ' . $throwable->getMessage() . "\n",
            ];
        }
    }

    /**
     * Native stdout/stderr/exit-code shape for `quadb del <key>`.
     *
     * @return array{exitCode: int, stdout: string, stderr: string}
     */
    public static function deleteCommandOutput(string $directory, string $key, bool $trackKeys = true): array
    {
        try {
            self::openForCommand($directory, $trackKeys)->delete($key);

            return [
                'exitCode' => 0,
                'stdout' => '',
                'stderr' => '',
            ];
        } catch (\Throwable $throwable) {
            return [
                'exitCode' => 1,
                'stdout' => '',
                'stderr' => 'quadb error: ' . $throwable->getMessage() . "\n",
            ];
        }
    }

    /**
     * Native stdout/stderr/exit-code shape for `quadb del --int <key>`.
     *
     * @return array{exitCode: int, stdout: string, stderr: string}
     */
    public static function deleteIntegerCommandOutput(string $directory, string $key, bool $trackKeys = true): array
    {
        try {
            self::openForCommand($directory, $trackKeys)->deleteInteger(self::parseQuadbCliIntegerKey($key));

            return [
                'exitCode' => 0,
                'stdout' => '',
                'stderr' => '',
            ];
        } catch (\Throwable $throwable) {
            return [
                'exitCode' => 1,
                'stdout' => '',
                'stderr' => 'quadb error: ' . $throwable->getMessage() . "\n",
            ];
        }
    }

    /**
     * Native stdout/stderr/exit-code shape for `quadb diff <head>`.
     *
     * @return array{exitCode: int, stdout: string, stderr: string}
     */
    public static function diffCommandOutput(
        string $directory,
        string $head,
        string $separator = ',',
        bool $trackKeys = true
    ): array {
        try {
            return [
                'exitCode' => 0,
                'stdout' => self::openForCommand($directory, $trackKeys)->diffLines($head, $separator),
                'stderr' => '',
            ];
        } catch (\Throwable $throwable) {
            return [
                'exitCode' => 1,
                'stdout' => '',
                'stderr' => 'quadb error: ' . $throwable->getMessage() . "\n",
            ];
        }
    }

    /**
     * Native stdout/stderr/exit-code shape for `quadb patch`.
     *
     * @return array{exitCode: int, stdout: string, stderr: string}
     */
    public static function patchCommandOutput(
        string $directory,
        string $input,
        string $separator = ',',
        bool $trackKeys = true
    ): array {
        try {
            self::openForCommand($directory, $trackKeys)->applyPatchLines($input, $separator);

            return [
                'exitCode' => 0,
                'stdout' => '',
                'stderr' => '',
            ];
        } catch (\Throwable $throwable) {
            return [
                'exitCode' => 1,
                'stdout' => '',
                'stderr' => 'quadb error: ' . $throwable->getMessage() . "\n",
            ];
        }
    }

    /**
     * Native stdout/stderr/exit-code shape for `quadb import`.
     *
     * @return array{exitCode: int, stdout: string, stderr: string}
     */
    public static function importCommandOutput(
        string $directory,
        string $input,
        string $separator = ',',
        bool $trackKeys = true
    ): array {
        try {
            self::openForCommand($directory, $trackKeys)->importLines($input, $separator);

            return [
                'exitCode' => 0,
                'stdout' => '',
                'stderr' => '',
            ];
        } catch (\Throwable $throwable) {
            return [
                'exitCode' => 1,
                'stdout' => '',
                'stderr' => 'quadb error: ' . $throwable->getMessage() . "\n",
            ];
        }
    }

    /**
     * Native stdout/stderr/exit-code shape for `quadb export`.
     *
     * @return array{exitCode: int, stdout: string, stderr: string}
     */
    public static function exportCommandOutput(
        string $directory,
        string $separator = ',',
        bool $trackKeys = true
    ): array {
        try {
            return [
                'exitCode' => 0,
                'stdout' => self::openForCommand($directory, $trackKeys)->exportLines($separator),
                'stderr' => '',
            ];
        } catch (\Throwable $throwable) {
            return [
                'exitCode' => 1,
                'stdout' => '',
                'stderr' => 'quadb error: ' . $throwable->getMessage() . "\n",
            ];
        }
    }

    /**
     * Native stdout/stderr/exit-code shape for `quadb import --int`.
     *
     * @return array{exitCode: int, stdout: string, stderr: string}
     */
    public static function importIntegerCommandOutput(
        string $directory,
        string $input,
        string $separator = ',',
        bool $trackKeys = true
    ): array {
        try {
            self::openForCommand($directory, $trackKeys)->importIntegerLines($input, $separator);

            return [
                'exitCode' => 0,
                'stdout' => '',
                'stderr' => '',
            ];
        } catch (\Throwable $throwable) {
            return [
                'exitCode' => 1,
                'stdout' => '',
                'stderr' => 'quadb error: ' . $throwable->getMessage() . "\n",
            ];
        }
    }

    /**
     * Native stdout/stderr/exit-code shape for `quadb export --int`.
     *
     * @return array{exitCode: int, stdout: string, stderr: string}
     */
    public static function exportIntegerCommandOutput(
        string $directory,
        string $separator = ',',
        bool $trackKeys = true
    ): array {
        try {
            return [
                'exitCode' => 0,
                'stdout' => self::openForCommand($directory, $trackKeys)->exportIntegerLines($separator),
                'stderr' => '',
            ];
        } catch (\Throwable $throwable) {
            return [
                'exitCode' => 1,
                'stdout' => '',
                'stderr' => 'quadb error: ' . $throwable->getMessage() . "\n",
            ];
        }
    }

    /**
     * Native stdout/stderr/exit-code shape for `quadb exportProof`.
     *
     * @param list<string> $keys
     *
     * @return array{exitCode: int, stdout: string, stderr: string}
     */
    public static function exportProofCommandOutput(
        string $directory,
        array $keys,
        string $format = 'HashedKeys',
        bool $hex = false,
        bool $dump = false,
        bool $integerKeys = false,
        bool $trackKeys = true
    ): array {
        try {
            $store = self::openForCommand($directory, $trackKeys);
            if ($integerKeys) {
                $integers = [];
                foreach ($keys as $key) {
                    $integers[] = self::parseQuadbCliIntegerKey((string) $key);
                }
                $proof = $store->exportIntegerProof($integers);
            } else {
                $proof = $store->exportProof($keys);
            }

            if ($dump) {
                return [
                    'exitCode' => 0,
                    'stdout' => $proof->dumpText(),
                    'stderr' => '',
                ];
            }

            $encodedProof = $proof->encode(self::proofEncodingTypeForCommandFormat($format));

            return [
                'exitCode' => 0,
                'stdout' => $hex ? '0x' . bin2hex($encodedProof) . "\n" : $encodedProof,
                'stderr' => '',
            ];
        } catch (\Throwable $throwable) {
            return [
                'exitCode' => 1,
                'stdout' => '',
                'stderr' => 'quadb error: ' . $throwable->getMessage() . "\n",
            ];
        }
    }

    /**
     * Native stdout/stderr/exit-code shape for `quadb exportProof --stdin`.
     *
     * @return array{exitCode: int, stdout: string, stderr: string}
     */
    public static function exportProofStdinCommandOutput(
        string $directory,
        string $input,
        string $format = 'HashedKeys',
        bool $hex = false,
        bool $dump = false,
        bool $integerKeys = false,
        bool $trackKeys = true
    ): array {
        try {
            $store = self::openForCommand($directory, $trackKeys);
            if ($integerKeys) {
                $proof = $store->exportIntegerProofFromKeyLines($input);
            } else {
                $proof = $store->exportProofFromKeyLines($input);
            }

            if ($dump) {
                return [
                    'exitCode' => 0,
                    'stdout' => $proof->dumpText(),
                    'stderr' => '',
                ];
            }

            $encodedProof = $proof->encode(self::proofEncodingTypeForCommandFormat($format));

            return [
                'exitCode' => 0,
                'stdout' => $hex ? '0x' . bin2hex($encodedProof) . "\n" : $encodedProof,
                'stderr' => '',
            ];
        } catch (\Throwable $throwable) {
            return [
                'exitCode' => 1,
                'stdout' => '',
                'stderr' => 'quadb error: ' . $throwable->getMessage() . "\n",
            ];
        }
    }

    /**
     * Native stdout/stderr/exit-code shape for `quadb importProof --hex`.
     *
     * @return array{exitCode: int, stdout: string, stderr: string}
     */
    public static function importProofHexCommandOutput(
        string $directory,
        string $proofHex,
        ?string $expectedRoot = null,
        bool $dump = false,
        bool $trackKeys = true
    ): array {
        try {
            $store = self::openForCommand($directory, $trackKeys);
            $encodedProof = self::decodeCommandHexText($proofHex);
            $decodedProof = Proof::decode($encodedProof);

            if ($dump) {
                return [
                    'exitCode' => 0,
                    'stdout' => $decodedProof->dumpText(),
                    'stderr' => '',
                ];
            }

            return self::importProofDecodedCommandOutput($store, $encodedProof, $decodedProof, $expectedRoot);
        } catch (\Throwable $throwable) {
            return [
                'exitCode' => 1,
                'stdout' => '',
                'stderr' => 'quadb error: ' . $throwable->getMessage() . "\n",
            ];
        }
    }

    /**
     * Native stdout/stderr/exit-code shape for binary `quadb importProof`.
     *
     * @return array{exitCode: int, stdout: string, stderr: string}
     */
    public static function importProofCommandOutput(
        string $directory,
        string $encodedProof,
        ?string $expectedRoot = null,
        bool $dump = false,
        bool $trackKeys = true
    ): array {
        try {
            $store = self::openForCommand($directory, $trackKeys);
            $decodedProof = Proof::decode($encodedProof);

            if ($dump) {
                return [
                    'exitCode' => 0,
                    'stdout' => $decodedProof->dumpText(),
                    'stderr' => '',
                ];
            }

            return self::importProofDecodedCommandOutput($store, $encodedProof, $decodedProof, $expectedRoot);
        } catch (\Throwable $throwable) {
            return [
                'exitCode' => 1,
                'stdout' => '',
                'stderr' => 'quadb error: ' . $throwable->getMessage() . "\n",
            ];
        }
    }

    /**
     * Native stdout/stderr/exit-code shape for `quadb mergeProof --hex`.
     *
     * @return array{exitCode: int, stdout: string, stderr: string}
     */
    public static function mergeProofHexCommandOutput(
        string $directory,
        string $proofHex,
        bool $trackKeys = true
    ): array {
        try {
            $store = self::openForCommand($directory, $trackKeys);
            $encodedProof = self::decodeCommandHexText($proofHex);
            $proof = Proof::decode($encodedProof);
            if ($proof->strands === []) {
                throw new \RuntimeException('empty proof');
            }

            $store->mergeProofBytes($encodedProof);

            return [
                'exitCode' => 0,
                'stdout' => '',
                'stderr' => '',
            ];
        } catch (\Throwable $throwable) {
            return [
                'exitCode' => 1,
                'stdout' => '',
                'stderr' => 'quadb error: ' . $throwable->getMessage() . "\n",
            ];
        }
    }

    /**
     * Native stdout/stderr/exit-code shape for binary `quadb mergeProof`.
     *
     * @return array{exitCode: int, stdout: string, stderr: string}
     */
    public static function mergeProofCommandOutput(
        string $directory,
        string $encodedProof,
        bool $trackKeys = true
    ): array {
        try {
            $store = self::openForCommand($directory, $trackKeys);
            $proof = Proof::decode($encodedProof);
            if ($proof->strands === []) {
                throw new \RuntimeException('empty proof');
            }
            if ($store->currentPartialProofState() === null) {
                throw new \RuntimeException('different roots, unable to merge proofs');
            }

            $store->mergeProofBytes($encodedProof);

            return [
                'exitCode' => 0,
                'stdout' => '',
                'stderr' => '',
            ];
        } catch (\Throwable $throwable) {
            return [
                'exitCode' => 1,
                'stdout' => '',
                'stderr' => 'quadb error: ' . $throwable->getMessage() . "\n",
            ];
        }
    }

    /**
     * @return array{exitCode: int, stdout: string, stderr: string}
     */
    private static function importProofDecodedCommandOutput(
        self $store,
        string $encodedProof,
        Proof $decodedProof,
        ?string $expectedRoot
    ): array {
        if ($expectedRoot !== null) {
            $rootBytes = self::decodeCommandHexText($expectedRoot);
            if ($rootBytes !== '' && strlen($rootBytes) !== 32) {
                if ($store->currentPartialProofState() !== null
                    || $store->tree()->rootHash() !== HashTree::EMPTY_HASH
                ) {
                    throw new \RuntimeException('current head must be empty before importing a proof');
                }
                SparseTree::importProof($decodedProof);
                throw new \RuntimeException('proof invalid');
            }

            $store->importProofBytes($encodedProof, $rootBytes === '' ? null : bin2hex($rootBytes));

            return [
                'exitCode' => 0,
                'stdout' => '',
                'stderr' => '',
            ];
        }

        return [
            'exitCode' => 0,
            'stdout' => $store->importProofBytesOutputText($encodedProof, $expectedRoot),
            'stderr' => '',
        ];
    }

    private static function proofEncodingTypeForCommandFormat(string $format): int
    {
        if ($format === 'HashedKeys') {
            return Proof::ENCODING_HASHED_KEYS;
        }
        if ($format === 'FullKeys') {
            return Proof::ENCODING_FULL_KEYS;
        }

        throw new \RuntimeException('unknown proof format');
    }

    public function directory(): string
    {
        return $this->directory;
    }

    public function nodeStore(): TrackedNodeStore
    {
        return $this->nodeStore;
    }

    /**
     * Returns a native, upstream-shaped view of the file-backed store split into
     * Quadrable's LMDB database buckets. Values that are binary in upstream LMDB
     * are returned as raw PHP strings. Proof-backed heads are reconstructed from
     * their persisted proof/update event history and projected into regular LMDB
     * leaf/interior id ranges.
     *
     * @return array{
     *     quadrable_head: array<int|string, string>,
     *     quadrable_nodesLeaf: array<int, string>,
     *     quadrable_nodesInterior: array<int, string>,
     *     quadrable_key: array<int, string>,
     *     quadrable_quadb_state: array<string, string>
     * }
     */
    public function lmdbBucketSnapshot(): array
    {
        $snapshot = $this->nodeStore->exportSnapshot();

        $heads = [];
        foreach ($snapshot['heads'] as $head => $nodeId) {
            $heads[(string) $head] = self::packUInt64Le((int) $nodeId);
        }
        ksort($heads, SORT_STRING);

        $leaves = [];
        $leafKeys = [];
        foreach ($snapshot['leaves'] as $nodeIdRaw => $leaf) {
            $nodeId = (int) $nodeIdRaw;
            if ($nodeId >= TrackedNodeStore::FIRST_MEMSTORE_NODE_ID) {
                continue;
            }

            $leaves[$nodeId] = self::encodeLmdbLeafNode($leaf);
            if ($this->trackKeys && isset($this->trackedKeys[$leaf['keyHash']])) {
                $leafKeys[$nodeId] = $this->trackedKeys[$leaf['keyHash']];
            }
        }
        ksort($leaves, SORT_NUMERIC);
        ksort($leafKeys, SORT_NUMERIC);

        $branches = [];
        foreach ($snapshot['branches'] as $nodeIdRaw => $branch) {
            $nodeId = (int) $nodeIdRaw;
            if ($nodeId >= TrackedNodeStore::FIRST_MEMSTORE_NODE_ID) {
                continue;
            }

            $branches[$nodeId] = self::encodeLmdbBranchNode($branch);
        }
        ksort($branches, SORT_NUMERIC);

        $nextLeafNodeId = self::nextLmdbLeafNodeId($leaves);
        $nextInteriorNodeId = self::nextLmdbInteriorNodeId($branches);
        $partialProjectionRoots = [];
        $partialEventProjectionCache = [];
        $projectedNodes = [];
        $projectPartialState = function (array $state) use (
            &$leaves,
            &$leafKeys,
            &$branches,
            &$nextLeafNodeId,
            &$nextInteriorNodeId,
            &$partialProjectionRoots,
            &$partialEventProjectionCache,
            &$projectedNodes
        ): int {
            $stateKey = hash(
                'sha256',
                self::jsonEncodeBinarySafe($state)
            );
            if (isset($partialProjectionRoots[$stateKey])) {
                return $partialProjectionRoots[$stateKey];
            }

            $partialProjectionRoots[$stateKey] = $this->projectPartialStateLmdbNodes(
                $state,
                $leaves,
                $leafKeys,
                $branches,
                $nextLeafNodeId,
                $nextInteriorNodeId,
                $projectedNodes,
                $partialEventProjectionCache
            );

            return $partialProjectionRoots[$stateKey];
        };

        $partialEntries = [];
        foreach ($this->partialProofHeads as $head => $partialState) {
            $partialEntries[] = [
                'head' => (string) $head,
                'state' => $partialState,
                'ordinal' => self::partialStorageOrdinal($partialState),
            ];
        }
        if ($this->partialDetachedHead !== null) {
            $partialEntries[] = [
                'head' => null,
                'state' => $this->partialDetachedHead,
                'ordinal' => self::partialStorageOrdinal($this->partialDetachedHead),
            ];
        }

        usort($partialEntries, static function (array $a, array $b): int {
            if ($a['ordinal'] !== $b['ordinal']) {
                return $a['ordinal'] <=> $b['ordinal'];
            }

            return (string) ($a['head'] ?? "\xff") <=> (string) ($b['head'] ?? "\xff");
        });

        $partialDetachedNodeId = null;
        foreach ($partialEntries as $entry) {
            $projectedNodeId = $projectPartialState($entry['state']);
            if ($entry['head'] === null) {
                $partialDetachedNodeId = $projectedNodeId;
                continue;
            }

            $heads[$entry['head']] = self::packUInt64Le($projectedNodeId);
        }
        ksort($heads, SORT_STRING);

        $state = [];
        if ($this->currentHead === null) {
            $state['detachedHead'] = self::packUInt64Le($partialDetachedNodeId ?? $this->detachedHeadNodeId);
        } else {
            $state['currHead'] = $this->currentHead;
        }

        return [
            'quadrable_head' => $heads,
            'quadrable_nodesLeaf' => $leaves,
            'quadrable_nodesInterior' => $branches,
            'quadrable_key' => $leafKeys,
            'quadrable_quadb_state' => $state,
        ];
    }

    /**
     * Returns the same native LMDB bucket projection as lmdbBucketSnapshot(), but
     * with raw LMDB entry keys exposed as bytes. Quadrable stores node and
     * leaf-key buckets with MDB_INTEGERKEY and `lmdb::to_sv<uint64_t>(nodeId)`,
     * while heads and quadb state use string keys.
     *
     * @return array{
     *     quadrable_head: list<array{key: string, value: string}>,
     *     quadrable_nodesLeaf: list<array{key: string, value: string}>,
     *     quadrable_nodesInterior: list<array{key: string, value: string}>,
     *     quadrable_key: list<array{key: string, value: string}>,
     *     quadrable_quadb_state: list<array{key: string, value: string}>
     * }
     */
    public function lmdbRawEntrySnapshot(): array
    {
        $snapshot = $this->lmdbBucketSnapshot();

        return [
            'quadrable_head' => self::lmdbStringKeyEntries($snapshot['quadrable_head']),
            'quadrable_nodesLeaf' => self::lmdbUInt64KeyEntries($snapshot['quadrable_nodesLeaf']),
            'quadrable_nodesInterior' => self::lmdbUInt64KeyEntries($snapshot['quadrable_nodesInterior']),
            'quadrable_key' => self::lmdbUInt64KeyEntries($snapshot['quadrable_key']),
            'quadrable_quadb_state' => self::lmdbStringKeyEntries($snapshot['quadrable_quadb_state']),
        ];
    }

    /**
     * Returns a portable dump of the native file-backed store. The `state`
     * payload is the same JSON-backed state this PHP port persists on disk,
     * while `rawEntries` records the upstream-shaped LMDB cursor bytes in hex
     * so a restore can prove it did not lose bucket/key/value fidelity.
     *
     * @return array{
     *     schemaVersion: int,
     *     format: string,
     *     trackKeys: bool,
     *     state: array<string, mixed>,
     *     current: array{detached: bool, head: ?string, rootHash: string, headNodeId: int},
     *     rawEntryDigest: string,
     *     rawEntries: array<string, list<array{keyHex: string, valueHex: string}>>
     * }
     */
    public function exportPortableDump(): array
    {
        $this->persist();
        $rawEntries = self::rawEntrySnapshotHex($this->lmdbRawEntrySnapshot());

        return [
            'schemaVersion' => 1,
            'format' => 'quadrable-quadb-portable-dump',
            'trackKeys' => $this->trackKeys,
            'state' => self::readStateFile($this->directory),
            'current' => $this->status(),
            'rawEntryDigest' => self::portableRawEntryDigest($rawEntries),
            'rawEntries' => $rawEntries,
        ];
    }

    /**
     * Returns a stable BLAKE2s-256 digest over upstream-shaped LMDB cursor
     * entries, preserving bucket order, cursor order, and raw byte lengths.
     *
     * @param array<string, list<array{keyHex: string, valueHex: string}>> $rawEntries
     */
    public static function portableRawEntryDigest(array $rawEntries): string
    {
        $normalized = self::normalizeRawEntrySnapshotHex($rawEntries);
        $input = "quadrable-quadb-raw-entry-digest-v1\0";

        foreach (self::lmdbBucketNames() as $bucket) {
            $input .= $bucket . "\0" . self::packUInt64Le(count($normalized[$bucket]));
            foreach ($normalized[$bucket] as $entry) {
                $key = (string) hex2bin($entry['keyHex']);
                $value = (string) hex2bin($entry['valueHex']);
                $input .= self::packUInt64Le(strlen($key)) . $key;
                $input .= self::packUInt64Le(strlen($value)) . $value;
            }
        }

        return Blake2s::hashHex($input);
    }

    /**
     * @param array<string, mixed> $dump
     */
    public static function restorePortableDump(string $directory, array $dump): self
    {
        if (is_dir($directory) && self::directoryHasEntries($directory)) {
            throw new \RuntimeException('restore target directory must be empty');
        }
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new \RuntimeException("Unable to create directory '{$directory}'");
        }
        if (($dump['schemaVersion'] ?? null) !== 1
            || ($dump['format'] ?? null) !== 'quadrable-quadb-portable-dump'
            || !array_key_exists('trackKeys', $dump)
            || !is_bool($dump['trackKeys'])
            || !isset($dump['state'])
            || !is_array($dump['state'])
            || !isset($dump['rawEntries'])
        ) {
            throw new \InvalidArgumentException('malformed quadrable portable dump');
        }

        $expectedRawEntries = self::normalizeRawEntrySnapshotHex($dump['rawEntries']);
        if (array_key_exists('rawEntryDigest', $dump)) {
            if (!is_string($dump['rawEntryDigest']) || !preg_match('/^[0-9a-f]{64}$/', $dump['rawEntryDigest'])) {
                throw new \InvalidArgumentException('portable dump raw entry digest is malformed');
            }
            if ($dump['rawEntryDigest'] !== self::portableRawEntryDigest($expectedRawEntries)) {
                throw new \RuntimeException('portable dump raw entry digest did not match the raw entries');
            }
        }

        $statePath = self::statePath($directory);
        $tmpPath = $statePath . '.tmp.' . bin2hex(random_bytes(4));
        $encoded = json_encode(
            $dump['state'],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        ) . "\n";

        if (file_put_contents($tmpPath, $encoded, LOCK_EX) === false) {
            throw new \RuntimeException("Unable to write Quadrable portable dump state '{$tmpPath}'");
        }
        if (!rename($tmpPath, $statePath)) {
            @unlink($tmpPath);
            throw new \RuntimeException("Unable to restore Quadrable portable dump state '{$statePath}'");
        }

        $store = self::open($directory, $dump['trackKeys']);
        $restoredRawEntries = self::rawEntrySnapshotHex($store->lmdbRawEntrySnapshot());
        if ($expectedRawEntries !== $restoredRawEntries) {
            throw new \RuntimeException('restored portable dump raw LMDB entries did not match the dump');
        }

        if (isset($dump['current'])) {
            $expectedCurrent = self::normalizePortableDumpCurrent($dump['current']);
            if ($expectedCurrent !== $store->status()) {
                throw new \RuntimeException('restored portable dump current head status did not match the dump');
            }
        }

        return $store;
    }

    /**
     * Restores a store from upstream-shaped LMDB cursor entries alone. Full
     * heads are restored into the tracked node store. Proof-backed heads are
     * restored as raw partial projections, preserving witness bucket bytes
     * until the head is edited.
     *
     * @param array<string, list<array{keyHex: string, valueHex: string}>> $rawEntries
     */
    public static function restoreRawEntryDump(string $directory, array $rawEntries, bool $trackKeys = true): self
    {
        if (is_dir($directory) && self::directoryHasEntries($directory)) {
            throw new \RuntimeException('restore target directory must be empty');
        }
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new \RuntimeException("Unable to create directory '{$directory}'");
        }

        $expectedRawEntries = self::normalizeRawEntrySnapshotHex($rawEntries);
        $parsed = self::parseFullHeadRawEntryDump($expectedRawEntries);

        $store = new self(
            $directory,
            TrackedNodeStore::fromSnapshot($parsed['trackedNodeStore']),
            $parsed['currentHead'],
            $parsed['detachedHeadNodeId'],
            $parsed['trackedKeys'],
            partialProofHeads: $parsed['partialProofHeads'],
            partialDetachedHead: $parsed['partialDetachedHead'],
            trackKeys: $trackKeys
        );
        $store->persist();

        $restoredRawEntries = self::rawEntrySnapshotHex($store->lmdbRawEntrySnapshot());
        if ($expectedRawEntries !== $restoredRawEntries) {
            throw new \RuntimeException('restored raw LMDB entries did not match the dump');
        }

        return $store;
    }

    public function currentHeadName(): ?string
    {
        return $this->currentHead;
    }

    public function isDetachedHead(): bool
    {
        return $this->currentHead === null;
    }

    public function tree(): TrackedSparseTree
    {
        if ($this->currentPartialProofState() !== null) {
            throw new \RuntimeException('current head is a proof-backed partial tree');
        }

        $tree = new TrackedSparseTree($this->nodeStore);

        if ($this->currentHead === null) {
            return $tree->checkout($this->detachedHeadNodeId);
        }

        return $tree->checkout($this->currentHead);
    }

    public function checkout(?string $head = null): TrackedSparseTree|SparseTree
    {
        if ($head === null) {
            $this->currentHead = null;
            $this->detachedHeadNodeId = 0;
            $this->partialDetachedHead = null;
        } else {
            $this->assertHeadName($head);
            $this->currentHead = $head;
            $this->detachedHeadNodeId = 0;
        }

        $this->persist();

        return $this->currentPartialTree() ?? $this->tree();
    }

    public function fork(?string $head = null, ?string $from = null): TrackedSparseTree|SparseTree
    {
        if ($from !== null) {
            $this->assertHeadName($from);
        }

        $partialSource = $from !== null
            ? ($this->partialProofHeads[$from] ?? null)
            : $this->currentPartialProofState();
        if ($partialSource !== null) {
            if ($head === null) {
                $this->currentHead = null;
                $this->detachedHeadNodeId = 0;
                $this->partialDetachedHead = $partialSource;
            } else {
                $this->assertHeadName($head);
                $this->partialProofHeads[$head] = $partialSource;
                $this->nodeStore->deleteHead($head);
                $this->currentHead = $head;
                $this->detachedHeadNodeId = 0;
            }

            $this->persist();

            return $this->partialTreeFromState($partialSource);
        }

        if ($from !== null) {
            $base = (new TrackedSparseTree($this->nodeStore))->checkout($from);
        } else {
            $base = $this->tree();
        }

        $nodeId = $base->headNodeId();
        if ($head === null) {
            $this->currentHead = null;
            $this->detachedHeadNodeId = $nodeId;
        } else {
            $this->assertHeadName($head);
            if ($nodeId >= TrackedNodeStore::FIRST_MEMSTORE_NODE_ID) {
                throw new \RuntimeException('attempted to store MemStore node into LMDB');
            }
            $this->nodeStore->setHeadNodeId($head, $nodeId);
            unset($this->partialProofHeads[$head]);
            $this->currentHead = $head;
            $this->detachedHeadNodeId = 0;
        }

        $this->persist();

        return $this->tree();
    }

    public function put(string $key, string $value): void
    {
        SparseTree::assertNonEmptyKey($key);

        if ($this->currentPartialProofState() !== null) {
            $this->applyPartialStringUpdate($key, false, $value);

            return;
        }

        $tree = $this->tree();
        $tree->put($key, $value);
        $this->recordStringKeyWrite($key);
        $this->save($tree);
    }

    public function putKey(Key $key, string $value): void
    {
        if ($this->currentPartialProofState() !== null) {
            $this->applyPartialRawUpdates([
                $key->hex() => [
                    'delete' => false,
                    'value' => $value,
                ],
            ]);

            return;
        }

        $tree = $this->tree();
        $tree->putKey($key, $value);
        $this->save($tree);
    }

    public function putInteger(int $key, string $value): void
    {
        $this->putKey(Key::fromInteger($key), $value);
    }

    public function putCompositeKey(int $integer, string $hashSuffixHex, string $value): void
    {
        $key = self::compositeKey($integer, $hashSuffixHex);

        if ($this->currentPartialProofState() !== null) {
            $this->applyPartialRawUpdates([
                $key->hex() => [
                    'delete' => false,
                    'value' => $value,
                ],
            ]);
            $this->recordCompositeKeyWrite($key, $integer, $hashSuffixHex);

            return;
        }

        $tree = $this->tree();
        $tree->putKey($key, $value);
        $this->recordCompositeKeyWrite($key, $integer, $hashSuffixHex);
        $this->save($tree);
    }

    public function delete(string $key): void
    {
        SparseTree::assertNonEmptyKey($key);

        if ($this->currentPartialProofState() !== null) {
            $this->applyPartialStringUpdate($key, true, '');

            return;
        }

        $tree = $this->tree();
        $tree->delete($key);
        $this->save($tree);
    }

    public function deleteKey(Key $key): void
    {
        if ($this->currentPartialProofState() !== null) {
            $this->applyPartialRawUpdates([
                $key->hex() => [
                    'delete' => true,
                    'value' => '',
                ],
            ]);

            return;
        }

        $tree = $this->tree();
        $tree->deleteKey($key);
        $this->save($tree);
    }

    public function deleteInteger(int $key): void
    {
        $this->deleteKey(Key::fromInteger($key));
    }

    public function deleteCompositeKey(int $integer, string $hashSuffixHex): void
    {
        $key = self::compositeKey($integer, $hashSuffixHex);

        if ($this->currentPartialProofState() !== null) {
            $this->applyPartialRawUpdates([
                $key->hex() => [
                    'delete' => true,
                    'value' => '',
                ],
            ]);
            $this->forgetCompositeKey($key);

            return;
        }

        $tree = $this->tree();
        $tree->deleteKey($key);
        $this->forgetCompositeKey($key);
        $this->save($tree);
    }

    public function get(string $key): string
    {
        SparseTree::assertNonEmptyKey($key);

        $partial = $this->currentPartialTree();
        if ($partial !== null) {
            $value = $partial->get($key);
        } else {
            $value = $this->tree()->get($key);
        }
        if ($value === null) {
            throw new \RuntimeException('key not found in db');
        }

        return $value;
    }

    public function getKey(Key $key): string
    {
        $partial = $this->currentPartialTree();
        if ($partial !== null) {
            $value = $partial->getKey($key);
        } else {
            $value = $this->tree()->getKey($key);
        }
        if ($value === null) {
            throw new \RuntimeException('key not found in db');
        }

        return $value;
    }

    public function getInteger(int $key): string
    {
        return $this->getKey(Key::fromInteger($key));
    }

    public function getCompositeKey(int $integer, string $hashSuffixHex): string
    {
        return $this->getKey(self::compositeKey($integer, $hashSuffixHex));
    }

    public function rootText(): string
    {
        return '0x' . $this->currentRootHash() . "\n";
    }

    public function lengthText(): string
    {
        return '';
    }

    public function statusText(): string
    {
        $head = $this->isDetachedHead()
            ? "Detached head\n"
            : 'Head: ' . $this->currentHead . "\n";

        return $head . 'Root: ' . $this->renderCurrentRootNode() . "\n";
    }

    /**
     * @return array{numNodes: int, numLeafNodes: int, numBranchNodes: int, numWitnessNodes: int, maxDepth: int, numBytes: int}
     */
    public function stats(): array
    {
        $partial = $this->currentPartialTree();
        if ($partial !== null) {
            return $partial->stats();
        }

        $tree = $this->tree();
        $stats = $tree->stats();

        return [
            'numNodes' => $stats['numNodes'],
            'numLeafNodes' => $stats['numLeafNodes'],
            'numBranchNodes' => $stats['numBranchNodes'],
            'numWitnessNodes' => 0,
            'maxDepth' => $stats['maxDepth'],
            'numBytes' => $this->trackedTreeNumBytes($tree),
        ];
    }

    public function statsText(): string
    {
        $stats = $this->stats();

        return 'numNodes:        ' . $stats['numNodes'] . "\n"
            . 'numLeafNodes:    ' . $stats['numLeafNodes'] . "\n"
            . 'numBranchNodes:  ' . $stats['numBranchNodes'] . "\n"
            . 'numWitnessNodes: ' . $stats['numWitnessNodes'] . "\n"
            . 'maxDepth:        ' . $stats['maxDepth'] . "\n"
            . 'numBytes:        ' . $stats['numBytes'] . "\n";
    }

    public function dumpTreeText(): string
    {
        $output = "-----------------\n";
        $partial = $this->currentPartialTree();

        if ($partial !== null) {
            $output .= $partial->dumpText();
        } else {
            $output .= $this->dumpTrackedNodeText($this->tree()->headNodeId(), 0);
        }

        return $output . "-----------------\n";
    }

    public function headText(): string
    {
        $heads = [];
        foreach ($this->nodeStore->heads() as $head => $nodeId) {
            $headName = (string) $head;
            $heads[] = [
                'head' => $headName,
                'nodeId' => $nodeId,
                'rootHash' => $this->nodeStore->nodeHash($nodeId),
            ];
        }
        foreach ($this->partialProofHeads as $head => $_partialState) {
            $headName = (string) $head;
            $partialTree = $this->partialTreeForHead($headName);
            $heads[] = [
                'head' => $headName,
                'nodeId' => $partialTree->partialRootNodeId(),
                'rootHash' => $partialTree->rootHash(),
            ];
        }

        usort($heads, static function (array $a, array $b): int {
            if ($a['nodeId'] === $b['nodeId']) {
                return $a['head'] <=> $b['head'];
            }

            return $b['nodeId'] <=> $a['nodeId'];
        });

        $output = '';
        if ($this->isDetachedHead()) {
            $output .= 'D> [detached] : ' . $this->renderCurrentRootNode() . "\n";
        }

        foreach ($heads as $head) {
            $prefix = !$this->isDetachedHead() && $this->currentHead === $head['head'] ? '=> ' : '   ';
            $output .= $prefix . $head['head'] . ' : ' . $this->renderRootNode($head['rootHash'], $head['nodeId']) . "\n";
        }

        return $output;
    }

    public function removeHead(?string $head = null): void
    {
        if ($head !== null) {
            $this->assertHeadName($head);
            $this->nodeStore->deleteHead($head);
            unset($this->partialProofHeads[$head]);
            $this->persist();

            return;
        }

        if ($this->isDetachedHead()) {
            $this->detachedHeadNodeId = 0;
            $this->partialDetachedHead = null;
            $this->persist();

            return;
        }

        $this->nodeStore->deleteHead((string) $this->currentHead);
        unset($this->partialProofHeads[(string) $this->currentHead]);
        $this->persist();
    }

    /**
     * @return array{total: int, garbage: int}
     */
    public function garbageCollect(): array
    {
        $extraRoots = [];
        if ($this->currentHead === null && $this->partialDetachedHead === null && $this->detachedHeadNodeId !== 0) {
            $extraRoots[] = $this->detachedHeadNodeId;
        }

        $rawProjectionPartialStorage = $this->hasRawProjectionPartialProofStorage();
        $stats = $this->nodeStore->garbageCollect($extraRoots);
        $partialStats = $this->garbageCollectPartialProofStorage();
        $this->pruneTrackedKeysToStoredLeaves();
        $this->persist();

        // Raw-entry-restored projections already contain the shared LMDB node
        // id space, including full-head nodes, so their stats are the upstream
        // bucket stats. The node store GC above still prunes its local copy.
        if ($rawProjectionPartialStorage && $partialStats['total'] > 0) {
            return $partialStats;
        }

        return [
            'total' => $stats['total'] + $partialStats['total'],
            'garbage' => $stats['garbage'] + $partialStats['garbage'],
        ];
    }

    public function garbageCollectText(): string
    {
        $stats = $this->garbageCollect();

        return 'Collected ' . $stats['garbage'] . '/' . $stats['total'] . " nodes\n";
    }

    public function save(?TrackedSparseTree $tree = null): void
    {
        if ($this->currentHead === null && $tree !== null) {
            $this->detachedHeadNodeId = $tree->headNodeId();
        }

        $this->persist();
    }

    public function importLines(string $input, string $separator = ','): int
    {
        if ($separator === '') {
            throw new \InvalidArgumentException('separator must be non-empty');
        }
        $this->assertWritableFullHead();

        $tree = $this->tree();
        $changes = $tree->change();
        $trackedKeys = [];
        $untrackedKeyHashes = [];
        $count = 0;

        foreach ($this->splitInputLines($input) as $line) {
            [$key, $value] = $this->splitSeparatedLine($line, $separator);
            SparseTree::assertNonEmptyKey($key);
            $changes->put($key, $value);
            $keyHash = $this->keyHash($key);
            if ($this->trackKeys) {
                $trackedKeys[$keyHash] = $key;
            } else {
                $untrackedKeyHashes[$keyHash] = true;
            }
            $count++;
        }

        if ($count > 0) {
            $changes->apply();
            if ($this->trackKeys) {
                $this->trackedKeys = array_replace($this->trackedKeys, $trackedKeys);
            } else {
                foreach (array_keys($untrackedKeyHashes) as $keyHash) {
                    unset($this->trackedKeys[$keyHash]);
                }
            }
            $this->save($tree);
        }

        return $count;
    }

    public function exportLines(string $separator = ','): string
    {
        if ($separator === '') {
            throw new \InvalidArgumentException('separator must be non-empty');
        }

        $partialState = $this->currentPartialProofState();
        if ($partialState !== null) {
            return $this->exportPartialLines(
                $this->partialTreeFromState($partialState),
                $separator,
                false,
                $this->partialStateTracksKeys($partialState)
            );
        }

        $output = '';
        foreach ($this->tree()->orderedEntries() as $entry) {
            $output .= $this->renderTrackedKey($entry->keyHex()) . $separator . $entry->value() . "\n";
        }

        return $output;
    }

    public function diffLines(string $head, string $separator = ','): string
    {
        $this->assertHeadName($head);
        if ($separator === '') {
            throw new \InvalidArgumentException('separator must be non-empty');
        }
        if ($this->currentPartialProofState() !== null || isset($this->partialProofHeads[$head])) {
            throw new \RuntimeException('cannot diff proof-backed partial trees');
        }

        $baseNodeId = $this->nodeStore->headNodeId($head);
        $currentNodeId = $this->tree()->headNodeId();
        $deltas = [];
        $this->diffNodeIds($baseNodeId, $currentNodeId, $deltas);

        $output = '';
        foreach ($deltas as $delta) {
            $output .= ($delta['deletion'] ? '-' : '+')
                . $this->renderTrackedKey($delta['keyHash'])
                . $separator
                . $delta['value']
                . "\n";
        }

        return $output;
    }

    public function applyPatchLines(string $input, string $separator = ','): int
    {
        if ($separator === '') {
            throw new \InvalidArgumentException('separator must be non-empty');
        }
        $this->assertWritableFullHead();

        $tree = $this->tree();
        $changes = $tree->change();
        $trackedKeys = [];
        $untrackedKeyHashes = [];
        $count = 0;

        foreach ($this->splitInputLines($input) as $line) {
            if ($line === '') {
                throw new \RuntimeException('empty line in patch');
            }
            if ($line[0] === '#') {
                continue;
            }

            $operation = $line[0];
            [$key, $value] = $this->splitSeparatedLine(substr($line, 1), $separator);

            if ($operation === '+') {
                SparseTree::assertNonEmptyKey($key);
                $changes->put($key, $value);
                $keyHash = $this->keyHash($key);
                if ($this->trackKeys) {
                    $trackedKeys[$keyHash] = $key;
                } else {
                    $untrackedKeyHashes[$keyHash] = true;
                }
            } elseif ($operation === '-') {
                SparseTree::assertNonEmptyKey($key);
                $changes->delete($key);
            } else {
                throw new \RuntimeException('unexpected line in patch');
            }

            $count++;
        }

        if ($count > 0) {
            $changes->apply();
            if ($this->trackKeys) {
                $this->trackedKeys = array_replace($this->trackedKeys, $trackedKeys);
            } else {
                foreach (array_keys($untrackedKeyHashes) as $keyHash) {
                    unset($this->trackedKeys[$keyHash]);
                }
            }
            $this->save($tree);
        }

        return $count;
    }

    public function importIntegerLines(string $input, string $separator = ','): int
    {
        if ($separator === '') {
            throw new \InvalidArgumentException('separator must be non-empty');
        }

        $normalized = str_replace(["\r\n", "\r"], "\n", $input);
        $lines = explode("\n", $normalized);
        if ($normalized !== '' && str_ends_with($normalized, "\n")) {
            array_pop($lines);
        }

        $updates = [];
        $count = 0;

        foreach ($lines as $line) {
            if ($line === '' && $normalized === '') {
                continue;
            }

            $separatorOffset = strpos($line, $separator);
            if ($separatorOffset === false) {
                throw new \RuntimeException("couldn't find separator in input line");
            }

            $key = substr($line, 0, $separatorOffset);
            $value = substr($line, $separatorOffset + strlen($separator));
            $integer = self::parseQuadbCliIntegerKey($key);

            $updates[Key::fromInteger($integer)->hex()] = [
                'delete' => false,
                'value' => $value,
            ];
            $count++;
        }

        if ($count > 0) {
            $partial = $this->currentPartialProofState();
            if ($partial !== null) {
                $this->applyPartialRawUpdates($updates);
            } else {
                $tree = $this->tree();
                $changes = $tree->change();
                foreach ($updates as $keyHex => $update) {
                    $changes->putKey(Key::fromHex($keyHex), $update['value']);
                }
                $changes->apply();
                $this->save($tree);
            }
        }

        return $count;
    }

    public function exportIntegerLines(string $separator = ','): string
    {
        if ($separator === '') {
            throw new \InvalidArgumentException('separator must be non-empty');
        }

        $partialState = $this->currentPartialProofState();
        if ($partialState !== null) {
            return $this->exportPartialLines(
                $this->partialTreeFromState($partialState),
                $separator,
                true,
                $this->partialStateTracksKeys($partialState)
            );
        }

        $output = '';
        foreach ($this->tree()->orderedEntries() as $entry) {
            $output .= $entry->key()->toInteger() . $separator . $entry->value() . "\n";
        }

        return $output;
    }

    private function exportPartialLines(
        SparseTree $partial,
        string $separator,
        bool $integerKeys,
        bool $trackKeys
    ): string {
        $snapshot = $partial->partialStorageSnapshot();
        $records = [];
        foreach ($snapshot['leaves'] as $record) {
            if (!is_array($record)
                || !isset($record['type'], $record['keyHash'])
                || !is_string($record['type'])
            ) {
                throw new \RuntimeException('partial export leaf record is malformed');
            }

            $keyHash = self::parseHashHexValue($record['keyHash'], 'partial export key hash');
            if ($record['type'] !== 'leaf' && $record['type'] !== 'witnessLeaf') {
                throw new \RuntimeException('partial export encountered an unknown leaf record type');
            }

            $records[$keyHash] = $record;
        }
        ksort($records, SORT_STRING);

        $output = '';
        foreach ($records as $keyHash => $record) {
            if ($integerKeys) {
                $renderedKey = (string) Key::fromHex($keyHash)->toInteger();
            } elseif ($trackKeys && isset($record['key']) && is_string($record['key']) && $record['key'] !== '') {
                $renderedKey = $record['key'];
            } elseif ($trackKeys && isset($this->trackedKeys[$keyHash])) {
                $renderedKey = $this->trackedKeys[$keyHash];
            } else {
                $renderedKey = self::renderUnknownHash($keyHash);
            }

            if ($record['type'] === 'leaf') {
                if (!isset($record['value']) || !is_string($record['value'])) {
                    throw new \RuntimeException('partial export leaf value is malformed');
                }
                $renderedValue = $record['value'];
            } else {
                $renderedValue = self::renderUnknownHash(
                    self::parseHashHexValue($record['valueHash'] ?? null, 'partial export witness value hash')
                );
            }

            $output .= $renderedKey . $separator . $renderedValue . "\n";
        }

        return $output;
    }

    public function importCompositeLines(string $input, string $separator = ','): int
    {
        if ($separator === '') {
            throw new \InvalidArgumentException('separator must be non-empty');
        }

        $updates = [];
        $metadata = [];
        $count = 0;

        foreach ($this->splitInputLines($input) as $line) {
            [$integerText, $suffixHex, $value] = $this->splitCompositeValueLine($line, $separator);
            $integer = self::parseCompositeIntegerText($integerText, 'composite integer key');
            $key = self::compositeKey($integer, $suffixHex);
            $suffixHex = self::normalizeCompositeSuffixHex($suffixHex);
            $updates[$key->hex()] = [
                'delete' => false,
                'value' => $value,
            ];
            $metadata[$key->hex()] = [
                'integer' => $integer,
                'suffixHex' => $suffixHex,
            ];
            $count++;
        }

        if ($count > 0) {
            $partial = $this->currentPartialProofState();
            if ($partial !== null) {
                $this->applyPartialRawUpdates($updates);
            } else {
                $tree = $this->tree();
                $changes = $tree->change();
                foreach ($updates as $keyHex => $update) {
                    $changes->putKey(Key::fromHex($keyHex), $update['value']);
                }
                $changes->apply();
                $this->save($tree);
            }

            foreach ($metadata as $keyHex => $record) {
                $this->recordCompositeKeyWrite(Key::fromHex($keyHex), $record['integer'], $record['suffixHex']);
            }
            $this->persist();
        }

        return $count;
    }

    public function exportCompositeLines(string $separator = ','): string
    {
        if ($separator === '') {
            throw new \InvalidArgumentException('separator must be non-empty');
        }
        if ($this->currentPartialProofState() !== null) {
            throw new \RuntimeException('cannot export all records from a proof-backed partial tree');
        }

        $output = '';
        foreach ($this->tree()->orderedEntries() as $entry) {
            $metadata = $this->compositeKeys[$entry->keyHex()] ?? null;
            if ($metadata === null) {
                throw new \RuntimeException('composite key metadata unavailable for ' . $entry->keyHex());
            }

            $output .= $metadata['integer'] . $separator . $metadata['suffixHex'] . $separator . $entry->value() . "\n";
        }

        return $output;
    }

    /**
     * @param list<string> $keys
     */
    public function exportProof(array $keys): Proof
    {
        return $this->sparseTreeForProofs()->exportProof($keys);
    }

    /**
     * @param list<string> $keys
     */
    public function exportProofBytes(array $keys, int $encodingType = Proof::ENCODING_HASHED_KEYS): string
    {
        return $this->exportProof($keys)->encode($encodingType);
    }

    public function exportProofFromKeyLines(string $input): Proof
    {
        return $this->exportProof($this->splitProofStdinLines($input));
    }

    public function exportProofBytesFromKeyLines(
        string $input,
        int $encodingType = Proof::ENCODING_HASHED_KEYS
    ): string
    {
        return $this->exportProofFromKeyLines($input)->encode($encodingType);
    }

    public function exportProofHexFromKeyLines(
        string $input,
        int $encodingType = Proof::ENCODING_HASHED_KEYS
    ): string
    {
        return '0x' . bin2hex($this->exportProofBytesFromKeyLines($input, $encodingType)) . "\n";
    }

    /**
     * @param list<string> $keys
     */
    public function exportProofHex(array $keys, int $encodingType = Proof::ENCODING_HASHED_KEYS): string
    {
        return '0x' . bin2hex($this->exportProofBytes($keys, $encodingType)) . "\n";
    }

    /**
     * @param list<string> $keys
     */
    public function exportProofDumpText(array $keys): string
    {
        return $this->exportProof($keys)->dumpText();
    }

    /**
     * @param list<int> $integers
     */
    public function exportIntegerProof(array $integers): Proof
    {
        $keys = [];
        foreach ($integers as $integer) {
            if (!is_int($integer)) {
                throw new \InvalidArgumentException('exportIntegerProof expects integer keys');
            }

            $keys[] = Key::fromInteger($integer);
        }

        return $this->sparseTreeForProofs()->exportRawProof($keys);
    }

    public function exportIntegerProofFromKeyLines(string $input): Proof
    {
        $integers = [];
        foreach ($this->splitProofStdinLines($input) as $line) {
            $integers[] = self::parseProofIntegerLine($line);
        }

        return $this->exportIntegerProof($integers);
    }

    public function exportIntegerProofBytesFromKeyLines(
        string $input,
        int $encodingType = Proof::ENCODING_HASHED_KEYS
    ): string
    {
        return $this->exportIntegerProofFromKeyLines($input)->encode($encodingType);
    }

    public function exportIntegerProofHexFromKeyLines(
        string $input,
        int $encodingType = Proof::ENCODING_HASHED_KEYS
    ): string
    {
        return '0x' . bin2hex($this->exportIntegerProofBytesFromKeyLines($input, $encodingType)) . "\n";
    }

    /**
     * @param list<int> $integers
     */
    public function exportIntegerProofBytes(array $integers, int $encodingType = Proof::ENCODING_HASHED_KEYS): string
    {
        return $this->exportIntegerProof($integers)->encode($encodingType);
    }

    /**
     * @param list<int> $integers
     */
    public function exportIntegerProofHex(array $integers, int $encodingType = Proof::ENCODING_HASHED_KEYS): string
    {
        return '0x' . bin2hex($this->exportIntegerProofBytes($integers, $encodingType)) . "\n";
    }

    /**
     * @param list<int> $integers
     */
    public function exportIntegerProofDumpText(array $integers): string
    {
        return $this->exportIntegerProof($integers)->dumpText();
    }

    public function exportCompositeProofFromKeyLines(string $input, string $separator = ','): Proof
    {
        $keys = [];
        foreach ($this->splitProofStdinLines($input) as $line) {
            [$integerText, $suffixHex] = $this->splitCompositeKeyLine($line, $separator);
            $keys[] = self::compositeKey(self::parseCompositeIntegerText($integerText, 'composite proof integer key'), $suffixHex);
        }

        return $this->sparseTreeForProofs()->exportRawProof($keys);
    }

    public function exportCompositeProofBytesFromKeyLines(
        string $input,
        string $separator = ',',
        int $encodingType = Proof::ENCODING_HASHED_KEYS
    ): string
    {
        return $this->exportCompositeProofFromKeyLines($input, $separator)->encode($encodingType);
    }

    public function exportCompositeProofHexFromKeyLines(
        string $input,
        string $separator = ',',
        int $encodingType = Proof::ENCODING_HASHED_KEYS
    ): string
    {
        return '0x' . bin2hex($this->exportCompositeProofBytesFromKeyLines($input, $separator, $encodingType)) . "\n";
    }

    public function importProofHex(string $proofHex, ?string $expectedRoot = null): string
    {
        return $this->importProofBytes(self::decodeProofHexText($proofHex), $expectedRoot);
    }

    public function importProofHexDumpText(string $proofHex): string
    {
        return Proof::decode(self::decodeProofHexText($proofHex))->dumpText();
    }

    public function importProofBytes(string $encodedProof, ?string $expectedRoot = null): string
    {
        if ($this->currentPartialProofState() !== null || $this->tree()->rootHash() !== HashTree::EMPTY_HASH) {
            throw new \RuntimeException('current head must be empty before importing a proof');
        }

        $root = $this->normalizeOptionalRoot($expectedRoot);
        $proof = Proof::decode($encodedProof);
        $partial = SparseTree::importProof($proof, $root ?? '');
        $storageOrdinal = $this->nextPartialStorageOrdinal();
        $state = [
            'rootHash' => $partial->rootHash(),
            'proofRootHash' => $partial->rootHash(),
            'storageId' => self::partialStorageId($this->currentHead, $storageOrdinal, $encodedProof),
            'storageOrdinal' => $storageOrdinal,
            'trackKeys' => $this->trackKeys,
            'proofStoragePruned' => false,
            'proofs' => [bin2hex($encodedProof)],
            'updates' => [],
            'events' => [
                [
                    'type' => 'proof',
                    'rootHash' => $partial->rootHash(),
                    'proof' => bin2hex($encodedProof),
                ],
            ],
        ];

        $this->storeCurrentPartialProofState($state);
        $this->persist();

        return $state['rootHash'];
    }

    public function importProofHexOutputText(string $proofHex, ?string $expectedRoot = null): string
    {
        return $this->importProofBytesOutputText(self::decodeProofHexText($proofHex), $expectedRoot);
    }

    public function importProofBytesOutputText(string $encodedProof, ?string $expectedRoot = null): string
    {
        $rootHash = $this->importProofBytes($encodedProof, $expectedRoot);

        if ($expectedRoot !== null) {
            return '';
        }

        return 'Imported UNAUTHENTICATED proof. Root: 0x' . $rootHash . "\n";
    }

    public function mergeProofHex(string $proofHex): string
    {
        return $this->mergeProofBytes(self::decodeProofHexText($proofHex));
    }

    public function mergeProofBytes(string $encodedProof): string
    {
        $state = $this->currentPartialProofState();
        if ($state === null) {
            throw new \RuntimeException('current head is not a proof-backed partial tree');
        }

        $partial = $this->partialTreeFromState($state);
        $rootBeforeMerge = $partial->rootHash();
        $proof = Proof::decode($encodedProof);
        $partial->mergeProof($proof);

        $proofHex = bin2hex($encodedProof);
        if (($state['rawProjection'] ?? false) === true) {
            $state['rootHash'] = $partial->rootHash();
            $state['proofStoragePruned'] = true;
            $state['proofStoragePrunedProjection'] = $this->rawProjectionAfterProofMerge($state, $proof, $rootBeforeMerge);
            $state['proofs'] = [];
            $state['updates'] = [];
            $state['events'] = [];
        } else {
            $state['proofs'][] = $proofHex;
            $state['proofStoragePruned'] = false;
            unset($state['proofStoragePrunedProjection']);
            $state['events'][] = [
                'type' => 'proof',
                'rootHash' => $rootBeforeMerge,
                'proof' => $proofHex,
            ];
        }
        $this->storeCurrentPartialProofState($state);
        $this->persist();

        return $partial->rootHash();
    }

    /**
     * @return array{detached: bool, head: ?string, rootHash: string, headNodeId: int}
     */
    public function status(): array
    {
        $partial = $this->currentPartialTree();

        return [
            'detached' => $this->isDetachedHead(),
            'head' => $this->currentHead,
            'rootHash' => $partial?->rootHash() ?? $this->tree()->rootHash(),
            'headNodeId' => $partial?->partialRootNodeId() ?? $this->tree()->headNodeId(),
        ];
    }

    private function sparseTreeForProofs(): SparseTree
    {
        $partial = $this->currentPartialTree();
        if ($partial !== null) {
            return $partial;
        }

        $sparse = new SparseTree();
        $changes = $sparse->change();

        foreach ($this->tree()->orderedEntries() as $entry) {
            $trackedKey = $this->trackKeys ? ($this->trackedKeys[$entry->keyHex()] ?? null) : null;
            if ($trackedKey !== null) {
                $changes->put($trackedKey, $entry->value());
            } else {
                $changes->putKey($entry->key(), $entry->value());
            }
        }

        $changes->apply();

        return $sparse;
    }

    private function persist(): void
    {
        $trackedKeys = $this->trackedKeys;
        ksort($trackedKeys, SORT_STRING);
        $compositeKeys = $this->compositeKeys;
        ksort($compositeKeys, SORT_STRING);

        $partialProofHeads = [];
        foreach ($this->partialProofHeads as $head => $state) {
            $partialProofHeads[(string) $head] = self::encodePartialProofState($state);
        }
        ksort($partialProofHeads, SORT_STRING);

        $encoded = json_encode([
            'schemaVersion' => 1,
            'trackedNodeStore' => self::encodeTrackedNodeStoreSnapshot($this->nodeStore->exportSnapshot()),
            'quadbState' => [
                'currentHead' => $this->currentHead,
                'detachedHeadNodeId' => $this->detachedHeadNodeId,
                'trackedKeys' => self::encodeStoredStringMap($trackedKeys),
                'compositeKeys' => $compositeKeys,
                'partialProofHeads' => $partialProofHeads,
                'partialDetachedHead' => self::encodePartialProofState($this->partialDetachedHead),
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";

        $statePath = self::statePath($this->directory);
        $tmpPath = $statePath . '.tmp.' . bin2hex(random_bytes(4));
        if (file_put_contents($tmpPath, $encoded, LOCK_EX) === false) {
            throw new \RuntimeException("Unable to write Quadrable store state '{$tmpPath}'");
        }
        if (!rename($tmpPath, $statePath)) {
            @unlink($tmpPath);
            throw new \RuntimeException("Unable to replace Quadrable store state '{$statePath}'");
        }
    }

    /**
     * @param array<string, mixed> $snapshot
     *
     * @return array<string, mixed>
     */
    private static function encodeTrackedNodeStoreSnapshot(array $snapshot): array
    {
        $encoded = $snapshot;
        if (!isset($encoded['leaves']) || !is_array($encoded['leaves'])) {
            return $encoded;
        }

        foreach ($encoded['leaves'] as $nodeId => $leaf) {
            if (!is_array($leaf) || !array_key_exists('value', $leaf) || !is_string($leaf['value'])) {
                continue;
            }

            $leaf['value'] = self::encodeStoredString($leaf['value']);
            $encoded['leaves'][$nodeId] = $leaf;
        }

        return $encoded;
    }

    /**
     * @param mixed $snapshot
     *
     * @return array<string, mixed>
     */
    private static function decodeTrackedNodeStoreSnapshot(mixed $snapshot): array
    {
        if (!is_array($snapshot)) {
            throw new \InvalidArgumentException('tracked node store snapshot is malformed');
        }

        $decoded = $snapshot;
        if (!isset($decoded['leaves']) || !is_array($decoded['leaves'])) {
            return $decoded;
        }

        foreach ($decoded['leaves'] as $nodeId => $leaf) {
            if (!is_array($leaf) || !array_key_exists('value', $leaf)) {
                continue;
            }

            $leaf['value'] = self::decodeStoredString($leaf['value'], 'tracked node leaf value');
            $decoded['leaves'][$nodeId] = $leaf;
        }

        return $decoded;
    }

    /**
     * @param array<string, string> $values
     *
     * @return array<string, mixed>
     */
    private static function encodeStoredStringMap(array $values): array
    {
        $encoded = [];
        foreach ($values as $key => $value) {
            $encoded[$key] = self::encodeStoredString($value);
        }

        return $encoded;
    }

    private static function encodeStoredString(string $value): string|array
    {
        if (@preg_match('//u', $value) === 1) {
            return $value;
        }

        return [
            '__quadrableEncoding' => 'base64',
            'data' => base64_encode($value),
        ];
    }

    private static function decodeStoredString(mixed $value, string $label): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_array($value)
            && ($value['__quadrableEncoding'] ?? null) === 'base64'
            && isset($value['data'])
            && is_string($value['data'])
        ) {
            $decoded = base64_decode($value['data'], true);
            if ($decoded === false) {
                throw new \InvalidArgumentException($label . ' has malformed base64 storage');
            }

            return $decoded;
        }

        throw new \InvalidArgumentException($label . ' must be a string');
    }

    /**
     * @param array<string, mixed>|null $state
     *
     * @return array<string, mixed>|null
     */
    private static function encodePartialProofState(?array $state): ?array
    {
        if ($state === null) {
            return null;
        }

        $encoded = $state;
        if (isset($encoded['updates']) && is_array($encoded['updates'])) {
            foreach ($encoded['updates'] as $index => $update) {
                $encoded['updates'][$index] = self::encodePartialProofUpdate($update);
            }
        }
        if (isset($encoded['events']) && is_array($encoded['events'])) {
            foreach ($encoded['events'] as $index => $event) {
                $encoded['events'][$index] = self::encodePartialProofUpdate($event);
            }
        }

        return $encoded;
    }

    private static function encodePartialProofUpdate(mixed $update): mixed
    {
        if (!is_array($update)) {
            return $update;
        }

        if (array_key_exists('value', $update) && is_string($update['value'])) {
            $update['value'] = self::encodeStoredString($update['value']);
        }
        if (array_key_exists('key', $update) && is_string($update['key'])) {
            $update['key'] = self::encodeStoredString($update['key']);
        }

        return $update;
    }

    /**
     * @return array<string, mixed>
     */
    private static function decodePartialProofState(mixed $state, string $label): array
    {
        if (!is_array($state)) {
            throw new \InvalidArgumentException($label . ' is malformed');
        }

        $decoded = $state;
        if (isset($decoded['updates']) && is_array($decoded['updates'])) {
            foreach ($decoded['updates'] as $index => $update) {
                $decoded['updates'][$index] = self::decodePartialProofUpdate($update, $label);
            }
        }
        if (isset($decoded['events']) && is_array($decoded['events'])) {
            foreach ($decoded['events'] as $index => $event) {
                $decoded['events'][$index] = self::decodePartialProofUpdate($event, $label);
            }
        }

        return $decoded;
    }

    private static function decodePartialProofUpdate(mixed $update, string $label): mixed
    {
        if (!is_array($update)) {
            return $update;
        }

        if (array_key_exists('value', $update)) {
            $update['value'] = self::decodeStoredString($update['value'], $label . ' value');
        }
        if (array_key_exists('key', $update)) {
            $update['key'] = self::decodeStoredString($update['key'], $label . ' key');
        }

        return $update;
    }

    private static function jsonEncodeBinarySafe(mixed $value): string
    {
        return json_encode(
            self::encodeBinaryStringsForJson($value),
            JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );
    }

    private static function encodeBinaryStringsForJson(mixed $value): mixed
    {
        if (is_string($value)) {
            return self::encodeStoredString($value);
        }

        if (!is_array($value)) {
            return $value;
        }

        $encoded = [];
        foreach ($value as $key => $nested) {
            $encoded[$key] = self::encodeBinaryStringsForJson($nested);
        }

        return $encoded;
    }

    private static function statePath(string $directory): string
    {
        return rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . self::STATE_FILE;
    }

    private static function commandDisplayDirectory(string $directory): string
    {
        return $directory . DIRECTORY_SEPARATOR;
    }

    /**
     * @return array<string, mixed>
     */
    private static function readStateFile(string $directory): array
    {
        $decoded = json_decode(
            (string) file_get_contents(self::statePath($directory)),
            true,
            flags: JSON_THROW_ON_ERROR
        );
        if (!is_array($decoded)) {
            throw new \InvalidArgumentException('Malformed quadrable file-backed store state');
        }

        return $decoded;
    }

    private static function directoryHasEntries(string $directory): bool
    {
        $entries = scandir($directory);
        if ($entries === false) {
            throw new \RuntimeException("Unable to inspect directory '{$directory}'");
        }

        foreach ($entries as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, list<array{key: string, value: string}>> $snapshot
     *
     * @return array<string, list<array{keyHex: string, valueHex: string}>>
     */
    private static function rawEntrySnapshotHex(array $snapshot): array
    {
        $out = [];
        foreach (self::lmdbBucketNames() as $bucket) {
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
     * @return list<string>
     */
    private static function lmdbBucketNames(): array
    {
        return [
            'quadrable_head',
            'quadrable_nodesLeaf',
            'quadrable_nodesInterior',
            'quadrable_key',
            'quadrable_quadb_state',
        ];
    }

    /**
     * @return array<string, list<array{keyHex: string, valueHex: string}>>
     */
    private static function normalizeRawEntrySnapshotHex(mixed $snapshot): array
    {
        if (!is_array($snapshot)) {
            throw new \InvalidArgumentException('portable dump raw entries must be an object');
        }

        $out = [];
        foreach (self::lmdbBucketNames() as $bucket) {
            if (!isset($snapshot[$bucket]) || !is_array($snapshot[$bucket])) {
                throw new \InvalidArgumentException('portable dump raw entries missing bucket ' . $bucket);
            }

            $out[$bucket] = [];
            foreach ($snapshot[$bucket] as $entry) {
                if (!is_array($entry)
                    || !isset($entry['keyHex'], $entry['valueHex'])
                    || !is_string($entry['keyHex'])
                    || !is_string($entry['valueHex'])
                    || !preg_match('/^(?:[0-9a-f]{2})*$/', $entry['keyHex'])
                    || !preg_match('/^(?:[0-9a-f]{2})*$/', $entry['valueHex'])
                ) {
                    throw new \InvalidArgumentException('portable dump raw entry is malformed');
                }

                $out[$bucket][] = [
                    'keyHex' => $entry['keyHex'],
                    'valueHex' => $entry['valueHex'],
                ];
            }
        }

        return $out;
    }

    /**
     * @return array{detached: bool, head: ?string, rootHash: string, headNodeId: int}
     */
    private static function normalizePortableDumpCurrent(mixed $current): array
    {
        if (!is_array($current)
            || !isset($current['detached'], $current['rootHash'], $current['headNodeId'])
            || !is_bool($current['detached'])
            || !is_string($current['rootHash'])
            || !preg_match('/^[0-9a-f]{64}$/', $current['rootHash'])
        ) {
            throw new \InvalidArgumentException('portable dump current status is malformed');
        }

        $head = $current['head'] ?? null;
        if ($head !== null && !is_string($head)) {
            throw new \InvalidArgumentException('portable dump current head must be a string or null');
        }

        return [
            'detached' => $current['detached'],
            'head' => $head,
            'rootHash' => $current['rootHash'],
            'headNodeId' => self::parseNonNegativeNodeId($current['headNodeId'], 'portable dump current head node id'),
        ];
    }

    /**
     * @param array<string, list<array{keyHex: string, valueHex: string}>> $rawEntries
     *
     * @return array{
     *     trackedNodeStore: array<string, mixed>,
     *     trackedKeys: array<string, string>,
     *     partialProofHeads: array<string, array<string, mixed>>,
     *     partialDetachedHead: ?array<string, mixed>,
     *     currentHead: ?string,
     *     detachedHeadNodeId: int
     * }
     */
    private static function parseFullHeadRawEntryDump(array $rawEntries): array
    {
        $heads = [];
        foreach ($rawEntries['quadrable_head'] as $entry) {
            $head = self::decodeRawEntryHexBytes($entry['keyHex'], 'raw LMDB head key');
            self::assertHeadNameValue($head);
            if (array_key_exists($head, $heads)) {
                throw new \InvalidArgumentException('raw LMDB head bucket contains a duplicate key');
            }

            $heads[$head] = self::unpackUInt64Le(
                self::decodeRawEntryHexBytes($entry['valueHex'], 'raw LMDB head value'),
                'raw LMDB head node id'
            );
        }
        ksort($heads, SORT_STRING);

        $rawLeaves = [];
        foreach ($rawEntries['quadrable_nodesLeaf'] as $entry) {
            $nodeId = self::parseRawUInt64EntryKey($entry['keyHex'], 'raw LMDB leaf node id');
            if (isset($rawLeaves[$nodeId])) {
                throw new \InvalidArgumentException('raw LMDB leaf bucket contains a duplicate node id');
            }

            $rawLeaves[$nodeId] = self::decodeRawLmdbLeafRecord(
                self::decodeRawEntryHexBytes($entry['valueHex'], 'raw LMDB leaf value')
            );
        }
        ksort($rawLeaves, SORT_NUMERIC);

        $rawBranches = [];
        foreach ($rawEntries['quadrable_nodesInterior'] as $entry) {
            $nodeId = self::parseRawUInt64EntryKey($entry['keyHex'], 'raw LMDB branch node id');
            if (isset($rawLeaves[$nodeId]) || isset($rawBranches[$nodeId])) {
                throw new \InvalidArgumentException('raw LMDB interior bucket contains a duplicate node id');
            }

            $rawBranches[$nodeId] = self::decodeRawLmdbInteriorRecord(
                self::decodeRawEntryHexBytes($entry['valueHex'], 'raw LMDB branch value')
            );
        }
        ksort($rawBranches, SORT_NUMERIC);

        $trackedKeys = [];
        $trackedKeysByNodeId = [];
        foreach ($rawEntries['quadrable_key'] as $entry) {
            $nodeId = self::parseRawUInt64EntryKey($entry['keyHex'], 'raw LMDB tracked-key node id');
            if (!isset($rawLeaves[$nodeId])) {
                throw new \InvalidArgumentException('raw LMDB tracked-key bucket references an unknown leaf');
            }

            $trackedKey = self::decodeRawEntryHexBytes($entry['valueHex'], 'raw LMDB tracked key');
            SparseTree::assertNonEmptyKey($trackedKey);
            $keyHash = (new HashTree())->keyHash($trackedKey);
            if ($keyHash !== $rawLeaves[$nodeId]['keyHash']) {
                throw new \InvalidArgumentException('raw LMDB tracked key does not match leaf key hash');
            }
            if (isset($trackedKeysByNodeId[$nodeId]) && $trackedKeysByNodeId[$nodeId] !== $trackedKey) {
                throw new \InvalidArgumentException('raw LMDB tracked-key bucket contains a duplicate node id');
            }
            if (isset($trackedKeys[$keyHash]) && $trackedKeys[$keyHash] !== $trackedKey) {
                throw new \InvalidArgumentException('raw LMDB tracked keys contain a hash collision');
            }

            $trackedKeys[$keyHash] = $trackedKey;
            $trackedKeysByNodeId[$nodeId] = $trackedKey;
        }
        ksort($trackedKeys, SORT_STRING);
        ksort($trackedKeysByNodeId, SORT_NUMERIC);

        [$currentHead, $detachedHeadNodeId] = self::parseRawQuadbState($rawEntries['quadrable_quadb_state']);

        $hashTree = new HashTree();
        $hashMemo = [0 => HashTree::EMPTY_HASH];
        $hashVisiting = [];
        $nodeHash = function (int $nodeId) use (
            &$nodeHash,
            &$hashMemo,
            &$hashVisiting,
            $rawLeaves,
            $rawBranches,
            $hashTree
        ): string {
            if (isset($hashMemo[$nodeId])) {
                return $hashMemo[$nodeId];
            }
            if (isset($hashVisiting[$nodeId])) {
                throw new \InvalidArgumentException('raw LMDB node graph contains a cycle');
            }
            if (isset($rawLeaves[$nodeId])) {
                $leaf = $rawLeaves[$nodeId];
                if ($leaf['type'] === 'leaf') {
                    $expectedHash = $hashTree->leafHashForKeyHash($leaf['keyHash'], $leaf['value']);
                } else {
                    $expectedHash = $hashTree->leafHashForKeyHashAndValueHash($leaf['keyHash'], $leaf['valueHash']);
                }
                if ($leaf['hash'] !== $expectedHash) {
                    throw new \InvalidArgumentException('raw LMDB leaf hash does not match stored key/value bytes');
                }

                return $hashMemo[$nodeId] = $leaf['hash'];
            }
            if (!isset($rawBranches[$nodeId])) {
                throw new \InvalidArgumentException('raw LMDB branch references an unknown child');
            }

            $branch = $rawBranches[$nodeId];
            if ($branch['type'] === 'witness') {
                return $hashMemo[$nodeId] = $branch['hash'];
            }

            $hashVisiting[$nodeId] = true;
            $expectedHash = $hashTree->branchHash(
                $nodeHash($branch['leftNodeId']),
                $nodeHash($branch['rightNodeId'])
            );
            unset($hashVisiting[$nodeId]);
            if ($branch['hash'] !== $expectedHash) {
                throw new \InvalidArgumentException('raw LMDB branch hash does not match child nodes');
            }

            return $hashMemo[$nodeId] = $branch['hash'];
        };

        foreach (array_keys($rawLeaves) as $nodeId) {
            $nodeHash($nodeId);
        }
        foreach (array_keys($rawBranches) as $nodeId) {
            $nodeHash($nodeId);
        }

        $witnessMemo = [0 => false];
        $visiting = [];
        $subtreeHasWitness = function (int $nodeId) use (
            &$subtreeHasWitness,
            &$witnessMemo,
            &$visiting,
            $rawLeaves,
            $rawBranches
        ): bool {
            if (isset($witnessMemo[$nodeId])) {
                return $witnessMemo[$nodeId];
            }
            if (isset($visiting[$nodeId])) {
                throw new \InvalidArgumentException('raw LMDB node graph contains a cycle');
            }
            if (isset($rawLeaves[$nodeId])) {
                return $witnessMemo[$nodeId] = $rawLeaves[$nodeId]['type'] === 'witnessLeaf';
            }
            if (!isset($rawBranches[$nodeId])) {
                throw new \InvalidArgumentException('raw LMDB head references an unknown node');
            }

            $branch = $rawBranches[$nodeId];
            if ($branch['type'] === 'witness') {
                return $witnessMemo[$nodeId] = true;
            }

            $visiting[$nodeId] = true;
            $hasWitness = $subtreeHasWitness($branch['leftNodeId'])
                || $subtreeHasWitness($branch['rightNodeId']);
            unset($visiting[$nodeId]);

            return $witnessMemo[$nodeId] = $hasWitness;
        };

        $fullLeaves = [];
        foreach ($rawLeaves as $nodeId => $leaf) {
            if ($leaf['type'] !== 'leaf') {
                continue;
            }
            $fullLeaves[$nodeId] = [
                'keyHash' => $leaf['keyHash'],
                'value' => $leaf['value'],
                'hash' => $leaf['hash'],
            ];
        }
        ksort($fullLeaves, SORT_NUMERIC);

        $fullBranches = [];
        foreach ($rawBranches as $nodeId => $branch) {
            if ($branch['type'] !== 'branch' || $subtreeHasWitness($nodeId)) {
                continue;
            }
            $fullBranches[$nodeId] = [
                'leftNodeId' => $branch['leftNodeId'],
                'rightNodeId' => $branch['rightNodeId'],
                'hash' => $branch['hash'],
            ];
        }
        ksort($fullBranches, SORT_NUMERIC);

        $projectionNodes = self::rawProjectionNodes($rawLeaves, $rawBranches, $trackedKeysByNodeId);
        $partialProofHeads = [];
        $fullHeads = [];
        $nextStorageOrdinal = 1;
        foreach ($heads as $head => $nodeId) {
            if ($nodeId !== 0) {
                $nodeHash($nodeId);
            }
            if ($nodeId !== 0 && $subtreeHasWitness($nodeId)) {
                $partialProofHeads[$head] = self::rawProjectionPartialState(
                    $head,
                    $nodeId,
                    $nodeHash($nodeId),
                    $projectionNodes,
                    $nextStorageOrdinal++
                );
                continue;
            }

            $fullHeads[$head] = $nodeId;
        }
        ksort($partialProofHeads, SORT_STRING);
        ksort($fullHeads, SORT_STRING);

        $partialDetachedHead = null;
        if ($detachedHeadNodeId !== 0) {
            $nodeHash($detachedHeadNodeId);
            if ($subtreeHasWitness($detachedHeadNodeId)) {
                $partialDetachedHead = self::rawProjectionPartialState(
                    null,
                    $detachedHeadNodeId,
                    $nodeHash($detachedHeadNodeId),
                    $projectionNodes,
                    $nextStorageOrdinal
                );
                $detachedHeadNodeId = 0;
            }
        }

        $snapshot = [
            'leaves' => $fullLeaves,
            'branches' => $fullBranches,
            'heads' => $fullHeads,
            'nextLeafNodeId' => self::nextLmdbLeafNodeId($fullLeaves),
            'nextBranchNodeId' => self::nextLmdbInteriorNodeId($fullBranches),
            'nextMemStoreNodeId' => TrackedNodeStore::FIRST_MEMSTORE_NODE_ID,
        ];

        $nodeStore = TrackedNodeStore::fromSnapshot($snapshot);
        if ($detachedHeadNodeId !== 0) {
            $nodeStore->nodeHash($detachedHeadNodeId);
        }

        return [
            'trackedNodeStore' => $snapshot,
            'trackedKeys' => $trackedKeys,
            'partialProofHeads' => $partialProofHeads,
            'partialDetachedHead' => $partialDetachedHead,
            'currentHead' => $currentHead,
            'detachedHeadNodeId' => $detachedHeadNodeId,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $rawLeaves
     * @param array<int, array<string, mixed>> $rawBranches
     * @param array<int, string> $trackedKeysByNodeId
     *
     * @return array<string, array<string, mixed>>
     */
    private static function rawProjectionNodes(array $rawLeaves, array $rawBranches, array $trackedKeysByNodeId): array
    {
        $nodes = [];
        foreach ($rawLeaves as $nodeId => $leaf) {
            if ($leaf['type'] === 'leaf') {
                $node = [
                    'type' => 'leaf',
                    'hash' => $leaf['hash'],
                    'keyHash' => $leaf['keyHash'],
                    'valueHex' => bin2hex($leaf['value']),
                ];
            } elseif ($leaf['type'] === 'witnessLeaf') {
                $node = [
                    'type' => 'witnessLeaf',
                    'hash' => $leaf['hash'],
                    'keyHash' => $leaf['keyHash'],
                    'valueHash' => $leaf['valueHash'],
                ];
            } else {
                throw new \RuntimeException('unrecognized raw LMDB leaf projection node type');
            }

            if (isset($trackedKeysByNodeId[$nodeId])) {
                $node['leafKeyHex'] = bin2hex($trackedKeysByNodeId[$nodeId]);
            }

            $nodes[(string) $nodeId] = $node;
        }

        foreach ($rawBranches as $nodeId => $branch) {
            if ($branch['type'] === 'witness') {
                $nodes[(string) $nodeId] = [
                    'type' => 'witness',
                    'hash' => $branch['hash'],
                ];
            } elseif ($branch['type'] === 'branch') {
                $nodes[(string) $nodeId] = [
                    'type' => 'branch',
                    'leftNodeId' => $branch['leftNodeId'],
                    'rightNodeId' => $branch['rightNodeId'],
                    'hash' => $branch['hash'],
                ];
            } else {
                throw new \RuntimeException('unrecognized raw LMDB interior projection node type');
            }
        }

        ksort($nodes, SORT_NUMERIC);

        return $nodes;
    }

    /**
     * @param array<string, array<string, mixed>> $projectionNodes
     *
     * @return array<string, mixed>
     */
    private static function rawProjectionPartialState(
        ?string $head,
        int $rootNodeId,
        string $rootHash,
        array $projectionNodes,
        int $storageOrdinal
    ): array {
        return [
            'rawProjection' => true,
            'rootHash' => $rootHash,
            'proofRootHash' => $rootHash,
            'storageId' => 'raw-entry-' . substr(hash(
                'sha256',
                ($head ?? '[detached]') . "\0" . $storageOrdinal . "\0" . $rootNodeId . "\0" . $rootHash
            ), 0, 32),
            'storageOrdinal' => $storageOrdinal,
            'trackKeys' => self::rawProjectionSubtreeHasTrackedKeys($rootNodeId, $projectionNodes),
            'proofStoragePruned' => true,
            'proofStoragePrunedProjection' => [
                'rootNodeId' => $rootNodeId,
                'nodes' => $projectionNodes,
            ],
            'proofs' => [],
            'updates' => [],
            'events' => [],
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $projectionNodes
     */
    private static function rawProjectionSubtreeHasTrackedKeys(int $rootNodeId, array $projectionNodes): bool
    {
        $visit = static function (int $nodeId) use (&$visit, $projectionNodes): bool {
            if ($nodeId === 0) {
                return false;
            }
            $node = $projectionNodes[(string) $nodeId] ?? null;
            if (!is_array($node)) {
                throw new \RuntimeException('raw projection references an unknown node');
            }

            if (($node['type'] ?? null) === 'leaf' || ($node['type'] ?? null) === 'witnessLeaf') {
                return array_key_exists('leafKeyHex', $node);
            }
            if (($node['type'] ?? null) === 'witness') {
                return false;
            }
            if (($node['type'] ?? null) === 'branch') {
                return $visit((int) $node['leftNodeId']) || $visit((int) $node['rightNodeId']);
            }

            throw new \RuntimeException('unrecognized raw projection node type');
        };

        return $visit($rootNodeId);
    }

    /**
     * @param list<array{keyHex: string, valueHex: string}> $entries
     *
     * @return array{?string, int}
     */
    private static function parseRawQuadbState(array $entries): array
    {
        if ($entries === []) {
            throw new \InvalidArgumentException('raw LMDB quadb state bucket is empty');
        }

        $currentHead = 'master';
        $detachedHeadNodeId = 0;
        $seenCurrent = false;
        $seenDetached = false;
        $seenKeys = [];

        foreach ($entries as $entry) {
            $key = self::decodeRawEntryHexBytes($entry['keyHex'], 'raw LMDB quadb state key');
            if (isset($seenKeys[$key])) {
                throw new \InvalidArgumentException('raw LMDB quadb state bucket contains a duplicate key');
            }
            $seenKeys[$key] = true;

            if ($key === 'currHead') {
                $currentHead = self::decodeRawEntryHexBytes($entry['valueHex'], 'raw LMDB current head');
                self::assertHeadNameValue($currentHead);
                $seenCurrent = true;
                continue;
            }

            if ($key === 'detachedHead') {
                $detachedHeadNodeId = self::unpackUInt64Le(
                    self::decodeRawEntryHexBytes($entry['valueHex'], 'raw LMDB detached head value'),
                    'raw LMDB detached head node id'
                );
                $currentHead = null;
                $seenDetached = true;
                continue;
            }

            throw new \InvalidArgumentException('raw LMDB quadb state bucket contains an unknown key');
        }

        if ($seenCurrent && $seenDetached) {
            throw new \InvalidArgumentException('raw LMDB quadb state cannot contain both currHead and detachedHead');
        }

        return [$currentHead, $detachedHeadNodeId];
    }

    /**
     * @return array<string, mixed>
     */
    private static function decodeRawLmdbLeafRecord(string $value): array
    {
        if (strlen($value) < 72) {
            throw new \InvalidArgumentException('raw LMDB leaf value is too short');
        }

        $nodeType = self::unpackUInt64Le(substr($value, 0, 8), 'raw LMDB leaf node type');
        if ($nodeType === self::NODE_TYPE_WITNESS_LEAF) {
            if (strlen($value) !== 104) {
                throw new \InvalidArgumentException('raw LMDB witness leaf value must be exactly 104 bytes');
            }

            return [
                'type' => 'witnessLeaf',
                'hash' => self::parseRawHashBytes(substr($value, 8, 32), 'raw LMDB witness leaf hash'),
                'keyHash' => self::parseRawHashBytes(substr($value, 40, 32), 'raw LMDB witness leaf key hash'),
                'valueHash' => self::parseRawHashBytes(substr($value, 72, 32), 'raw LMDB witness leaf value hash'),
            ];
        }
        if ($nodeType !== self::NODE_TYPE_LEAF) {
            throw new \InvalidArgumentException('raw LMDB leaf bucket contains a non-leaf node');
        }

        return [
            'type' => 'leaf',
            'hash' => self::parseRawHashBytes(substr($value, 8, 32), 'raw LMDB leaf hash'),
            'keyHash' => self::parseRawHashBytes(substr($value, 40, 32), 'raw LMDB leaf key hash'),
            'value' => substr($value, 72),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function decodeRawLmdbInteriorRecord(string $value): array
    {
        if (strlen($value) !== 48) {
            throw new \InvalidArgumentException('raw LMDB branch value must be exactly 48 bytes');
        }

        $firstWord = self::unpackUInt64Le(substr($value, 0, 8), 'raw LMDB branch reference word');
        if ($firstWord === self::NODE_TYPE_WITNESS) {
            $secondNodeId = self::unpackUInt64Le(substr($value, 40, 8), 'raw LMDB witness branch padding');
            if ($secondNodeId !== 0) {
                throw new \InvalidArgumentException('raw LMDB witness branch record is malformed');
            }

            return [
                'type' => 'witness',
                'hash' => self::parseRawHashBytes(substr($value, 8, 32), 'raw LMDB witness branch hash'),
            ];
        }

        $nodeType = $firstWord & 0xf;
        $firstNodeId = intdiv($firstWord, 16);
        $secondNodeId = self::unpackUInt64Le(substr($value, 40, 8), 'raw LMDB branch second child');

        if ($nodeType === self::NODE_TYPE_BRANCH_LEFT) {
            if ($firstNodeId === 0 || $secondNodeId !== 0) {
                throw new \InvalidArgumentException('raw LMDB left-branch record is malformed');
            }
            $leftNodeId = $firstNodeId;
            $rightNodeId = 0;
        } elseif ($nodeType === self::NODE_TYPE_BRANCH_RIGHT) {
            if ($firstNodeId === 0 || $secondNodeId !== 0) {
                throw new \InvalidArgumentException('raw LMDB right-branch record is malformed');
            }
            $leftNodeId = 0;
            $rightNodeId = $firstNodeId;
        } elseif ($nodeType === self::NODE_TYPE_BRANCH_BOTH) {
            if ($firstNodeId === 0 || $secondNodeId === 0) {
                throw new \InvalidArgumentException('raw LMDB two-child branch record is malformed');
            }
            $leftNodeId = $firstNodeId;
            $rightNodeId = $secondNodeId;
        } else {
            throw new \InvalidArgumentException('raw LMDB interior bucket contains an unknown branch type');
        }

        return [
            'type' => 'branch',
            'leftNodeId' => $leftNodeId,
            'rightNodeId' => $rightNodeId,
            'hash' => self::parseRawHashBytes(substr($value, 8, 32), 'raw LMDB branch hash'),
        ];
    }

    private static function parseRawUInt64EntryKey(string $keyHex, string $label): int
    {
        return self::unpackUInt64Le(self::decodeRawEntryHexBytes($keyHex, $label), $label);
    }

    private static function decodeRawEntryHexBytes(string $hex, string $label): string
    {
        $bytes = hex2bin($hex);
        if ($bytes === false) {
            throw new \InvalidArgumentException($label . ' must be lowercase byte hex');
        }

        return $bytes;
    }

    private static function parseRawHashBytes(string $bytes, string $label): string
    {
        if (strlen($bytes) !== 32) {
            throw new \InvalidArgumentException($label . ' must be exactly 32 bytes');
        }

        return bin2hex($bytes);
    }

    private function assertHeadName(string $head): void
    {
        self::assertHeadNameValue($head);
    }

    private static function assertHeadNameValue(string $head): void
    {
        if ($head === '') {
            throw new \InvalidArgumentException('head name must be non-empty');
        }
    }

    private static function parseNonNegativeNodeId(mixed $value, string $label): int
    {
        if (is_int($value)) {
            $nodeId = $value;
        } elseif (is_string($value) && preg_match('/^(0|[1-9][0-9]*)$/', $value)) {
            $max = (string) PHP_INT_MAX;
            if (strlen($value) > strlen($max) || (strlen($value) === strlen($max) && strcmp($value, $max) > 0)) {
                throw new \InvalidArgumentException($label . ' exceeds PHP integer range');
            }

            $nodeId = (int) $value;
        } else {
            throw new \InvalidArgumentException($label . ' must be a non-negative integer');
        }

        if ($nodeId < 0) {
            throw new \InvalidArgumentException($label . ' must be non-negative');
        }

        return $nodeId;
    }

    private function keyHash(string $key): string
    {
        return (new HashTree())->keyHash($key);
    }

    private function assertWritableFullHead(): void
    {
        if ($this->currentPartialProofState() !== null) {
            throw new \RuntimeException('current head is a proof-backed partial tree');
        }
    }

    private function trackedTreeNumBytes(TrackedSparseTree $tree): int
    {
        $numBytes = 0;
        foreach ($tree->walkNodeIds() as $nodeId) {
            if ($this->nodeStore->isLeaf($nodeId)) {
                $numBytes += 72 + strlen($this->nodeStore->leaf($nodeId)['value']);
            } elseif ($this->nodeStore->isBranch($nodeId)) {
                $numBytes += 48;
            } else {
                throw new \RuntimeException('stats traversal encountered an unknown node');
            }
        }

        return $numBytes;
    }

    private function pruneTrackedKeysToStoredLeaves(): void
    {
        $snapshot = $this->nodeStore->exportSnapshot();
        $liveKeyHashes = [];
        foreach ($snapshot['leaves'] as $leaf) {
            $liveKeyHashes[$leaf['keyHash']] = true;
        }

        foreach (array_keys($this->trackedKeys) as $keyHash) {
            if (!isset($liveKeyHashes[$keyHash])) {
                unset($this->trackedKeys[$keyHash]);
            }
        }

        foreach (array_keys($this->compositeKeys) as $keyHash) {
            if (!isset($liveKeyHashes[$keyHash])) {
                unset($this->compositeKeys[$keyHash]);
            }
        }
    }

    private function hasRawProjectionPartialProofStorage(): bool
    {
        foreach ($this->partialProofHeads as $state) {
            if (($state['rawProjection'] ?? false) === true) {
                return true;
            }
        }

        return is_array($this->partialDetachedHead)
            && (($this->partialDetachedHead['rawProjection'] ?? false) === true);
    }

    /**
     * Projects a proof-backed head into upstream-shaped LMDB nodes. Before a
     * native GC pass, proof events are replayed as separate importProof writes
     * so mergeProof leaves the same kind of unreferenced imported nodes that the
     * C++ store later sweeps.
     *
     * @param array<string, mixed> $state
     * @param array<int, string> $leaves
     * @param array<int, string> $leafKeys
     * @param array<int, string> $branches
     * @param array<int, array<string, mixed>> $projectedNodes
     * @param array<string, array{root: int, map: array<int, int>}> $eventProjectionCache
     */
    private function projectPartialStateLmdbNodes(
        array $state,
        array &$leaves,
        array &$leafKeys,
        array &$branches,
        int &$nextLeafNodeId,
        int &$nextInteriorNodeId,
        array &$projectedNodes,
        array &$eventProjectionCache
    ): int {
        $trackKeys = $this->partialStateTracksKeys($state);

        if (($state['proofStoragePruned'] ?? false) === true) {
            if (isset($state['proofStoragePrunedProjection']) && is_array($state['proofStoragePrunedProjection'])) {
                return $this->applyPrunedPartialProjectionLmdbNodes(
                    $state['proofStoragePrunedProjection'],
                    $leaves,
                    $leafKeys,
                    $branches,
                    $nextLeafNodeId,
                    $nextInteriorNodeId,
                    $projectedNodes,
                    $trackKeys
                );
            }

            return $this->projectPartialTreeLmdbNodes(
                $this->partialTreeFromState($state),
                $leaves,
                $leafKeys,
                $branches,
                $nextLeafNodeId,
                $nextInteriorNodeId,
                $projectedNodes,
                trackKeys: $trackKeys
            );
        }

        $current = null;
        $currentProjectedRoot = null;
        $currentProjectionMap = [];

        foreach ($state['events'] as $eventIndex => $event) {
            $prefixKey = self::partialEventProjectionKey($state, $eventIndex + 1);

            if ($event['type'] === 'proof') {
                $proof = Proof::decode((string) hex2bin($event['proof']));
                $imported = SparseTree::importProof($proof, $event['rootHash']);

                if ($current === null) {
                    $current = $imported;
                    if (isset($eventProjectionCache[$prefixKey])) {
                        $currentProjectedRoot = $eventProjectionCache[$prefixKey]['root'];
                        $currentProjectionMap = $eventProjectionCache[$prefixKey]['map'];

                        continue;
                    }

                    $currentProjectionMap = [];
                    $currentProjectedRoot = $this->projectPartialTreeLmdbNodes(
                        $imported,
                        $leaves,
                        $leafKeys,
                        $branches,
                        $nextLeafNodeId,
                        $nextInteriorNodeId,
                        $projectedNodes,
                        [],
                        $currentProjectionMap,
                        $trackKeys
                    );
                    $eventProjectionCache[$prefixKey] = [
                        'root' => $currentProjectedRoot,
                        'map' => $currentProjectionMap,
                    ];

                    continue;
                }

                if ($current->rootHash() !== $event['rootHash']) {
                    throw new \RuntimeException('partial proof event root mismatch');
                }

                $current->mergeProof($proof);
                if (isset($eventProjectionCache[$prefixKey])) {
                    $currentProjectedRoot = $eventProjectionCache[$prefixKey]['root'];
                    $currentProjectionMap = $eventProjectionCache[$prefixKey]['map'];

                    continue;
                }

                $importedProjectionMap = [];
                $importedProjectedRoot = $this->projectPartialTreeLmdbNodes(
                    $imported,
                    $leaves,
                    $leafKeys,
                    $branches,
                    $nextLeafNodeId,
                    $nextInteriorNodeId,
                    $projectedNodes,
                    [],
                    $importedProjectionMap,
                    $trackKeys
                );
                $currentProjectedRoot = $this->mergeProjectedProofNodes(
                    (int) $currentProjectedRoot,
                    $importedProjectedRoot,
                    $projectedNodes,
                    $branches,
                    $nextInteriorNodeId
                );
                $currentProjectionMap = [];
                $eventProjectionCache[$prefixKey] = [
                    'root' => $currentProjectedRoot,
                    'map' => $currentProjectionMap,
                ];

                continue;
            }

            if ($current === null) {
                throw new \RuntimeException('partial proof update occurred before proof import');
            }

            $rawUpdate = [
                'delete' => $event['delete'],
                'value' => $event['value'],
            ];
            if (isset($event['key'])) {
                $rawUpdate['key'] = $event['key'];
            }

            $current->applyRawUpdates([
                $event['keyHash'] => $rawUpdate,
            ]);
            if (isset($eventProjectionCache[$prefixKey])) {
                $currentProjectedRoot = $eventProjectionCache[$prefixKey]['root'];
                $currentProjectionMap = $eventProjectionCache[$prefixKey]['map'];

                continue;
            }

            $projectionMap = [];
            $currentProjectedRoot = $this->projectPartialTreeLmdbNodes(
                $current,
                $leaves,
                $leafKeys,
                $branches,
                $nextLeafNodeId,
                $nextInteriorNodeId,
                $projectedNodes,
                $currentProjectionMap,
                $projectionMap,
                $trackKeys
            );
            $currentProjectionMap = $projectionMap;
            $eventProjectionCache[$prefixKey] = [
                'root' => $currentProjectedRoot,
                'map' => $currentProjectionMap,
            ];
        }

        if ($current === null) {
            throw new \RuntimeException('partial proof state has no proofs');
        }
        if ($current->rootHash() !== $state['rootHash']) {
            throw new \RuntimeException('partial proof state root mismatch');
        }

        if ($currentProjectedRoot !== null) {
            return (int) $currentProjectedRoot;
        }

        return $this->projectPartialTreeLmdbNodes(
            $this->partialTreeFromState($state),
            $leaves,
            $leafKeys,
            $branches,
            $nextLeafNodeId,
            $nextInteriorNodeId,
            $projectedNodes,
            trackKeys: $trackKeys
        );
    }

    /**
     * @param array<int, string> $leaves
     * @param array<int, string> $leafKeys
     * @param array<int, string> $branches
     * @param array<int, array<string, mixed>> $projectedNodes
     */
    private function projectPartialTreeLmdbNodes(
        SparseTree $partial,
        array &$leaves,
        array &$leafKeys,
        array &$branches,
        int &$nextLeafNodeId,
        int &$nextInteriorNodeId,
        array &$projectedNodes,
        array $reuseNodeIds = [],
        ?array &$nativeNodeIdMap = null,
        ?bool $trackKeys = null
    ): int {
        $trackKeys ??= $this->trackKeys;
        $partialSnapshot = $partial->partialStorageSnapshot();
        $idMap = [0 => 0];
        $pending = [];

        foreach ($partialSnapshot['leaves'] as $nativeNodeId => $leaf) {
            $pending[(int) $nativeNodeId] = [
                'kind' => 'leaf',
                'record' => $leaf,
            ];
        }
        foreach ($partialSnapshot['branches'] as $nativeNodeId => $branch) {
            $pending[(int) $nativeNodeId] = [
                'kind' => $branch['type'] === 'witness' ? 'witness' : 'branch',
                'record' => $branch,
            ];
        }
        ksort($pending, SORT_NUMERIC);

        while ($pending !== []) {
            $progress = false;

            foreach ($pending as $nativeNodeId => $entry) {
                $record = $entry['record'];

                if (isset($reuseNodeIds[$nativeNodeId])) {
                    $mappedNodeId = $reuseNodeIds[$nativeNodeId];
                    if ($mappedNodeId !== 0 && !isset($projectedNodes[$mappedNodeId])) {
                        throw new \RuntimeException('partial proof storage projection references an unknown reusable node');
                    }

                    $idMap[$nativeNodeId] = $mappedNodeId;
                    unset($pending[$nativeNodeId]);
                    $progress = true;

                    continue;
                }

                if ($entry['kind'] === 'leaf') {
                    $mappedNodeId = $nextLeafNodeId++;
                    $idMap[$nativeNodeId] = $mappedNodeId;
                    $leaves[$mappedNodeId] = self::encodeLmdbPartialLeafNode($record);
                    $projectedNodes[$mappedNodeId] = $record;
                    if ($trackKeys && isset($record['key']) && $record['key'] !== '') {
                        $leafKeys[$mappedNodeId] = $record['key'];
                    }
                    unset($pending[$nativeNodeId]);
                    $progress = true;

                    continue;
                }

                if ($entry['kind'] === 'witness') {
                    $mappedNodeId = $nextInteriorNodeId++;
                    $idMap[$nativeNodeId] = $mappedNodeId;
                    $branches[$mappedNodeId] = self::encodeLmdbWitnessNode($record);
                    $projectedNodes[$mappedNodeId] = $record;
                    unset($pending[$nativeNodeId]);
                    $progress = true;

                    continue;
                }

                $leftNativeNodeId = (int) $record['leftNodeId'];
                $rightNativeNodeId = (int) $record['rightNodeId'];
                if (!array_key_exists($leftNativeNodeId, $idMap) || !array_key_exists($rightNativeNodeId, $idMap)) {
                    continue;
                }

                $mappedNodeId = $nextInteriorNodeId++;
                $mappedRecord = [
                    'type' => 'branch',
                    'leftNodeId' => $idMap[$leftNativeNodeId],
                    'rightNodeId' => $idMap[$rightNativeNodeId],
                    'hash' => $record['hash'],
                ];
                $idMap[$nativeNodeId] = $mappedNodeId;
                $branches[$mappedNodeId] = self::encodeLmdbBranchNode($mappedRecord);
                $projectedNodes[$mappedNodeId] = $mappedRecord;
                unset($pending[$nativeNodeId]);
                $progress = true;
            }

            if (!$progress) {
                throw new \RuntimeException('partial proof storage projection contains unresolved child nodes');
            }
        }

        ksort($leaves, SORT_NUMERIC);
        ksort($leafKeys, SORT_NUMERIC);
        ksort($branches, SORT_NUMERIC);

        $rootNodeId = (int) $partialSnapshot['rootNodeId'];
        $nativeNodeIdMap = $idMap;

        return $idMap[$rootNodeId] ?? 0;
    }

    /**
     * Re-applies the exact projected LMDB node ids that survived an upstream-
     * shaped GC pass. Upstream deletes garbage records in place, so retained
     * proof nodes keep their original integer LMDB keys after `quadb gc`.
     *
     * @param array<string, mixed> $projection
     * @param array<int, string> $leaves
     * @param array<int, string> $leafKeys
     * @param array<int, string> $branches
     * @param array<int, array<string, mixed>> $projectedNodes
     */
    private function applyPrunedPartialProjectionLmdbNodes(
        array $projection,
        array &$leaves,
        array &$leafKeys,
        array &$branches,
        int &$nextLeafNodeId,
        int &$nextInteriorNodeId,
        array &$projectedNodes,
        ?bool $trackKeys = null
    ): int {
        $trackKeys ??= $this->trackKeys;
        $rootNodeId = self::parseNonNegativeNodeId($projection['rootNodeId'] ?? 0, 'pruned proof root node id');
        $nodes = $projection['nodes'] ?? [];
        if (!is_array($nodes)) {
            throw new \RuntimeException('pruned proof projection nodes are malformed');
        }

        ksort($nodes, SORT_NUMERIC);
        foreach ($nodes as $nodeIdRaw => $encodedNode) {
            $nodeId = self::parseNonNegativeNodeId($nodeIdRaw, 'pruned proof node id');
            if (!is_array($encodedNode)) {
                throw new \RuntimeException('pruned proof projection node is malformed');
            }

            $leafKey = null;
            $record = self::decodePrunedProjectedNode($encodedNode, $leafKey);
            if (($record['type'] ?? null) === 'leaf' || ($record['type'] ?? null) === 'witnessLeaf') {
                if ($nodeId >= TrackedNodeStore::FIRST_INTERIOR_NODE_ID) {
                    throw new \RuntimeException('pruned proof leaf node id is outside the leaf id range');
                }

                $raw = self::encodeLmdbPartialLeafNode($record);
                if (isset($leaves[$nodeId]) && $leaves[$nodeId] !== $raw) {
                    throw new \RuntimeException('pruned proof projection collides with an existing leaf node id');
                }
                $leaves[$nodeId] = $raw;
                if ($leafKey !== null) {
                    if (isset($leafKeys[$nodeId]) && $leafKeys[$nodeId] !== $leafKey) {
                        throw new \RuntimeException('pruned proof projection collides with an existing tracked key id');
                    }
                    $leafKeys[$nodeId] = $leafKey;
                }
            } elseif (($record['type'] ?? null) === 'witness') {
                if ($nodeId < TrackedNodeStore::FIRST_INTERIOR_NODE_ID) {
                    throw new \RuntimeException('pruned proof witness node id is outside the interior id range');
                }

                $raw = self::encodeLmdbWitnessNode($record);
                if (isset($branches[$nodeId]) && $branches[$nodeId] !== $raw) {
                    throw new \RuntimeException('pruned proof projection collides with an existing witness node id');
                }
                $branches[$nodeId] = $raw;
            } elseif (($record['type'] ?? null) === 'branch') {
                if ($nodeId < TrackedNodeStore::FIRST_INTERIOR_NODE_ID) {
                    throw new \RuntimeException('pruned proof branch node id is outside the interior id range');
                }

                $raw = self::encodeLmdbBranchNode($record);
                if (isset($branches[$nodeId]) && $branches[$nodeId] !== $raw) {
                    throw new \RuntimeException('pruned proof projection collides with an existing branch node id');
                }
                $branches[$nodeId] = $raw;
            } else {
                throw new \RuntimeException('unrecognized pruned proof projection node type');
            }

            if (isset($projectedNodes[$nodeId]) && $projectedNodes[$nodeId] !== $record) {
                throw new \RuntimeException('pruned proof projection collides with an existing projected node');
            }
            $projectedNodes[$nodeId] = $record;
        }

        $staleLeafKeys = $projection['staleLeafKeys'] ?? [];
        if (!is_array($staleLeafKeys)) {
            throw new \RuntimeException('pruned proof stale tracked keys are malformed');
        }
        foreach ($staleLeafKeys as $nodeIdRaw => $leafKeyHex) {
            $nodeId = self::parseNonNegativeNodeId($nodeIdRaw, 'pruned proof stale tracked-key node id');
            $leafKey = self::decodeEvenHexBytes($leafKeyHex, 'pruned proof stale tracked key');
            if (isset($leafKeys[$nodeId]) && $leafKeys[$nodeId] !== $leafKey) {
                throw new \RuntimeException('pruned proof stale tracked key collides with an existing tracked key id');
            }
            $leafKeys[$nodeId] = $leafKey;
        }

        ksort($leaves, SORT_NUMERIC);
        ksort($leafKeys, SORT_NUMERIC);
        ksort($branches, SORT_NUMERIC);
        $nextLeafNodeId = max($nextLeafNodeId, self::nextLmdbLeafNodeId($leaves));
        $nextInteriorNodeId = max($nextInteriorNodeId, self::nextLmdbInteriorNodeId($branches));

        return $rootNodeId;
    }

    /**
     * @param array<int, array<string, mixed>> $projectedNodes
     * @param array<int, string> $branches
     */
    private function mergeProjectedProofNodes(
        int $originalNodeId,
        int $newNodeId,
        array &$projectedNodes,
        array &$branches,
        int &$nextInteriorNodeId
    ): int {
        $original = $this->projectedNodeRecord($originalNodeId, $projectedNodes);
        $new = $this->projectedNodeRecord($newNodeId, $projectedNodes);

        if (($this->isProjectedWitnessAny($original) && !$this->isProjectedWitnessAny($new))
            || ($original['type'] === 'witness' && $new['type'] === 'witnessLeaf')
        ) {
            return $newNodeId;
        }

        if ($original['type'] === 'branch' && $new['type'] === 'branch') {
            $leftNodeId = $this->mergeProjectedProofNodes(
                (int) $original['leftNodeId'],
                (int) $new['leftNodeId'],
                $projectedNodes,
                $branches,
                $nextInteriorNodeId
            );
            $rightNodeId = $this->mergeProjectedProofNodes(
                (int) $original['rightNodeId'],
                (int) $new['rightNodeId'],
                $projectedNodes,
                $branches,
                $nextInteriorNodeId
            );

            if ($original['leftNodeId'] === $leftNodeId && $original['rightNodeId'] === $rightNodeId) {
                return $originalNodeId;
            }
            if ($new['leftNodeId'] === $leftNodeId && $new['rightNodeId'] === $rightNodeId) {
                return $newNodeId;
            }

            $nodeId = $nextInteriorNodeId++;
            $record = [
                'type' => 'branch',
                'leftNodeId' => $leftNodeId,
                'rightNodeId' => $rightNodeId,
                'hash' => (new HashTree())->branchHash(
                    $this->projectedNodeHash($leftNodeId, $projectedNodes),
                    $this->projectedNodeHash($rightNodeId, $projectedNodes)
                ),
            ];
            $branches[$nodeId] = self::encodeLmdbBranchNode($record);
            $projectedNodes[$nodeId] = $record;
            ksort($branches, SORT_NUMERIC);

            return $nodeId;
        }

        return $originalNodeId;
    }

    /**
     * @param array<int, array<string, mixed>> $projectedNodes
     *
     * @return array<string, mixed>
     */
    private function projectedNodeRecord(int $nodeId, array $projectedNodes): array
    {
        if ($nodeId === 0) {
            return [
                'type' => 'empty',
                'hash' => HashTree::EMPTY_HASH,
            ];
        }
        if (!isset($projectedNodes[$nodeId])) {
            throw new \RuntimeException('partial proof storage projection references an unknown node');
        }

        return $projectedNodes[$nodeId];
    }

    /**
     * @param array<int, array<string, mixed>> $projectedNodes
     */
    private function projectedNodeHash(int $nodeId, array $projectedNodes): string
    {
        return $this->projectedNodeRecord($nodeId, $projectedNodes)['hash'];
    }

    /**
     * @param array<string, mixed> $node
     */
    private function isProjectedWitnessAny(array $node): bool
    {
        return $node['type'] === 'witness' || $node['type'] === 'witnessLeaf';
    }

    /**
     * @return array{total: int, garbage: int}
     */
    private function garbageCollectPartialProofStorage(): array
    {
        if ($this->partialProofHeads === [] && $this->partialDetachedHead === null) {
            return [
                'total' => 0,
                'garbage' => 0,
            ];
        }

        $leaves = [];
        $leafKeys = [];
        $branches = [];
        $projectedNodes = [];
        $nextLeafNodeId = 1;
        $nextInteriorNodeId = TrackedNodeStore::FIRST_INTERIOR_NODE_ID;
        $partialProjectionRoots = [];
        $partialEventProjectionCache = [];
        $markedRoots = [];
        $partialHeadRootNodeIds = [];
        $partialDetachedRootNodeId = null;
        $rawProjectionPartialStorage = $this->hasRawProjectionPartialProofStorage();

        $projectState = function (array $state) use (
            &$leaves,
            &$leafKeys,
            &$branches,
            &$projectedNodes,
            &$nextLeafNodeId,
            &$nextInteriorNodeId,
            &$partialProjectionRoots,
            &$partialEventProjectionCache
        ): int {
            $stateKey = hash(
                'sha256',
                self::jsonEncodeBinarySafe($state)
            );
            if (isset($partialProjectionRoots[$stateKey])) {
                return $partialProjectionRoots[$stateKey];
            }

            $partialProjectionRoots[$stateKey] = $this->projectPartialStateLmdbNodes(
                $state,
                $leaves,
                $leafKeys,
                $branches,
                $nextLeafNodeId,
                $nextInteriorNodeId,
                $projectedNodes,
                $partialEventProjectionCache
            );

            return $partialProjectionRoots[$stateKey];
        };

        foreach ($this->partialProofHeads as $head => $state) {
            $rootNodeId = $projectState($state);
            $partialHeadRootNodeIds[(string) $head] = $rootNodeId;
            $markedRoots[] = $rootNodeId;
        }

        if ($this->partialDetachedHead !== null) {
            $detachedRoot = $projectState($this->partialDetachedHead);
            $partialDetachedRootNodeId = $detachedRoot;
            if ($this->currentHead === null) {
                $markedRoots[] = $detachedRoot;
            }
        }

        if ($rawProjectionPartialStorage) {
            foreach ($this->nodeStore->heads() as $nodeId) {
                if (isset($projectedNodes[$nodeId])) {
                    $markedRoots[] = $nodeId;
                }
            }
            if ($this->currentHead === null
                && $this->partialDetachedHead === null
                && $this->detachedHeadNodeId !== 0
                && isset($projectedNodes[$this->detachedHeadNodeId])
            ) {
                $markedRoots[] = $this->detachedHeadNodeId;
            }
        }

        $total = count($projectedNodes);
        $marked = [];
        foreach ($markedRoots as $rootNodeId) {
            $this->markProjectedNodeReachable($rootNodeId, $projectedNodes, $marked);
        }

        $garbage = 0;
        foreach (array_keys($projectedNodes) as $nodeId) {
            if (!isset($marked[$nodeId])) {
                $garbage++;
            }
        }

        if ($garbage > 0) {
            $this->markPartialProofStoragePruned(
                $projectedNodes,
                $leafKeys,
                $partialHeadRootNodeIds,
                $partialDetachedRootNodeId
            );
        }

        return [
            'total' => $total,
            'garbage' => $garbage,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $projectedNodes
     * @param array<int, true> $marked
     */
    private function markProjectedNodeReachable(int $nodeId, array $projectedNodes, array &$marked): void
    {
        if ($nodeId === 0 || isset($marked[$nodeId])) {
            return;
        }
        if (!isset($projectedNodes[$nodeId])) {
            throw new \RuntimeException('partial proof GC root references an unknown node');
        }

        $marked[$nodeId] = true;
        $node = $projectedNodes[$nodeId];
        if ($node['type'] === 'branch') {
            $this->markProjectedNodeReachable((int) $node['leftNodeId'], $projectedNodes, $marked);
            $this->markProjectedNodeReachable((int) $node['rightNodeId'], $projectedNodes, $marked);
        }
    }

    /**
     * @param array<int, array<string, mixed>> $projectedNodes
     * @param array<int, string> $leafKeys
     * @param array<string, int> $partialHeadRootNodeIds
     */
    private function markPartialProofStoragePruned(
        array $projectedNodes,
        array $leafKeys,
        array $partialHeadRootNodeIds,
        ?int $partialDetachedRootNodeId
    ): void
    {
        foreach ($this->partialProofHeads as $head => $state) {
            $state['proofStoragePruned'] = true;
            $state['proofStoragePrunedProjection'] = $this->prunedPartialProjection(
                $partialHeadRootNodeIds[(string) $head] ?? 0,
                $projectedNodes,
                $leafKeys,
                $this->currentHead === (string) $head && !$this->partialStateTracksKeys($state)
            );
            $this->partialProofHeads[$head] = $state;
        }
        if ($this->partialDetachedHead !== null) {
            $this->partialDetachedHead['proofStoragePruned'] = true;
            $this->partialDetachedHead['proofStoragePrunedProjection'] = $this->prunedPartialProjection(
                $partialDetachedRootNodeId ?? 0,
                $projectedNodes,
                $leafKeys,
                $this->currentHead === null && !$this->partialStateTracksKeys($this->partialDetachedHead)
            );
        }
    }

    /**
     * @param array<int, array<string, mixed>> $projectedNodes
     * @param array<int, string> $leafKeys
     *
     * @return array{rootNodeId: int, nodes: array<string, array<string, mixed>>}
     */
    private function prunedPartialProjection(
        int $rootNodeId,
        array $projectedNodes,
        array $leafKeys,
        bool $preserveStaleLeafKeys = false
    ): array
    {
        $marked = [];
        $this->markProjectedNodeReachable($rootNodeId, $projectedNodes, $marked);

        $nodes = [];
        foreach (array_keys($marked) as $nodeId) {
            $nodes[(string) $nodeId] = self::encodePrunedProjectedNode(
                $projectedNodes[$nodeId],
                $leafKeys[$nodeId] ?? null
            );
        }
        ksort($nodes, SORT_NUMERIC);

        $projection = [
            'rootNodeId' => $rootNodeId,
            'nodes' => $nodes,
        ];

        if ($preserveStaleLeafKeys) {
            $staleLeafKeys = [];
            foreach ($leafKeys as $nodeId => $leafKey) {
                if (!isset($marked[$nodeId])) {
                    $staleLeafKeys[(string) $nodeId] = bin2hex($leafKey);
                }
            }
            if ($staleLeafKeys !== []) {
                ksort($staleLeafKeys, SORT_NUMERIC);
                $projection['staleLeafKeys'] = $staleLeafKeys;
            }
        }

        return $projection;
    }

    /**
     * Reprojects a raw-entry-restored partial tree after a write. Existing raw
     * projection nodes are seeded first so untouched leaves, witnesses, and
     * orphan proof-import records keep their upstream LMDB ids until GC.
     *
     * @param array<string, mixed> $state
     *
     * @return array{rootNodeId: int, nodes: array<string, array<string, mixed>>}
     */
    private function rawProjectionAfterPartialMutation(array $state, SparseTree $partial): array
    {
        if (($state['rawProjection'] ?? false) !== true
            || !isset($state['proofStoragePrunedProjection'])
            || !is_array($state['proofStoragePrunedProjection'])
        ) {
            throw new \RuntimeException('raw projection state is malformed');
        }

        $leaves = [];
        $leafKeys = [];
        $branches = [];
        $projectedNodes = [];
        $nextLeafNodeId = 1;
        $nextInteriorNodeId = TrackedNodeStore::FIRST_INTERIOR_NODE_ID;
        $trackKeys = $this->partialStateTracksKeys($state);

        $this->applyPrunedPartialProjectionLmdbNodes(
            $state['proofStoragePrunedProjection'],
            $leaves,
            $leafKeys,
            $branches,
            $nextLeafNodeId,
            $nextInteriorNodeId,
            $projectedNodes,
            $trackKeys
        );

        $reuseNodeIds = [];
        foreach (array_keys($projectedNodes) as $nodeId) {
            $reuseNodeIds[(int) $nodeId] = (int) $nodeId;
        }

        $rootNodeId = $this->projectPartialTreeLmdbNodes(
            $partial,
            $leaves,
            $leafKeys,
            $branches,
            $nextLeafNodeId,
            $nextInteriorNodeId,
            $projectedNodes,
            $reuseNodeIds,
            trackKeys: $trackKeys
        );

        return $this->allProjectedPartialProjection($rootNodeId, $projectedNodes, $leafKeys);
    }

    /**
     * Reprojects a raw-entry-restored partial tree after mergeProof. The newly
     * imported proof is projected as a separate import first, matching the
     * upstream storage shape where mergeProof leaves now-unreachable imported
     * proof nodes for the next GC sweep.
     *
     * @param array<string, mixed> $state
     *
     * @return array{rootNodeId: int, nodes: array<string, array<string, mixed>>}
     */
    private function rawProjectionAfterProofMerge(array $state, Proof $proof, string $rootBeforeMerge): array
    {
        if (($state['rawProjection'] ?? false) !== true
            || !isset($state['proofStoragePrunedProjection'])
            || !is_array($state['proofStoragePrunedProjection'])
        ) {
            throw new \RuntimeException('raw projection state is malformed');
        }

        $leaves = [];
        $leafKeys = [];
        $branches = [];
        $projectedNodes = [];
        $nextLeafNodeId = 1;
        $nextInteriorNodeId = TrackedNodeStore::FIRST_INTERIOR_NODE_ID;
        $trackKeys = $this->partialStateTracksKeys($state);

        $currentProjectedRoot = $this->applyPrunedPartialProjectionLmdbNodes(
            $state['proofStoragePrunedProjection'],
            $leaves,
            $leafKeys,
            $branches,
            $nextLeafNodeId,
            $nextInteriorNodeId,
            $projectedNodes,
            $trackKeys
        );

        $imported = SparseTree::importProof($proof, $rootBeforeMerge);
        $importedProjectedRoot = $this->projectPartialTreeLmdbNodes(
            $imported,
            $leaves,
            $leafKeys,
            $branches,
            $nextLeafNodeId,
            $nextInteriorNodeId,
            $projectedNodes,
            trackKeys: $trackKeys
        );

        $mergedProjectedRoot = $this->mergeProjectedProofNodes(
            $currentProjectedRoot,
            $importedProjectedRoot,
            $projectedNodes,
            $branches,
            $nextInteriorNodeId
        );

        return $this->allProjectedPartialProjection($mergedProjectedRoot, $projectedNodes, $leafKeys);
    }

    /**
     * @param array<int, array<string, mixed>> $projectedNodes
     * @param array<int, string> $leafKeys
     *
     * @return array{rootNodeId: int, nodes: array<string, array<string, mixed>>}
     */
    private function allProjectedPartialProjection(int $rootNodeId, array $projectedNodes, array $leafKeys): array
    {
        $nodes = [];
        foreach ($projectedNodes as $nodeId => $node) {
            $nodes[(string) $nodeId] = self::encodePrunedProjectedNode(
                $node,
                $leafKeys[$nodeId] ?? null
            );
        }
        ksort($nodes, SORT_NUMERIC);

        return [
            'rootNodeId' => $rootNodeId,
            'nodes' => $nodes,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function currentPartialProofState(): ?array
    {
        if ($this->currentHead === null) {
            return $this->partialDetachedHead;
        }

        return $this->partialProofHeads[$this->currentHead] ?? null;
    }

    private function nextPartialStorageOrdinal(): int
    {
        $max = 0;
        foreach ($this->partialProofHeads as $state) {
            $max = max($max, self::partialStorageOrdinal($state));
        }
        if ($this->partialDetachedHead !== null) {
            $max = max($max, self::partialStorageOrdinal($this->partialDetachedHead));
        }

        return $max + 1;
    }

    /**
     * @param array<string, mixed> $state
     */
    private function storeCurrentPartialProofState(array $state): void
    {
        if ($this->currentHead === null) {
            $this->detachedHeadNodeId = 0;
            $this->partialDetachedHead = $state;

            return;
        }

        $this->partialProofHeads[$this->currentHead] = $state;
        $this->nodeStore->deleteHead($this->currentHead);
    }

    private function currentPartialTree(): ?SparseTree
    {
        $state = $this->currentPartialProofState();
        if ($state === null) {
            return null;
        }

        return $this->partialTreeFromState($state);
    }

    private function partialTreeForHead(string $head): SparseTree
    {
        if (!isset($this->partialProofHeads[$head])) {
            throw new \RuntimeException('head is not proof-backed');
        }

        return $this->partialTreeFromState($this->partialProofHeads[$head]);
    }

    /**
     * @param array<string, mixed> $state
     */
    private function partialTreeFromState(array $state): SparseTree
    {
        if (($state['rawProjection'] ?? false) === true) {
            $partial = SparseTree::fromPartialStorageSnapshot(
                self::partialStorageSnapshotFromPrunedProjection($state['proofStoragePrunedProjection'])
            );
            if ($partial->rootHash() !== $state['rootHash']) {
                throw new \RuntimeException('raw projection partial proof state root mismatch');
            }

            return $partial;
        }

        $partial = null;

        foreach ($state['events'] as $event) {
            if ($event['type'] === 'proof') {
                $proof = Proof::decode((string) hex2bin($event['proof']));
                if ($partial === null) {
                    $partial = SparseTree::importProof($proof, $event['rootHash']);
                    continue;
                }

                if ($partial->rootHash() !== $event['rootHash']) {
                    throw new \RuntimeException('partial proof event root mismatch');
                }

                $partial->mergeProof($proof);
                continue;
            }

            if ($partial === null) {
                throw new \RuntimeException('partial proof update occurred before proof import');
            }

            $rawUpdate = [
                'delete' => $event['delete'],
                'value' => $event['value'],
            ];
            if (isset($event['key'])) {
                $rawUpdate['key'] = $event['key'];
            }

            $partial->applyRawUpdates([
                $event['keyHash'] => $rawUpdate,
            ]);
        }

        if ($partial === null) {
            throw new \RuntimeException('partial proof state has no proofs');
        }
        if ($partial->rootHash() !== $state['rootHash']) {
            throw new \RuntimeException('partial proof state root mismatch');
        }

        return $partial;
    }

    private function applyPartialStringUpdate(string $key, bool $delete, string $value): void
    {
        $state = $this->currentPartialProofState();
        if ($state === null) {
            throw new \RuntimeException('current head is not a proof-backed partial tree');
        }
        $rawProjection = ($state['rawProjection'] ?? false) === true;
        $trackKeys = $this->partialStateTracksKeys($state);

        $keyHash = $this->keyHash($key);
        $partial = $this->partialTreeFromState($state);
        $rawUpdate = [
            'delete' => $delete,
            'value' => $value,
        ];
        if ($trackKeys) {
            $rawUpdate['key'] = $key;
        }

        $partial->applyRawUpdates([$keyHash => $rawUpdate]);

        $state['rootHash'] = $partial->rootHash();
        $update = [
            'delete' => $delete,
            'keyHash' => $keyHash,
            'value' => $value,
        ];
        if ($trackKeys) {
            $update['key'] = $key;
        }

        if ($rawProjection) {
            $state['proofStoragePruned'] = true;
            $state['proofStoragePrunedProjection'] = $this->rawProjectionAfterPartialMutation($state, $partial);
            $state['proofs'] = [];
            $state['updates'] = [];
            $state['events'] = [];
        } else {
            $state['proofStoragePruned'] = false;
            unset($state['proofStoragePrunedProjection']);
            $state['updates'][] = $update;
            $state['events'][] = [
                'type' => 'update',
            ] + $update;
        }
        if (!$delete) {
            $this->recordStringKeyWrite($key, $trackKeys);
        }

        $this->storeCurrentPartialProofState($state);
        $this->persist();
    }

    /**
     * @param array<string, array{delete: bool, value: string}> $updates
     */
    private function applyPartialRawUpdates(array $updates): void
    {
        $state = $this->currentPartialProofState();
        if ($state === null) {
            throw new \RuntimeException('current head is not a proof-backed partial tree');
        }
        $rawProjection = ($state['rawProjection'] ?? false) === true;

        ksort($updates, SORT_STRING);

        $partial = $this->partialTreeFromState($state);
        $partial->applyRawUpdates($updates);

        $state['rootHash'] = $partial->rootHash();
        if ($rawProjection) {
            $state['proofStoragePruned'] = true;
            $state['proofStoragePrunedProjection'] = $this->rawProjectionAfterPartialMutation($state, $partial);
            $state['proofs'] = [];
            $state['updates'] = [];
            $state['events'] = [];
        } else {
            $state['proofStoragePruned'] = false;
            unset($state['proofStoragePrunedProjection']);
            foreach ($updates as $keyHash => $update) {
                $record = [
                    'delete' => $update['delete'],
                    'keyHash' => $keyHash,
                    'value' => $update['value'],
                ];
                $state['updates'][] = $record;
                $state['events'][] = ['type' => 'update'] + $record;
            }
        }

        $this->storeCurrentPartialProofState($state);
        $this->persist();
    }

    private function currentRootHash(): string
    {
        $partial = $this->currentPartialTree();
        if ($partial !== null) {
            return $partial->rootHash();
        }

        return $this->tree()->rootHash();
    }

    private function renderCurrentRootNode(): string
    {
        $partial = $this->currentPartialTree();
        if ($partial !== null) {
            return $this->renderRootNode($partial->rootHash(), $partial->partialRootNodeId());
        }

        if ($this->currentHead === null) {
            return $this->renderNode($this->detachedHeadNodeId);
        }

        $tree = $this->tree();

        return $this->renderNode($tree->headNodeId());
    }

    private function normalizeOptionalRoot(?string $root): ?string
    {
        if ($root === null) {
            return null;
        }

        $root = trim($root);
        if (str_starts_with($root, '0x') || str_starts_with($root, '0X')) {
            $root = substr($root, 2);
        }
        if (!preg_match('/^[0-9a-f]{64}$/', $root)) {
            throw new \InvalidArgumentException('expected root must be lowercase 32-byte hex');
        }

        return $root;
    }

    private static function decodeProofHexText(string $proofHex): string
    {
        $hex = preg_replace('/\s+/', '', $proofHex);
        if (!is_string($hex)) {
            throw new \InvalidArgumentException('invalid hex proof text');
        }
        if (str_starts_with($hex, '0x') || str_starts_with($hex, '0X')) {
            $hex = substr($hex, 2);
        }
        if ($hex === '' || strlen($hex) % 2 !== 0 || !preg_match('/^[0-9a-fA-F]+$/', $hex)) {
            throw new \InvalidArgumentException('expected hexadecimal proof');
        }

        $decoded = hex2bin($hex);
        if ($decoded === false) {
            throw new \InvalidArgumentException('expected hexadecimal proof');
        }

        return $decoded;
    }

    private static function decodeCommandHexText(string $input): string
    {
        $hex = str_replace([" ", "\t", "\n", "\r", "\f", "\v"], '', $input);
        if (str_starts_with($hex, '0x')) {
            $hex = substr($hex, 2);
        }
        if (strlen($hex) % 2 !== 0) {
            $hex = '0' . $hex;
        }

        $output = '';
        for ($offset = 0, $length = strlen($hex); $offset < $length; $offset += 2) {
            $output .= chr(
                (self::decodeCommandHexNibble($hex[$offset]) << 4)
                | self::decodeCommandHexNibble($hex[$offset + 1])
            );
        }

        return $output;
    }

    private static function decodeCommandHexNibble(string $char): int
    {
        $code = ord($char);
        if ($code >= 48 && $code <= 57) {
            return $code - 48;
        }
        if ($code >= 97 && $code <= 102) {
            return $code - 87;
        }
        if ($code >= 65 && $code <= 70) {
            return $code - 55;
        }

        throw new \RuntimeException('unexpected character in from_hex: ' . $code);
    }

    /**
     * @return array<string, mixed>
     */
    private static function parsePartialProofState(mixed $state, string $label): array
    {
        if (is_array($state) && ($state['rawProjection'] ?? false) === true) {
            if (!isset($state['rootHash'])
                || !is_string($state['rootHash'])
                || !preg_match('/^[0-9a-f]{64}$/', $state['rootHash'])
                || (($state['proofStoragePruned'] ?? null) !== true)
            ) {
                throw new \InvalidArgumentException($label . ' raw projection is malformed');
            }

            $proofRootHash = $state['proofRootHash'] ?? $state['rootHash'];
            if (!is_string($proofRootHash) || !preg_match('/^[0-9a-f]{64}$/', $proofRootHash)) {
                throw new \InvalidArgumentException($label . ' raw projection has malformed proof root');
            }

            foreach (['proofs', 'updates', 'events'] as $emptyListKey) {
                if (array_key_exists($emptyListKey, $state)
                    && (!is_array($state[$emptyListKey]) || $state[$emptyListKey] !== [])
                ) {
                    throw new \InvalidArgumentException($label . ' raw projection cannot contain proof event history');
                }
            }

            $storageOrdinal = 0;
            if (array_key_exists('storageOrdinal', $state)) {
                $storageOrdinal = self::parseNonNegativeNodeId($state['storageOrdinal'], $label . ' storage ordinal');
            }

            $storageId = $state['storageId'] ?? null;
            if ($storageId !== null && (!is_string($storageId) || $storageId === '')) {
                throw new \InvalidArgumentException($label . ' has malformed storage id');
            }
            if ($storageId === null) {
                $storageId = 'raw-entry-' . hash(
                    'sha256',
                    $state['rootHash'] . "\0" . $storageOrdinal
                );
            }
            $trackKeys = self::parsePartialStateTrackKeys($state, $label);

            return [
                'rawProjection' => true,
                'rootHash' => $state['rootHash'],
                'proofRootHash' => $proofRootHash,
                'storageId' => $storageId,
                'storageOrdinal' => $storageOrdinal,
                'trackKeys' => $trackKeys,
                'proofStoragePruned' => true,
                'proofStoragePrunedProjection' => self::parsePrunedProofProjection(
                    $state['proofStoragePrunedProjection'] ?? null,
                    $label
                ),
                'proofs' => [],
                'updates' => [],
                'events' => [],
            ];
        }

        if (!is_array($state)
            || !isset($state['rootHash'], $state['proofs'])
            || !is_string($state['rootHash'])
            || !preg_match('/^[0-9a-f]{64}$/', $state['rootHash'])
            || !is_array($state['proofs'])
            || $state['proofs'] === []
        ) {
            throw new \InvalidArgumentException($label . ' is malformed');
        }
        $proofRootHash = $state['proofRootHash'] ?? $state['rootHash'];
        if (!is_string($proofRootHash) || !preg_match('/^[0-9a-f]{64}$/', $proofRootHash)) {
            throw new \InvalidArgumentException($label . ' has malformed proof root');
        }

        $proofs = [];
        foreach ($state['proofs'] as $proofHex) {
            $proofs[] = self::parsePartialProofHex($proofHex, $label . ' has malformed proof bytes');
        }

        $updatesRaw = $state['updates'] ?? [];
        if (!is_array($updatesRaw)) {
            throw new \InvalidArgumentException($label . ' has malformed proof-backed updates');
        }

        $updates = [];
        foreach ($updatesRaw as $update) {
            $updates[] = self::parsePartialProofUpdate($update, $label);
        }

        $events = [];
        if (array_key_exists('events', $state)) {
            if (!is_array($state['events']) || $state['events'] === []) {
                throw new \InvalidArgumentException($label . ' has malformed proof-backed event history');
            }

            $eventProofs = [];
            $eventUpdates = [];
            foreach ($state['events'] as $event) {
                if (!is_array($event) || !isset($event['type']) || !is_string($event['type'])) {
                    throw new \InvalidArgumentException($label . ' has malformed proof-backed event');
                }

                if ($event['type'] === 'proof') {
                    if (!isset($event['rootHash']) || !is_string($event['rootHash']) || !preg_match('/^[0-9a-f]{64}$/', $event['rootHash'])) {
                        throw new \InvalidArgumentException($label . ' has malformed proof event root');
                    }
                    $proofHex = self::parsePartialProofHex($event['proof'] ?? null, $label . ' has malformed proof event bytes');

                    $events[] = [
                        'type' => 'proof',
                        'rootHash' => $event['rootHash'],
                        'proof' => $proofHex,
                    ];
                    $eventProofs[] = $proofHex;
                    continue;
                }

                if ($event['type'] !== 'update') {
                    throw new \InvalidArgumentException($label . ' has unknown proof-backed event');
                }

                $parsedUpdate = self::parsePartialProofUpdate($event, $label);
                $events[] = ['type' => 'update'] + $parsedUpdate;
                $eventUpdates[] = $parsedUpdate;
            }

            if ($events[0]['type'] !== 'proof' || $eventProofs !== $proofs || $eventUpdates !== $updates) {
                throw new \InvalidArgumentException($label . ' has inconsistent proof-backed event history');
            }
        } else {
            foreach ($proofs as $proofHex) {
                $events[] = [
                    'type' => 'proof',
                    'rootHash' => $proofRootHash,
                    'proof' => $proofHex,
                ];
            }
            foreach ($updates as $update) {
                $events[] = ['type' => 'update'] + $update;
            }
        }

        $storageOrdinal = 0;
        if (array_key_exists('storageOrdinal', $state)) {
            $storageOrdinal = self::parseNonNegativeNodeId($state['storageOrdinal'], $label . ' storage ordinal');
        }

        $storageId = $state['storageId'] ?? null;
        if ($storageId !== null && (!is_string($storageId) || $storageId === '')) {
            throw new \InvalidArgumentException($label . ' has malformed storage id');
        }
        if ($storageId === null) {
            $storageId = self::legacyPartialStorageId($proofRootHash, $proofs, $updates, $events);
        }
        $trackKeys = self::parsePartialStateTrackKeys($state, $label);

        $proofStoragePruned = false;
        if (array_key_exists('proofStoragePruned', $state)) {
            if (!is_bool($state['proofStoragePruned'])) {
                throw new \InvalidArgumentException($label . ' has malformed proof storage GC flag');
            }

            $proofStoragePruned = $state['proofStoragePruned'];
        }

        $proofStoragePrunedProjection = null;
        if (array_key_exists('proofStoragePrunedProjection', $state)
            && $state['proofStoragePrunedProjection'] !== null
        ) {
            $proofStoragePrunedProjection = self::parsePrunedProofProjection(
                $state['proofStoragePrunedProjection'],
                $label
            );
        }

        return [
            'rootHash' => $state['rootHash'],
            'proofRootHash' => $proofRootHash,
            'storageId' => $storageId,
            'storageOrdinal' => $storageOrdinal,
            'trackKeys' => $trackKeys,
            'proofStoragePruned' => $proofStoragePruned,
            'proofStoragePrunedProjection' => $proofStoragePrunedProjection,
            'proofs' => $proofs,
            'updates' => $updates,
            'events' => $events,
        ];
    }

    private static function partialStorageOrdinal(array $state): int
    {
        return self::parseNonNegativeNodeId($state['storageOrdinal'] ?? 0, 'partial proof storage ordinal');
    }

    /**
     * @param array<string, mixed> $state
     */
    private function partialStateTracksKeys(array $state): bool
    {
        $trackKeys = $state['trackKeys'] ?? null;

        return is_bool($trackKeys) ? $trackKeys : $this->trackKeys;
    }

    /**
     * @param array<string, mixed> $state
     */
    private static function parsePartialStateTrackKeys(array $state, string $label): ?bool
    {
        if (!array_key_exists('trackKeys', $state)) {
            return null;
        }
        if (!is_bool($state['trackKeys'])) {
            throw new \InvalidArgumentException($label . ' has malformed key tracking mode');
        }

        return $state['trackKeys'];
    }

    private static function partialStorageId(?string $head, int $storageOrdinal, string $encodedProof): string
    {
        return 'proof-' . $storageOrdinal . '-'
            . substr(hash('sha256', ($head ?? '[detached]') . "\0" . $storageOrdinal . "\0" . $encodedProof), 0, 24);
    }

    /**
     * @param array<string, mixed> $state
     */
    private static function partialEventProjectionKey(array $state, int $eventCount): string
    {
        return (string) $state['storageId']
            . ':' . $eventCount
            . ':' . hash(
                'sha256',
                self::jsonEncodeBinarySafe(array_slice($state['events'], 0, $eventCount))
            );
    }

    /**
     * @param list<string> $proofs
     * @param list<array{delete: bool, keyHash: string, value: string, key?: string}> $updates
     * @param list<array<string, mixed>> $events
     */
    private static function legacyPartialStorageId(string $proofRootHash, array $proofs, array $updates, array $events): string
    {
        return 'legacy-' . hash(
            'sha256',
            self::jsonEncodeBinarySafe([
                'proofRootHash' => $proofRootHash,
                'proofs' => $proofs,
                'updates' => $updates,
                'events' => $events,
            ])
        );
    }

    private static function parsePartialProofHex(mixed $proofHex, string $message): string
    {
        if (!is_string($proofHex)
            || $proofHex === ''
            || strlen($proofHex) % 2 !== 0
            || !preg_match('/^[0-9a-f]+$/', $proofHex)
        ) {
            throw new \InvalidArgumentException($message);
        }

        return $proofHex;
    }

    /**
     * @return array{delete: bool, keyHash: string, value: string, key?: string}
     */
    private static function parsePartialProofUpdate(mixed $update, string $label): array
    {
        if (!is_array($update)
            || !isset($update['delete'], $update['keyHash'], $update['value'])
            || !is_bool($update['delete'])
            || !is_string($update['keyHash'])
            || !preg_match('/^[0-9a-f]{64}$/', $update['keyHash'])
            || !is_string($update['value'])
        ) {
            throw new \InvalidArgumentException($label . ' has malformed proof-backed update');
        }

        $parsedUpdate = [
            'delete' => $update['delete'],
            'keyHash' => $update['keyHash'],
            'value' => $update['value'],
        ];
        if (array_key_exists('key', $update)) {
            if (!is_string($update['key']) || $update['key'] === '') {
                throw new \InvalidArgumentException($label . ' has malformed proof-backed update key');
            }
            if ((new HashTree())->keyHash($update['key']) !== $update['keyHash']) {
                throw new \InvalidArgumentException($label . ' has mismatched proof-backed update key');
            }

            $parsedUpdate['key'] = $update['key'];
        }

        return $parsedUpdate;
    }

    /**
     * @return array{rootNodeId: int, nodes: array<string, array<string, mixed>>}
     */
    private static function parsePrunedProofProjection(mixed $projection, string $label): array
    {
        if (!is_array($projection) || !isset($projection['rootNodeId'], $projection['nodes']) || !is_array($projection['nodes'])) {
            throw new \InvalidArgumentException($label . ' has malformed pruned proof projection');
        }

        $nodes = [];
        foreach ($projection['nodes'] as $nodeId => $node) {
            self::parseNonNegativeNodeId($nodeId, $label . ' pruned proof node id');
            if (!is_array($node)) {
                throw new \InvalidArgumentException($label . ' has malformed pruned proof node');
            }

            $leafKey = null;
            self::decodePrunedProjectedNode($node, $leafKey);
            $nodes[(string) $nodeId] = $node;
        }
        ksort($nodes, SORT_NUMERIC);

        $parsed = [
            'rootNodeId' => self::parseNonNegativeNodeId(
                $projection['rootNodeId'],
                $label . ' pruned proof root node id'
            ),
            'nodes' => $nodes,
        ];

        if (array_key_exists('staleLeafKeys', $projection)) {
            if (!is_array($projection['staleLeafKeys'])) {
                throw new \InvalidArgumentException($label . ' has malformed stale tracked keys');
            }

            $staleLeafKeys = [];
            foreach ($projection['staleLeafKeys'] as $nodeId => $leafKeyHex) {
                self::parseNonNegativeNodeId($nodeId, $label . ' stale tracked-key node id');
                self::decodeEvenHexBytes($leafKeyHex, $label . ' stale tracked key');
                $staleLeafKeys[(string) $nodeId] = $leafKeyHex;
            }
            ksort($staleLeafKeys, SORT_NUMERIC);
            if ($staleLeafKeys !== []) {
                $parsed['staleLeafKeys'] = $staleLeafKeys;
            }
        }

        return $parsed;
    }

    /**
     * @param array{rootNodeId: int, nodes: array<string, array<string, mixed>>} $projection
     *
     * @return array{rootNodeId: int, leaves: array<string, array<string, mixed>>, branches: array<string, array<string, mixed>>}
     */
    private static function partialStorageSnapshotFromPrunedProjection(array $projection): array
    {
        $leaves = [];
        $branches = [];
        foreach ($projection['nodes'] as $nodeId => $node) {
            $leafKey = null;
            $record = self::decodePrunedProjectedNode($node, $leafKey);
            if ($leafKey !== null) {
                $record['key'] = $leafKey;
            }

            if ($record['type'] === 'leaf' || $record['type'] === 'witnessLeaf') {
                $leaves[(string) $nodeId] = $record;
                continue;
            }

            $branches[(string) $nodeId] = $record;
        }
        ksort($leaves, SORT_NUMERIC);
        ksort($branches, SORT_NUMERIC);

        return [
            'rootNodeId' => $projection['rootNodeId'],
            'leaves' => $leaves,
            'branches' => $branches,
        ];
    }

    /**
     * @param array<string, mixed> $node
     * @return array<string, mixed>
     */
    private static function decodePrunedProjectedNode(array $node, ?string &$leafKey = null): array
    {
        if (!isset($node['type']) || !is_string($node['type'])) {
            throw new \InvalidArgumentException('pruned proof node type is malformed');
        }

        $leafKey = null;
        if (array_key_exists('leafKeyHex', $node)) {
            $leafKey = self::decodeEvenHexBytes($node['leafKeyHex'], 'pruned proof tracked key');
        }

        if ($node['type'] === 'leaf') {
            return [
                'type' => 'leaf',
                'hash' => self::parseHashHexValue($node['hash'] ?? null, 'pruned proof leaf hash'),
                'keyHash' => self::parseHashHexValue($node['keyHash'] ?? null, 'pruned proof leaf key hash'),
                'value' => self::decodeEvenHexBytes($node['valueHex'] ?? null, 'pruned proof leaf value'),
                'key' => $leafKey,
            ];
        }

        if ($node['type'] === 'witnessLeaf') {
            return [
                'type' => 'witnessLeaf',
                'hash' => self::parseHashHexValue($node['hash'] ?? null, 'pruned proof witness leaf hash'),
                'keyHash' => self::parseHashHexValue($node['keyHash'] ?? null, 'pruned proof witness leaf key hash'),
                'valueHash' => self::parseHashHexValue($node['valueHash'] ?? null, 'pruned proof witness leaf value hash'),
                'key' => $leafKey,
            ];
        }

        if ($node['type'] === 'witness') {
            return [
                'type' => 'witness',
                'hash' => self::parseHashHexValue($node['hash'] ?? null, 'pruned proof witness hash'),
            ];
        }

        if ($node['type'] === 'branch') {
            return [
                'type' => 'branch',
                'leftNodeId' => self::parseNonNegativeNodeId($node['leftNodeId'] ?? null, 'pruned proof branch left id'),
                'rightNodeId' => self::parseNonNegativeNodeId($node['rightNodeId'] ?? null, 'pruned proof branch right id'),
                'hash' => self::parseHashHexValue($node['hash'] ?? null, 'pruned proof branch hash'),
            ];
        }

        throw new \InvalidArgumentException('unrecognized pruned proof node type');
    }

    /**
     * @param array<string, mixed> $node
     * @return array<string, mixed>
     */
    private static function encodePrunedProjectedNode(array $node, ?string $leafKey): array
    {
        if (($node['type'] ?? null) === 'leaf') {
            $encoded = [
                'type' => 'leaf',
                'hash' => (string) $node['hash'],
                'keyHash' => (string) $node['keyHash'],
                'valueHex' => bin2hex((string) $node['value']),
            ];
        } elseif (($node['type'] ?? null) === 'witnessLeaf') {
            $encoded = [
                'type' => 'witnessLeaf',
                'hash' => (string) $node['hash'],
                'keyHash' => (string) $node['keyHash'],
                'valueHash' => (string) $node['valueHash'],
            ];
        } elseif (($node['type'] ?? null) === 'witness') {
            return [
                'type' => 'witness',
                'hash' => (string) $node['hash'],
            ];
        } elseif (($node['type'] ?? null) === 'branch') {
            return [
                'type' => 'branch',
                'leftNodeId' => (int) $node['leftNodeId'],
                'rightNodeId' => (int) $node['rightNodeId'],
                'hash' => (string) $node['hash'],
            ];
        } else {
            throw new \RuntimeException('unrecognized pruned proof projection node type');
        }

        if ($leafKey !== null) {
            $encoded['leafKeyHex'] = bin2hex($leafKey);
        }

        return $encoded;
    }

    private static function parseHashHexValue(mixed $value, string $label): string
    {
        if (!is_string($value) || !preg_match('/^[0-9a-f]{64}$/', $value)) {
            throw new \InvalidArgumentException($label . ' must be lowercase 32-byte hex');
        }

        return $value;
    }

    private static function decodeEvenHexBytes(mixed $value, string $label): string
    {
        if (!is_string($value) || strlen($value) % 2 !== 0 || !preg_match('/^[0-9a-f]*$/', $value)) {
            throw new \InvalidArgumentException($label . ' must be lowercase byte hex');
        }

        $decoded = hex2bin($value);
        if ($decoded === false) {
            throw new \InvalidArgumentException($label . ' must be lowercase byte hex');
        }

        return $decoded;
    }

    /**
     * @return list<string>
     */
    private function splitInputLines(string $input): array
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", $input);
        if ($normalized === '') {
            return [];
        }

        $lines = explode("\n", $normalized);
        if (str_ends_with($normalized, "\n")) {
            array_pop($lines);
        }

        return $lines;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitSeparatedLine(string $line, string $separator): array
    {
        $separatorOffset = strpos($line, $separator);
        if ($separatorOffset === false) {
            throw new \RuntimeException("couldn't find separator in input line");
        }

        return [
            substr($line, 0, $separatorOffset),
            substr($line, $separatorOffset + strlen($separator)),
        ];
    }

    /**
     * @return list<string>
     */
    private function splitProofStdinLines(string $input): array
    {
        if ($input === '') {
            return [];
        }

        $lines = explode("\n", $input);
        if (str_ends_with($input, "\n")) {
            array_pop($lines);
        }

        return $lines;
    }

    private static function parseProofIntegerLine(string $line): int
    {
        return self::parseQuadbCliIntegerKey($line);
    }

    private static function parseQuadbCliIntegerKey(string $text): int
    {
        $trimmed = ltrim($text, " \t\n\r\v\f");
        if (!preg_match('/^[+-]?[0-9]+/', $trimmed, $matches)) {
            throw new \InvalidArgumentException('stoi');
        }

        $token = $matches[0];
        $negative = str_starts_with($token, '-');
        $unsigned = $token;
        if ($token[0] === '-' || $token[0] === '+') {
            $unsigned = substr($token, 1);
        }
        $unsigned = ltrim($unsigned, '0');
        if ($unsigned === '') {
            $unsigned = '0';
        }

        $limit = $negative ? '2147483648' : '2147483647';
        if (strlen($unsigned) > strlen($limit)
            || (strlen($unsigned) === strlen($limit) && strcmp($unsigned, $limit) > 0)
        ) {
            throw new \InvalidArgumentException('stoi');
        }

        if ($negative && $unsigned !== '0') {
            throw new \InvalidArgumentException('int range exceeded');
        }

        return (int) $token;
    }

    private static function parseCompositeIntegerText(string $line, string $label): int
    {
        if (!preg_match('/^(0|[1-9][0-9]*)$/', $line)) {
            throw new \InvalidArgumentException($label . ' must be a non-negative integer');
        }

        return self::parseCompositeIntegerValue($line, $label);
    }

    private static function parseCompositeIntegerValue(mixed $value, string $label): int
    {
        $integer = self::parseNonNegativeNodeId($value, $label);
        if ($integer > Key::MAX_INTEGER) {
            throw new \InvalidArgumentException('int range exceeded');
        }

        return $integer;
    }

    private static function normalizeCompositeSuffixHex(mixed $suffixHex): string
    {
        if (!is_string($suffixHex)) {
            throw new \InvalidArgumentException('composite hash suffix must be hexadecimal text');
        }

        if (str_starts_with($suffixHex, '0x') || str_starts_with($suffixHex, '0X')) {
            $suffixHex = substr($suffixHex, 2);
        }
        $suffixHex = strtolower($suffixHex);
        if (strlen($suffixHex) < 46
            || strlen($suffixHex) > 62
            || strlen($suffixHex) % 2 !== 0
            || !preg_match('/^[0-9a-f]+$/', $suffixHex)
        ) {
            throw new \InvalidArgumentException('truncated hash should be 23-31 bytes');
        }

        return $suffixHex;
    }

    private static function compositeKey(int $integer, string $suffixHex): Key
    {
        return Key::fromIntegerAndHash($integer, hex2bin(self::normalizeCompositeSuffixHex($suffixHex)));
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private function splitCompositeValueLine(string $line, string $separator): array
    {
        $first = strpos($line, $separator);
        if ($first === false) {
            throw new \RuntimeException("couldn't find separator in input line");
        }

        $second = strpos($line, $separator, $first + strlen($separator));
        if ($second === false) {
            throw new \RuntimeException("couldn't find separator in input line");
        }

        return [
            substr($line, 0, $first),
            substr($line, $first + strlen($separator), $second - $first - strlen($separator)),
            substr($line, $second + strlen($separator)),
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitCompositeKeyLine(string $line, string $separator): array
    {
        if ($separator === '') {
            throw new \InvalidArgumentException('separator must be non-empty');
        }

        [$integer, $suffixHex, $extra] = $this->splitCompositeValueLine($line . $separator, $separator);
        if ($extra !== '') {
            throw new \RuntimeException('unexpected composite proof key line payload');
        }

        return [$integer, $suffixHex];
    }

    /**
     * @return array<string, array{value: string}>
     */
    private function entriesByKeyHex(TrackedSparseTree $tree): array
    {
        $entries = [];
        foreach ($tree->orderedEntries() as $entry) {
            $entries[$entry->keyHex()] = [
                'value' => $entry->value(),
            ];
        }

        return $entries;
    }

    /**
     * @param list<array{keyHash: string, value: string, deletion: bool}> $output
     */
    private function diffNodeIds(int $nodeIdA, int $nodeIdB, array &$output): void
    {
        if ($nodeIdA === $nodeIdB) {
            return;
        }

        if ($this->nodeStore->nodeHash($nodeIdA) === $this->nodeStore->nodeHash($nodeIdB)) {
            return;
        }

        $aIsBranch = $this->nodeStore->isBranch($nodeIdA);
        $bIsBranch = $this->nodeStore->isBranch($nodeIdB);
        $aIsLeaf = $this->nodeStore->isLeaf($nodeIdA);
        $bIsLeaf = $this->nodeStore->isLeaf($nodeIdB);

        if ($aIsBranch && $bIsBranch) {
            $branchA = $this->nodeStore->branch($nodeIdA);
            $branchB = $this->nodeStore->branch($nodeIdB);
            $this->diffNodeIds($branchA['leftNodeId'], $branchB['leftNodeId'], $output);
            $this->diffNodeIds($branchA['rightNodeId'], $branchB['rightNodeId'], $output);

            return;
        }

        if (!$aIsBranch && $bIsBranch) {
            $foundLeaf = false;
            $leafA = $aIsLeaf ? $this->nodeStore->leaf($nodeIdA) : null;
            $this->walkDiffLeaves($nodeIdB, static function (array $leafB) use (&$foundLeaf, $leafA, &$output): void {
                if ($leafA !== null && $leafB['keyHash'] === $leafA['keyHash']) {
                    $foundLeaf = true;
                    if ($leafB['value'] !== $leafA['value']) {
                        self::pushDiffLeaf($output, $leafA, true);
                        self::pushDiffLeaf($output, $leafB, false);
                    }

                    return;
                }

                self::pushDiffLeaf($output, $leafB, false);
            });
            if ($leafA !== null && !$foundLeaf) {
                self::pushDiffLeaf($output, $leafA, true);
            }

            return;
        }

        if ($aIsBranch && !$bIsBranch) {
            $foundLeaf = false;
            $leafB = $bIsLeaf ? $this->nodeStore->leaf($nodeIdB) : null;
            $this->walkDiffLeaves($nodeIdA, static function (array $leafA) use (&$foundLeaf, $leafB, &$output): void {
                if ($leafB !== null && $leafA['keyHash'] === $leafB['keyHash']) {
                    $foundLeaf = true;
                    if ($leafA['value'] !== $leafB['value']) {
                        self::pushDiffLeaf($output, $leafA, true);
                        self::pushDiffLeaf($output, $leafB, false);
                    }

                    return;
                }

                self::pushDiffLeaf($output, $leafA, true);
            });
            if ($leafB !== null && !$foundLeaf) {
                self::pushDiffLeaf($output, $leafB, false);
            }

            return;
        }

        if ($aIsLeaf && $bIsLeaf) {
            $leafA = $this->nodeStore->leaf($nodeIdA);
            $leafB = $this->nodeStore->leaf($nodeIdB);
            if ($leafA['keyHash'] !== $leafB['keyHash'] || $leafA['value'] !== $leafB['value']) {
                self::pushDiffLeaf($output, $leafA, true);
                self::pushDiffLeaf($output, $leafB, false);
            }

            return;
        }

        if ($aIsLeaf) {
            self::pushDiffLeaf($output, $this->nodeStore->leaf($nodeIdA), true);

            return;
        }

        if ($bIsLeaf) {
            self::pushDiffLeaf($output, $this->nodeStore->leaf($nodeIdB), false);
        }
    }

    /**
     * @param callable(array{keyHash: string, value: string, hash: string}): void $callback
     */
    private function walkDiffLeaves(int $nodeId, callable $callback): void
    {
        if ($nodeId === 0) {
            return;
        }

        if ($this->nodeStore->isLeaf($nodeId)) {
            $callback($this->nodeStore->leaf($nodeId));

            return;
        }

        if (!$this->nodeStore->isBranch($nodeId)) {
            throw new \RuntimeException('diff traversal encountered an unknown node');
        }

        $branch = $this->nodeStore->branch($nodeId);
        $this->walkDiffLeaves($branch['leftNodeId'], $callback);
        $this->walkDiffLeaves($branch['rightNodeId'], $callback);
    }

    /**
     * @param list<array{keyHash: string, value: string, deletion: bool}> $output
     * @param array{keyHash: string, value: string, hash: string} $leaf
     */
    private static function pushDiffLeaf(array &$output, array $leaf, bool $deletion): void
    {
        $output[] = [
            'keyHash' => $leaf['keyHash'],
            'value' => $leaf['value'],
            'deletion' => $deletion,
        ];
    }

    private function renderTrackedKey(string $keyHex): string
    {
        if (!$this->trackKeys) {
            return self::renderUnknownKey($keyHex);
        }

        return $this->trackedKeys[$keyHex] ?? self::renderUnknownKey($keyHex);
    }

    private function recordStringKeyWrite(string $key, ?bool $trackKeys = null): void
    {
        $trackKeys ??= $this->trackKeys;
        $keyHash = $this->keyHash($key);
        if ($trackKeys) {
            $this->trackedKeys[$keyHash] = $key;

            return;
        }

        unset($this->trackedKeys[$keyHash]);
    }

    private function recordCompositeKeyWrite(Key $key, int $integer, string $hashSuffixHex): void
    {
        $keyHex = $key->hex();
        if ($this->trackKeys) {
            $this->compositeKeys[$keyHex] = [
                'integer' => $integer,
                'suffixHex' => self::normalizeCompositeSuffixHex($hashSuffixHex),
            ];

            return;
        }

        unset($this->compositeKeys[$keyHex]);
    }

    private function forgetCompositeKey(Key $key): void
    {
        unset($this->compositeKeys[$key->hex()]);
    }

    private static function renderUnknownKey(string $keyHex): string
    {
        return self::renderUnknownHash($keyHex);
    }

    private static function renderUnknownHash(string $hashHex): string
    {
        self::parseHashHexValue($hashHex, 'unknown rendered hash');

        return 'H(?)=0x' . substr($hashHex, 0, 12) . '...';
    }

    /**
     * @param array{keyHash: string, value: string, hash: string} $leaf
     */
    private static function encodeLmdbLeafNode(array $leaf): string
    {
        return self::packUInt64Le(self::NODE_TYPE_LEAF)
            . self::hashBytes($leaf['hash'])
            . self::hashBytes($leaf['keyHash'])
            . $leaf['value'];
    }

    /**
     * @param array<string, mixed> $leaf
     */
    private static function encodeLmdbPartialLeafNode(array $leaf): string
    {
        if (($leaf['type'] ?? null) === 'leaf') {
            return self::packUInt64Le(self::NODE_TYPE_LEAF)
                . self::hashBytes((string) $leaf['hash'])
                . self::hashBytes((string) $leaf['keyHash'])
                . (string) $leaf['value'];
        }

        if (($leaf['type'] ?? null) === 'witnessLeaf') {
            return self::packUInt64Le(self::NODE_TYPE_WITNESS_LEAF)
                . self::hashBytes((string) $leaf['hash'])
                . self::hashBytes((string) $leaf['keyHash'])
                . self::hashBytes((string) $leaf['valueHash']);
        }

        throw new \RuntimeException('unrecognized partial leaf storage type');
    }

    /**
     * @param array{leftNodeId: int, rightNodeId: int, hash: string} $branch
     */
    private static function encodeLmdbBranchNode(array $branch): string
    {
        $leftNodeId = $branch['leftNodeId'];
        $rightNodeId = $branch['rightNodeId'];

        if ($rightNodeId === 0) {
            $firstWord = self::packNodeRefWord($leftNodeId, self::NODE_TYPE_BRANCH_LEFT);
            $rightWord = self::packUInt64Le(0);
        } elseif ($leftNodeId === 0) {
            $firstWord = self::packNodeRefWord($rightNodeId, self::NODE_TYPE_BRANCH_RIGHT);
            $rightWord = self::packUInt64Le(0);
        } else {
            $firstWord = self::packNodeRefWord($leftNodeId, self::NODE_TYPE_BRANCH_BOTH);
            $rightWord = self::packUInt64Le($rightNodeId);
        }

        return $firstWord . self::hashBytes($branch['hash']) . $rightWord;
    }

    /**
     * @param array<string, mixed> $witness
     */
    private static function encodeLmdbWitnessNode(array $witness): string
    {
        return self::packUInt64Le(self::NODE_TYPE_WITNESS)
            . self::hashBytes((string) $witness['hash'])
            . self::packUInt64Le(0);
    }

    /**
     * @param array<int, string> $leaves
     */
    private static function nextLmdbLeafNodeId(array $leaves): int
    {
        $maxNodeId = 0;
        foreach (array_keys($leaves) as $nodeId) {
            if (is_int($nodeId) && $nodeId < TrackedNodeStore::FIRST_INTERIOR_NODE_ID) {
                $maxNodeId = max($maxNodeId, $nodeId);
            }
        }

        return $maxNodeId + 1;
    }

    /**
     * @param array<int, string> $branches
     */
    private static function nextLmdbInteriorNodeId(array $branches): int
    {
        $maxNodeId = TrackedNodeStore::FIRST_INTERIOR_NODE_ID - 1;
        foreach (array_keys($branches) as $nodeId) {
            if (is_int($nodeId)
                && $nodeId >= TrackedNodeStore::FIRST_INTERIOR_NODE_ID
                && $nodeId < TrackedNodeStore::FIRST_MEMSTORE_NODE_ID
            ) {
                $maxNodeId = max($maxNodeId, $nodeId);
            }
        }

        return $maxNodeId + 1;
    }

    /**
     * @param array<int, string> $bucket
     *
     * @return list<array{key: string, value: string}>
     */
    private static function lmdbUInt64KeyEntries(array $bucket): array
    {
        ksort($bucket, SORT_NUMERIC);

        $entries = [];
        foreach ($bucket as $nodeId => $value) {
            if (!is_int($nodeId) || $nodeId < 0) {
                throw new \RuntimeException('LMDB integer-key bucket contains a non-integer key');
            }

            $entries[] = [
                'key' => self::packUInt64Le($nodeId),
                'value' => $value,
            ];
        }

        return $entries;
    }

    /**
     * @param array<int|string, string> $bucket
     *
     * @return list<array{key: string, value: string}>
     */
    private static function lmdbStringKeyEntries(array $bucket): array
    {
        ksort($bucket, SORT_STRING);

        $entries = [];
        foreach ($bucket as $key => $value) {
            if (!is_string($key) && !is_int($key)) {
                throw new \RuntimeException('LMDB string-key bucket contains a non-string key');
            }

            $entries[] = [
                'key' => (string) $key,
                'value' => $value,
            ];
        }

        return $entries;
    }

    private static function packNodeRefWord(int $nodeId, int $nodeType): string
    {
        if ($nodeId < 0 || $nodeType < 0 || $nodeType > 15) {
            throw new \InvalidArgumentException('invalid Quadrable node reference word');
        }

        $low = (($nodeId % 268435456) * 16) + $nodeType;
        $high = intdiv($nodeId, 268435456);
        if ($high > 0xffffffff) {
            throw new \InvalidArgumentException('Quadrable node id exceeds uint64 storage range');
        }

        return pack('V2', $low, $high);
    }

    private static function packUInt64Le(int $value): string
    {
        if ($value < 0) {
            throw new \InvalidArgumentException('uint64 value must be non-negative');
        }

        return pack('V2', $value % 4294967296, intdiv($value, 4294967296));
    }

    private static function unpackUInt64Le(string $bytes, string $label): int
    {
        if (strlen($bytes) !== 8) {
            throw new \InvalidArgumentException($label . ' must be exactly eight bytes');
        }

        $parts = unpack('Vlow/Vhigh', $bytes);
        if (!is_array($parts)) {
            throw new \InvalidArgumentException($label . ' could not be decoded');
        }

        $value = $parts['low'] + ($parts['high'] * 4294967296);
        if (!is_int($value) && (!is_float($value) || $value > PHP_INT_MAX || floor($value) !== $value)) {
            throw new \InvalidArgumentException($label . ' exceeds PHP integer range');
        }
        if ($value > PHP_INT_MAX) {
            throw new \InvalidArgumentException($label . ' exceeds PHP integer range');
        }

        return (int) $value;
    }

    private static function hashBytes(string $hashHex): string
    {
        if (!preg_match('/^[0-9a-f]{64}$/', $hashHex)) {
            throw new \InvalidArgumentException('Expected lowercase 32-byte hash hex');
        }

        $bytes = hex2bin($hashHex);
        if ($bytes === false) {
            throw new \InvalidArgumentException('Expected lowercase 32-byte hash hex');
        }

        return $bytes;
    }

    private function dumpTrackedNodeText(int $nodeId, int $depth): string
    {
        $output = str_repeat(' ', $depth * 2)
            . $this->renderAbbreviatedRootNode($this->nodeStore->nodeHash($nodeId), $nodeId)
            . ' ';

        if ($nodeId === 0) {
            return $output . "empty\n";
        }

        if ($this->nodeStore->isLeaf($nodeId)) {
            $leaf = $this->nodeStore->leaf($nodeId);

            return $output . 'leaf: ' . $this->renderTrackedKey($leaf['keyHash'])
                . ' = ' . $leaf['value'] . "\n";
        }

        if (!$this->nodeStore->isBranch($nodeId)) {
            throw new \RuntimeException('dump tree traversal encountered an unknown node');
        }

        $branch = $this->nodeStore->branch($nodeId);

        return $output . "branch:\n"
            . $this->dumpTrackedNodeText($branch['leftNodeId'], $depth + 1)
            . $this->dumpTrackedNodeText($branch['rightNodeId'], $depth + 1);
    }

    private function renderNode(int $nodeId): string
    {
        return $this->renderRootNode($this->nodeStore->nodeHash($nodeId), $nodeId);
    }

    private function renderRootNode(string $rootHash, int $nodeId): string
    {
        return '0x' . $rootHash . ' (' . $nodeId . ')';
    }

    private function renderAbbreviatedRootNode(string $rootHash, int $nodeId): string
    {
        return '0x' . substr($rootHash, 0, 8) . '... (' . $nodeId . ')';
    }
}
