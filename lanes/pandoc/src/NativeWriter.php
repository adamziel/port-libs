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
            if (is_array($native) && is_string($native['t'] ?? null)) {
                $blocks[] = $native;
                continue;
            }

            $blocks[] = $this->block($child);
        }

        return $blocks;
    }

    /**
     * @return array<string, mixed>
     */
    private function block(AstNode $node): array
    {
        return match ($node->type) {
            'paragraph' => ['t' => 'Para', 'c' => $this->inlines($node->children)],
            'plain' => ['t' => 'Plain', 'c' => $this->inlines($node->children)],
            default => throw new \InvalidArgumentException('Native writer can only emit native constructors or supported shared AST blocks'),
        };
    }

    /**
     * @param list<AstNode> $nodes
     * @return list<array<string, mixed>>
     */
    private function inlines(array $nodes): array
    {
        $inlines = [];
        foreach ($nodes as $node) {
            if (!$node instanceof AstNode) {
                throw new \InvalidArgumentException('Native writer inline children must be AST nodes');
            }

            array_push($inlines, ...$this->inline($node));
        }

        return $inlines;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function inline(AstNode $node): array
    {
        $native = $node->attr('native');
        if (is_array($native) && is_string($native['t'] ?? null)) {
            return [$native];
        }

        return match ($node->type) {
            'text' => $this->textInlines((string) $node->attr('text', '')),
            'space' => [['t' => 'Space']],
            'softbreak' => [['t' => 'SoftBreak']],
            'linebreak' => [['t' => 'LineBreak']],
            'emph' => [['t' => 'Emph', 'c' => $this->inlines($node->children)]],
            'strong' => [['t' => 'Strong', 'c' => $this->inlines($node->children)]],
            'code' => [[
                't' => 'Code',
                'c' => [$this->attrTuple($node), (string) $node->attr('text', '')],
            ]],
            'link' => [[
                't' => 'Link',
                'c' => [
                    $this->attrTuple($node),
                    $this->inlines($node->children),
                    [(string) $node->attr('url', ''), (string) $node->attr('title', '')],
                ],
            ]],
            default => $node->children === []
                ? throw new \InvalidArgumentException('Native writer cannot emit unsupported shared AST inline nodes')
                : $this->inlines($node->children),
        };
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function textInlines(string $text): array
    {
        if ($text === '') {
            return [];
        }

        $parts = preg_split('/([ \t]+)/', $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        if ($parts === false) {
            throw new \RuntimeException('Unable to split native inline text');
        }

        $inlines = [];
        foreach ($parts as $part) {
            if (preg_match('/^[ \t]+$/', $part) === 1) {
                $inlines[] = ['t' => 'Space'];
                continue;
            }

            $inlines[] = ['t' => 'Str', 'c' => $part];
        }

        return $inlines;
    }

    /**
     * @return array{0:string, 1:list<string>, 2:list<array{0:string, 1:string}>}
     */
    private function attrTuple(AstNode $node): array
    {
        $id = (string) $node->attr('id', '');
        $classes = [];
        $rawClasses = $node->attr('classes', []);
        if (is_array($rawClasses)) {
            foreach ($rawClasses as $class) {
                if (is_string($class) && $class !== '') {
                    $classes[] = $class;
                }
            }
        }

        $attributes = [];
        $rawAttributes = $node->attr('attributes', []);
        if (is_array($rawAttributes)) {
            foreach ($rawAttributes as $key => $value) {
                if (is_string($key) && is_scalar($value)) {
                    $attributes[] = [$key, (string) $value];
                }
            }
        }

        return [$id, $classes, $attributes];
    }
}
