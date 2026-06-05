<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class OpcRelationship
{
    public const TARGET_MODE_INTERNAL = 'Internal';
    public const TARGET_MODE_EXTERNAL = 'External';

    /** @var list<string> */
    private const UNSAFE_EXTERNAL_SCHEMES = ['data', 'file', 'javascript', 'vbscript'];

    public function __construct(
        public readonly string $id,
        public readonly string $type,
        public readonly string $target,
        public readonly string $targetMode = self::TARGET_MODE_INTERNAL,
    ) {
        if ($id === '' || $type === '' || $target === '') {
            throw new \InvalidArgumentException('OPC relationship Id, Type, and Target must be non-empty');
        }

        self::assertRelationshipId($id);

        if ($targetMode !== self::TARGET_MODE_INTERNAL && $targetMode !== self::TARGET_MODE_EXTERNAL) {
            throw new \InvalidArgumentException('Unsupported OPC relationship TargetMode: ' . $targetMode);
        }
    }

    public function isExternal(): bool
    {
        return $this->targetMode === self::TARGET_MODE_EXTERNAL;
    }

    /**
     * @return array{kind:string, scheme:?string, allowed:bool, issues:list<string>}
     */
    public function externalTargetPreflight(): array
    {
        if (!$this->isExternal()) {
            throw new \LogicException('OPC external target preflight requires TargetMode="External"');
        }

        $scheme = null;
        if (preg_match('/^([A-Za-z][A-Za-z0-9+.-]*):/', $this->target, $matches) === 1) {
            $kind = 'absolute-uri';
            $scheme = strtolower($matches[1]);
        } elseif (str_starts_with($this->target, '//')) {
            $kind = 'network-path-reference';
        } elseif (str_starts_with($this->target, '#')) {
            $kind = 'fragment-reference';
        } else {
            $kind = 'relative-reference';
        }

        $issues = [];
        if (preg_match('/[\x00-\x1F\x7F]/', $this->target) === 1) {
            $issues[] = 'external-target-control-character';
        }

        if ($scheme !== null && in_array($scheme, self::UNSAFE_EXTERNAL_SCHEMES, true)) {
            $issues[] = 'external-target-unsafe-scheme';
        }

        return [
            'kind' => $kind,
            'scheme' => $scheme,
            'allowed' => $issues === [],
            'issues' => $issues,
        ];
    }

    private static function assertRelationshipId(string $id): void
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9._-]*$/D', $id) !== 1) {
            throw new \InvalidArgumentException('OPC relationship Id must be an XML NCName-style identifier');
        }
    }
}
