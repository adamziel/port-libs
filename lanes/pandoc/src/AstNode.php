<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class AstNode
{
    private const COMPOUND_STORAGE_MARKER = "\0pandoc-ast-compound";

    /** @var \WeakMap<AstNode, array<string, mixed>>|null */
    private static ?\WeakMap $resolvedAttributes = null;

    /**
     * `storage` deliberately has only the data needed by the node shape:
     *
     * - string: one `text` attribute and no children;
     * - AstNode: one child and no attributes;
     * - list<AstNode>: children and no attributes;
     * - array<string, mixed>: attributes and no children;
     * - list{array<string, mixed>|string, AstNode|list<AstNode>|null, ?AstAttributeResolver}:
     *   attributes, children, and optional lazy attributes.
     *
     * This keeps the common text leaf, wrapper, and container forms to two
     * object properties: `type` and this value. PHP arrays have a large
     * per-instance cost, so avoiding an empty attribute map and an empty or
     * singleton children map has a material effect on document-sized ASTs.
     *
     * @var string|AstNode|array<mixed>|null
     */
    private readonly string|self|array|null $storage;

    /**
     * @param array<string, mixed> $attrs
     * @param list<AstNode> $children
     */
    public function __construct(
        public readonly string $type,
        array $attrs = [],
        array $children = [],
        ?AstAttributeResolver $attributeResolver = null,
    ) {
        $text = count($attrs) === 1
            && isset($attrs['text'])
            && is_string($attrs['text'])
            ? $attrs['text']
            : null;
        $childStorage = match (count($children)) {
            0 => null,
            1 => $children[0],
            default => $children,
        };

        if ($attributeResolver !== null || ($text !== null ? $childStorage !== null : ($attrs !== [] && $childStorage !== null))) {
            $this->storage = $this->compoundStorage(
                $text ?? $attrs,
                $childStorage,
                $attributeResolver,
                $text === null && self::hasNumericKeys($attrs),
            );

            return;
        }

        if ($text !== null) {
            $this->storage = $text;

            return;
        }

        if ($attrs !== []) {
            $this->storage = self::hasNumericKeys($attrs)
                ? [self::COMPOUND_STORAGE_MARKER, $attrs, null]
                : $attrs;

            return;
        }

        $this->storage = $childStorage;
    }

    public function attr(string $name, mixed $default = null): mixed
    {
        $attrs = $this->directAttributes();
        if (is_string($attrs)) {
            if ($name === 'text') {
                return $attrs;
            }
        } elseif (array_key_exists($name, $attrs)) {
            return $attrs[$name];
        }

        $resolver = $this->attributeResolver();
        if ($resolver !== null && $resolver->has($name, $this)) {
            return $resolver->get($name, $this);
        }

        return $default;
    }

    public function hasAttr(string $name): bool
    {
        $attrs = $this->directAttributes();
        if ((is_string($attrs) && $name === 'text') || (is_array($attrs) && array_key_exists($name, $attrs))) {
            return true;
        }

        $resolver = $this->attributeResolver();

        return $resolver !== null && $resolver->has($name, $this);
    }

    /**
     * Returns only attributes stored directly on this node.
     *
     * This is useful for transforms which deliberately do not need optional
     * lazy review metadata.
     *
     * @return array<string, mixed>
     */
    public function baseAttrs(): array
    {
        $attrs = $this->directAttributes();

        return is_string($attrs) ? ['text' => $attrs] : $attrs;
    }

    public function attributeResolver(): ?AstAttributeResolver
    {
        $offset = $this->compoundStorageOffset();
        if ($offset === null || !is_array($this->storage)) {
            return null;
        }

        $resolver = $this->storage[$offset + 2] ?? null;

        return $resolver instanceof AstAttributeResolver ? $resolver : null;
    }

    /**
     * @return list<AstNode>
     */
    public function children(): array
    {
        $children = $this->directChildren();
        if ($children instanceof self) {
            return [$children];
        }

        return $children ?? [];
    }

    /**
     * Preserve the established public `$node->attrs` and `$node->children`
     * access patterns while keeping their compact forms internal.
     *
     * @return array<string, mixed>|list<AstNode>
     */
    public function __get(string $name): mixed
    {
        if ($name === 'children') {
            return $this->children();
        }

        if ($name !== 'attrs') {
            return null;
        }

        $resolver = $this->attributeResolver();
        if ($resolver === null) {
            return $this->baseAttrs();
        }

        $resolved = self::$resolvedAttributes;
        if ($resolved !== null && isset($resolved[$this])) {
            return $resolved[$this];
        }

        $attrs = $resolver->materialize($this->baseAttrs(), $this);
        $resolved = self::$resolvedAttributes ??= new \WeakMap();
        $resolved[$this] = $attrs;

        return $attrs;
    }

    public function __isset(string $name): bool
    {
        return $name === 'attrs' || $name === 'children';
    }

    /**
     * @param array<string, mixed>|string $attrs
     * @param AstNode|list<AstNode>|null $children
     * @return list<mixed>
     */
    private function compoundStorage(
        array|string $attrs,
        AstNode|array|null $children,
        ?AstAttributeResolver $resolver,
        bool $mustMark,
    ): array {
        if ($mustMark) {
            return [self::COMPOUND_STORAGE_MARKER, $attrs, $children, $resolver];
        }

        return $resolver === null
            ? [$attrs, $children]
            : [$attrs, $children, $resolver];
    }

    /**
     * @return array<string, mixed>|string
     */
    private function directAttributes(): array|string
    {
        if (is_string($this->storage)) {
            return $this->storage;
        }

        $offset = $this->compoundStorageOffset();
        if ($offset !== null && is_array($this->storage)) {
            return $this->storage[$offset];
        }

        if (is_array($this->storage) && !(($this->storage[0] ?? null) instanceof self)) {
            return $this->storage;
        }

        return [];
    }

    /**
     * @return AstNode|list<AstNode>|null
     */
    private function directChildren(): AstNode|array|null
    {
        if ($this->storage instanceof self) {
            return $this->storage;
        }

        $offset = $this->compoundStorageOffset();
        if ($offset !== null && is_array($this->storage)) {
            return $this->storage[$offset + 1];
        }

        if (is_array($this->storage) && ($this->storage[0] ?? null) instanceof self) {
            return $this->storage;
        }

        return null;
    }

    /**
     * Returns the index of the direct attributes in a compound storage array.
     */
    private function compoundStorageOffset(): ?int
    {
        if (!is_array($this->storage)) {
            return null;
        }

        if (($this->storage[0] ?? null) === self::COMPOUND_STORAGE_MARKER) {
            return 1;
        }

        if (!array_key_exists(1, $this->storage)) {
            return null;
        }

        $attrs = $this->storage[0] ?? null;

        return is_string($attrs) || is_array($attrs) ? 0 : null;
    }

    /**
     * Numeric root keys are not a normal AST attribute shape, but retaining
     * them avoids an ambiguity with a list of child nodes.
     *
     * @param array<string, mixed> $attrs
     */
    private static function hasNumericKeys(array $attrs): bool
    {
        foreach (array_keys($attrs) as $key) {
            if (is_int($key)) {
                return true;
            }
        }

        return false;
    }
}
