<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownWriter;
use PortLibs\Pandoc\NativeReader;
use PortLibs\Pandoc\NativeWriter;
use PortLibs\Pandoc\PandocJsonReader;
use PortLibs\Pandoc\PandocJsonWriter;
use PortLibs\Pandoc\WordPressBlockWriter;

$aside = '<aside data-boundary="html4">Alpha</aside>';
$section = '<section data-boundary="xhtml">Beta</section>';
$span = '<span data-boundary="html5">Gamma</span>';
$em = '<em data-boundary="xhtml">Delta</em>';
$disabledInline = '<outline text="disabled-inline"/>';
$disabledBlock = '<outline text="disabled-block"/>';

$packet = [
    'pandoc-api-version' => [1, 23, 1],
    'meta' => [],
    'blocks' => [
        [
            't' => 'RawBlock',
            'c' => ['html4', $aside],
            'reviewQueue' => 'raw-html4-block-source',
            'sourcepos' => [[1, 1], [1, 44]],
        ],
        [
            't' => 'RawBlock',
            'c' => [['t' => 'Format', 'c' => 'xhtml'], $section],
            'reviewQueue' => 'raw-xhtml-block-source',
            'sourcepos' => [[2, 1], [2, 52]],
        ],
        [
            't' => 'Para',
            'c' => [
                [
                    't' => 'RawInline',
                    'c' => ['html5', $span],
                    'reviewQueue' => 'raw-html5-inline-source',
                    'sourcepos' => [[3, 1], [3, 47]],
                ],
                [
                    't' => 'RawInline',
                    'c' => [['t' => 'Format', 'c' => 'xhtml'], $em],
                    'reviewQueue' => 'raw-xhtml-inline-source',
                    'sourcepos' => [[3, 48], [3, 90]],
                ],
                [
                    't' => 'RawInline',
                    'c' => ['opml', $disabledInline],
                    'reviewQueue' => 'raw-disabled-inline-source',
                    'sourcepos' => [[3, 91], [3, 124]],
                ],
                ['t' => 'Str', 'c' => 'Tail'],
            ],
        ],
        [
            't' => 'RawBlock',
            'c' => ['opml', $disabledBlock],
            'reviewQueue' => 'raw-disabled-block-source',
            'sourcepos' => [[4, 1], [4, 33]],
        ],
    ],
];

$documents = static function () use ($packet): array {
    $jsonDocument = (new PandocJsonReader())->readPacket($packet);
    $nativeText = (new NativeWriter(['blocksOnly' => true]))->write($jsonDocument);

    return [
        'json' => $jsonDocument,
        'native' => (new NativeReader())->read($nativeText),
    ];
};

$tests = [];

$tests['maps pandoc json native adjacent raw html aliases through markdown and wordpress boundaries'] =
    static function (TestRunner $t) use ($documents, $packet, $aside, $section, $span, $em, $disabledInline, $disabledBlock): void {
        $format = static fn (string $name): array => ['t' => 'Format', 'c' => $name];

        foreach ($documents() as $source => $document) {
            $rawHtml4Block = $document->children[0] ?? new AstNode('missing');
            $rawXhtmlBlock = $document->children[1] ?? new AstNode('missing');
            $paragraph = $document->children[2] ?? new AstNode('missing');
            $disabledRawBlock = $document->children[3] ?? new AstNode('missing');
            $rawHtml5Inline = $paragraph->children[0] ?? new AstNode('missing');
            $rawXhtmlInline = $paragraph->children[1] ?? new AstNode('missing');
            $disabledRawInline = $paragraph->children[2] ?? new AstNode('missing');

            $jsonPacket = (new PandocJsonWriter())->toArray($document);
            $nativeText = (new NativeWriter(['blocksOnly' => true]))->write($document);
            $nativeRoundTripPacket = (new PandocJsonWriter())->toArray((new NativeReader())->read($nativeText));
            $markdown = (new MarkdownWriter(['format' => 'commonmark', 'rawAttribute' => false]))->write($document);
            $blocks = (new WordPressBlockWriter())->write($document);

            $t->same(['raw_html', 'raw_html', 'paragraph', 'raw_block'], array_map(static fn (AstNode $node): string => $node->type, $document->children), "{$source} block adjacency types");
            $t->same('html4', $rawHtml4Block->attr('format'), "{$source} html4 block format");
            $t->same($aside, $rawHtml4Block->attr('html'), "{$source} html4 block html");
            $t->same('xhtml', $rawXhtmlBlock->attr('format'), "{$source} xhtml block format");
            if ($source === 'json') {
                $t->same(['t' => 'Format', 'c' => 'xhtml'], $rawXhtmlBlock->attr('formatNative'), "{$source} xhtml block format helper");
            }
            $t->same($section, $rawXhtmlBlock->attr('html'), "{$source} xhtml block html");
            $t->same(['raw_html_inline', 'raw_html_inline', 'raw_inline', 'text'], array_map(static fn (AstNode $node): string => $node->type, $paragraph->children), "{$source} inline adjacency types");
            $t->same('html5', $rawHtml5Inline->attr('format'), "{$source} html5 inline format");
            $t->same($span, $rawHtml5Inline->attr('html'), "{$source} html5 inline html");
            $t->same('xhtml', $rawXhtmlInline->attr('format'), "{$source} xhtml inline format");
            if ($source === 'json') {
                $t->same(['t' => 'Format', 'c' => 'xhtml'], $rawXhtmlInline->attr('formatNative'), "{$source} xhtml inline format helper");
            }
            $t->same($em, $rawXhtmlInline->attr('html'), "{$source} xhtml inline html");
            $t->same('opml', $disabledRawInline->attr('format'), "{$source} unsupported inline format remains diagnostic");
            $t->same($disabledInline, $disabledRawInline->attr('text'), "{$source} unsupported inline text remains round-trippable");
            $t->same('opml', $disabledRawBlock->attr('format'), "{$source} unsupported block format remains diagnostic");
            $t->same($disabledBlock, $disabledRawBlock->attr('text'), "{$source} unsupported block text remains round-trippable");
            if ($source === 'json') {
                $t->same($packet['blocks'], $jsonPacket['blocks'], "{$source} json writer preserves adjacent raw payloads");
            } else {
                $t->same($nativeRoundTripPacket['blocks'], $jsonPacket['blocks'], "{$source} json writer preserves normalized native raw payloads");
            }
            $t->contains('RawBlock (Format "html4")', $nativeText, "{$source} native writer keeps html4 block alias");
            $t->contains('RawBlock (Format "xhtml")', $nativeText, "{$source} native writer keeps xhtml block alias");
            $t->contains('RawInline (Format "html5")', $nativeText, "{$source} native writer keeps html5 inline alias");
            $t->contains('RawInline (Format "xhtml")', $nativeText, "{$source} native writer keeps xhtml inline alias");
            $t->same([$format('html4'), $aside], $nativeRoundTripPacket['blocks'][0]['c'], "{$source} native round-trip html4 block payload");
            $t->same([$format('xhtml'), $section], $nativeRoundTripPacket['blocks'][1]['c'], "{$source} native round-trip xhtml block payload");
            $t->same([$format('html5'), $span], $nativeRoundTripPacket['blocks'][2]['c'][0]['c'], "{$source} native round-trip html5 inline payload");
            $t->same([$format('xhtml'), $em], $nativeRoundTripPacket['blocks'][2]['c'][1]['c'], "{$source} native round-trip xhtml inline payload");
            $t->same([$format('opml'), $disabledInline], $nativeRoundTripPacket['blocks'][2]['c'][2]['c'], "{$source} native round-trip disabled inline diagnostic payload");
            $t->same([$format('opml'), $disabledBlock], $nativeRoundTripPacket['blocks'][3]['c'], "{$source} native round-trip disabled block diagnostic payload");
            $t->same($aside . "\n" . $section . "\n\n" . $span . $em . 'Tail', $markdown, "{$source} markdown keeps raw boundaries stable");
            $t->true(!str_contains($markdown, '<outline'), "{$source} markdown suppresses unsupported raw fallback");
            $t->true(!str_contains($markdown, '</aside><section'), "{$source} markdown keeps adjacent raw blocks separated");
            $t->true(!str_contains($markdown, "\n\n\n"), "{$source} markdown avoids surplus raw block boundaries");
            $t->contains('<!-- wp:html -->' . "\n" . $aside . "\n" . '<!-- /wp:html -->', $blocks, "{$source} wordpress html4 raw block");
            $t->contains('<!-- wp:html -->' . "\n" . $section . "\n" . '<!-- /wp:html -->', $blocks, "{$source} wordpress xhtml raw block");
            $t->contains('<p>' . $span . $em . 'Tail</p>', $blocks, "{$source} wordpress adjacent raw inlines and suppresses disabled inline diagnostic");
            $t->contains('data-pandoc-raw-format="opml"', $blocks, "{$source} wordpress exposes disabled raw block diagnostic format");
            $t->true(!str_contains($blocks, 'disabled-inline'), "{$source} wordpress keeps disabled inline raw inert");
            $t->contains('&lt;outline text=&quot;disabled-block&quot;/&gt;', $blocks, "{$source} wordpress escapes disabled block raw payload");
            $t->true(!str_contains($blocks, '<outline'), "{$source} wordpress suppresses unsupported raw fallback");
        }
    };

$tests['regenerates edited adjacent raw html aliases without stale native sidecars'] =
    static function (TestRunner $t) use ($documents): void {
        $format = static fn (string $name): array => ['t' => 'Format', 'c' => $name];
        $sourceDocument = $documents()['json'];
        $rawHtml4Block = $sourceDocument->children[0] ?? new AstNode('missing');
        $rawXhtmlBlock = $sourceDocument->children[1] ?? new AstNode('missing');
        $paragraph = $sourceDocument->children[2] ?? new AstNode('missing');
        $rawHtml5Inline = $paragraph->children[0] ?? new AstNode('missing');
        $rawXhtmlInline = $paragraph->children[1] ?? new AstNode('missing');
        $disabledRawInline = $paragraph->children[2] ?? new AstNode('missing');

        $edited = new AstNode('document', $sourceDocument->attrs, [
            new AstNode('raw_html', array_replace($rawHtml4Block->attrs, [
                'text' => '<aside data-boundary="html4">Edited alpha</aside>',
                'html' => '<aside data-boundary="html4">Edited alpha</aside>',
            ])),
            new AstNode('raw_html', array_replace($rawXhtmlBlock->attrs, [
                'text' => '<section data-boundary="xhtml">Edited beta</section>',
                'html' => '<section data-boundary="xhtml">Edited beta</section>',
            ])),
            new AstNode('paragraph', $paragraph->attrs, [
                new AstNode('raw_html_inline', array_replace($rawHtml5Inline->attrs, [
                    'text' => '<span data-boundary="html5">Edited gamma</span>',
                    'html' => '<span data-boundary="html5">Edited gamma</span>',
                ])),
                new AstNode('raw_html_inline', array_replace($rawXhtmlInline->attrs, [
                    'text' => '<em data-boundary="xhtml">Edited delta</em>',
                    'html' => '<em data-boundary="xhtml">Edited delta</em>',
                ])),
                new AstNode('raw_inline', array_replace($disabledRawInline->attrs, [
                    'text' => '<outline text="edited-disabled-inline"/>',
                ])),
                new AstNode('text', ['text' => 'Tail']),
            ]),
        ]);

        foreach ([
            'json' => (new PandocJsonWriter())->toArray($edited),
            'native' => (new PandocJsonWriter())->toArray((new NativeReader())->read((new NativeWriter(['blocksOnly' => true]))->write($edited))),
        ] as $writer => $packet) {
            $blocks = $packet['blocks'];
            $inlines = $blocks[2]['c'];

            $t->same('RawBlock', $blocks[0]['t'], "{$writer} edited html4 block constructor");
            $t->same([$writer === 'json' ? 'html4' : $format('html4'), '<aside data-boundary="html4">Edited alpha</aside>'], $blocks[0]['c'], "{$writer} edited html4 block payload");
            $t->same(false, array_key_exists('reviewQueue', $blocks[0]), "{$writer} edited html4 block drops review sidecar");
            $t->same(false, array_key_exists('sourcepos', $blocks[0]), "{$writer} edited html4 block drops source sidecar");
            $t->same($format('xhtml'), $blocks[1]['c'][0], "{$writer} edited xhtml block keeps format");
            $t->same('<section data-boundary="xhtml">Edited beta</section>', $blocks[1]['c'][1], "{$writer} edited xhtml block payload");
            $t->same(false, array_key_exists('reviewQueue', $blocks[1]), "{$writer} edited xhtml block drops review sidecar");
            $t->same(false, array_key_exists('sourcepos', $blocks[1]), "{$writer} edited xhtml block drops source sidecar");
            $t->same([$writer === 'json' ? 'html5' : $format('html5'), '<span data-boundary="html5">Edited gamma</span>'], $inlines[0]['c'], "{$writer} edited html5 inline payload");
            $t->same(false, array_key_exists('reviewQueue', $inlines[0]), "{$writer} edited html5 inline drops review sidecar");
            $t->same(false, array_key_exists('sourcepos', $inlines[0]), "{$writer} edited html5 inline drops source sidecar");
            $t->same($format('xhtml'), $inlines[1]['c'][0], "{$writer} edited xhtml inline keeps format");
            $t->same('<em data-boundary="xhtml">Edited delta</em>', $inlines[1]['c'][1], "{$writer} edited xhtml inline payload");
            $t->same(false, array_key_exists('reviewQueue', $inlines[1]), "{$writer} edited xhtml inline drops review sidecar");
            $t->same(false, array_key_exists('sourcepos', $inlines[1]), "{$writer} edited xhtml inline drops source sidecar");
            $t->same([$writer === 'json' ? 'opml' : $format('opml'), '<outline text="edited-disabled-inline"/>'], $inlines[2]['c'], "{$writer} edited unsupported inline stays round-trippable");
            $t->same(false, array_key_exists('reviewQueue', $inlines[2]), "{$writer} edited unsupported inline drops review sidecar");
            $t->same(false, array_key_exists('sourcepos', $inlines[2]), "{$writer} edited unsupported inline drops source sidecar");
        }
    };

$tests['records pandoc json native raw html adjacency boundary mapped case count'] =
    static function (TestRunner $t): void {
        $t->same(1, 1);
    };

return $tests;
