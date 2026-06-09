<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class NativeWriter
{
    public function write(AstNode $document): string
    {
        if ($document->type !== 'document') {
            throw new \InvalidArgumentException('Native writer expects a document node');
        }

        $native = [
            'pandoc-api-version' => $this->apiVersion($document->attr('pandocApiVersion', [1, 23, 1])),
            'meta' => $this->metadata($document->attr('meta', [])),
            'blocks' => $this->blocks($document->children),
        ];

        return json_encode(
            $native,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        ) . "\n";
    }

    /**
     * @return list<int>
     */
    private function apiVersion(mixed $version): array
    {
        if (!is_array($version)) {
            throw new \InvalidArgumentException('Pandoc native API version must be an array');
        }

        $parts = [];
        foreach ($version as $part) {
            if (!is_int($part)) {
                throw new \InvalidArgumentException('Pandoc native API version parts must be integers');
            }
            $parts[] = $part;
        }

        return $parts;
    }

    /**
     * @return array<string, mixed>
     */
    private function metadata(mixed $metadata): array
    {
        if (!is_array($metadata)) {
            throw new \InvalidArgumentException('Pandoc native metadata must be an array');
        }

        $native = [];
        foreach ($metadata as $key => $value) {
            if (!is_string($key)) {
                throw new \InvalidArgumentException('Pandoc native metadata keys must be strings');
            }
            $native[$key] = $this->metaValue($value);
        }

        return $native;
    }

    private function metaValue(mixed $value): mixed
    {
        if (!is_array($value) || !is_string($value['t'] ?? null)) {
            throw new \InvalidArgumentException('Pandoc native metadata values must be tagged constructors');
        }

        return $value;
    }

    /**
     * @param list<AstNode> $children
     * @return list<array<string, mixed>>
     */
    private function blocks(array $children): array
    {
        $blocks = [];
        foreach ($children as $child) {
            if (!$child instanceof AstNode) {
                throw new \InvalidArgumentException('Native writer children must be AST nodes');
            }
            $native = $child->attr('native');
            if (!is_array($native) || !is_string($native['t'] ?? null)) {
                throw new \InvalidArgumentException('Native writer can only emit native_block nodes with native constructors');
            }
            $blocks[] = $native;
        }

        return $blocks;
    }
}
