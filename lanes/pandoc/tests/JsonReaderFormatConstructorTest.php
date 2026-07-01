<?php

declare(strict_types=1);

use PortLibs\Pandoc\JsonReader;
use PortLibs\Pandoc\JsonWriter;

return [
    'accepts raw format helper constructors through current pandoc json reader' => static function (TestRunner $t): void {
        $source = [
            'pandoc-api-version' => [1, 23, 1, 2],
            'meta' => [],
            'blocks' => [
                ['t' => 'RawBlock', 'c' => [
                    ['t' => 'Format', 'c' => ['html']],
                    '<aside data-review="format">raw</aside>',
                ]],
                ['t' => 'RawBlock', 'c' => [
                    ['t' => 'Format', 'c' => 'opml'],
                    '<outline text="review"/>',
                ]],
                ['t' => 'Para', 'c' => [
                    ['t' => 'RawInline', 'c' => [
                        ['t' => 'Format', 'c' => [['tex']]],
                        '\\alpha',
                    ]],
                    ['t' => 'Space'],
                    ['t' => 'RawInline', 'c' => [
                        ['t' => 'Format', 'c' => 'opml'],
                        '<outline text="inline"/>',
                    ]],
                ]],
            ],
        ];

        $document = (new JsonReader())->read(json_encode($source, JSON_THROW_ON_ERROR));
        $rawHtml = $document->children[0];
        $rawGeneric = $document->children[1];
        $paragraph = $document->children[2];
        $rawTexInline = $paragraph->children[0];
        $rawGenericInline = $paragraph->children[2];
        $decoded = json_decode((new JsonWriter())->write($document), true, 512, JSON_THROW_ON_ERROR);

        $t->same('raw_html', $rawHtml->type);
        $t->same('<aside data-review="format">raw</aside>', $rawHtml->attr('html'));
        $t->same('raw_block', $rawGeneric->type);
        $t->same('opml', $rawGeneric->attr('format'));
        $t->same('<outline text="review"/>', $rawGeneric->attr('text'));
        $t->same('raw_tex_inline', $rawTexInline->type);
        $t->same('\\alpha', $rawTexInline->attr('tex'));
        $t->same('raw_inline', $rawGenericInline->type);
        $t->same('opml', $rawGenericInline->attr('format'));
        $t->same('<outline text="inline"/>', $rawGenericInline->attr('text'));
        $t->same('html', $decoded['blocks'][0]['c'][0]);
        $t->same('opml', $decoded['blocks'][1]['c'][0]);
        $t->same('tex', $decoded['blocks'][2]['c'][0]['c'][0]);
        $t->same('opml', $decoded['blocks'][2]['c'][2]['c'][0]);
    },
];
