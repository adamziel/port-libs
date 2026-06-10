<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownWriter;
use PortLibs\Pandoc\MediaBag;
use PortLibs\Pandoc\PandocJsonReader;
use PortLibs\Pandoc\PandocJsonWriter;
use PortLibs\Pandoc\WordPressBlockWriter;

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

    'loads resource map entries by canonicalized media source keys' => static function (TestRunner $t): void {
        $bag = new MediaBag();
        $relativeBytes = "canonical diagram bytes\n";
        $windowsBytes = "windows source cover bytes\n";

        $document = new AstNode('document', [], [
            new AstNode('paragraph', [], [
                new AstNode('image', [
                    'url' => 'assets/diagram.png',
                    'title' => 'Canonical diagram',
                ], [new AstNode('text', ['text' => 'Canonical diagram'])]),
            ]),
            new AstNode('paragraph', [], [
                new AstNode('image', [
                    'url' => 'C:/imports/source cover.jpg',
                    'title' => 'Windows source cover',
                ], [new AstNode('text', ['text' => 'Windows source cover'])]),
            ]),
        ]);

        $filled = $bag->fillDocument($document, [
            'assets\\draft\\..\\diagram.png' => [
                'contents' => $relativeBytes,
                'mimeType' => 'image/png',
            ],
            'C:\\imports\\source cover.jpg' => $windowsBytes,
        ]);

        $directoryBySource = [];
        foreach ($bag->directory() as $entry) {
            $directoryBySource[$entry['source']] = $entry;
        }

        $windowsPath = sha1($windowsBytes) . '.jpg';
        $t->same([
            'media-resource-loaded:assets/diagram.png',
            'media-resource-loaded:C:/imports/source cover.jpg',
        ], $filled['diagnostics']);
        $t->same('assets/diagram.png', $directoryBySource['assets/diagram.png']['path']);
        $t->same('image/png', $directoryBySource['assets/diagram.png']['mimeType']);
        $t->same(strlen($relativeBytes), $directoryBySource['assets/diagram.png']['byteLength']);
        $t->same($windowsPath, $directoryBySource['C:/imports/source cover.jpg']['path']);
        $t->same('image/jpeg', $directoryBySource['C:/imports/source cover.jpg']['mimeType']);

        $extracted = $bag->extractMedia($filled['document'], 'bag-media');
        $mappedDocument = $extracted['document'];
        $t->same('bag-media/assets/diagram.png', $mappedDocument->children[0]->children[0]->attr('url'));
        $t->same('bag-media/' . $windowsPath, $mappedDocument->children[1]->children[0]->attr('url'));
        $t->same([
            'media-resource-mapped:assets/diagram.png',
            'media-resource-mapped:C:/imports/source cover.jpg',
        ], $extracted['diagnostics']);
    },

    'maps query and fragment media urls through path-only resource keys' => static function (TestRunner $t): void {
        $bag = new MediaBag();
        $bytes = "<svg><text>review plot</text></svg>\n";
        $source = 'assets/plots/figure.svg?download=1#review';
        $document = new AstNode('document', [], [
            new AstNode('paragraph', [], [
                new AstNode('image', [
                    'url' => $source,
                    'title' => 'Review plot',
                ], [new AstNode('text', ['text' => 'Review plot'])]),
            ]),
        ]);

        $filled = $bag->fillDocument($document, [
            'assets\\plots\\./figure.svg' => [
                'contents' => $bytes,
                'mimeType' => 'image/svg+xml',
            ],
        ]);
        $directory = $bag->directory();
        $expectedMediaPath = sha1($bytes) . '.svg';

        $t->same(['media-resource-loaded:' . $source], $filled['diagnostics']);
        $t->same(1, count($directory));
        $t->same($source, $directory[0]['source']);
        $t->same($expectedMediaPath, $directory[0]['path']);
        $t->same('image/svg+xml', $directory[0]['mimeType']);
        $t->true(!str_contains($directory[0]['path'], '?'), 'Media path must not preserve query delimiters');
        $t->true(!str_contains($directory[0]['path'], '#'), 'Media path must not preserve fragment delimiters');

        $extracted = $bag->extractMedia($filled['document'], 'review-media');
        $mappedImage = $extracted['document']->children[0]->children[0];
        $t->same('review-media/' . $expectedMediaPath, $mappedImage->attr('url'));
        $t->same('review-media/' . $expectedMediaPath, $extracted['entries'][0]['path']);
        $t->same($expectedMediaPath, $extracted['entries'][0]['mediaPath']);
        $t->same(['media-resource-mapped:' . $source], $extracted['diagnostics']);
    },

    'loads percent encoded relative media urls through decoded resource keys' => static function (TestRunner $t): void {
        $bag = new MediaBag();
        $bytes = "encoded filename bytes\n";
        $unsafeBytes = "unsafe traversal bytes\n";
        $source = 'assets/review%20figure.png';
        $unsafeSource = 'unsafe/%2e%2e/escape.png';
        $document = new AstNode('document', [], [
            new AstNode('paragraph', [], [
                new AstNode('image', [
                    'url' => $source,
                    'title' => 'Review figure',
                ], [new AstNode('text', ['text' => 'Review figure'])]),
            ]),
            new AstNode('paragraph', [], [
                new AstNode('image', [
                    'url' => $unsafeSource,
                    'title' => 'Unsafe figure',
                ], [new AstNode('text', ['text' => 'Unsafe figure'])]),
            ]),
        ]);

        $filled = $bag->fillDocument($document, [
            'assets/review figure.png' => [
                'contents' => $bytes,
                'mimeType' => 'image/png',
            ],
            'unsafe/../escape.png' => [
                'contents' => $unsafeBytes,
                'mimeType' => 'image/png',
            ],
        ]);
        $directory = $bag->directory();

        $t->same([
            'media-resource-loaded:' . $source,
            'media-resource-missing:' . $unsafeSource,
        ], $filled['diagnostics']);
        $t->same(1, count($directory));
        $t->same($source, $directory[0]['source']);
        $t->same('assets/review figure.png', $directory[0]['path']);
        $t->same('image/png', $directory[0]['mimeType']);
        $t->same(strlen($bytes), $directory[0]['byteLength']);
        $t->same(sha1($bytes), $directory[0]['sha1']);
        $t->true(!str_contains($directory[0]['path'], '%'), 'Decoded safe media path should not preserve percent escapes');

        $missingPlaceholder = $filled['document']->children[1]->children[0];
        $t->same('span', $missingPlaceholder->type);
        $t->same($unsafeSource, $missingPlaceholder->attr('attributes')['original-image-src']);

        $extracted = $bag->extractMedia($filled['document'], 'review-media');
        $mappedDocument = $extracted['document'];
        $t->same('review-media/assets/review figure.png', $mappedDocument->children[0]->children[0]->attr('url'));
        $t->same('span', $mappedDocument->children[1]->children[0]->type);
        $t->same('review-media/assets/review figure.png', $extracted['entries'][0]['path']);
        $t->same('assets/review figure.png', $extracted['entries'][0]['mediaPath']);
        $t->same(['media-resource-mapped:' . $source], $extracted['diagnostics']);
    },

    'deletes mapped media resources by canonical source key' => static function (TestRunner $t): void {
        $bag = new MediaBag();
        $keptBytes = "kept vector bytes\n";
        $deletedBytes = "deleted raster bytes\n";
        $bag->insertMedia('assets\\kept.svg', null, $keptBytes);
        $bag->insertMedia('assets/review/../deleted.png', 'image/png', $deletedBytes);

        $bag->deleteMedia('assets/deleted.png');

        $document = new AstNode('document', [], [
            new AstNode('paragraph', [], [
                new AstNode('image', [
                    'url' => 'assets/kept.svg',
                    'title' => 'Kept asset',
                ], [new AstNode('text', ['text' => 'Kept asset'])]),
            ]),
            new AstNode('paragraph', [], [
                new AstNode('image', [
                    'url' => 'assets/deleted.png',
                    'title' => 'Deleted asset',
                ], [new AstNode('text', ['text' => 'Deleted asset'])]),
            ]),
        ]);

        $items = $bag->mediaItems();
        $directory = $bag->directory();
        $extracted = $bag->extractMedia($document, 'review-media/');
        $mappedDocument = $extracted['document'];

        $t->true($bag->has('assets/kept.svg'));
        $t->true(!$bag->has('assets/deleted.png'));
        $t->same(1, count($items));
        $t->same('assets/kept.svg', $items[0]['path']);
        $t->same('image/svg+xml', $items[0]['mimeType']);
        $t->same($keptBytes, $items[0]['contents']);
        $t->same(strlen($keptBytes), $items[0]['byteLength']);
        $t->same(sha1($keptBytes), $items[0]['sha1']);
        $t->same($directory[0]['path'], $items[0]['path']);
        $t->same(1, count($extracted['entries']));
        $t->same('review-media/assets/kept.svg', $mappedDocument->children[0]->children[0]->attr('url'));
        $t->same('assets/deleted.png', $mappedDocument->children[1]->children[0]->attr('url'));
        $t->same(['media-resource-mapped:assets/kept.svg'], $extracted['diagnostics']);
    },

    'reuses preloaded path-only media for url-suffixed image sources' => static function (TestRunner $t): void {
        $bag = new MediaBag();
        $bytes = "<svg><text>preloaded chart</text></svg>\n";
        $source = 'assets/charts/review.svg?width=640#caption';
        $bag->insertMedia('assets\\charts\\review.svg', 'image/svg+xml', $bytes);

        $document = new AstNode('document', [], [
            new AstNode('paragraph', [], [
                new AstNode('image', [
                    'url' => $source,
                    'title' => 'Preloaded review chart',
                ], [new AstNode('text', ['text' => 'Preloaded review chart'])]),
            ]),
        ]);

        $filled = $bag->fillDocument($document, []);
        $extracted = $bag->extractMedia($filled['document'], 'media');
        $mappedImage = $extracted['document']->children[0]->children[0];

        $t->same([], $filled['diagnostics']);
        $t->same('image', $filled['document']->children[0]->children[0]->type);
        $t->same('media/assets/charts/review.svg', $mappedImage->attr('url'));
        $t->same('media/assets/charts/review.svg', $extracted['entries'][0]['path']);
        $t->same('assets/charts/review.svg', $extracted['entries'][0]['mediaPath']);
        $t->same($bytes, $extracted['entries'][0]['contents']);
        $t->same(['media-resource-mapped:' . $source], $extracted['diagnostics']);
    },

    'maps remote media urls through path-only uri resource keys' => static function (TestRunner $t): void {
        $bag = new MediaBag();
        $loadedBytes = "<svg><text>remote loaded chart</text></svg>\n";
        $preloadedBytes = "remote preloaded photo bytes\n";
        $loadedSource = 'https://cdn.example.test/media/loaded.svg?download=1#review';
        $loadedKey = 'https://cdn.example.test/media/loaded.svg';
        $preloadedSource = 'https://assets.example.test/media/preloaded.jpg?cache=20260609#xywh=10,10,20,20';
        $preloadedKey = 'https://assets.example.test/media/preloaded.jpg';
        $bag->insertMedia($preloadedKey, null, $preloadedBytes);

        $document = new AstNode('document', [], [
            new AstNode('paragraph', [], [
                new AstNode('image', [
                    'url' => $loadedSource,
                    'title' => 'Remote loaded chart',
                ], [new AstNode('text', ['text' => 'Remote loaded chart'])]),
            ]),
            new AstNode('paragraph', [], [
                new AstNode('image', [
                    'url' => $preloadedSource,
                    'title' => 'Remote preloaded photo',
                ], [new AstNode('text', ['text' => 'Remote preloaded photo'])]),
            ]),
        ]);

        $filled = $bag->fillDocument($document, [
            $loadedKey => [
                'contents' => $loadedBytes,
                'mimeType' => 'image/svg+xml',
            ],
        ]);
        $directoryBySource = [];
        foreach ($bag->directory() as $entry) {
            $directoryBySource[$entry['source']] = $entry;
        }
        $loadedPath = sha1($loadedBytes) . '.svg';
        $preloadedPath = sha1($preloadedBytes) . '.jpg';

        $t->same(['media-resource-loaded:' . $loadedSource], $filled['diagnostics']);
        $t->same(2, count($directoryBySource));
        $t->same($loadedPath, $directoryBySource[$loadedSource]['path']);
        $t->same('image/svg+xml', $directoryBySource[$loadedSource]['mimeType']);
        $t->same($preloadedPath, $directoryBySource[$preloadedKey]['path']);
        $t->same('image/jpeg', $directoryBySource[$preloadedKey]['mimeType']);
        $t->true(!str_contains(implode(',', array_column($directoryBySource, 'path')), '?'), 'Remote query delimiters must not become media path characters');
        $t->true(!str_contains(implode(',', array_column($directoryBySource, 'path')), '#'), 'Remote fragment delimiters must not become media path characters');

        $extracted = $bag->extractMedia($filled['document'], 'remote-media');
        $mappedDocument = $extracted['document'];
        $entriesBySource = [];
        foreach ($extracted['entries'] as $entry) {
            $entriesBySource[$entry['source']] = $entry;
        }

        $t->same('remote-media/' . $loadedPath, $mappedDocument->children[0]->children[0]->attr('url'));
        $t->same('remote-media/' . $preloadedPath, $mappedDocument->children[1]->children[0]->attr('url'));
        $t->same(2, count($extracted['entries']));
        $t->same($loadedBytes, $entriesBySource[$loadedSource]['contents']);
        $t->same($preloadedBytes, $entriesBySource[$preloadedKey]['contents']);
        $t->same([
            'media-resource-mapped:' . $loadedSource,
            'media-resource-mapped:' . $preloadedSource,
        ], $extracted['diagnostics']);
    },

    'disambiguates decoded media extraction path collisions' => static function (TestRunner $t): void {
        $bag = new MediaBag();
        $encodedSource = 'assets/%6Co%67o.png';
        $encodedBytes = "encoded logo bytes\n";
        $literalBytes = "literal logo bytes\n";
        $bag->insertMedia($encodedSource, 'image/png', $encodedBytes);
        $bag->insertMedia('assets/logo.png', 'image/png', $literalBytes);

        $document = new AstNode('document', [], [
            new AstNode('paragraph', [], [
                new AstNode('image', [
                    'url' => 'assets/logo.png',
                    'title' => 'Literal logo',
                ], [new AstNode('text', ['text' => 'Literal logo'])]),
            ]),
            new AstNode('paragraph', [], [
                new AstNode('image', [
                    'url' => $encodedSource,
                    'title' => 'Encoded logo',
                ], [new AstNode('text', ['text' => 'Encoded logo'])]),
            ]),
        ]);

        $expectedEncodedPath = 'assets/logo-' . substr(sha1($encodedSource . "\0" . sha1($encodedBytes)), 0, 12) . '.png';
        $extracted = $bag->extractMedia($document, 'media');
        $mappedDocument = $extracted['document'];
        $entriesBySource = [];
        foreach ($extracted['entries'] as $entry) {
            $entriesBySource[$entry['source']] = $entry;
        }

        $t->same('assets/logo.png', $entriesBySource['assets/logo.png']['mediaPath']);
        $t->same($expectedEncodedPath, $entriesBySource[$encodedSource]['mediaPath']);
        $t->same('media/assets/logo.png', $mappedDocument->children[0]->children[0]->attr('url'));
        $t->same('media/' . $expectedEncodedPath, $mappedDocument->children[1]->children[0]->attr('url'));
        $t->same([
            'media-resource-path-collision:' . $encodedSource,
            'media-resource-mapped:assets/logo.png',
            'media-resource-mapped:' . $encodedSource,
        ], $extracted['diagnostics']);
        $t->same(2, count(array_unique(array_column($extracted['entries'], 'path'))));
    },

    'preserves media bag provenance through markdown json and wordpress handoff' => static function (TestRunner $t): void {
        $bag = new MediaBag();
        $bytes = "review image bytes\n";
        $bag->insertMedia('Pictures/review.png', 'image/png', $bytes);
        $document = new AstNode('document', [], [
            new AstNode('paragraph', [], [
                new AstNode('image', [
                    'url' => 'Pictures/review.png',
                    'title' => 'Review image',
                ], [new AstNode('text', ['text' => 'Review image'])]),
            ]),
        ]);

        $extracted = $bag->extractMedia($document, 'media');
        $image = $extracted['document']->children[0]->children[0];
        $attributes = $image->attr('attributes');

        $t->same('media/Pictures/review.png', $image->attr('url'));
        $t->same('Pictures/review.png', $attributes['data-pandoc-media-source']);
        $t->same('Pictures/review.png', $attributes['data-pandoc-media-path']);
        $t->same('media/Pictures/review.png', $attributes['data-pandoc-media-target']);
        $t->same('image/png', $attributes['data-pandoc-media-type']);
        $t->same((string) strlen($bytes), $attributes['data-pandoc-media-bytes']);
        $t->same(sha1($bytes), $attributes['data-pandoc-media-sha1']);

        $markdown = (new MarkdownWriter())->write($extracted['document']);
        $t->contains('![Review image](media/Pictures/review.png "Review image"){', $markdown);
        $t->contains('data-pandoc-media-source="Pictures/review.png"', $markdown);
        $t->contains('data-pandoc-media-sha1="' . sha1($bytes) . '"', $markdown);

        $blocks = (new WordPressBlockWriter())->write($extracted['document']);
        $t->contains('<img src="media/Pictures/review.png"', $blocks);
        $t->contains('data-pandoc-media-source="Pictures/review.png"', $blocks);

        $roundTrip = (new PandocJsonReader())->read((new PandocJsonWriter())->write($extracted['document']));
        $roundTripImage = $roundTrip->children[0]->children[0];
        $t->same('media/Pictures/review.png', $roundTripImage->attr('url'));
        $t->same(sha1($bytes), $roundTripImage->attr('attributes')['data-pandoc-media-sha1']);
    },

    'keeps malformed inline media resources as bounded review placeholders' => static function (TestRunner $t): void {
        $bag = new MediaBag();
        $badDataUri = 'data:image/png;base64,not valid base64 %%';
        $document = new AstNode('document', [], [
            new AstNode('paragraph', [], [
                new AstNode('image', [
                    'url' => $badDataUri,
                    'title' => 'Broken inline image',
                ], [new AstNode('text', ['text' => 'Broken inline image'])]),
            ]),
        ]);

        $filled = $bag->fillDocument($document, []);
        $placeholder = $filled['document']->children[0]->children[0];

        $t->same(['media-resource-invalid:data-uri'], $filled['diagnostics']);
        $t->same([], $bag->directory());
        $t->same('span', $placeholder->type);
        $t->same(['image', 'placeholder'], $placeholder->attr('classes'));
        $t->same($badDataUri, $placeholder->attr('attributes')['original-image-src']);
        $t->same('Broken inline image', $placeholder->attr('attributes')['original-image-title']);
    },
];
