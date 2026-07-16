<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\NativeReader;
use PortLibs\Pandoc\NativeWriter;
use PortLibs\Pandoc\PandocJsonReader;
use PortLibs\Pandoc\PandocJsonWriter;
use PortLibs\Pandoc\WordPressBlockWriter;

return [
    'preserves mixed figure block payloads through json native and wordpress handoff' => static function (TestRunner $t): void {
        $document = new AstNode('document', ['pandocApiVersion' => [1, 23, 1], 'meta' => []], [
            new AstNode('figure', [
                'id' => 'json-native-mixed-figure',
                'classes' => ['json-native-mixed'],
                'caption' => 'Mixed figure review',
            ], [
                new AstNode('text', ['text' => 'Review']),
                new AstNode('space'),
                new AstNode('link', [
                    'url' => 'https://example.test/source',
                    'title' => 'Source packet',
                ], [
                    new AstNode('text', ['text' => 'source']),
                ]),
                new AstNode('space'),
                new AstNode('raw_html_inline', ['html' => '<span data-raw="inline">raw</span>']),
                new AstNode('code_block', ['text' => 'wp post get 42', 'classes' => ['bash']]),
                new AstNode('text', ['text' => 'Tail']),
                new AstNode('space'),
                new AstNode('raw_html_inline', ['html' => '<mark>done</mark>']),
            ]),
        ]);

        $jsonPacket = (new PandocJsonWriter())->toArray($document);
        $nativePacket = json_decode((new NativeWriter())->write($document), true, 512, JSON_THROW_ON_ERROR);

        foreach (['json' => $jsonPacket, 'native' => $nativePacket] as $source => $packet) {
            $figure = $packet['blocks'][0];
            $figureBlocks = $figure['c'][2];

            $t->same('Figure', $figure['t'], "{$source} figure constructor");
            $t->same(['json-native-mixed-figure', ['json-native-mixed'], []], $figure['c'][0], "{$source} figure attrs");
            $t->same(['Plain', 'CodeBlock', 'Plain'], array_map(static fn (array $block): string => $block['t'], $figureBlocks), "{$source} mixed figure children flush to blocks");
            $t->same(['Str', 'Space', 'Link', 'Space', 'RawInline'], array_map(static fn (array $inline): string => $inline['t'], $figureBlocks[0]['c']), "{$source} leading inline run keeps link and raw inline");
            $t->same(['html', '<span data-raw="inline">raw</span>'], $figureBlocks[0]['c'][4]['c'], "{$source} leading raw inline payload");
            $t->same('wp post get 42', $figureBlocks[1]['c'][1], "{$source} nested code block payload");
            $t->same(['Str', 'Space', 'RawInline'], array_map(static fn (array $inline): string => $inline['t'], $figureBlocks[2]['c']), "{$source} trailing inline run keeps raw inline");
        }

        $roundTrips = [
            'json' => (new PandocJsonReader())->readPacket($jsonPacket),
            'native' => (new NativeReader())->read(json_encode($nativePacket, JSON_THROW_ON_ERROR)),
        ];

        foreach ($roundTrips as $source => $roundTrip) {
            $figure = $roundTrip->children[0];
            $leadingChildren = $figure->children[0]->children;
            $trailingChildren = $figure->children[2]->children;
            $leadingTypes = array_map(static fn (AstNode $child): string => $child->type, $leadingChildren);
            $trailingTypes = array_map(static fn (AstNode $child): string => $child->type, $trailingChildren);
            $leadingLinks = array_values(array_filter($leadingChildren, static fn (AstNode $child): bool => $child->type === 'link'));
            $leadingRaw = array_values(array_filter($leadingChildren, static fn (AstNode $child): bool => $child->type === 'raw_html_inline'));
            $trailingRaw = array_values(array_filter($trailingChildren, static fn (AstNode $child): bool => $child->type === 'raw_html_inline'));

            $t->same('figure', $figure->type, "{$source} round-trip figure node");
            $t->same('json-native-mixed-figure', $figure->attr('id'), "{$source} round-trip figure id");
            $t->same(['plain', 'code_block', 'plain'], array_map(static fn (AstNode $child): string => $child->type, $figure->children), "{$source} round-trip figure child blocks");
            $t->same(true, in_array('link', $leadingTypes, true), "{$source} leading link survives");
            $t->same(true, in_array('raw_html_inline', $leadingTypes, true), "{$source} leading raw inline survives");
            $t->same(true, in_array('raw_html_inline', $trailingTypes, true), "{$source} trailing raw inline survives");
            $t->same('https://example.test/source', $leadingLinks[0]->attr('url'), "{$source} leading link target");
            $t->same('<span data-raw="inline">raw</span>', $leadingRaw[0]->attr('html'), "{$source} leading raw inline html");
            $t->same('<mark>done</mark>', $trailingRaw[0]->attr('html'), "{$source} trailing raw inline html");
        }

        $blocks = (new WordPressBlockWriter())->write($roundTrips['json']);

        $t->contains('<!-- wp:html -->', $blocks);
        $t->contains('<figure id="json-native-mixed-figure" class="json-native-mixed">', $blocks);
        $t->contains('<p>Review <a href="https://example.test/source" title="Source packet">source</a> <span data-raw="inline">raw</span></p>', $blocks);
        $t->contains('<pre class="wp-block-code"><code class="language-bash">wp post get 42</code></pre>', $blocks);
        $t->contains('<p>Tail <mark>done</mark></p>', $blocks);
        $t->contains('<figcaption>Mixed figure review</figcaption>', $blocks);
        $t->true(!str_contains($blocks, '<img src=""'), 'Expected mixed-content figures not to synthesize empty image blocks');
        $t->true(!str_contains($blocks, '<!-- wp:image -->'), 'Expected mixed-content figures to avoid image-only WordPress blocks');
    },
];
