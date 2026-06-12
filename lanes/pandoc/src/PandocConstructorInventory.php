<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class PandocConstructorInventory
{
    private const BLOCK_NODE_TYPES = [
        'plain',
        'paragraph',
        'heading',
        'code_block',
        'raw_html',
        'raw_tex',
        'raw_markdown',
        'raw_block',
        'blockquote',
        'ordered_list',
        'bullet_list',
        'definition_list',
        'line_block',
        'horizontal_rule',
        'null_block',
        'div',
        'figure',
        'table',
    ];

    private const INLINE_NODE_TYPES = [
        'text',
        'space',
        'softbreak',
        'linebreak',
        'emph',
        'strong',
        'underline',
        'strikeout',
        'superscript',
        'subscript',
        'small_caps',
        'quoted',
        'code',
        'math',
        'raw_html_inline',
        'raw_tex',
        'raw_markdown',
        'raw_inline',
        'citation',
        'citation_group',
        'link',
        'image',
        'note',
        'span',
    ];

    private const SCALAR_HELPER_ATTRS = [
        'attrConstructor',
        'captionConstructor',
        'shortCaptionMaybeConstructor',
        'shortCaptionConstructor',
        'listStyleConstructor',
        'listDelimiterConstructor',
        'quoteTypeConstructor',
        'mathTypeConstructor',
        'citationConstructor',
        'citationModeConstructor',
        'rowHeadColumnsConstructor',
        'alignmentConstructor',
        'rowSpanConstructor',
        'colSpanConstructor',
    ];

    private const LIST_HELPER_ATTRS = [
        'alignmentConstructors',
        'columnWidthConstructors',
    ];

    /**
     * @param array<string, mixed> $documentAttrs
     * @param list<AstNode> $children
     * @return array<string, array<string, int>>
     */
    public static function fromDocumentParts(array $documentAttrs, array $children): array
    {
        $inventory = [
            'blockConstructors' => [],
            'inlineConstructors' => [],
            'helperConstructors' => [],
            'metaConstructors' => [],
            'unknownBlockConstructors' => [],
            'unknownInlineConstructors' => [],
        ];

        if (is_string($documentAttrs['metaConstructor'] ?? null) && $documentAttrs['metaConstructor'] !== '') {
            self::increment($inventory['metaConstructors'], $documentAttrs['metaConstructor']);
        }
        self::collectMetaConstructors($documentAttrs['metaConstructors'] ?? [], $inventory['metaConstructors']);
        foreach ($children as $child) {
            self::collectNode($child, $inventory);
        }

        foreach ($inventory as &$bucket) {
            ksort($bucket);
        }
        unset($bucket);

        return $inventory;
    }

    /**
     * @param array<string, array<string, int>> $inventory
     */
    private static function collectNode(AstNode $node, array &$inventory): void
    {
        $nativeInlineConstructors = self::stringList($node->attr('nativeInlineConstructors', []));
        if ($node->type === 'text' && $nativeInlineConstructors !== []) {
            foreach ($nativeInlineConstructors as $constructor) {
                self::increment($inventory['inlineConstructors'], $constructor);
            }
        } else {
            $constructor = $node->attr('constructor');
            if (is_string($constructor) && $constructor !== '') {
                self::collectNodeConstructor($node, $constructor, $inventory);
            }
        }

        foreach (self::SCALAR_HELPER_ATTRS as $attr) {
            $constructor = $node->attr($attr);
            if (is_string($constructor) && $constructor !== '') {
                self::increment($inventory['helperConstructors'], $constructor);
            }
        }

        foreach (self::LIST_HELPER_ATTRS as $attr) {
            foreach (self::stringList($node->attr($attr, [])) as $constructor) {
                self::increment($inventory['helperConstructors'], $constructor);
            }
        }

        foreach ($node->children as $child) {
            self::collectNode($child, $inventory);
        }
    }

    /**
     * @param array<string, array<string, int>> $inventory
     */
    private static function collectNodeConstructor(AstNode $node, string $constructor, array &$inventory): void
    {
        if ($node->type === 'native_block') {
            self::increment($inventory['unknownBlockConstructors'], $constructor);
            return;
        }

        if ($node->type === 'native_inline') {
            self::increment($inventory['unknownInlineConstructors'], $constructor);
            return;
        }

        if (in_array($node->type, self::INLINE_NODE_TYPES, true)) {
            self::increment($inventory['inlineConstructors'], $constructor);
            return;
        }

        if (in_array($node->type, self::BLOCK_NODE_TYPES, true)) {
            self::increment($inventory['blockConstructors'], $constructor);
            return;
        }

        self::increment($inventory['helperConstructors'], $constructor);
    }

    /**
     * @param array<string, int> $metaConstructors
     */
    private static function collectMetaConstructors(mixed $tree, array &$metaConstructors): void
    {
        if (!is_array($tree)) {
            return;
        }

        if (is_string($tree['_constructor'] ?? null) && $tree['_constructor'] !== '') {
            self::increment($metaConstructors, $tree['_constructor']);
        }

        foreach ($tree as $key => $value) {
            if ($key === '_constructor') {
                continue;
            }
            self::collectMetaConstructors($value, $metaConstructors);
        }
    }

    /**
     * @return list<string>
     */
    private static function stringList(mixed $value): array
    {
        if (!is_array($value) || !array_is_list($value)) {
            return [];
        }

        $strings = [];
        foreach ($value as $item) {
            if (is_string($item) && $item !== '') {
                $strings[] = $item;
            }
        }

        return $strings;
    }

    /**
     * @param array<string, int> $bucket
     */
    private static function increment(array &$bucket, string $constructor): void
    {
        $bucket[$constructor] = ($bucket[$constructor] ?? 0) + 1;
    }
}
