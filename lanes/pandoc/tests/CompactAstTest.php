<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\CompactDelimitedTableAttributes;
use PortLibs\Pandoc\DelimitedTextReader;
use PortLibs\Pandoc\PandocConverter;

$materialize = static function (AstNode $node) use (&$materialize): array {
    return [
        'type' => $node->type,
        'attrs' => $node->attrs,
        'children' => array_map($materialize, $node->children),
    ];
};

return [
    'compact delimited AST resolves to the legacy tree and writer output' => static function (TestRunner $t) use ($materialize): void {
        $source = implode("\n", [
            'id,title,published',
            '42,"Legacy, ""quoted"" title",true',
            "43,\"Two\nline title\",false,extra",
            '44,Needs review',
            '',
        ]);
        $reader = new DelimitedTextReader();
        $legacy = $reader->readCsv($source, ['compactAst' => false]);
        $compact = $reader->readCsv($source);

        $legacyWordPress = PandocConverter::write($legacy, 'wordpress');
        $compactWordPress = PandocConverter::write($compact, 'wordpress');
        $t->same($legacyWordPress, $compactWordPress, 'WordPress output must not force expanded table review metadata');
        foreach (['wordpress', 'html', 'native', 'plain'] as $format) {
            $t->same(
                PandocConverter::write($legacy, $format),
                PandocConverter::write($compact, $format),
                "{$format} output must be unchanged by compact delimited AST storage"
            );
        }
        $t->same($materialize($legacy), $materialize($compact));
    },
    'compact delimited cells defer source provenance until it is requested' => static function (TestRunner $t): void {
        $document = (new DelimitedTextReader())->readCsv("id,title\n42,Example\n");
        $table = $document->children[0];
        $cell = $table->children[1]->children[0]->children[1];

        $resolver = $table->attributeResolver();
        $t->true($resolver instanceof CompactDelimitedTableAttributes);
        $t->same(false, $resolver->geometryIsMaterialized());
        $t->same(false, array_key_exists('tableGeometry', $table->baseAttrs()));
        $t->same(false, array_key_exists('sourceStartOffset', $cell->baseAttrs()));
        $t->same(true, $cell->hasAttr('sourceStartOffset'));
        $t->same(12, $cell->attr('sourceStartOffset'));
        $t->same([
            'header',
            'text',
            'sourceColumn',
            'originalColumnCount',
            'repairedColumnCount',
            'rowRepair',
            'sourceFieldStatus',
            'sourceRow',
            'sourceRowNumber',
            'sourceField',
            'sourceFieldNumber',
            'sourceStartOffset',
            'sourceEndOffset',
            'sourceByteRange',
            'sourceByteLength',
            'sourceStartLine',
            'sourceStartLineNumber',
            'sourceStartByteColumn',
            'sourceStartByteColumnNumber',
            'sourceEndLine',
            'sourceEndLineNumber',
            'sourceEndByteColumn',
            'sourceEndByteColumnNumber',
            'sourceLocationUnit',
            'sourceEndOffsetPolicy',
            'sourceQuoted',
            'sourceQuoteClosed',
            'sourceMultiline',
        ], array_keys($cell->attrs));
        $t->same('Example', $cell->attrs['text']);
        PandocConverter::write($document, 'wordpress');
        $t->same(false, $resolver->geometryIsMaterialized());
        $t->same(2, $table->attr('tableGeometry')['columnCount'] ?? null);
        $t->same(true, $resolver->geometryIsMaterialized());

        $padded = (new DelimitedTextReader())->readCsv("id,title\n42\n")->children[0]->children[1]->children[0]->children[1];
        $t->same('not-recorded', $padded->attr('sourceStartOffset', 'not-recorded'));
        $t->same('not-recorded', $padded->attr('sourceField', 'not-recorded'));
        $t->same('padded', $padded->attr('sourceFieldStatus'));
        $t->throws(\InvalidArgumentException::class, static function (): void {
            (new DelimitedTextReader())->readCsv("id,title\n42,Example\n", ['compactAst' => 'true']);
        });
    },
    'single-text AST nodes preserve their public attribute shape' => static function (TestRunner $t): void {
        $node = new AstNode('text', ['text' => 'compact text']);
        $parent = new AstNode('strong', [], [$node]);

        $t->same('compact text', $node->attr('text'));
        $t->same(true, $node->hasAttr('text'));
        $t->same(['text' => 'compact text'], $node->attrs);
        $t->same([$node], $parent->children);
        $t->same([$node], $parent->children());
    },
    'compact AST storage preserves compound nodes without per-node compatibility fields' => static function (TestRunner $t): void {
        $text = new AstNode('text', ['text' => 'linked text']);
        $link = new AstNode('link', ['url' => 'https://example.test/', 'title' => 'Example'], [$text]);
        $numericAttrs = new AstNode('custom', [0 => 'first', 1 => 'second']);
        $instanceProperties = array_values(array_filter(
            (new \ReflectionClass(AstNode::class))->getProperties(),
            static fn (\ReflectionProperty $property): bool => !$property->isStatic(),
        ));

        $t->same('https://example.test/', $link->attr('url'));
        $t->same(['url' => 'https://example.test/', 'title' => 'Example'], $link->attrs);
        $t->same([$text], $link->children);
        $t->same([0 => 'first', 1 => 'second'], $numericAttrs->attrs);
        $t->same([], $numericAttrs->children);
        $t->same(2, count($instanceProperties));
    },
    'compact text-child nodes preserve the materialized AST and writer output' => static function (TestRunner $t) use ($materialize): void {
        $single = AstNode::withTextFromChildren('paragraph', [], [
            new AstNode('text', ['text' => 'Standalone text']),
        ]);
        $strong = AstNode::withCompactTextChildren('strong', [], [
            new AstNode('text', ['text' => 'emphasized']),
        ]);
        $mixed = AstNode::withTextFromChildren('paragraph', [], [
            new AstNode('text', ['text' => 'Before ']),
            $strong,
            new AstNode('text', ['text' => ' after']),
        ]);
        $compact = new AstNode('document', [], [$single, $mixed]);
        $expanded = new AstNode('document', [], [
            new AstNode('paragraph', ['text' => 'Standalone text'], [
                new AstNode('text', ['text' => 'Standalone text']),
            ]),
            new AstNode('paragraph', ['text' => 'Before emphasized after'], [
                new AstNode('text', ['text' => 'Before ']),
                new AstNode('strong', [], [new AstNode('text', ['text' => 'emphasized'])]),
                new AstNode('text', ['text' => ' after']),
            ]),
        ]);

        $t->same('Standalone text', $single->attr('text'));
        $t->same(['text' => 'Standalone text'], $single->attrs);
        $t->same('emphasized', $strong->children[0]->attr('text'));
        $t->same($materialize($expanded), $materialize($compact));
        foreach (['wordpress', 'html', 'native', 'plain'] as $format) {
            $t->same(PandocConverter::write($expanded, $format), PandocConverter::write($compact, $format));
        }
    },
    'serialized AST children preserve materialization and writer output' => static function (TestRunner $t) use ($materialize): void {
        $children = [
            new AstNode('text', ['text' => 'Before ']),
            new AstNode('softbreak'),
            new AstNode('emph', [], [new AstNode('text', ['text' => 'emphasized'])]),
            new AstNode('linebreak'),
            new AstNode('text', ['text' => 'after']),
        ];
        $compact = new AstNode('document', [], [
            AstNode::withSerializedChildren('paragraph', [], $children),
        ]);
        $expanded = new AstNode('document', [], [
            new AstNode('paragraph', [], $children),
        ]);

        $t->same($materialize($expanded), $materialize($compact));
        foreach (['wordpress', 'html', 'native', 'plain'] as $format) {
            $t->same(PandocConverter::write($expanded, $format), PandocConverter::write($compact, $format));
        }
    },
];
