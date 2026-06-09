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
        $attrs = [
            'constructor' => $block['t'],
            'native' => $block,
        ];

        return match ($block['t']) {
            'Para' => $this->inlineBlock('paragraph', $attrs, $block['c'] ?? []),
            'Plain' => $this->inlineBlock('plain', $attrs, $block['c'] ?? []),
            default => new AstNode('native_block', $attrs),
        };
    }

    /**
     * @param array<string, mixed> $attrs
     */
    private function inlineBlock(string $type, array $attrs, mixed $nativeInlines): AstNode
    {
        $children = $this->inlines($nativeInlines);
        $attrs['text'] = $this->plainTextFromInlines($children);

        return new AstNode($type, $attrs, $children);
    }

    /**
     * @return list<AstNode>
     */
    private function inlines(mixed $nativeInlines): array
    {
        if (!is_array($nativeInlines)) {
            throw new \InvalidArgumentException('Pandoc native JSON inlines must be an array');
        }

        $nodes = [];
        $text = '';
        foreach ($nativeInlines as $inline) {
            if (!is_array($inline) || !is_string($inline['t'] ?? null)) {
                throw new \InvalidArgumentException('Pandoc native JSON inlines must be tagged constructors');
            }

            if ($inline['t'] === 'Str') {
                $content = $inline['c'] ?? '';
                if (!is_string($content)) {
                    throw new \InvalidArgumentException('Pandoc native JSON Str inline content must be a string');
                }
                $text .= $content;
                continue;
            }

            if ($inline['t'] === 'Space') {
                $text .= ' ';
                continue;
            }

            $this->flushText($text, $nodes);
            $nodes[] = $this->inline($inline);
        }
        $this->flushText($text, $nodes);

        return $nodes;
    }

    /**
     * @param array<string, mixed> $inline
     */
    private function inline(array $inline): AstNode
    {
        $attrs = [
            'constructor' => $inline['t'],
            'native' => $inline,
        ];

        return match ($inline['t']) {
            'SoftBreak' => new AstNode('softbreak', $attrs),
            'LineBreak' => new AstNode('linebreak', $attrs),
            'Emph' => new AstNode('emph', $attrs, $this->inlines($inline['c'] ?? [])),
            'Strong' => new AstNode('strong', $attrs, $this->inlines($inline['c'] ?? [])),
            'Code' => $this->codeInline($attrs, $inline['c'] ?? []),
            'Link' => $this->linkInline($attrs, $inline['c'] ?? []),
            default => new AstNode('native_inline', $attrs),
        };
    }

    /**
     * @param array<string, mixed> $attrs
     */
    private function codeInline(array $attrs, mixed $content): AstNode
    {
        if (is_array($content) && isset($content[0], $content[1]) && is_string($content[1])) {
            $attrs = array_replace($attrs, $this->attrsFromTuple($content[0]));
            $attrs['text'] = $content[1];

            return new AstNode('code', $attrs);
        }

        throw new \InvalidArgumentException('Pandoc native JSON Code inline content must contain attributes and text');
    }

    /**
     * @param array<string, mixed> $attrs
     */
    private function linkInline(array $attrs, mixed $content): AstNode
    {
        if (!is_array($content) || !isset($content[0], $content[1], $content[2])) {
            throw new \InvalidArgumentException('Pandoc native JSON Link inline content must contain attributes, label, and target');
        }

        $target = $content[2];
        if (!is_array($target) || !is_string($target[0] ?? null) || !is_string($target[1] ?? null)) {
            throw new \InvalidArgumentException('Pandoc native JSON Link target must contain URL and title strings');
        }

        $attrs = array_replace($attrs, $this->attrsFromTuple($content[0]), [
            'url' => $target[0],
            'title' => $target[1],
        ]);

        return new AstNode('link', $attrs, $this->inlines($content[1]));
    }

    /**
     * @return array<string, mixed>
     */
    private function attrsFromTuple(mixed $attr): array
    {
        if (!is_array($attr)) {
            return [];
        }

        $attrs = [];
        if (is_string($attr[0] ?? null) && $attr[0] !== '') {
            $attrs['id'] = $attr[0];
        }

        if (is_array($attr[1] ?? null)) {
            $classes = [];
            foreach ($attr[1] as $class) {
                if (is_string($class) && $class !== '') {
                    $classes[] = $class;
                }
            }
            if ($classes !== []) {
                $attrs['classes'] = $classes;
            }
        }

        if (is_array($attr[2] ?? null)) {
            $attributes = [];
            foreach ($attr[2] as $pair) {
                if (is_array($pair) && is_string($pair[0] ?? null) && is_string($pair[1] ?? null)) {
                    $attributes[$pair[0]] = $pair[1];
                }
            }
            if ($attributes !== []) {
                $attrs['attributes'] = $attributes;
            }
        }

        return $attrs;
    }

    /**
     * @param list<AstNode> $nodes
     */
    private function plainTextFromInlines(array $nodes): string
    {
        $text = '';
        foreach ($nodes as $node) {
            if ($node->type === 'text' || $node->type === 'code') {
                $text .= (string) $node->attr('text', '');
                continue;
            }

            if ($node->type === 'softbreak') {
                $text .= ' ';
                continue;
            }

            if ($node->type === 'linebreak') {
                $text .= "\n";
                continue;
            }

            $text .= $this->plainTextFromInlines($node->children);
        }

        return $text;
    }

    /**
     * @param list<AstNode> $nodes
     */
    private function flushText(string &$text, array &$nodes): void
    {
        if ($text === '') {
            return;
        }

        $nodes[] = new AstNode('text', ['text' => $text]);
        $text = '';
    }
}
