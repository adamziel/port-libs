<?php

declare(strict_types=1);

use PortLibs\LightningCSS\SourceMap;

return [
    'source map encodes upstream input-source-map remapped columns' => static function (TestRunner $t): void {
        $map = new SourceMap();
        $imported = $map->addSource('sass/_demo.scss');
        $stdin = $map->addSource('stdin');
        $map->setSourceContent($imported, ".imported {\n  content: \"yay, file support!\";\n}");
        $map->setSourceContent($stdin, "@import \"_variables\";\n@import \"_demo\";\n\n.selector {\n  margin: \$size;\n  background-color: \$brandColor;\n\n  .nested {\n    margin: \$size / 2;\n  }\n}");

        $map->addMapping(0, 0, $imported, 0, 0);
        $map->addMapping(0, 39, $stdin, 3, 0);
        $map->addMapping(0, 82, $stdin, 3, 0);

        $t->same('AAAA,uCCGA,2CAAA', $map->writeVlq());
        $t->same(
            '{"version":3,"sourceRoot":null,"mappings":"AAAA,uCCGA,2CAAA","sources":["sass/_demo.scss","stdin"],"sourcesContent":[".imported {\n  content: \"yay, file support!\";\n}","@import \"_variables\";\n@import \"_demo\";\n\n.selector {\n  margin: $size;\n  background-color: $brandColor;\n\n  .nested {\n    margin: $size / 2;\n  }\n}"],"names":[]}',
            $map->toJson()
        );
    },
    'source map encodes upstream license-comment generated line offsets' => static function (TestRunner $t): void {
        $source = "/*! a single line comment */\n    /*!\n      a comment\n      containing\n      multiple\n      lines\n    */\n    .a {\n      display: flex;\n    }\n\n    .b {\n      display: hidden;\n    }\n    ";
        $map = new SourceMap();
        $sourceIndex = $map->addSource('input.css');
        $map->setSourceContent($sourceIndex, $source);

        $map->addPrinterMapping(7, 0, $sourceIndex, 7, 5);
        $map->addPrinterMapping(7, 16, $sourceIndex, 11, 5);

        $t->same(';;;;;;;AAOI,gBAIA', $map->writeVlq());
        $t->same(
            '{"version":3,"sourceRoot":null,"mappings":";;;;;;;AAOI,gBAIA","sources":["input.css"],"sourcesContent":["/*! a single line comment */\n    /*!\n      a comment\n      containing\n      multiple\n      lines\n    */\n    .a {\n      display: flex;\n    }\n\n    .b {\n      display: hidden;\n    }\n    "],"names":[]}',
            $map->toJson()
        );
    },
    'source map encodes upstream cli css module semicolon offsets' => static function (TestRunner $t): void {
        $map = new SourceMap();
        $sourceIndex = $map->addSource('test.css');

        foreach ([[0, 1], [4, 5], [8, 9], [18, 14], [22, 18], [26, 22]] as [$generatedLine, $originalLine]) {
            $map->addPrinterMapping($generatedLine, 0, $sourceIndex, $originalLine, 7);
        }

        $t->same('AACM;;;;AAIA;;;;AAIA;;;;;;;;;;AAKA;;;;AAIA;;;;AAIA', $map->writeVlq());
        $decoded = SourceMap::decodeVlq('AACM;;;;AAIA;;;;AAIA;;;;;;;;;;AAKA;;;;AAIA;;;;AAIA');
        $t->same(
            [
                ['generatedLine' => 0, 'generatedColumn' => 0, 'sourceIndex' => 0, 'originalLine' => 1, 'originalColumn' => 6, 'nameIndex' => null],
                ['generatedLine' => 4, 'generatedColumn' => 0, 'sourceIndex' => 0, 'originalLine' => 5, 'originalColumn' => 6, 'nameIndex' => null],
                ['generatedLine' => 8, 'generatedColumn' => 0, 'sourceIndex' => 0, 'originalLine' => 9, 'originalColumn' => 6, 'nameIndex' => null],
                ['generatedLine' => 18, 'generatedColumn' => 0, 'sourceIndex' => 0, 'originalLine' => 14, 'originalColumn' => 6, 'nameIndex' => null],
                ['generatedLine' => 22, 'generatedColumn' => 0, 'sourceIndex' => 0, 'originalLine' => 18, 'originalColumn' => 6, 'nameIndex' => null],
                ['generatedLine' => 26, 'generatedColumn' => 0, 'sourceIndex' => 0, 'originalLine' => 22, 'originalColumn' => 6, 'nameIndex' => null],
            ],
            $decoded
        );
    },
    'source map encodes upstream bundled source-index offset deltas' => static function (TestRunner $t): void {
        $map = new SourceMap();
        $entry = $map->addSource('a.css');
        $imported = $map->addSource('sass/_demo.scss');
        $stdin = $map->addSource('stdin');
        $map->setSourceContent($entry, "\n        @import \"/b.css\";\n        .a { color: red; }\n      ");
        $map->setSourceContent($imported, ".imported {\n  content: \"yay, file support!\";\n}");
        $map->setSourceContent($stdin, "@import \"_variables\";\n@import \"_demo\";\n\n.selector {\n  margin: \$size;\n  background-color: \$brandColor;\n\n  .nested {\n    margin: \$size / 2;\n  }\n}");

        $map->addMapping(0, 0, $imported, 0, 0);
        $map->addMapping(0, 39, $stdin, 3, 0);
        $map->addMapping(0, 82, $stdin, 3, 0);
        $map->addMappingWithOffset(0, 30, $entry, 2, 8, 0, 82);

        $t->same('ACAA,uCCGA,2CAAA,8BFDQ', $map->writeVlq());
        $t->same(
            '{"version":3,"sourceRoot":null,"mappings":"ACAA,uCCGA,2CAAA,8BFDQ","sources":["a.css","sass/_demo.scss","stdin"],"sourcesContent":["\n        @import \"/b.css\";\n        .a { color: red; }\n      ",".imported {\n  content: \"yay, file support!\";\n}","@import \"_variables\";\n@import \"_demo\";\n\n.selector {\n  margin: $size;\n  background-color: $brandColor;\n\n  .nested {\n    margin: $size / 2;\n  }\n}"],"names":[]}',
            $map->toJson()
        );

        $decoded = SourceMap::decodeVlq($map->writeVlq());
        $t->same(112, $decoded[3]['generatedColumn']);
        $t->same(0, $decoded[3]['sourceIndex']);
        $t->same(2, $decoded[3]['originalLine']);
        $t->same(8, $decoded[3]['originalColumn']);
    },
    'source map applies generated offsets and merges nested source maps' => static function (TestRunner $t): void {
        $child = new SourceMap();
        $childSource = $child->addSource('blocks/card.module.css');
        $child->setSourceContent($childSource, ".card {\n  color: red;\n}\n.icon {\n  color: blue;\n}");
        $child->addMapping(0, 0, $childSource, 0, 0, 'card');
        $child->addMapping(0, 18, $childSource, 3, 0, 'icon');

        $map = new SourceMap();
        $entry = $map->addSource('theme.css');
        $map->setSourceContent($entry, "@import \"blocks/card.module.css\";\n.theme { color: green; }");
        $map->addMapping(0, 0, $entry, 1, 0);
        $map->addSourceMap($child, 2);

        $offsetSource = $map->addSource('offset.css');
        $map->addMappingWithOffset(1, 4, $offsetSource, 7, 2, 3, 11, 'offsetRule');

        $decoded = SourceMap::decodeVlq($map->writeVlq());
        $t->same([0, 2, 2, 4], array_column($decoded, 'generatedLine'));
        $t->same([0, 0, 18, 15], array_column($decoded, 'generatedColumn'));
        $t->same([0, 1, 1, 2], array_column($decoded, 'sourceIndex'));
        $t->same([null, 0, 1, 2], array_column($decoded, 'nameIndex'));
        $t->same(['theme.css', 'blocks/card.module.css', 'offset.css'], $map->toArray(null, false)['sources']);
        $t->same(['card', 'icon', 'offsetRule'], $map->toArray(null, false)['names']);

        $t->throws(InvalidArgumentException::class, static function () use ($map, $entry): void {
            $map->addMappingWithOffset(0, 0, $entry, 0, 0, -1, 0);
        });
        $t->throws(InvalidArgumentException::class, static function () use ($map, $entry): void {
            $map->addMappingWithOffset(0, 0, $entry, 0, 0, 0, -1);
        });
    },
    'source map replaces overlapped source-map lines when merging nested maps' => static function (TestRunner $t): void {
        $map = new SourceMap();
        $entry = $map->addSource('entry.css');
        $map->setSourceContent($entry, ".entry{}\n.keep{}");
        $map->addMapping(0, 0, $entry, 0, 0, 'parentTop');
        $map->addMapping(1, 0, $entry, 1, 0, 'parentMiddle');
        $map->addMapping(2, 0, $entry, 2, 0, 'parentBottom');
        $map->addMapping(4, 0, $entry, 4, 0, 'parentKeep');

        $child = new SourceMap();
        $childSource = $child->addSource('child.css');
        $child->setSourceContent($childSource, ".child{}\n\n.end{}");
        $child->addMapping(0, 5, $childSource, 0, 2, 'childStart');
        $child->addMapping(2, 7, $childSource, 2, 4, 'childEnd');

        $map->addSourceMap($child);
        $decoded = SourceMap::decodeVlq($map->writeVlq());
        $data = $map->toArray(null, false);

        $t->same([0, 2, 4], array_column($decoded, 'generatedLine'));
        $t->same([5, 7, 0], array_column($decoded, 'generatedColumn'));
        $t->same([1, 1, 0], array_column($decoded, 'sourceIndex'));
        $t->same([0, 2, 4], array_column($decoded, 'originalLine'));
        $t->same([4, 5, 3], array_column($decoded, 'nameIndex'));
        $t->same(['entry.css', 'child.css'], $data['sources']);

        $negativeOffset = new SourceMap();
        $negativeEntry = $negativeOffset->addSource('negative-entry.css');
        $negativeOffset->addMapping(0, 0, $negativeEntry, 0, 0, 'droppedParent');
        $negativeChild = new SourceMap();
        $negativeChildSource = $negativeChild->addSource('negative-child.css');
        $negativeChild->addMapping(0, 1, $negativeChildSource, 0, 0, 'droppedChild');
        $negativeChild->addMapping(1, 3, $negativeChildSource, 1, 2, 'keptChild');

        $negativeOffset->addSourceMap($negativeChild, -1);
        $negativeDecoded = SourceMap::decodeVlq($negativeOffset->writeVlq());

        $t->same([0], array_column($negativeDecoded, 'generatedLine'));
        $t->same([3], array_column($negativeDecoded, 'generatedColumn'));
        $t->same([1], array_column($negativeDecoded, 'sourceIndex'));
        $t->same([1], array_column($negativeDecoded, 'originalLine'));
    },
    'source map keeps generated-column deltas line-local and original offsets global' => static function (TestRunner $t): void {
        $map = new SourceMap();
        $sourceIndex = $map->addSource('blocks.css');
        $map->addMapping(0, 12, $sourceIndex, 2, 4);
        $map->addMapping(0, 30, $sourceIndex, 2, 18);
        $map->addMapping(3, 4, $sourceIndex, 9, 2);
        $map->addMapping(3, 9, $sourceIndex, 9, 16);

        $decoded = SourceMap::decodeVlq($map->writeVlq());

        $t->same(12, $decoded[0]['generatedColumn']);
        $t->same(30, $decoded[1]['generatedColumn']);
        $t->same(4, $decoded[2]['generatedColumn']);
        $t->same(9, $decoded[3]['generatedColumn']);
        $t->same(9, $decoded[2]['originalLine']);
        $t->same(16, $decoded[3]['originalColumn']);
    },
    'source map parses upstream raw vlq maps with generated-only segments and names' => static function (TestRunner $t): void {
        $json = '{"version":3,"mappings":"A,MAAMA;ECGCC;A","sources":["compiled.css","tokens.scss"],"sourcesContent":[".compiled{}",".token{}"],"names":["token","accent"]}';

        $map = SourceMap::fromJson($json);
        $decoded = SourceMap::decodeVlq($map->writeVlq());
        $data = $map->toArray(null, false);

        $t->same('A,MAAMA;ECGCC;A', $map->writeVlq());
        $t->same(null, $decoded[0]['sourceIndex']);
        $t->same(6, $decoded[1]['generatedColumn']);
        $t->same(0, $decoded[1]['nameIndex']);
        $t->same(1, $decoded[2]['sourceIndex']);
        $t->same(1, $decoded[2]['nameIndex']);
        $t->same(null, $decoded[3]['sourceIndex']);
        $t->same(['compiled.css', 'tokens.scss'], $data['sources']);
        $t->same(['.compiled{}', '.token{}'], $data['sourcesContent']);
        $t->same(['token', 'accent'], $data['names']);
    },
    'source map imports raw vlq maps with upstream line and column offsets' => static function (TestRunner $t): void {
        $map = new SourceMap();
        $entry = $map->addSource('entry.css');
        $map->setSourceContent($entry, '.entry{}');
        $map->addMapping(0, 0, $entry, 0, 0);

        $map->addVlqMap(
            'A,MAAMA;ECGCC;A',
            ['compiled.css', 'tokens.scss'],
            ['.compiled{}', '.token{}'],
            ['token', 'accent'],
            3,
            10
        );

        $decoded = SourceMap::decodeVlq($map->writeVlq());
        $data = $map->toArray(null, false);
        $closest = $map->findClosestMapping(3, 16);

        $t->same([0, 3, 3, 4, 5], array_column($decoded, 'generatedLine'));
        $t->same([0, 10, 16, 12, 10], array_column($decoded, 'generatedColumn'));
        $t->same([0, null, 1, 2, null], array_column($decoded, 'sourceIndex'));
        $t->same([null, null, 0, 1, null], array_column($decoded, 'nameIndex'));
        $t->same(['entry.css', 'compiled.css', 'tokens.scss'], $data['sources']);
        $t->same(['.entry{}', '.compiled{}', '.token{}'], $data['sourcesContent']);
        $t->same(1, $closest['sourceIndex'] ?? null);
        $t->same(null, $map->findClosestMapping(2, 0));
    },
    'source map rejects invalid raw vlq map indexes' => static function (TestRunner $t): void {
        $map = new SourceMap();

        $t->throws(OutOfBoundsException::class, static function () use ($map): void {
            $map->addVlqMap('ACAA', [], [], []);
        });
        $t->throws(InvalidArgumentException::class, static function (): void {
            SourceMap::fromJson('{"version":3,"mappings":"A","sources":[7],"names":[]}');
        });
    },
    'source map imports upstream json defaults and data URLs' => static function (TestRunner $t): void {
        $jsonWithoutContents = SourceMap::fromJson('{"version":3,"sourceRoot":"/","mappings":";C","sources":["file.js"],"names":[]}');
        $jsonWithNullContents = SourceMap::fromJson('{"version":3,"sourceRoot":"/","mappings":";C","sources":["file.js"],"sourcesContent":[null],"names":[]}');

        $t->same([''], $jsonWithoutContents->toArray(null, false)['sourcesContent']);
        $t->same([''], $jsonWithNullContents->toArray(null, false)['sourcesContent']);
        $t->same(
            '{"version":3,"sourceRoot":"/","mappings":";C","sources":["file.js"],"sourcesContent":[""],"names":[]}',
            $jsonWithoutContents->toJson('/')
        );

        $sparseContents = new SourceMap();
        $sparseContents->addSource('first.css');
        $second = $sparseContents->addSource('second.css');
        $sparseContents->setSourceContent($second, '.second{}');
        $t->same(['', '.second{}'], $sparseContents->toArray(null, false)['sourcesContent']);

        $sourceOnly = new SourceMap();
        $sourceOnly->addSource('file.css');
        $t->same([], $sourceOnly->toArray(null, false)['sourcesContent']);

        $dataUrlMap = new SourceMap();
        $dataUrlMap->addGeneratedMapping(1, 1);
        $dataUrl = $dataUrlMap->toDataUrl('/');
        $t->same(
            'data:application/json;charset=utf-8;base64,eyJ2ZXJzaW9uIjozLCJzb3VyY2VSb290IjoiLyIsIm1hcHBpbmdzIjoiO0MiLCJzb3VyY2VzIjpbXSwic291cmNlc0NvbnRlbnQiOltdLCJuYW1lcyI6W119',
            $dataUrl
        );

        $roundTrip = SourceMap::fromDataUrl($dataUrl);
        $t->same(';C', $roundTrip->writeVlq());
        $t->same(
            [['generatedLine' => 1, 'generatedColumn' => 1, 'sourceIndex' => null, 'originalLine' => null, 'originalColumn' => null, 'nameIndex' => null]],
            SourceMap::decodeVlq($roundTrip->writeVlq())
        );

        $percentEncoded = 'data:application/json,' . rawurlencode('{"version":3,"sourceRoot":"/","mappings":";C","sources":[],"sourcesContent":[],"names":[]}');
        $t->same(';C', SourceMap::fromDataUrl($percentEncoded)->writeVlq());
        $t->throws(InvalidArgumentException::class, static function () use ($dataUrl): void {
            SourceMap::fromDataUrl(str_replace('application/json', 'text/plain', $dataUrl));
        });
    },
    'source map normalizes upstream project-root source paths' => static function (TestRunner $t): void {
        $map = new SourceMap('/srv/www/site/wp-content/themes/example');
        $style = $map->addSource('/srv/www/site/wp-content/themes/example/style.css');
        $styleAgain = $map->addSource('file:///srv/www/site/wp-content/themes/example/./style.css');
        $shared = $map->addSource('/srv/www/site/wp-content/themes/shared/tokens.css');
        $relative = $map->addSource('./blocks/cover.css');
        $virtual = $map->addSource('theme://generated/cover.css');

        $map->addMapping(0, 0, $style, 0, 0);
        $map->addMapping(0, 20, $shared, 4, 2, 'sharedToken');
        $map->addMapping(1, 0, $relative, 2, 1);
        $map->addMapping(1, 12, $virtual, 0, 0);

        $data = $map->toArray(null, false);
        $decoded = SourceMap::decodeVlq($map->writeVlq());

        $t->same($style, $styleAgain);
        $t->same(['style.css', '../shared/tokens.css', 'blocks/cover.css', 'theme://generated/cover.css'], $data['sources']);
        $t->same([0, 1, 2, 3], array_column($decoded, 'sourceIndex'));
        $t->same([0, 20, 0, 12], array_column($decoded, 'generatedColumn'));
        $t->same(['sharedToken'], $data['names']);

        $windows = new SourceMap('C:\\www\\theme\\css');
        $windows->addSource('C:\\www\\theme\\blocks\\card.css');
        $windows->addSource('C:\\www\\theme\\css\\.\\editor.css');
        $t->same(['../blocks/card.css', 'editor.css'], $windows->toArray(null, false)['sources']);
    },
    'source map imports raw vlq maps with upstream project-root source normalization' => static function (TestRunner $t): void {
        $map = SourceMap::fromJson(
            '{"version":3,"mappings":"AAAA;AACA","sources":["/srv/www/site/wp-content/themes/example/style.css","file:///srv/www/site/wp-content/themes/shared/tokens.css"],"sourcesContent":[".theme{}",".tokens{}"],"names":[]}',
            '/srv/www/site/wp-content/themes/example'
        );

        $data = $map->toArray(null, false);
        $decoded = SourceMap::decodeVlq($map->writeVlq());

        $t->same('AAAA;AACA', $map->writeVlq());
        $t->same(['style.css', '../shared/tokens.css'], $data['sources']);
        $t->same(['.theme{}', '.tokens{}'], $data['sourcesContent']);
        $t->same([0, 1], array_column($decoded, 'generatedLine'));
        $t->same([0, 0], array_column($decoded, 'sourceIndex'));
    },
    'source map offsets generated columns with upstream overlap semantics' => static function (TestRunner $t): void {
        $map = new SourceMap();
        $sourceIndex = $map->addSource('blocks.css');
        $map->addMapping(0, 0, $sourceIndex, 0, 0);
        $map->addMapping(0, 10, $sourceIndex, 1, 0);
        $map->addMapping(0, 20, $sourceIndex, 2, 0);
        $map->addMapping(0, 30, $sourceIndex, 3, 0);

        $map->offsetColumns(0, 20, -15);
        $decoded = SourceMap::decodeVlq($map->writeVlq());

        $t->same([0, 5, 15], array_column($decoded, 'generatedColumn'));
        $t->same([0, 2, 3], array_column($decoded, 'originalLine'));

        $map->offsetColumns(0, 5, 4);
        $decoded = SourceMap::decodeVlq($map->writeVlq());

        $t->same([0, 9, 19], array_column($decoded, 'generatedColumn'));
        $t->same([0, 2, 3], array_column($decoded, 'originalLine'));
        $beforeNoop = $map->writeVlq();
        $map->offsetColumns(12, 3, -4);
        $t->same($beforeNoop, $map->writeVlq());
        $t->throws(InvalidArgumentException::class, static function () use ($map): void {
            $map->offsetColumns(0, 3, -4);
        });
    },
    'source map offsets generated lines by inserting and removing mapping lines' => static function (TestRunner $t): void {
        $map = new SourceMap();
        $sourceIndex = $map->addSource('theme.css');
        foreach ([0, 2, 4, 6] as $line) {
            $map->addMapping($line, 0, $sourceIndex, $line, 0);
        }

        $map->offsetLines(2, 2);
        $decoded = SourceMap::decodeVlq($map->writeVlq());

        $t->same([0, 4, 6, 8], array_column($decoded, 'generatedLine'));
        $t->same([0, 2, 4, 6], array_column($decoded, 'originalLine'));

        $map->offsetLines(6, -2);
        $decoded = SourceMap::decodeVlq($map->writeVlq());

        $t->same([0, 4, 6], array_column($decoded, 'generatedLine'));
        $t->same([0, 4, 6], array_column($decoded, 'originalLine'));
        $empty = new SourceMap();
        $empty->offsetLines(1, -2);
        $t->same('', $empty->writeVlq());
        $t->throws(InvalidArgumentException::class, static function () use ($map): void {
            $map->offsetLines(1, -2);
        });
    },
    'source map preserves empty generated-line spans from upstream line offsets' => static function (TestRunner $t): void {
        $map = new SourceMap();
        $sourceIndex = $map->addSource('theme.css');
        $map->addMapping(0, 0, $sourceIndex, 0, 0);

        $map->offsetLines(1, 2);

        $t->same('AAAA;;', $map->writeVlq());
        $t->same([0], array_column(SourceMap::decodeVlq($map->writeVlq()), 'generatedLine'));

        $map->offsetLines(3, -1);
        $t->same('AAAA;', $map->writeVlq());

        $map->offsetLines(2, -1);
        $t->same('AAAA', $map->writeVlq());
    },
    'source map replaces parent mappings with empty child lines from nested maps' => static function (TestRunner $t): void {
        $parent = new SourceMap();
        $entry = $parent->addSource('entry.css');
        foreach ([0, 1, 2, 3, 4] as $line) {
            $parent->addMapping($line, 0, $entry, $line, 0, 'parent' . $line);
        }

        $child = new SourceMap();
        $childSource = $child->addSource('child.css');
        $child->addMapping(0, 3, $childSource, 7, 1, 'childRule');
        $child->offsetLines(1, 2);

        $parent->addSourceMap($child, 1);
        $decoded = SourceMap::decodeVlq($parent->writeVlq());
        $data = $parent->toArray(null, false);

        $t->same([0, 1, 4], array_column($decoded, 'generatedLine'));
        $t->same([0, 3, 0], array_column($decoded, 'generatedColumn'));
        $t->same([0, 1, 0], array_column($decoded, 'sourceIndex'));
        $t->same([0, 7, 4], array_column($decoded, 'originalLine'));
        $t->same(['parent0', 'parent1', 'parent2', 'parent3', 'parent4', 'childRule'], $data['names']);
        $t->same(5, $decoded[1]['nameIndex']);
    },
    'source map adds upstream empty line maps with line offsets' => static function (TestRunner $t): void {
        $map = new SourceMap();
        $map->addEmptyMap('theme.css', ".wp-block-cover {}\n\n.wp-block-button {}\n", 2);
        $map->addEmptyMap('tokens.css', "--wp--preset--color--primary: #06c;\n:root {}\n", -1);

        $decoded = SourceMap::decodeVlq($map->writeVlq());
        $data = $map->toArray(null, false);

        $t->same([0, 2, 3, 4], array_column($decoded, 'generatedLine'));
        $t->same([1, 0, 1, 2], array_column($decoded, 'originalLine'));
        $t->same([1, 0, 0, 0], array_column($decoded, 'sourceIndex'));
        $t->same(['theme.css', 'tokens.css'], $data['sources']);
        $t->same(
            [".wp-block-cover {}\n\n.wp-block-button {}\n", "--wp--preset--color--primary: #06c;\n:root {}\n"],
            $data['sourcesContent']
        );
    },
    'source map rejects upstream unsigned 32-bit offset overflow' => static function (TestRunner $t): void {
        $map = new SourceMap();
        $sourceIndex = $map->addSource('theme.css');
        $map->addMapping(0, 0, $sourceIndex, 0, 0);
        $maxUnsigned32 = 4294967295;

        $t->throws(InvalidArgumentException::class, static function () use ($map, $sourceIndex, $maxUnsigned32): void {
            $map->addMappingWithOffset($maxUnsigned32, 0, $sourceIndex, 0, 0, 1, 0);
        });
        $t->throws(InvalidArgumentException::class, static function () use ($map, $sourceIndex, $maxUnsigned32): void {
            $map->addMappingWithOffset(0, $maxUnsigned32, $sourceIndex, 0, 0, 0, 1);
        });
        $t->throws(InvalidArgumentException::class, static function () use ($map, $maxUnsigned32): void {
            $map->offsetColumns(0, $maxUnsigned32, 1);
        });
        $t->throws(InvalidArgumentException::class, static function () use ($map, $maxUnsigned32): void {
            $map->offsetLines($maxUnsigned32, 1);
        });
        $t->throws(InvalidArgumentException::class, static function () use ($map, $maxUnsigned32): void {
            $map->addVlqMap('A', [], [], [], 0, $maxUnsigned32 + 1);
        });
    },
    'source map extends generated mappings through upstream input maps' => static function (TestRunner $t): void {
        $map = new SourceMap();
        $compiled = $map->addSource('cache/compiled.css');
        $map->setSourceContent($compiled, ".card{color:red}.icon{color:blue}\n.keep{}");
        $map->addMapping(0, 0, $compiled, 0, 0, 'compiledCard');
        $map->addMapping(0, 17, $compiled, 0, 10, 'compiledIcon');
        $map->addMapping(0, 34, $compiled, 0, 80, 'compiledAfterLast');
        $map->addMapping(1, 4, $compiled, 1, 1, 'compiledGeneratedOnly');
        $map->addMapping(2, 3, $compiled, 2, 2, 'compiledMissingLine');
        $map->addGeneratedMapping(3, 2);

        $inputMap = new SourceMap();
        $card = $inputMap->addSource('src/card.scss');
        $tokens = $inputMap->addSource('src/_tokens.scss');
        $inputMap->setSourceContent($card, ".card {\n  color: \$brand;\n}");
        $inputMap->setSourceContent($tokens, "\$brand: red;\n\$icon: blue;\n");
        $inputMap->addMapping(0, 0, $card, 10, 2, 'card');
        $inputMap->addMapping(0, 10, $tokens, 3, 7, 'token');
        $inputMap->addGeneratedMapping(1, 0);

        $map->extendWithSourceMap($inputMap);

        $decoded = SourceMap::decodeVlq($map->writeVlq());
        $data = $map->toArray(null, false);

        $t->same([0, 0, 0, 1, 2, 3], array_column($decoded, 'generatedLine'));
        $t->same([0, 17, 34, 4, 3, 2], array_column($decoded, 'generatedColumn'));
        $t->same([1, 2, 1, null, null, null], array_column($decoded, 'sourceIndex'));
        $t->same([10, 3, 10, null, null, null], array_column($decoded, 'originalLine'));
        $t->same([2, 7, 2, null, null, null], array_column($decoded, 'originalColumn'));
        $t->same([5, 6, 5, null, null, null], array_column($decoded, 'nameIndex'));
        $t->same(['cache/compiled.css', 'src/card.scss', 'src/_tokens.scss'], $data['sources']);
        $t->same(
            [
                ".card{color:red}.icon{color:blue}\n.keep{}",
                ".card {\n  color: \$brand;\n}",
                "\$brand: red;\n\$icon: blue;\n",
            ],
            $data['sourcesContent']
        );
        $t->same(
            [
                'compiledCard',
                'compiledIcon',
                'compiledAfterLast',
                'compiledGeneratedOnly',
                'compiledMissingLine',
                'card',
                'token',
            ],
            $data['names']
        );
    },
];
