<?php

declare(strict_types=1);

use PortLibs\Pandoc\JsonReader;
use PortLibs\Pandoc\JsonWriter;

return [
    'accepts legacy two entry link and image target tuples in compatibility reader' => static function (TestRunner $t): void {
        $source = [
            'pandoc-api-version' => [1, 23, 1, 2],
            'meta' => [],
            'blocks' => [
                ['t' => 'Para', 'c' => [
                    ['t' => 'Link', 'c' => [
                        [['t' => 'Str', 'c' => 'source']],
                        ['https://example.test/source', 'Legacy title'],
                    ]],
                    ['t' => 'Space'],
                    ['t' => 'Image', 'c' => [
                        [['t' => 'Str', 'c' => 'Logo']],
                        ['media/logo.png', 'fig:Legacy logo'],
                    ]],
                ]],
            ],
        ];

        $document = (new JsonReader())->read(json_encode($source, JSON_THROW_ON_ERROR));
        $paragraph = $document->children[0];
        $link = $paragraph->children[0];
        $image = $paragraph->children[2];
        $decoded = json_decode((new JsonWriter())->write($document), true, 512, JSON_THROW_ON_ERROR);

        $t->same('paragraph', $paragraph->type);
        $t->same('link', $link->type);
        $t->same('source', $link->children[0]->attr('text'));
        $t->same('https://example.test/source', $link->attr('url'));
        $t->same('Legacy title', $link->attr('title'));
        $t->same('image', $image->type);
        $t->same('Logo', $image->attr('alt'));
        $t->same('media/logo.png', $image->attr('url'));
        $t->same('fig:Legacy logo', $image->attr('title'));

        $encodedLink = $decoded['blocks'][0]['c'][0];
        $encodedImage = $decoded['blocks'][0]['c'][2];
        $t->same('Link', $encodedLink['t']);
        $t->same(3, count($encodedLink['c']));
        $t->same(['', [], []], $encodedLink['c'][0]);
        $t->same(['https://example.test/source', 'Legacy title'], $encodedLink['c'][2]);
        $t->same('Image', $encodedImage['t']);
        $t->same(3, count($encodedImage['c']));
        $t->same(['', [], []], $encodedImage['c'][0]);
        $t->same(['media/logo.png', 'fig:Legacy logo'], $encodedImage['c'][2]);
    },
];
