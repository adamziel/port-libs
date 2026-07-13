<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\MediaBag;
use PortLibs\Pandoc\PandocMediaExtractor;
use PortLibs\Pandoc\PandocJsonReader;
use PortLibs\Pandoc\PandocJsonWriter;
use PortLibs\Pandoc\WordPressBlockWriter;

return [
    'keeps unplaced pdf image objects out of reconstructed document text' => static function (TestRunner $t): void {
        $document = new AstNode('document', [], [
            new AstNode('paragraph', ['text' => 'Reconstructed PDF text.'], [
                new AstNode('text', ['text' => 'Reconstructed PDF text.']),
            ]),
        ]);
        $jpegStream = "\xFF\xD8" . str_repeat('A', 9000) . "\xFF\xD9";
        $pdfBytes = "7 0 obj\n"
            . "<< /Subtype /Image /Width 320 /Height 240 /Filter /DCTDecode >>\n"
            . "stream\n"
            . $jpegStream
            . "\nendstream\n"
            . "endobj\n";

        $extracted = (new PandocMediaExtractor())->extract($document, $pdfBytes, 'pdf', [
            'destination' => 'media',
            'imageMode' => 'important',
        ]);
        $children = $extracted['document']->children;

        $t->same(1, count($children));
        $t->same('paragraph', $children[0]->type);
        $t->same('Reconstructed PDF text.', $children[0]->attr('text'));
        $t->same(0, count($extracted['entries']));
        $t->contains('extract-media-pdf-placement-unanchored-scan:0', implode(',', $extracted['diagnostics']));

        $blocks = (new WordPressBlockWriter())->write($extracted['document']);
        $t->contains('Reconstructed PDF text.', $blocks);
        $t->true(!str_contains($blocks, 'pandoc-pdf-extracted-images'));
        $t->true(!str_contains($blocks, '<img'));
    },

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

    'rejects unsafe media extraction destinations before remapping' => static function (TestRunner $t): void {
        $bag = new MediaBag();
        $bytes = "safe destination bytes\n";
        $bag->insertMedia('assets/review.png', 'image/png', $bytes);
        $document = new AstNode('document', [], [
            new AstNode('paragraph', [], [
                new AstNode('image', [
                    'url' => 'assets/review.png',
                    'title' => 'Review image',
                ], [new AstNode('text', ['text' => 'Review image'])]),
            ]),
        ]);

        $safe = $bag->extractMedia($document, '.\\review-media//assets/');
        $t->same('review-media/assets/assets/review.png', $safe['document']->children[0]->children[0]->attr('url'));
        $t->same('review-media/assets/assets/review.png', $safe['entries'][0]['path']);

        foreach (['../outside', '/tmp/media', 'C:/imports/media', 'https://cdn.example.test/media', 'media/%2e%2e/outside'] as $destination) {
            $t->throws(\InvalidArgumentException::class, static fn (): array => $bag->extractMedia($document, $destination));
        }
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

    'normalizes parameterized declared media types during resource mapping' => static function (TestRunner $t): void {
        $bag = new MediaBag();
        $bytes = "<svg><text>parameterized type</text></svg>\n";
        $source = 'https://cdn.example.test/render?id=cover#svg';
        $document = new AstNode('document', [], [
            new AstNode('paragraph', [], [
                new AstNode('image', [
                    'url' => $source,
                    'title' => 'Parameterized SVG',
                ], [new AstNode('text', ['text' => 'Parameterized SVG'])]),
            ]),
        ]);

        $filled = $bag->fillDocument($document, [
            'https://cdn.example.test/render' => [
                'contents' => $bytes,
                'mimeType' => 'IMAGE/SVG+XML; CHARSET=UTF-8',
            ],
        ]);
        $directory = $bag->directory();
        $path = sha1($bytes) . '.svg';

        $t->same(['media-resource-loaded:' . $source], $filled['diagnostics']);
        $t->same(1, count($directory));
        $t->same($source, $directory[0]['source']);
        $t->same($path, $directory[0]['path']);
        $t->same('image/svg+xml', $directory[0]['mimeType']);
        $t->same('declared', $directory[0]['mimeTypeSource']);
        $t->same('application/octet-stream', $directory[0]['inferredMimeType']);
        $t->same('declared-mime-matches-path', $directory[0]['mimeRepairSummary']);
        $t->true(!str_contains($directory[0]['mimeType'], ';'), 'Media bag MIME type should not retain parameters');

        $extracted = $bag->extractMedia($filled['document'], 'media');
        $mappedImage = $extracted['document']->children[0]->children[0];
        $attributes = $mappedImage->attr('attributes');

        $t->same('media/' . $path, $mappedImage->attr('url'));
        $t->same('media/' . $path, $extracted['entries'][0]['path']);
        $t->same('image/svg+xml', $extracted['entries'][0]['mimeType']);
        $t->same('image/svg+xml', $attributes['data-pandoc-media-type']);
        $t->same('declared', $attributes['data-pandoc-media-mime-source']);
        $t->same('declared-mime-matches-path', $attributes['data-pandoc-media-mime-repair']);
        $t->same(['media-resource-mapped:' . $source], $extracted['diagnostics']);
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

        $blocks = (new WordPressBlockWriter())->write($extracted['document']);
        $t->contains('<img src="media/Pictures/review.png"', $blocks);
        $t->contains('data-pandoc-media-source="Pictures/review.png"', $blocks);

        $roundTrip = (new PandocJsonReader())->read((new PandocJsonWriter())->write($extracted['document']));
        $roundTripImage = $roundTrip->children[0]->children[0];
        $t->same('media/Pictures/review.png', $roundTripImage->attr('url'));
        $t->same(sha1($bytes), $roundTripImage->attr('attributes')['data-pandoc-media-sha1']);
    },

    'rebases linked media resources across markdown and native ast handoff' => static function (TestRunner $t): void {
        $markdownBag = new MediaBag();
        $packetBytes = "%PDF linked packet\n";
        $chartBytes = "<svg><text>linked chart</text></svg>\n";
        $markdownDocument = (new MarkdownReader())->read(
            'Download [review packet](downloads/review.pdf "Review packet") and inspect ![Chart](figures/chart.svg).'
        );

        $filled = $markdownBag->fillDocument($markdownDocument, [
            'downloads/review.pdf' => [
                'contents' => $packetBytes,
                'mimeType' => 'application/pdf',
            ],
            'figures/chart.svg' => [
                'contents' => $chartBytes,
                'mimeType' => 'image/svg+xml',
            ],
        ]);
        $extracted = $markdownBag->extractMedia($filled['document'], 'media');
        $mappedParagraph = $extracted['document']->children[0];
        $mappedLink = $mappedParagraph->children[1];
        $mappedImage = $mappedParagraph->children[3];

        $t->same([
            'media-resource-link-loaded:downloads/review.pdf',
            'media-resource-loaded:figures/chart.svg',
        ], $filled['diagnostics']);
        $t->same([
            'media-resource-link-mapped:downloads/review.pdf',
            'media-resource-mapped:figures/chart.svg',
        ], $extracted['diagnostics']);
        $t->same('media/downloads/review.pdf', $mappedLink->attr('url'));
        $t->same('media/figures/chart.svg', $mappedImage->attr('url'));
        $t->same('downloads/review.pdf', $mappedLink->attr('attributes')['data-pandoc-media-source']);
        $t->same('application/pdf', $mappedLink->attr('attributes')['data-pandoc-media-type']);
        $t->same(sha1($packetBytes), $mappedLink->attr('attributes')['data-pandoc-media-sha1']);
        $blocks = (new WordPressBlockWriter())->write($extracted['document']);
        $t->contains('<a href="media/downloads/review.pdf" title="Review packet" data-pandoc-media-source="downloads/review.pdf"', $blocks);
        $t->contains('<img src="media/figures/chart.svg" alt="Chart"', $blocks);

        $nativeBag = new MediaBag();
        $nativeBytes = "source attachment bytes\n";
        $nativeDocument = (new PandocJsonReader())->readPacket([
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [[
                't' => 'Para',
                'c' => [[
                    't' => 'Link',
                    'c' => [
                        ['', [], []],
                        [['t' => 'Str', 'c' => 'attachment']],
                        ['assets/source.txt', 'Source attachment'],
                    ],
                ]],
            ]],
        ]);

        $nativeBag->insertMedia('assets/source.txt', 'text/plain', $nativeBytes);
        $nativeExtracted = $nativeBag->extractMedia($nativeDocument, 'native-media');
        $nativePacket = (new PandocJsonWriter())->toArray($nativeExtracted['document']);

        $t->same(['media-resource-link-mapped:assets/source.txt'], $nativeExtracted['diagnostics']);
        $t->same('native-media/assets/source.txt', $nativeExtracted['document']->children[0]->children[0]->attr('url'));
        $t->same('native-media/assets/source.txt', $nativePacket['blocks'][0]['c'][0]['c'][2][0]);
        $t->same('Source attachment', $nativePacket['blocks'][0]['c'][0]['c'][2][1]);
    },

    'infers linked resource mime types from package-local paths' => static function (TestRunner $t): void {
        $bag = new MediaBag();
        $cssBytes = "body { color: #334; }\n";
        $audioBytes = "ID3 review audio bytes\n";
        $fontBytes = "wOF2 review font bytes\n";
        $jsonBytes = '{"ok":true}';
        $cssSource = 'styles/site.CSS?rev=1#screen';
        $audioSource = 'media/clip.MP3';
        $fontSource = 'fonts/review.woff2';
        $jsonSource = 'data:application/json,' . rawurlencode($jsonBytes);
        $link = static fn (string $url, string $title, string $text): AstNode => new AstNode('link', [
            'url' => $url,
            'title' => $title,
        ], [new AstNode('text', ['text' => $text])]);
        $document = new AstNode('document', [], [
            new AstNode('paragraph', [], [
                $link($cssSource, 'Stylesheet', 'stylesheet'),
                new AstNode('space'),
                $link($audioSource, 'Audio', 'audio'),
                new AstNode('space'),
                $link($fontSource, 'Font', 'font'),
                new AstNode('space'),
                $link($jsonSource, 'JSON metadata', 'metadata'),
            ]),
        ]);

        $filled = $bag->fillDocument($document, [
            'styles/site.CSS' => $cssBytes,
            $audioSource => $audioBytes,
            $fontSource => $fontBytes,
        ]);
        $directoryBySource = [];
        foreach ($bag->directory() as $entry) {
            $directoryBySource[$entry['source']] = $entry;
        }
        $cssPath = sha1($cssBytes) . '.css';
        $jsonPath = sha1($jsonBytes) . '.json';

        $t->same([
            'media-resource-link-loaded:' . $cssSource,
            'media-resource-link-loaded:' . $audioSource,
            'media-resource-link-loaded:' . $fontSource,
            'media-resource-link-loaded:data-uri',
        ], $filled['diagnostics']);
        $t->same($cssPath, $directoryBySource[$cssSource]['path']);
        $t->same('text/css', $directoryBySource[$cssSource]['mimeType']);
        $t->same($audioSource, $directoryBySource[$audioSource]['path']);
        $t->same('audio/mpeg', $directoryBySource[$audioSource]['mimeType']);
        $t->same($fontSource, $directoryBySource[$fontSource]['path']);
        $t->same('font/woff2', $directoryBySource[$fontSource]['mimeType']);
        $t->same($jsonPath, $directoryBySource[$jsonSource]['path']);
        $t->same('application/json', $directoryBySource[$jsonSource]['mimeType']);

        $extracted = $bag->extractMedia($filled['document'], 'media');
        $mappedParagraph = $extracted['document']->children[0];
        $mappedCss = $mappedParagraph->children[0];
        $mappedAudio = $mappedParagraph->children[2];
        $mappedFont = $mappedParagraph->children[4];
        $mappedJson = $mappedParagraph->children[6];

        $t->same([
            'media-resource-link-mapped:' . $cssSource,
            'media-resource-link-mapped:' . $audioSource,
            'media-resource-link-mapped:' . $fontSource,
            'media-resource-link-mapped:data-uri',
        ], $extracted['diagnostics']);
        $t->same('media/' . $cssPath, $mappedCss->attr('url'));
        $t->same('text/css', $mappedCss->attr('attributes')['data-pandoc-media-type']);
        $t->same('media/' . $audioSource, $mappedAudio->attr('url'));
        $t->same('audio/mpeg', $mappedAudio->attr('attributes')['data-pandoc-media-type']);
        $t->same('media/' . $fontSource, $mappedFont->attr('url'));
        $t->same('font/woff2', $mappedFont->attr('attributes')['data-pandoc-media-type']);
        $t->same('media/' . $jsonPath, $mappedJson->attr('url'));
        $t->same('application/json', $mappedJson->attr('attributes')['data-pandoc-media-type']);
        $blocks = (new WordPressBlockWriter())->write($extracted['document']);
        $t->contains('<a href="media/' . $fontSource . '" title="Font" data-pandoc-media-source="' . $fontSource . '"', $blocks);
        $t->contains('data-pandoc-media-type="font/woff2"', $blocks);

        $roundTrip = (new PandocJsonReader())->read((new PandocJsonWriter())->write($extracted['document']));
        $t->same('font/woff2', $roundTrip->children[0]->children[4]->attr('attributes')['data-pandoc-media-type']);
        $t->same('application/json', $roundTrip->children[0]->children[6]->attr('attributes')['data-pandoc-media-type']);
    },

    'diagnoses media bag repair conflicts with stable provenance' => static function (TestRunner $t): void {
        $bag = new MediaBag();
        $upperLogoBytes = "upper-case logo bytes\n";
        $lowerLogoBytes = "lower-case logo bytes\n";
        $encodedSource = 'assets/review%20figure.png';
        $encodedBytes = "encoded exact figure bytes\n";
        $decodedBytes = "decoded repaired figure bytes\n";
        $reportSource = 'downloads/report.pdf?raw=1';
        $reportExactBytes = "%PDF exact linked report\n";
        $reportRepairBytes = "%PDF repaired linked report\n";
        $photoSource = 'media/photo.png';
        $photoBytes = "jpeg bytes behind png path\n";
        $link = static fn (string $url, string $title, string $text): AstNode => new AstNode('link', [
            'url' => $url,
            'title' => $title,
        ], [new AstNode('text', ['text' => $text])]);

        $bag->insertMedia('Assets/Logo.png', 'image/png', $upperLogoBytes);
        $bag->insertMedia('assets/logo.png', 'image/png', $lowerLogoBytes);

        $document = new AstNode('document', [], [
            new AstNode('paragraph', [], [
                new AstNode('image', [
                    'url' => $encodedSource,
                    'title' => 'Encoded review figure',
                ], [new AstNode('text', ['text' => 'Encoded review figure'])]),
            ]),
            new AstNode('paragraph', [], [
                $link($reportSource, 'Review report', 'report'),
                new AstNode('space'),
                $link('Assets/Logo.png', 'Upper logo', 'upper logo'),
                new AstNode('space'),
                $link('assets/logo.png', 'Lower logo', 'lower logo'),
                new AstNode('space'),
                $link($photoSource, 'Photo', 'photo'),
            ]),
        ]);

        $filled = $bag->fillDocument($document, [
            $encodedSource => [
                'contents' => $encodedBytes,
                'mimeType' => 'image/png',
            ],
            'assets/review figure.png' => [
                'contents' => $decodedBytes,
                'mimeType' => 'image/png',
            ],
            $reportSource => [
                'contents' => $reportExactBytes,
                'mimeType' => 'application/pdf',
            ],
            'downloads/report.pdf' => [
                'contents' => $reportRepairBytes,
                'mimeType' => 'application/pdf',
            ],
            $photoSource => [
                'contents' => $photoBytes,
                'mimeType' => 'image/jpeg',
            ],
        ]);
        $expectedLowerLogoPath = 'assets/logo-' . substr(sha1('assets/logo.png' . "\0" . sha1($lowerLogoBytes)), 0, 12) . '.png';

        $t->same([
            'media-resource-repair-conflict:' . $encodedSource,
            'media-resource-percent-decode-conflict:' . $encodedSource,
            'media-resource-loaded:' . $encodedSource,
            'media-resource-repair-conflict:' . $reportSource,
            'media-resource-link-duplicate-mime-summary:' . $reportSource . ':application/pdf=2',
            'media-resource-link-mime-group-conflict:' . $reportSource,
            'media-resource-link-loaded:' . $reportSource,
            'media-resource-content-type-conflict:' . $photoSource,
            'media-resource-link-loaded:' . $photoSource,
        ], $filled['diagnostics']);

        $extracted = $bag->extractMedia($filled['document'], 'media');
        $diagnostics = implode(',', $extracted['diagnostics']);
        $mappedImage = $extracted['document']->children[0]->children[0];
        $mappedParagraph = $extracted['document']->children[1];
        $mappedReport = $mappedParagraph->children[0];
        $mappedUpperLogo = $mappedParagraph->children[2];
        $mappedLowerLogo = $mappedParagraph->children[4];
        $mappedPhoto = $mappedParagraph->children[6];
        $imageAttributes = $mappedImage->attr('attributes');
        $reportAttributes = $mappedReport->attr('attributes');
        $lowerLogoAttributes = $mappedLowerLogo->attr('attributes');
        $upperLogoAttributes = $mappedUpperLogo->attr('attributes');
        $photoAttributes = $mappedPhoto->attr('attributes');

        $t->contains('media-resource-path-casefold-conflict:assets/logo.png', $diagnostics);
        $t->contains('media-resource-path-collision:assets/logo.png', $diagnostics);
        $t->contains('media-resource-content-type-conflict:' . $photoSource, $diagnostics);
        $t->contains('media-resource-mapped:' . $encodedSource, $diagnostics);
        $t->contains('media-resource-link-mapped:' . $reportSource, $diagnostics);
        $t->same('media/assets/review figure.png', $mappedImage->attr('url'));
        $t->same('media/' . sha1($reportExactBytes) . '.pdf', $mappedReport->attr('url'));
        $t->same('media/Assets/Logo.png', $mappedUpperLogo->attr('url'));
        $t->same('media/' . $expectedLowerLogoPath, $mappedLowerLogo->attr('url'));
        $t->same('media/media/photo.png', $mappedPhoto->attr('url'));

        $t->same($encodedSource, $imageAttributes['data-pandoc-media-source']);
        $t->same($encodedSource, $imageAttributes['data-pandoc-media-canonical-source']);
        $t->same('assets/review figure.png', $imageAttributes['data-pandoc-media-original-path']);
        $t->same('assets/review figure.png', $imageAttributes['data-pandoc-media-path']);
        $t->same('false', $imageAttributes['data-pandoc-media-path-repaired']);
        $t->same($reportSource, $reportAttributes['data-pandoc-media-source']);
        $t->same($reportSource, $reportAttributes['data-pandoc-media-canonical-source']);
        $t->same(sha1($reportExactBytes) . '.pdf', $reportAttributes['data-pandoc-media-original-path']);
        $t->same('media/' . sha1($reportExactBytes) . '.pdf', $reportAttributes['data-pandoc-media-target']);
        $t->same('application/pdf', $reportAttributes['data-pandoc-media-type']);
        $t->same('assets/logo.png', $lowerLogoAttributes['data-pandoc-media-source']);
        $t->same('assets/logo.png', $lowerLogoAttributes['data-pandoc-media-canonical-source']);
        $t->same('assets/logo.png', $lowerLogoAttributes['data-pandoc-media-original-path']);
        $t->same($expectedLowerLogoPath, $lowerLogoAttributes['data-pandoc-media-path']);
        $t->same('media/' . $expectedLowerLogoPath, $lowerLogoAttributes['data-pandoc-media-target']);
        $t->same('true', $lowerLogoAttributes['data-pandoc-media-path-repaired']);
        $t->same(sha1('assets/logo.png'), $lowerLogoAttributes['data-pandoc-media-source-sha1']);
        $t->same('false', $upperLogoAttributes['data-pandoc-media-path-repaired']);
        $t->same('image/jpeg', $photoAttributes['data-pandoc-media-type']);
    },

    'reports linked resource repair provenance and duplicate mime groups' => static function (TestRunner $t): void {
        $bag = new MediaBag();
        $normalizedSource = 'docs/drafts/../Review.pdf';
        $encodedSource = 'docs/review%20packet.pdf';
        $mismatchedSource = 'docs/style.PDF?download=1';
        $caseUpperSource = 'Media/Case.PDF';
        $caseLowerSource = 'media/case.pdf';
        $normalizedBytes = "%PDF normalized path\n";
        $encodedBytes = "%PDF encoded path\n";
        $mismatchedBytes = "body { color: #224; }\n";
        $caseUpperBytes = "%PDF upper case\n";
        $caseLowerBytes = "%PDF lower case\n";
        $link = static fn (string $url, string $label): AstNode => new AstNode('link', [
            'url' => $url,
            'title' => $label,
        ], [new AstNode('text', ['text' => $label])]);

        $document = new AstNode('document', [], [
            new AstNode('paragraph', [], [
                $link($normalizedSource, 'normalized'),
                new AstNode('space'),
                $link($encodedSource, 'encoded'),
                new AstNode('space'),
                $link($mismatchedSource, 'style'),
                new AstNode('space'),
                $link($caseUpperSource, 'case upper'),
                new AstNode('space'),
                $link($caseLowerSource, 'case lower'),
            ]),
        ]);

        $filled = $bag->fillDocument($document, [
            'docs/Review.pdf' => [
                'contents' => $normalizedBytes,
                'mimeType' => 'application/pdf',
            ],
            'docs/review packet.pdf' => [
                'contents' => $encodedBytes,
                'mimeType' => 'application/pdf',
            ],
            'docs/style.PDF' => [
                'contents' => $mismatchedBytes,
                'mimeType' => 'text/css',
            ],
            $caseUpperSource => [
                'contents' => $caseUpperBytes,
                'mimeType' => 'application/pdf',
            ],
            $caseLowerSource => [
                'contents' => $caseLowerBytes,
                'mimeType' => 'application/pdf',
            ],
        ]);
        $directoryBySource = [];
        foreach ($bag->directory() as $entry) {
            $directoryBySource[$entry['source']] = $entry;
        }

        $mismatchedPath = sha1($mismatchedBytes) . '.css';
        $caseLowerPath = 'media/case-' . substr(sha1($caseLowerSource . "\0" . sha1($caseLowerBytes)), 0, 12) . '.pdf';
        $t->same([
            'media-resource-link-loaded:' . $normalizedSource,
            'media-resource-link-loaded:' . $encodedSource,
            'media-resource-content-type-conflict:' . $mismatchedSource,
            'media-resource-link-loaded:' . $mismatchedSource,
            'media-resource-link-loaded:' . $caseUpperSource,
            'media-resource-link-loaded:' . $caseLowerSource,
        ], $filled['diagnostics']);
        $t->same('docs/Review.pdf', $directoryBySource[$normalizedSource]['canonicalSource']);
        $t->same('docs/Review.pdf', $directoryBySource[$normalizedSource]['path']);
        $t->same('normalized-path', $directoryBySource[$normalizedSource]['pathRepairSummary']);
        $t->same('docs/review packet.pdf', $directoryBySource[$encodedSource]['path']);
        $t->same('percent-decoded-path', $directoryBySource[$encodedSource]['pathRepairSummary']);
        $t->same($mismatchedPath, $directoryBySource[$mismatchedSource]['path']);
        $t->same('url-suffix-hash-path', $directoryBySource[$mismatchedSource]['pathRepairSummary']);
        $t->same('application/pdf', $directoryBySource[$mismatchedSource]['inferredMimeType']);
        $t->same('extension-content-type-disagreement:.pdf:application/pdf=>text/css:path-extension-from-content-type', $directoryBySource[$mismatchedSource]['mimeRepairSummary']);

        $extracted = $bag->extractMedia($filled['document'], 'media');
        $mappedParagraph = $extracted['document']->children[0];
        $mappedNormalized = $mappedParagraph->children[0];
        $mappedEncoded = $mappedParagraph->children[2];
        $mappedMismatch = $mappedParagraph->children[4];
        $mappedUpper = $mappedParagraph->children[6];
        $mappedLower = $mappedParagraph->children[8];
        $entriesBySource = [];
        foreach ($extracted['entries'] as $entry) {
            $entriesBySource[$entry['source']] = $entry;
        }

        $t->contains('media-resource-path-collision:' . $caseLowerSource, implode(',', $extracted['diagnostics']));
        $t->contains('media-resource-path-casefold-collision:' . $caseLowerSource, implode(',', $extracted['diagnostics']));
        $t->contains('media-resource-link-mapped:' . $caseLowerSource, implode(',', $extracted['diagnostics']));
        $t->same('media/docs/Review.pdf', $mappedNormalized->attr('url'));
        $t->same('media/docs/review packet.pdf', $mappedEncoded->attr('url'));
        $t->same('media/' . $mismatchedPath, $mappedMismatch->attr('url'));
        $t->same('media/Media/Case.PDF', $mappedUpper->attr('url'));
        $t->same('media/' . $caseLowerPath, $mappedLower->attr('url'));
        $t->same($caseLowerPath, $entriesBySource[$caseLowerSource]['mediaPath']);
        $t->same('safe-relative-path,casefold-path-collision-disambiguated', $entriesBySource[$caseLowerSource]['extractionPathRepairSummary']);
        $t->same('application-pdf', $entriesBySource[$normalizedSource]['linkedMimeGroup']);
        $t->same(4, $entriesBySource[$normalizedSource]['linkedMimeGroupSize']);
        $t->true(!array_key_exists('linkedMimeGroup', $entriesBySource[$mismatchedSource]), 'Single CSS linked resource should not receive a duplicate MIME group');

        $normalizedAttrs = $mappedNormalized->attr('attributes');
        $encodedAttrs = $mappedEncoded->attr('attributes');
        $mismatchAttrs = $mappedMismatch->attr('attributes');
        $lowerAttrs = $mappedLower->attr('attributes');
        $t->same($normalizedSource, $normalizedAttrs['data-pandoc-media-source']);
        $t->same('docs/Review.pdf', $normalizedAttrs['data-pandoc-media-canonical-source']);
        $t->same('docs/Review.pdf', $normalizedAttrs['data-pandoc-media-source-path']);
        $t->same(sha1($normalizedSource), $normalizedAttrs['data-pandoc-media-source-sha1']);
        $t->same('normalized-path', $normalizedAttrs['data-pandoc-media-path-repair']);
        $t->same('percent-decoded-path', $encodedAttrs['data-pandoc-media-path-repair']);
        $t->same('application/pdf', $mismatchAttrs['data-pandoc-media-inferred-type']);
        $t->same('declared', $mismatchAttrs['data-pandoc-media-mime-source']);
        $t->same('extension-content-type-disagreement:.pdf:application/pdf=>text/css:path-extension-from-content-type', $mismatchAttrs['data-pandoc-media-mime-repair']);
        $t->same('application-pdf', $lowerAttrs['data-pandoc-media-linked-mime-group']);
        $t->same('4', $lowerAttrs['data-pandoc-media-linked-mime-group-size']);
        $t->same('safe-relative-path,casefold-path-collision-disambiguated', $lowerAttrs['data-pandoc-media-path-repair']);
        $blocks = (new WordPressBlockWriter())->write($extracted['document']);
        $roundTrip = (new PandocJsonReader())->read((new PandocJsonWriter())->write($extracted['document']));
        $t->contains('data-pandoc-media-linked-mime-group="application-pdf"', $blocks);
        $t->same('casefold-path-collision-disambiguated', explode(',', $roundTrip->children[0]->children[8]->attr('attributes')['data-pandoc-media-path-repair'])[1]);
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
