<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\NativeReader;
use PortLibs\Pandoc\NativeWriter;
use PortLibs\Pandoc\PandocJsonReader;
use PortLibs\Pandoc\PandocJsonWriter;

return [
    'preserves structural inline constructor sidecars through native writer rebuilt parents' => static function (TestRunner $t): void {
        $sourceInlines = [
            ['t' => 'Emph', 'c' => [['t' => 'Str', 'c' => 'emph']], 'reviewQueue' => 'emph-source'],
            ['t' => 'Strong', 'c' => [['t' => 'Str', 'c' => 'strong']], 'reviewQueue' => 'strong-source'],
            ['t' => 'Underline', 'c' => [['t' => 'Str', 'c' => 'underline']], 'reviewQueue' => 'underline-source'],
            ['t' => 'Strikeout', 'c' => [['t' => 'Str', 'c' => 'strikeout']], 'reviewQueue' => 'strikeout-source'],
            ['t' => 'Superscript', 'c' => [['t' => 'Str', 'c' => 'superscript']], 'reviewQueue' => 'superscript-source'],
            ['t' => 'Subscript', 'c' => [['t' => 'Str', 'c' => 'subscript']], 'reviewQueue' => 'subscript-source'],
            ['t' => 'SmallCaps', 'c' => [['t' => 'Str', 'c' => 'smallcaps']], 'reviewQueue' => 'smallcaps-source'],
            ['t' => 'Quoted', 'c' => [['t' => 'SingleQuote'], [['t' => 'Str', 'c' => 'quoted']]], 'reviewQueue' => 'quoted-source'],
            ['t' => 'Span', 'c' => [
                ['constructor-span', ['review'], [['data-kind', 'span']]],
                [['t' => 'Str', 'c' => 'span']],
            ], 'reviewQueue' => 'span-source'],
            ['t' => 'Note', 'c' => [
                ['t' => 'Plain', 'c' => [['t' => 'Str', 'c' => 'note']]],
            ], 'noteLabel' => 'constructor-note', 'reviewQueue' => 'note-source'],
        ];
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [
                ['t' => 'Para', 'c' => $sourceInlines, 'reviewQueue' => 'paragraph-source'],
            ],
        ];
        $expectedTypes = [
            'emph',
            'strong',
            'underline',
            'strikeout',
            'superscript',
            'subscript',
            'small_caps',
            'quoted',
            'span',
            'note',
        ];
        $inlineText = static function (array $inlines): string {
            $text = '';
            foreach ($inlines as $inline) {
                $text .= match ($inline['t'] ?? '') {
                    'Str', 'Code', 'Math' => (string) ($inline['c'] ?? ''),
                    'Space', 'SoftBreak', 'LineBreak' => ' ',
                    default => '',
                };
            }

            return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
        };

        foreach ([
            'json reader' => (new PandocJsonReader())->readPacket($packet),
            'native reader' => (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR)),
        ] as $source => $document) {
            $children = $document->children[0]->children;
            $rebuilt = new AstNode('document', $document->attrs, [
                new AstNode('paragraph', [], $children),
            ]);

            $t->same($expectedTypes, array_map(static fn (AstNode $node): string => $node->type, $children), "{$source} reads structural inline constructors");
            foreach ($children as $index => $node) {
                $t->same($sourceInlines[$index], $node->attr('native'), "{$source} retains source inline native sidecar {$index}");
            }

            foreach ([
                'json writer' => (new PandocJsonWriter())->toArray($rebuilt),
                'native writer' => json_decode((new NativeWriter())->write($rebuilt), true, 512, JSON_THROW_ON_ERROR),
            ] as $writer => $encoded) {
                $t->same($sourceInlines, $encoded['blocks'][0]['c'], "{$source} {$writer} preserves child sidecars when parent is rebuilt");
                $t->same(false, array_key_exists('reviewQueue', $encoded['blocks'][0]), "{$source} {$writer} regenerates rebuilt parent");
            }

            $editedChildren = $children;
            $editedChildren[2] = new AstNode('underline', $children[2]->attrs, [
                new AstNode('text', ['text' => 'edited underline']),
            ]);
            $edited = new AstNode('document', $document->attrs, [
                new AstNode('paragraph', [], $editedChildren),
            ]);

            foreach ([
                'json writer' => (new PandocJsonWriter())->toArray($edited),
                'native writer' => json_decode((new NativeWriter())->write($edited), true, 512, JSON_THROW_ON_ERROR),
            ] as $writer => $encoded) {
                $editedInline = $encoded['blocks'][0]['c'][2];

                $t->same($sourceInlines[1], $encoded['blocks'][0]['c'][1], "{$source} {$writer} preserves preceding unedited sidecar");
                $t->same('Underline', $editedInline['t'], "{$source} {$writer} regenerates edited constructor");
                $t->same('edited underline', $inlineText($editedInline['c']), "{$source} {$writer} regenerates edited payload text");
                $t->same(false, array_key_exists('reviewQueue', $editedInline), "{$source} {$writer} drops stale edited sidecar");
                $t->same($sourceInlines[3], $encoded['blocks'][0]['c'][3], "{$source} {$writer} preserves following unedited sidecar");
            }
        }
    },
];
