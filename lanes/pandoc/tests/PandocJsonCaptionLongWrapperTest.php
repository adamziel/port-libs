<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\NativeReader;
use PortLibs\Pandoc\NativeWriter;
use PortLibs\Pandoc\PandocJsonReader;
use PortLibs\Pandoc\PandocJsonWriter;

return [
    'preserves wrapped long caption natives while rebuilding table and figure wrappers' => static function (TestRunner $t): void {
        $tableLongBlock = ['t' => 'Plain', 'c' => [
            ['t' => 'Str', 'c' => 'Long'],
            ['t' => 'Space'],
            ['t' => 'Str', 'c' => 'table'],
        ], 'reviewQueue' => 'table-long-source'];
        $figureLongBlock = ['t' => 'Plain', 'c' => [
            ['t' => 'Str', 'c' => 'Long'],
            ['t' => 'Space'],
            ['t' => 'Str', 'c' => 'figure'],
        ], 'reviewQueue' => 'figure-long-source'];
        $figureShort = ['t' => 'Just', 'c' => [
            't' => 'ShortCaption',
            'c' => [[
                ['t' => 'Str', 'c' => 'Figure'],
                ['t' => 'Space'],
                ['t' => 'Str', 'c' => 'short'],
            ]],
        ], 'reviewQueue' => 'figure-short-source'];
        $tableCaption = ['t' => 'Caption', 'c' => [
            ['t' => 'Nothing', 'reviewQueue' => 'table-short-source'],
            [[$tableLongBlock]],
        ], 'reviewQueue' => 'table-caption-source'];
        $figureCaption = ['t' => 'Caption', 'c' => [
            $figureShort,
            [[$figureLongBlock]],
        ], 'reviewQueue' => 'figure-caption-source'];
        $tableBlock = ['t' => 'Table', 'c' => [
            ['caption-long-table', ['json-native'], []],
            $tableCaption,
            [],
            ['t' => 'TableHead', 'c' => [['', [], []], []]],
            [],
            ['t' => 'TableFoot', 'c' => [['', [], []], []]],
        ], 'reviewQueue' => 'table-wrapper-source'];
        $figureBlock = ['t' => 'Figure', 'c' => [
            ['caption-long-figure', [], []],
            $figureCaption,
            [['t' => 'Para', 'c' => [
                ['t' => 'Str', 'c' => 'Figure'],
                ['t' => 'Space'],
                ['t' => 'Str', 'c' => 'body'],
            ]]],
        ], 'reviewQueue' => 'figure-wrapper-source'];
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [$tableBlock, $figureBlock],
        ];
        $documents = [
            'json' => (new PandocJsonReader())->readPacket($packet),
            'native' => (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR)),
        ];
        $stripWrapperNative = static function (AstNode $node): array {
            $attrs = $node->attrs;
            unset($attrs['constructor'], $attrs['native']);

            return $attrs;
        };
        $encode = static function (AstNode $document): array {
            return [
                'json' => (new PandocJsonWriter())->toArray($document),
                'native' => json_decode((new NativeWriter())->write($document), true, 512, JSON_THROW_ON_ERROR),
            ];
        };
        $inlineText = static fn (string $first, string $second): array => [
            new AstNode('text', ['text' => $first]),
            new AstNode('space'),
            new AstNode('text', ['text' => $second]),
        ];

        foreach ($documents as $source => $document) {
            $table = $document->children[0];
            $figure = $document->children[1];
            $rebuilt = new AstNode('document', $document->attrs, [
                new AstNode('table', $stripWrapperNative($table), $table->children),
                new AstNode('figure', $stripWrapperNative($figure), $figure->children),
            ]);

            foreach ($encode($rebuilt) as $writer => $encoded) {
                $t->same(false, array_key_exists('reviewQueue', $encoded['blocks'][0]), "{$source} {$writer} rebuilds table wrapper");
                $t->same(false, array_key_exists('reviewQueue', $encoded['blocks'][1]), "{$source} {$writer} rebuilds figure wrapper");
                $t->same($tableCaption, $encoded['blocks'][0]['c'][1], "{$source} {$writer} preserves wrapped table caption native");
                $t->same($figureCaption, $encoded['blocks'][1]['c'][1], "{$source} {$writer} preserves wrapped figure caption native");
            }

            $shortEditedTable = new AstNode('table', array_replace($stripWrapperNative($table), [
                'shortCaptionInlines' => $inlineText('Edited', 'short'),
                'shortCaption' => 'Edited short',
            ]), $table->children);
            $shortEdited = new AstNode('document', $document->attrs, [$shortEditedTable, new AstNode('figure', $stripWrapperNative($figure), $figure->children)]);

            foreach ($encode($shortEdited) as $writer => $encoded) {
                $caption = $encoded['blocks'][0]['c'][1];
                $t->same(false, array_key_exists('reviewQueue', $caption), "{$source} {$writer} drops stale table caption sidecar after short edit");
                $t->same('Just', $caption['c'][0]['t'], "{$source} {$writer} emits edited table short caption constructor");
                $t->same([
                    ['t' => 'Str', 'c' => 'Edited'],
                    ['t' => 'Space'],
                    ['t' => 'Str', 'c' => 'short'],
                ], $caption['c'][0]['c']['c'][0], "{$source} {$writer} emits edited table short caption");
                $t->same([[$tableLongBlock]], $caption['c'][1], "{$source} {$writer} keeps unchanged wrapped table long caption");
            }

            $editedLongBlock = ['t' => 'Plain', 'c' => [
                ['t' => 'Str', 'c' => 'Edited'],
                ['t' => 'Space'],
                ['t' => 'Str', 'c' => 'long'],
            ]];
            $longEditedTable = new AstNode('table', array_replace($stripWrapperNative($table), [
                'captionBlocks' => [new AstNode('plain', [], $inlineText('Edited', 'long'))],
                'caption' => 'Edited long',
            ]), $table->children);
            $longEdited = new AstNode('document', $document->attrs, [$longEditedTable, new AstNode('figure', $stripWrapperNative($figure), $figure->children)]);

            foreach ($encode($longEdited) as $writer => $encoded) {
                $caption = $encoded['blocks'][0]['c'][1];
                $t->same(false, array_key_exists('reviewQueue', $caption), "{$source} {$writer} drops stale table caption sidecar after long edit");
                $t->same(['t' => 'Nothing', 'reviewQueue' => 'table-short-source'], $caption['c'][0], "{$source} {$writer} preserves unchanged table short helper");
                $t->same([$editedLongBlock], $caption['c'][1], "{$source} {$writer} canonicalizes edited table long caption");
            }
        }
    },
];
