<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class LsRefsCommand
{
    private const ARGUMENT_PREFIXES = ['symrefs', 'peel', 'ref-prefix ', 'unborn'];

    /**
     * @param list<string> $features
     * @param list<string> $arguments
     */
    private function __construct(
        private readonly ProtocolCapabilities $capabilities,
        private readonly array $features,
        private readonly array $arguments,
    ) {
    }

    /**
     * @param null|list<string> $refPrefixes
     */
    public static function create(?array $refPrefixes, ProtocolCapabilities $capabilities, ?string $agent = null): self
    {
        $features = [];
        if ($agent !== null) {
            $features[] = 'agent';
        }

        $arguments = ['symrefs', 'peel'];
        if (($capabilities->capability('ls-refs')?->supports('unborn')) === true) {
            $arguments[] = 'unborn';
        }
        foreach (self::prefixArguments($refPrefixes ?? []) as $argument) {
            $arguments[] = $argument;
        }

        return new self($capabilities, $features, $arguments);
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

    public function validate(): void
    {
        foreach ($this->arguments as $argument) {
            $known = false;
            foreach (self::ARGUMENT_PREFIXES as $prefix) {
                if (str_starts_with($argument, $prefix)) {
                    $known = true;
                    break;
                }
            }
            if (!$known) {
                throw new \InvalidArgumentException("ls-refs: argument {$argument} is not known or allowed");
            }
        }

        $allowed = $this->capabilities->capability('ls-refs')?->values() ?? [];
        foreach ($this->features as $feature) {
            if ($feature === 'agent' || in_array($feature, $allowed, true)) {
                continue;
            }
            throw new \InvalidArgumentException("ls-refs: capability {$feature} is not supported");
        }
    }

    /**
     * @param list<string> $prefixes
     * @return list<string>
     */
    public static function prefixArguments(array $prefixes): array
    {
        $seen = [];
        $out = [];
        foreach ($prefixes as $prefix) {
            if (isset($seen[$prefix])) {
                continue;
            }
            $seen[$prefix] = true;
            $out[] = 'ref-prefix ' . $prefix;
        }

        return $out;
    }

    /**
     * @return list<RemoteRef>
     */
    public static function parseV2Refs(string $lines): array
    {
        $refs = [];
        foreach (preg_split('/\r?\n/', trim($lines)) ?: [] as $line) {
            if ($line === '') {
                continue;
            }
            $refs[] = self::parseV2RefLine($line);
        }

        return $refs;
    }

    public static function parseV2RefLine(string $line): RemoteRef
    {
        $tokens = explode(' ', rtrim($line), 4);
        if (count($tokens) < 2 || $tokens[1] === '') {
            throw new \InvalidArgumentException("Malformed V2 ref line: {$line}");
        }

        [$oid, $name] = $tokens;
        $object = null;
        if ($oid !== 'unborn') {
            self::assertObjectId($oid);
            $object = strtolower($oid);
        }

        $attributes = [];
        if (isset($tokens[2])) {
            $attributes[] = $tokens[2];
        }
        if (isset($tokens[3])) {
            $attributes = array_merge($attributes, explode(' ', $tokens[3]));
        }
        if (count($attributes) > 2) {
            throw new \InvalidArgumentException("Malformed V2 ref line: {$line}");
        }

        $symrefTarget = null;
        $peeled = null;
        foreach ($attributes as $attribute) {
            if (!str_contains($attribute, ':')) {
                throw new \InvalidArgumentException("Malformed V2 ref line: {$line}");
            }
            [$attributeName, $value] = explode(':', $attribute, 2);
            if ($value === '') {
                throw new \InvalidArgumentException("Malformed V2 ref line: {$line}");
            }
            if ($attributeName === 'symref-target') {
                $symrefTarget = $value;
            } elseif ($attributeName === 'peeled') {
                self::assertObjectId($value);
                $peeled = strtolower($value);
            } else {
                throw new \InvalidArgumentException("Unknown V2 ref attribute {$attributeName}");
            }
        }

        if ($symrefTarget !== null) {
            if ($symrefTarget === '(null)') {
                if ($object === null) {
                    throw new \RuntimeException("got 'unborn' while (null) was a symref target");
                }
                return $peeled === null
                    ? RemoteRef::direct($name, $object)
                    : RemoteRef::peeled($name, $object, $peeled);
            }

            if ($object === null) {
                return RemoteRef::unborn($name, $symrefTarget);
            }

            return RemoteRef::symbolic($name, $symrefTarget, $peeled ?? $object, $peeled === null ? null : $object);
        }

        if ($peeled !== null) {
            if ($object === null) {
                throw new \RuntimeException("got 'unborn' as tag target");
            }
            return RemoteRef::peeled($name, $object, $peeled);
        }

        if ($object === null) {
            throw new \RuntimeException("got 'unborn' as object name of direct reference");
        }

        return RemoteRef::direct($name, $object);
    }

    private static function assertObjectId(string $oid): void
    {
        if (preg_match('/^[0-9a-fA-F]{40}$/', $oid) !== 1) {
            throw new \InvalidArgumentException('Protocol ref object id must be a 40-character SHA-1 hex string');
        }
    }
}
