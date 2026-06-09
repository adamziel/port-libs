<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MediaBag;

return [
    'maps pandoc media bag resources to safe extraction paths' => static function (TestRunner $t): void {
        $bag = new MediaBag();
        $relativeBytes = "relative image bytes\n";
        $absoluteBytes = "absolute image bytes\n";
        $pngData = "print \"hello\"\n";
        $dataUri = 'data:image/png;base64,' . base64_encode($pngData) . ';.lua+%2f%2e%2e%2f%2e%2e%2fa%2elua';
        $gifUri = 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

        $document = new AstNode('document', [], [
            new AstNode('paragraph', [], [
                new AstNode('image', [
                    'url' => 'Pictures/review.png',
                    'title' => 'Review image',
                ], [new AstNode('text', ['text' => 'Review image'])]),
            ]),
            new AstNode('paragraph', [], [
                new AstNode('image', [
                    'url' => '/tmp/source/lalune.jpg',
                    'title' => 'Absolute source',
                ], [new AstNode('text', ['text' => 'Absolute source'])]),
            ]),
            new AstNode('paragraph', [], [
                new AstNode('image', [
                    'url' => $dataUri,
                    'title' => 'Inline payload',
                ], [new AstNode('text', ['text' => 'Inline payload'])]),
            ]),
            new AstNode('paragraph', [], [
                new AstNode('image', [
                    'url' => 'missing.png',
                    'title' => 'Missing source',
                ], [new AstNode('text', ['text' => 'Missing source'])]),
            ]),
        ]);

        $filled = $bag->fillDocument($document, [
            'Pictures/review.png' => [
                'contents' => $relativeBytes,
                'mimeType' => 'image/png',
            ],
            '/tmp/source/lalune.jpg' => $absoluteBytes,
        ]);
        $bag->insertDataUri($gifUri);

        $directory = $bag->directory();
        $directoryBySource = [];
        foreach ($directory as $entry) {
            $directoryBySource[$entry['source']] = $entry;
        }

        $t->same([
            'media-resource-loaded:Pictures/review.png',
            'media-resource-loaded:/tmp/source/lalune.jpg',
            'media-resource-loaded:data-uri',
            'media-resource-missing:missing.png',
        ], $filled['diagnostics']);
        $t->same('Pictures/review.png', $directoryBySource['Pictures/review.png']['path']);
        $t->same('image/png', $directoryBySource['Pictures/review.png']['mimeType']);
        $t->same(strlen($relativeBytes), $directoryBySource['Pictures/review.png']['byteLength']);
        $t->same(sha1($absoluteBytes) . '.jpg', $directoryBySource['/tmp/source/lalune.jpg']['path']);
        $t->same('image/jpeg', $directoryBySource['/tmp/source/lalune.jpg']['mimeType']);
        $t->same(sha1($pngData) . '.png', $directoryBySource[$dataUri]['path']);
        $t->same('image/png', $directoryBySource[$dataUri]['mimeType']);
        $t->same('d5fceb6532643d0d84ffe09c40c481ecdf59e15a.gif', $directoryBySource[$gifUri]['path']);
        $t->true(!str_contains(implode(',', array_column($directory, 'path')), '..'), 'Media paths must not contain parent traversal');
        $t->true(!str_contains(implode(',', array_column($directory, 'path')), 'a.lua'), 'Data URI payload suffix must not become a media path');

        $missingPlaceholder = $filled['document']->children[3]->children[0];
        $t->same('span', $missingPlaceholder->type);
        $t->same(['image', 'placeholder'], $missingPlaceholder->attr('classes'));
        $t->same('missing.png', $missingPlaceholder->attr('attributes')['original-image-src']);

        $extracted = $bag->extractMedia($filled['document'], 'media');
        $mappedDocument = $extracted['document'];
        $t->same('media/Pictures/review.png', $mappedDocument->children[0]->children[0]->attr('url'));
        $t->same('media/' . sha1($absoluteBytes) . '.jpg', $mappedDocument->children[1]->children[0]->attr('url'));
        $t->same('media/' . sha1($pngData) . '.png', $mappedDocument->children[2]->children[0]->attr('url'));
        $t->same('span', $mappedDocument->children[3]->children[0]->type);
        $t->same(4, count($extracted['entries']));
        $t->contains('media-resource-mapped:Pictures/review.png', implode(',', $extracted['diagnostics']));
        $t->contains('media-resource-mapped:/tmp/source/lalune.jpg', implode(',', $extracted['diagnostics']));
        $t->contains('media-resource-mapped:data-uri', implode(',', $extracted['diagnostics']));
    },

    'normalizes media bag lookup keys without decoding safe relative names' => static function (TestRunner $t): void {
        $bag = new MediaBag();
        $bytes = "photo bytes\n";
        $bag->insertMedia('assets\\review photo.jpg', null, $bytes);
        $bag->insertMedia('unsafe/%2e%2e/escape.png', 'image/png', "unsafe bytes\n");

        $relative = $bag->lookup('assets/review photo.jpg');
        $escaped = $bag->lookup('unsafe/%2e%2e/escape.png');

        $t->same('assets/review photo.jpg', $relative['path']);
        $t->same('image/jpeg', $relative['mimeType']);
        $t->same($bytes, $relative['contents']);
        $t->same(sha1("unsafe bytes\n") . '.png', $escaped['path']);
        $t->same('image/png', $escaped['mimeType']);
    },
];
