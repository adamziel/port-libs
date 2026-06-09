<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class NativeReader
{
    public function read(string $nativeJson): AstNode
    {
        $native = json_decode($nativeJson, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($native)) {
            throw new \InvalidArgumentException('Pandoc native JSON must decode to an object');
        }

        $attrs = [
            'meta' => $this->metadata($native['meta'] ?? []),
            'nativeFormat' => 'pandoc-json',
        ];

        if (isset($native['pandoc-api-version'])) {
            $attrs['pandocApiVersion'] = $this->apiVersion($native['pandoc-api-version']);
        }

        $children = [];
        foreach ($this->blocks($native['blocks'] ?? []) as $block) {
            $children[] = $this->block($block);
        }

        return new AstNode('document', $attrs, $children);
    }

    /**
     * @return array<string, mixed>
     */
    private function metadata(mixed $metadata): array
    {
        if (!is_array($metadata)) {
            throw new \InvalidArgumentException('Pandoc native JSON meta must be an object');
        }

        $normalized = [];
        foreach ($metadata as $key => $value) {
            if (!is_string($key)) {
                throw new \InvalidArgumentException('Pandoc native JSON meta keys must be strings');
            }
            $normalized[$key] = $this->metaValue($value);
        }

        return $normalized;
    }

    private function metaValue(mixed $value): mixed
    {
        if (!is_array($value) || !is_string($value['t'] ?? null)) {
            throw new \InvalidArgumentException('Pandoc native JSON meta values must be tagged constructors');
        }

        return $value;
    }

    /**
     * @return list<int>
     */
    private function apiVersion(mixed $version): array
    {
        if (!is_array($version)) {
            throw new \InvalidArgumentException('Pandoc native JSON API version must be an array');
        }

        $parts = [];
        foreach ($version as $part) {
            if (!is_int($part)) {
                throw new \InvalidArgumentException('Pandoc native JSON API version parts must be integers');
            }
            $parts[] = $part;
        }

        return $parts;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function blocks(mixed $blocks): array
    {
        if (!is_array($blocks)) {
            throw new \InvalidArgumentException('Pandoc native JSON blocks must be an array');
        }

        $normalized = [];
        foreach ($blocks as $block) {
            if (!is_array($block) || !is_string($block['t'] ?? null)) {
                throw new \InvalidArgumentException('Pandoc native JSON blocks must be tagged constructors');
            }
            $normalized[] = $block;
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $block
     */
    private function block(array $block): AstNode
    {
        return new AstNode('native_block', [
            'constructor' => $block['t'],
            'native' => $block,
        ]);
    }
}
