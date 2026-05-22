<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class FetchCommand
{
    public const PROTOCOL_V1 = 'v1';
    public const PROTOCOL_V2 = 'v2';

    private const ARGUMENT_PREFIXES = [
        'want ',
        'have ',
        'done',
        'thin-pack',
        'no-progress',
        'include-tag',
        'ofs-delta',
        'shallow ',
        'deepen ',
        'deepen-relative',
        'deepen-since ',
        'deepen-not ',
        'filter ',
        'want-ref ',
        'sideband-all',
        'packfile-uris ',
        'wait-for-done',
    ];

    private const V1_FEATURE_ORDER = [
        'multi_ack',
        'thin-pack',
        'side-band',
        'side-band-64k',
        'ofs-delta',
        'shallow',
        'deepen-since',
        'deepen-not',
        'deepen-relative',
        'no-progress',
        'include-tag',
        'multi_ack_detailed',
        'allow-tip-sha1-in-want',
        'allow-reachable-sha1-in-want',
        'no-done',
        'filter',
    ];

    private const V2_FEATURE_ORDER = [
        'shallow',
        'filter',
        'ref-in-want',
        'sideband-all',
        'packfile-uris',
        'wait-for-done',
    ];

    /**
     * @param list<string> $features
     * @param list<string> $arguments
     * @param null|list<string> $featuresForFirstWant
     * @param list<string> $haves
     */
    private function __construct(
        private readonly string $protocolVersion,
        private readonly ProtocolCapabilities $capabilities,
        private array $features,
        private array $arguments,
        private ?array $featuresForFirstWant,
        private array $haves = [],
    ) {
        self::assertProtocolVersion($protocolVersion);
    }

    public static function createV1(ProtocolCapabilities $capabilities): self
    {
        $features = self::defaultFeatures(self::PROTOCOL_V1, $capabilities);

        return new self(
            self::PROTOCOL_V1,
            $capabilities,
            $features,
            [],
            self::featuresForFirstWant($features)
        );
    }

    public static function createV2(ProtocolCapabilities $capabilities): self
    {
        $features = self::defaultFeatures(self::PROTOCOL_V2, $capabilities);

        return new self(
            self::PROTOCOL_V2,
            $capabilities,
            $features,
            self::initialV2Arguments($features),
            null
        );
    }

    /**
     * @return list<string>
     */
    public static function defaultFeatures(string $protocolVersion, ProtocolCapabilities $capabilities): array
    {
        self::assertProtocolVersion($protocolVersion);

        if ($protocolVersion === self::PROTOCOL_V2) {
            $supported = $capabilities->capability('fetch')?->values() ?? [];

            return array_values(array_filter(
                self::V2_FEATURE_ORDER,
                static fn (string $feature): bool => in_array($feature, $supported, true)
            ));
        }

        $hasMultiAckDetailed = $capabilities->contains('multi_ack_detailed');
        $hasSideBand64k = $capabilities->contains('side-band-64k');

        return array_values(array_filter(
            self::V1_FEATURE_ORDER,
            static function (string $feature) use ($capabilities, $hasMultiAckDetailed, $hasSideBand64k): bool {
                if ($feature === 'multi_ack' && $hasMultiAckDetailed) {
                    return false;
                }
                if ($feature === 'side-band' && $hasSideBand64k) {
                    return false;
                }
                if ($feature === 'no-progress') {
                    return false;
                }

                return $capabilities->contains($feature);
            }
        ));
    }

    /**
     * @param list<string> $features
     * @return list<string>
     */
    public static function initialV2Arguments(array $features): array
    {
        $arguments = ['thin-pack', 'ofs-delta'];
        if (in_array('sideband-all', $features, true)) {
            $arguments[] = 'sideband-all';
        }

        return $arguments;
    }

    public function protocolVersion(): string
    {
        return $this->protocolVersion;
    }

    /**
     * @return list<string>
     */
    public function features(): array
    {
        return $this->features;
    }

    /**
     * @return list<string>
     */
    public function arguments(): array
    {
        return $this->arguments;
    }

    /**
     * @return list<string>
     */
    public function haves(): array
    {
        return $this->haves;
    }

    public function isEmpty(): bool
    {
        foreach ($this->arguments as $argument) {
            if (str_starts_with($argument, 'want ')) {
                return false;
            }
        }

        return $this->haves === [];
    }

    public function isStateless(bool $transportIsStateless): bool
    {
        return $transportIsStateless || $this->protocolVersion === self::PROTOCOL_V2;
    }

    public function canUseFilter(): bool
    {
        return in_array('filter', $this->features, true);
    }

    public function canUseShallow(): bool
    {
        return in_array('shallow', $this->features, true);
    }

    public function canUseDeepen(): bool
    {
        return $this->canUseShallow();
    }

    public function canUseDeepenSince(): bool
    {
        return $this->protocolVersion === self::PROTOCOL_V2
            ? $this->canUseShallow()
            : in_array('deepen-since', $this->features, true);
    }

    public function canUseDeepenNot(): bool
    {
        return $this->protocolVersion === self::PROTOCOL_V2
            ? $this->canUseShallow()
            : in_array('deepen-not', $this->features, true);
    }

    public function canUseDeepenRelative(): bool
    {
        return $this->protocolVersion === self::PROTOCOL_V2
            ? $this->canUseShallow()
            : in_array('deepen-relative', $this->features, true);
    }

    public function canUseRefInWant(): bool
    {
        return in_array('ref-in-want', $this->features, true);
    }

    public function canUseIncludeTag(): bool
    {
        return $this->protocolVersion === self::PROTOCOL_V2 || in_array('include-tag', $this->features, true);
    }

    public function canUseSidebandAll(): bool
    {
        return in_array('sideband-all', $this->features, true);
    }

    public function canUsePackfileUris(): bool
    {
        return in_array('packfile-uris', $this->features, true);
    }

    public function addFeature(string $feature): void
    {
        if ($feature === '') {
            throw new \InvalidArgumentException('fetch: feature cannot be empty');
        }

        if ($this->protocolVersion === self::PROTOCOL_V2) {
            $this->arguments[] = $feature;
            return;
        }

        if ($this->featuresForFirstWant === null) {
            throw new \RuntimeException('fetch: v1 features must be added before the first want');
        }
        if (!in_array($feature, $this->featuresForFirstWant, true)) {
            $this->featuresForFirstWant[] = $feature;
        }
    }

    public function useIncludeTag(): void
    {
        if (!$this->canUseIncludeTag()) {
            throw new \LogicException('fetch: include-tag is not supported');
        }

        $this->addFeature('include-tag');
    }

    public function want(string $objectId): void
    {
        $oid = self::normalizeObjectId($objectId);
        if ($this->featuresForFirstWant !== null) {
            $line = $this->featuresForFirstWant === []
                ? $oid
                : $oid . ' ' . implode(' ', $this->featuresForFirstWant);
            $this->featuresForFirstWant = null;
            $this->arguments[] = 'want ' . $line;
            return;
        }

        $this->arguments[] = 'want ' . $oid;
    }

    public function wantRef(string $refPath): void
    {
        if (!$this->canUseRefInWant()) {
            throw new \LogicException('fetch: want-ref requires ref-in-want support');
        }
        self::assertRefPath($refPath);
        $this->arguments[] = 'want-ref ' . $refPath;
    }

    public function have(string $objectId): void
    {
        $this->haves[] = 'have ' . self::normalizeObjectId($objectId);
    }

    public function shallow(string $objectId): void
    {
        $this->requireCapability($this->canUseShallow(), 'shallow');
        $this->arguments[] = 'shallow ' . self::normalizeObjectId($objectId);
    }

    public function deepen(int $depth): void
    {
        $this->requireCapability($this->canUseDeepen(), 'shallow');
        if ($depth < 1) {
            throw new \InvalidArgumentException('fetch: deepen depth must be positive');
        }
        $this->arguments[] = 'deepen ' . $depth;
    }

    public function deepenSince(int $secondsSinceUnixEpoch): void
    {
        $this->requireCapability($this->canUseDeepenSince(), 'deepen-since');
        if ($secondsSinceUnixEpoch < 0) {
            throw new \InvalidArgumentException('fetch: deepen-since must be non-negative');
        }
        $this->arguments[] = 'deepen-since ' . $secondsSinceUnixEpoch;
    }

    public function deepenRelative(): void
    {
        $this->requireCapability($this->canUseDeepenRelative(), 'deepen-relative');
        $this->arguments[] = 'deepen-relative';
    }

    public function deepenNot(string $refPath): void
    {
        $this->requireCapability($this->canUseDeepenNot(), 'deepen-not');
        self::assertRefPath($refPath);
        $this->arguments[] = 'deepen-not ' . $refPath;
    }

    public function filter(string|FetchFilterSpec $spec): void
    {
        $this->requireCapability($this->canUseFilter(), 'filter');
        $spec = $spec instanceof FetchFilterSpec ? $spec->spec : $spec;
        if ($spec === '') {
            throw new \InvalidArgumentException('fetch: filter spec cannot be empty');
        }
        $this->arguments[] = 'filter ' . $spec;
    }

    public function done(): void
    {
        $this->arguments[] = 'done';
    }

    /**
     * @return list<string>
     */
    public function requestArguments(bool $addDoneArgument = false): array
    {
        $arguments = $this->arguments;
        if ($this->protocolVersion === self::PROTOCOL_V1) {
            $firstWantPosition = null;
            foreach ($arguments as $position => $argument) {
                if (str_starts_with($argument, 'want ')) {
                    $firstWantPosition = $position;
                    break;
                }
            }
            if ($firstWantPosition !== null && $firstWantPosition !== 0) {
                $firstWant = $arguments[$firstWantPosition];
                array_splice($arguments, $firstWantPosition, 1);
                array_unshift($arguments, $firstWant);
            }
        }

        $arguments = array_merge($arguments, $this->haves);
        if ($addDoneArgument) {
            $arguments[] = 'done';
        }

        return $arguments;
    }

    public function validate(): void
    {
        foreach (array_merge($this->arguments, $this->haves) as $argument) {
            $known = false;
            foreach (self::ARGUMENT_PREFIXES as $prefix) {
                if (str_starts_with($argument, $prefix)) {
                    $known = true;
                    break;
                }
            }
            if (!$known) {
                throw new \InvalidArgumentException("fetch: argument {$argument} is not known or allowed");
            }
        }

        foreach ($this->features as $feature) {
            if ($this->isFeatureSupportedByServer($feature)) {
                continue;
            }
            throw new \InvalidArgumentException("fetch: capability {$feature} is not supported");
        }

        foreach ($this->arguments as $argument) {
            $this->validateFeatureBackedArgument($argument);
        }
    }

    /**
     * @param list<string> $features
     * @return list<string>
     */
    private static function featuresForFirstWant(array $features): array
    {
        return array_values(array_filter(
            $features,
            static fn (string $feature): bool => $feature !== 'include-tag'
        ));
    }

    private function requireCapability(bool $supported, string $feature): void
    {
        if (!$supported) {
            throw new \LogicException("fetch: {$feature} is not supported");
        }
    }

    private function isFeatureSupportedByServer(string $feature): bool
    {
        if ($this->protocolVersion === self::PROTOCOL_V1) {
            return $this->capabilities->contains($feature);
        }

        if ($feature === 'agent') {
            return true;
        }

        return $this->capabilities->capability('fetch')?->supports($feature) === true;
    }

    private function validateFeatureBackedArgument(string $argument): void
    {
        if (str_starts_with($argument, 'filter ') && !$this->canUseFilter()) {
            throw new \InvalidArgumentException('fetch: capability filter is not supported');
        }
        if (str_starts_with($argument, 'want-ref ') && !$this->canUseRefInWant()) {
            throw new \InvalidArgumentException('fetch: capability ref-in-want is not supported');
        }
        if (str_starts_with($argument, 'sideband-all') && !$this->canUseSidebandAll()) {
            throw new \InvalidArgumentException('fetch: capability sideband-all is not supported');
        }
        if (str_starts_with($argument, 'packfile-uris ') && !$this->canUsePackfileUris()) {
            throw new \InvalidArgumentException('fetch: capability packfile-uris is not supported');
        }
        if ((str_starts_with($argument, 'shallow ') || str_starts_with($argument, 'deepen ')) && !$this->canUseShallow()) {
            throw new \InvalidArgumentException('fetch: capability shallow is not supported');
        }
        if (str_starts_with($argument, 'deepen-since ') && !$this->canUseDeepenSince()) {
            throw new \InvalidArgumentException('fetch: capability deepen-since is not supported');
        }
        if (str_starts_with($argument, 'deepen-not ') && !$this->canUseDeepenNot()) {
            throw new \InvalidArgumentException('fetch: capability deepen-not is not supported');
        }
        if (str_starts_with($argument, 'deepen-relative') && !$this->canUseDeepenRelative()) {
            throw new \InvalidArgumentException('fetch: capability deepen-relative is not supported');
        }
    }

    private static function normalizeObjectId(string $objectId): string
    {
        if (preg_match('/^[0-9a-fA-F]{40}$/', $objectId) !== 1) {
            throw new \InvalidArgumentException('fetch: object id must be a 40-character SHA-1 hex string');
        }

        return strtolower($objectId);
    }

    private static function assertRefPath(string $refPath): void
    {
        if ($refPath === '' || str_contains($refPath, "\0") || str_contains($refPath, "\n") || str_contains($refPath, "\r")) {
            throw new \InvalidArgumentException('fetch: ref path is not valid');
        }
    }

    private static function assertProtocolVersion(string $protocolVersion): void
    {
        if ($protocolVersion !== self::PROTOCOL_V1 && $protocolVersion !== self::PROTOCOL_V2) {
            throw new \InvalidArgumentException("fetch: unsupported protocol version {$protocolVersion}");
        }
    }
}
