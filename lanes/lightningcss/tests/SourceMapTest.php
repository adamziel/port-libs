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
    'source map offsets upstream generated-only mappings' => static function (TestRunner $t): void {
        $map = new SourceMap();
        $sourceIndex = $map->addSource('theme.css');

        $map->addGeneratedMappingWithOffset(0, 5, 2, 7);
        $map->addMappingWithOffset(0, 0, $sourceIndex, 0, 0, 2, 18, 'rule');
        $map->addGeneratedMappingWithOffset(1, 2, 2, 0);

        $decoded = SourceMap::decodeVlq($map->writeVlq());

        $t->same(';;Y,MAAAA;E', $map->writeVlq());
        $t->same([2, 2, 3], array_column($decoded, 'generatedLine'));
        $t->same([12, 18, 2], array_column($decoded, 'generatedColumn'));
        $t->same([null, 0, null], array_column($decoded, 'sourceIndex'));
        $t->same([null, 0, null], array_column($decoded, 'originalLine'));
        $t->same([null, 0, null], array_column($decoded, 'nameIndex'));
        $t->throws(InvalidArgumentException::class, static function () use ($map): void {
            $map->addGeneratedMappingWithOffset(0, 0, -1, 0);
        });
        $t->throws(InvalidArgumentException::class, static function () use ($map): void {
            $map->addGeneratedMappingWithOffset(0, 0, 0, -1);
        });
    },
    'source map replays decoded mapping records with upstream offsets' => static function (TestRunner $t): void {
        $map = new SourceMap();
        $sourceIndex = $map->addSource('compiled.css');
        $map->setSourceContent($sourceIndex, ".compiled{}\n");
        $nameIndex = $map->addName('compiledRule');

        $map->addMappingRecordWithOffset(
            [
                'generatedLine' => 0,
                'generatedColumn' => 2,
                'sourceIndex' => $sourceIndex,
                'originalLine' => 4,
                'originalColumn' => 3,
                'nameIndex' => $nameIndex,
            ],
            2,
            5
        );
        $map->addMappingRecordWithOffset(
            [
                'generatedLine' => 1,
                'generatedColumn' => 1,
                'sourceIndex' => null,
                'originalLine' => null,
                'originalColumn' => null,
                'nameIndex' => null,
            ],
            2,
            5
        );

        $decoded = SourceMap::decodeVlq($map->writeVlq());

        $t->same(';;OAIGA;M', $map->writeVlq());
        $t->same([2, 3], array_column($decoded, 'generatedLine'));
        $t->same([7, 6], array_column($decoded, 'generatedColumn'));
        $t->same([0, null], array_column($decoded, 'sourceIndex'));
        $t->same([4, null], array_column($decoded, 'originalLine'));
        $t->same([3, null], array_column($decoded, 'originalColumn'));
        $t->same([0, null], array_column($decoded, 'nameIndex'));
        $t->same(['compiled.css'], $map->toArray(null, false)['sources']);
        $t->same([".compiled{}\n"], $map->toArray(null, false)['sourcesContent']);
        $t->same(['compiledRule'], $map->toArray(null, false)['names']);

        $t->throws(InvalidArgumentException::class, static function () use ($map, $sourceIndex, $nameIndex): void {
            $map->addMappingRecordWithOffset(
                [
                    'generatedLine' => 0,
                    'generatedColumn' => 0,
                    'sourceIndex' => $sourceIndex,
                    'originalLine' => 0,
                    'originalColumn' => 0,
                    'nameIndex' => $nameIndex,
                ],
                -1,
                0
            );
        });
        $t->throws(InvalidArgumentException::class, static function () use ($map): void {
            $map->addMappingRecordWithOffset(
                [
                    'generatedLine' => 0,
                    'generatedColumn' => 0,
                    'sourceIndex' => null,
                    'originalLine' => null,
                    'originalColumn' => null,
                    'nameIndex' => null,
                ],
                0,
                -1
            );
        });
        $t->throws(InvalidArgumentException::class, static function () use ($map, $nameIndex): void {
            $map->addMappingRecordWithOffset(
                [
                    'generatedLine' => 0,
                    'generatedColumn' => 0,
                    'sourceIndex' => null,
                    'originalLine' => null,
                    'originalColumn' => null,
                    'nameIndex' => $nameIndex,
                ],
                0,
                0
            );
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
    'source map deduplicates imported raw vlq tables before applying offsets' => static function (TestRunner $t): void {
        $map = new SourceMap('/srv/www/site/wp-content/themes/example');
        $shared = $map->addSource('/srv/www/site/wp-content/themes/example/shared.css');
        $map->setSourceContent($shared, '.shared{color:old}');
        $map->addName('shared-rule');

        $map->addVlqMap(
            'AAAAA,ICEGC;ACACC',
            [
                '/srv/www/site/wp-content/themes/example/shared.css',
                './blocks/card.css',
                'shared.css',
            ],
            [
                '.shared{color:green}',
                '.card{color:red}',
                '.shared{color:rebeccapurple}',
            ],
            [
                'shared-rule',
                'card-rule',
                'shared-rule',
            ],
            2,
            3
        );

        $decoded = SourceMap::decodeVlq($map->writeVlq());
        $data = $map->toArray(null, false);

        $t->same(';;GAAAA,ICEGC;GDACD', $map->writeVlq());
        $t->same([2, 2, 3], array_column($decoded, 'generatedLine'));
        $t->same([3, 7, 3], array_column($decoded, 'generatedColumn'));
        $t->same([0, 1, 0], array_column($decoded, 'sourceIndex'));
        $t->same([0, 2, 2], array_column($decoded, 'originalLine'));
        $t->same([0, 3, 4], array_column($decoded, 'originalColumn'));
        $t->same([0, 1, 0], array_column($decoded, 'nameIndex'));
        $t->same(['shared.css', 'blocks/card.css'], $data['sources']);
        $t->same(['.shared{color:rebeccapurple}', '.card{color:red}'], $data['sourcesContent']);
        $t->same(['shared-rule', 'card-rule'], $data['names']);
        $t->same($shared, $map->getSourceIndex('file:///srv/www/site/wp-content/themes/example/shared.css'));
        $t->same(0, $map->getNameIndex('shared-rule'));
    },
    'source map imports negative-offset raw vlq maps after skipped-line deltas' => static function (TestRunner $t): void {
        $map = new SourceMap();
        $map->addVlqMap(
            'AAEIA;ACGEC',
            ['prelude.css', 'block.css'],
            ['.prelude{}', '.wp-block-cover{}'],
            ['prelude-rule', 'block-rule'],
            -1,
            4
        );

        $decoded = SourceMap::decodeVlq($map->writeVlq());
        $data = $map->toArray(null, false);

        $t->same('ICKMC', $map->writeVlq());
        $t->same([0], array_column($decoded, 'generatedLine'));
        $t->same([4], array_column($decoded, 'generatedColumn'));
        $t->same([1], array_column($decoded, 'sourceIndex'));
        $t->same([5], array_column($decoded, 'originalLine'));
        $t->same([6], array_column($decoded, 'originalColumn'));
        $t->same([1], array_column($decoded, 'nameIndex'));
        $t->same(['prelude.css', 'block.css'], $data['sources']);
        $t->same(['.prelude{}', '.wp-block-cover{}'], $data['sourcesContent']);
        $t->same(['prelude-rule', 'block-rule'], $data['names']);
    },
    'source map preserves upstream skipped same-line vlq relative state' => static function (TestRunner $t): void {
        $map = new SourceMap();
        $map->addVlqMap(
            'AAAAA,CAAC;ACCEC',
            ['prelude.css', 'block.css'],
            ['.prelude{}', '.wp-block-cover{}'],
            ['prelude-rule', 'block-rule'],
            -1,
            3
        );

        $decoded = SourceMap::decodeVlq($map->writeVlq());
        $data = $map->toArray(null, false);

        $t->same('GCCGC', $map->writeVlq());
        $t->same([0], array_column($decoded, 'generatedLine'));
        $t->same([3], array_column($decoded, 'generatedColumn'));
        $t->same([1], array_column($decoded, 'sourceIndex'));
        $t->same([1], array_column($decoded, 'originalLine'));
        $t->same([3], array_column($decoded, 'originalColumn'));
        $t->same([1], array_column($decoded, 'nameIndex'));
        $t->same(['prelude.css', 'block.css'], $data['sources']);
        $t->same(['.prelude{}', '.wp-block-cover{}'], $data['sourcesContent']);
        $t->same(['prelude-rule', 'block-rule'], $data['names']);
    },
    'source map preserves skipped generated-only vlq state before kept offsets' => static function (TestRunner $t): void {
        $map = new SourceMap();
        $map->addVlqMap(
            'AAAAA,E;ACECC',
            ['prelude.css', 'block.css'],
            ['.prelude{}', '.wp-block-cover{}'],
            ['prelude-rule', 'block-rule'],
            -1,
            5
        );

        $decoded = SourceMap::decodeVlq($map->writeVlq());
        $data = $map->toArray(null, false);

        $t->same('KCECC', $map->writeVlq());
        $t->same([0], array_column($decoded, 'generatedLine'));
        $t->same([5], array_column($decoded, 'generatedColumn'));
        $t->same([1], array_column($decoded, 'sourceIndex'));
        $t->same([2], array_column($decoded, 'originalLine'));
        $t->same([1], array_column($decoded, 'originalColumn'));
        $t->same([1], array_column($decoded, 'nameIndex'));
        $t->same(['prelude.css', 'block.css'], $data['sources']);
        $t->same(['.prelude{}', '.wp-block-cover{}'], $data['sourcesContent']);
        $t->same(['prelude-rule', 'block-rule'], $data['names']);
    },
    'source map preserves imported tables when raw vlq offsets skip every mapping' => static function (TestRunner $t): void {
        $map = new SourceMap();
        $map->addVlqMap(
            'AAAAA;AACA',
            ['blocks/skipped.scss', 'blocks/unused.scss'],
            ['.skipped { color: red }', '.unused { color: blue }'],
            ['skippedRule', 'unusedRule'],
            -3,
            0
        );

        $data = $map->toArray(null, false);

        $t->same('', $map->writeVlq());
        $t->same([], $map->getMappings());
        $t->same(['blocks/skipped.scss', 'blocks/unused.scss'], $data['sources']);
        $t->same(['.skipped { color: red }', '.unused { color: blue }'], $data['sourcesContent']);
        $t->same(['skippedRule', 'unusedRule'], $data['names']);
        $t->same(
            '{"version":3,"mappings":"","sources":["blocks/skipped.scss","blocks/unused.scss"],"sourcesContent":[".skipped { color: red }",".unused { color: blue }"],"names":["skippedRule","unusedRule"]}',
            $map->toJson(null, false)
        );
    },
    'source map imports separator-only raw vlq tables without generated spans' => static function (TestRunner $t): void {
        $map = new SourceMap();
        $map->addVlqMap(
            ';;;,,',
            ['blocks/empty.css'],
            ['.empty{}'],
            ['emptyRule'],
            4,
            9
        );

        $t->same('', $map->writeVlq());
        $t->same([], $map->getMappings());
        $t->same(['blocks/empty.css'], $map->getSources());
        $t->same(['.empty{}'], $map->getSourcesContent());
        $t->same(['emptyRule'], $map->getNames());
        $t->same(
            '{"version":3,"mappings":"","sources":["blocks/empty.css"],"sourcesContent":[".empty{}"],"names":["emptyRule"]}',
            $map->toJson(null, false)
        );

        $parent = new SourceMap();
        $entry = $parent->addSource('entry.css');
        $parent->setSourceContent($entry, ".entry{}\n");
        $parent->addMapping(0, 0, $entry, 0, 0, 'entryRule');
        $parent->addSourceMap($map, 2);

        $t->same('AAAAA', $parent->writeVlq());
        $t->same([0], array_column($parent->getMappings(), 'generatedLine'));
        $t->same(['entry.css', 'blocks/empty.css'], $parent->getSources());
        $t->same([".entry{}\n", '.empty{}'], $parent->getSourcesContent());
        $t->same(['entryRule', 'emptyRule'], $parent->getNames());
        $t->same([], $map->getSources());
        $t->same('', $map->writeVlq());
    },
    'source map imports raw vlq maps with upstream negative column offsets' => static function (TestRunner $t): void {
        $generatedOnly = new SourceMap();
        $generatedOnly->addVlqMap('K,I;O', ['generated.css'], ['.generated{}'], [], 0, -3);
        $generatedOnlyDecoded = SourceMap::decodeVlq($generatedOnly->writeVlq());

        $t->same('E,I;I', $generatedOnly->writeVlq());
        $t->same([0, 0, 1], array_column($generatedOnlyDecoded, 'generatedLine'));
        $t->same([2, 6, 4], array_column($generatedOnlyDecoded, 'generatedColumn'));
        $t->same([null, null, null], array_column($generatedOnlyDecoded, 'sourceIndex'));

        $sourceBacked = new SourceMap();
        $sourceBacked->addVlqMap('KAAA,IACA;OACA', ['source.css'], ['.source{}'], [], 0, -3);
        $sourceBackedDecoded = SourceMap::decodeVlq($sourceBacked->writeVlq());

        $t->same('EAAA,IACA;IACA', $sourceBacked->writeVlq());
        $t->same([2, 6, 4], array_column($sourceBackedDecoded, 'generatedColumn'));
        $t->same([0, 0, 0], array_column($sourceBackedDecoded, 'sourceIndex'));
        $t->same([0, 1, 2], array_column($sourceBackedDecoded, 'originalLine'));

        $skippedLine = new SourceMap();
        $skippedLine->addVlqMap('KAAA;OACA', ['source.css'], ['.source{}'], [], -1, -3);
        $skippedLineDecoded = SourceMap::decodeVlq($skippedLine->writeVlq());

        $t->same('IACA', $skippedLine->writeVlq());
        $t->same([0], array_column($skippedLineDecoded, 'generatedLine'));
        $t->same([4], array_column($skippedLineDecoded, 'generatedColumn'));
        $t->same([1], array_column($skippedLineDecoded, 'originalLine'));
        $t->throws(InvalidArgumentException::class, static function (): void {
            $map = new SourceMap();
            $map->addVlqMap('A', [], [], [], 0, -1);
        });
    },
    'source map imports upstream raw vlq byte-stream mappings without comma separators' => static function (TestRunner $t): void {
        $map = SourceMap::fromJson(
            '{"version":3,"mappings":"AAAAAA","sources":["compiled.css"],"sourcesContent":[".compiled{}"],"names":["rule"]}'
        );

        $decoded = SourceMap::decodeVlq($map->writeVlq());
        $streamDecoded = SourceMap::decodeVlq('AAAAAA');
        $data = $map->toArray(null, false);

        $t->same('AAAAA,A', $map->writeVlq());
        $t->same([0, 0], array_column($decoded, 'generatedLine'));
        $t->same([0, 0], array_column($decoded, 'generatedColumn'));
        $t->same([0, null], array_column($decoded, 'sourceIndex'));
        $t->same([0, null], array_column($decoded, 'originalLine'));
        $t->same([0, null], array_column($decoded, 'originalColumn'));
        $t->same([0, null], array_column($decoded, 'nameIndex'));
        $t->same($decoded, $streamDecoded);
        $t->same(['compiled.css'], $data['sources']);
        $t->same(['.compiled{}'], $data['sourcesContent']);
        $t->same(['rule'], $data['names']);

        $offsetMap = new SourceMap();
        $offsetMap->addVlqMap(
            'AAAAAA;C',
            ['compiled.css'],
            ['.compiled{}'],
            ['rule'],
            2,
            4
        );
        $offsetDecoded = SourceMap::decodeVlq($offsetMap->writeVlq());

        $t->same(';;IAAAA,A;K', $offsetMap->writeVlq());
        $t->same([2, 2, 3], array_column($offsetDecoded, 'generatedLine'));
        $t->same([4, 4, 5], array_column($offsetDecoded, 'generatedColumn'));
        $t->same([0, null, null], array_column($offsetDecoded, 'sourceIndex'));
    },
    'source map offsets duplicate generated columns like upstream vlq mapping lines' => static function (TestRunner $t): void {
        $positive = new SourceMap();
        $positive->addVlqMap(
            'AAAAAA,C',
            ['compiled.css'],
            ['.compiled{}'],
            ['rule']
        );
        $positive->offsetColumns(0, 0, 5);
        $positiveDecoded = SourceMap::decodeVlq($positive->writeVlq());

        $t->same('AAAAA,K,C', $positive->writeVlq());
        $t->same([0, 5, 6], array_column($positiveDecoded, 'generatedColumn'));
        $t->same([0, null, null], array_column($positiveDecoded, 'sourceIndex'));
        $t->same([0, null, null], array_column($positiveDecoded, 'nameIndex'));

        $negative = new SourceMap();
        $negative->addVlqMap(
            'AAAAAA,K',
            ['compiled.css'],
            ['.compiled{}'],
            ['rule']
        );
        $negative->offsetColumns(0, 5, -5);
        $negativeDecoded = SourceMap::decodeVlq($negative->writeVlq());

        $t->same('AAAAA,A', $negative->writeVlq());
        $t->same([0, 0], array_column($negativeDecoded, 'generatedColumn'));
        $t->same([0, null], array_column($negativeDecoded, 'sourceIndex'));
        $t->same([0, null], array_column($negativeDecoded, 'nameIndex'));
    },
    'source map offsets triple duplicate generated columns at upstream binary-search boundary' => static function (TestRunner $t): void {
        $lookup = new SourceMap();
        $lookup->addVlqMap(
            'AAAAAA,A,CACAC',
            ['compiled.css'],
            ['.compiled{}'],
            ['first', 'second']
        );

        $exactDuplicate = $lookup->findClosestMapping(0, 0);
        $afterDuplicates = $lookup->findClosestMapping(0, 1);

        $t->same(0, $exactDuplicate['generatedLine'] ?? null);
        $t->same(0, $exactDuplicate['generatedColumn'] ?? null);
        $t->same(null, $exactDuplicate['sourceIndex'] ?? null);
        $t->same(null, $exactDuplicate['nameIndex'] ?? null);
        $t->same(1, $afterDuplicates['generatedColumn'] ?? null);
        $t->same(0, $afterDuplicates['sourceIndex'] ?? null);
        $t->same(1, $afterDuplicates['nameIndex'] ?? null);

        $positive = new SourceMap();
        $positive->addVlqMap(
            'AAAAAA,A,CACAC',
            ['compiled.css'],
            ['.compiled{}'],
            ['first', 'second']
        );
        $positive->offsetColumns(0, 0, 5);
        $positiveDecoded = SourceMap::decodeVlq($positive->writeVlq());

        $t->same('AAAAA,A,K,CACAC', $positive->writeVlq());
        $t->same([0, 0, 5, 6], array_column($positiveDecoded, 'generatedColumn'));
        $t->same([0, null, null, 0], array_column($positiveDecoded, 'sourceIndex'));
        $t->same([0, null, null, 1], array_column($positiveDecoded, 'nameIndex'));

        $negative = new SourceMap();
        $negative->addVlqMap(
            'AAAAAA,A,KACAC',
            ['compiled.css'],
            ['.compiled{}'],
            ['first', 'shifted']
        );
        $negative->offsetColumns(0, 5, -5);
        $negativeDecoded = SourceMap::decodeVlq($negative->writeVlq());
        $negativeClosest = $negative->findClosestMapping(0, 0);

        $t->same('AAAAA,A,AACAC', $negative->writeVlq());
        $t->same([0, 0, 0], array_column($negativeDecoded, 'generatedColumn'));
        $t->same([0, null, 0], array_column($negativeDecoded, 'sourceIndex'));
        $t->same([0, null, 1], array_column($negativeDecoded, 'originalLine'));
        $t->same([0, null, 1], array_column($negativeDecoded, 'nameIndex'));
        $t->same(1, $negativeClosest['originalLine'] ?? null);
        $t->same(1, $negativeClosest['nameIndex'] ?? null);
    },
    'source map preserves duplicate-column offset boundaries through buffers and nested maps' => static function (TestRunner $t): void {
        $raw = new SourceMap();
        $raw->addVlqMap(
            'AAAAAA,CACAC',
            ['compiled.css'],
            ['.compiled{}'],
            ['first', 'second']
        );

        $buffer = $raw->toBuffer();
        $restored = SourceMap::fromBuffer('/', $buffer);

        $t->same([0, 0, 1], array_column($restored->getMappings(), 'generatedColumn'));
        $t->same([0, null, 0], array_column($restored->getMappings(), 'sourceIndex'));
        $t->same([0, null, 1], array_column($restored->getMappings(), 'nameIndex'));
        $restored->offsetColumns(0, 0, 5);
        $restoredDecoded = SourceMap::decodeVlq($restored->writeVlq());

        $t->same('AAAAA,K,CACAC', $restored->writeVlq());
        $t->same([0, 5, 6], array_column($restoredDecoded, 'generatedColumn'));
        $t->same([0, null, 0], array_column($restoredDecoded, 'sourceIndex'));
        $t->same([0, null, 1], array_column($restoredDecoded, 'originalLine'));
        $t->same([0, null, 1], array_column($restoredDecoded, 'nameIndex'));

        $parent = new SourceMap();
        $entry = $parent->addSource('entry.css');
        $parent->setSourceContent($entry, ".entry{}\n");
        $parent->addMapping(0, 0, $entry, 0, 0, 'entryTop');
        $parent->addMapping(1, 4, $entry, 1, 2, 'entryReplaced');
        $child = SourceMap::fromBuffer('/', $buffer);

        $parent->addSourceMap($child, 1);
        $t->same([0, 0, 1], array_column(array_slice($parent->getMappings(), 1), 'generatedColumn'));
        $t->same([], $child->getMappings());

        $parent->offsetColumns(1, 0, 3);
        $parentDecoded = SourceMap::decodeVlq($parent->writeVlq());
        $parentData = $parent->toArray(null, false);

        $t->same('AAAAA;ACAAE,G,CACAC', $parent->writeVlq());
        $t->same([0, 1, 1, 1], array_column($parentDecoded, 'generatedLine'));
        $t->same([0, 0, 3, 4], array_column($parentDecoded, 'generatedColumn'));
        $t->same([0, 1, null, 1], array_column($parentDecoded, 'sourceIndex'));
        $t->same([0, 2, null, 3], array_column($parentDecoded, 'nameIndex'));
        $t->same(['entry.css', 'compiled.css'], $parentData['sources']);
        $t->same([".entry{}\n", '.compiled{}'], $parentData['sourcesContent']);
        $t->same(['entryTop', 'entryReplaced', 'first', 'second'], $parentData['names']);
    },
    'source map applies upstream negative column-offset boundaries on duplicate vlq columns' => static function (TestRunner $t): void {
        $map = SourceMap::fromJson(
            '{"version":3,"mappings":"AAAAA,EACAC,AACAC,GACAC","sources":["compiled.css"],"sourcesContent":[".compiled{}"],"names":["first","start-a","start-b","boundary"]}'
        );

        $t->same([0, 2, 2, 5], array_column($map->getMappings(), 'generatedColumn'));
        $t->same([0, 1, 2, 3], array_column($map->getMappings(), 'nameIndex'));

        $map->offsetColumns(0, 5, -3);
        $decoded = SourceMap::decodeVlq($map->writeVlq());
        $closest = $map->findClosestMapping(0, 2);
        $data = $map->toArray(null, false);

        $t->same('AAAAA,EACAC,AAEAE', $map->writeVlq());
        $t->same([0, 2, 2], array_column($decoded, 'generatedColumn'));
        $t->same([0, 1, 3], array_column($decoded, 'originalLine'));
        $t->same([0, 1, 3], array_column($decoded, 'nameIndex'));
        $t->same(3, $closest['originalLine'] ?? null);
        $t->same(3, $closest['nameIndex'] ?? null);
        $t->same(['first', 'start-a', 'start-b', 'boundary'], $data['names']);
        $t->same(['.compiled{}'], $data['sourcesContent']);
    },
    'source map trims trailing negative column-offset windows without appending mappings' => static function (TestRunner $t): void {
        $map = new SourceMap();
        $sourceIndex = $map->addSource('compiled.css');
        $map->setSourceContent($sourceIndex, '.compiled{}');
        $map->addMapping(0, 0, $sourceIndex, 0, 0, 'first');
        $map->addMapping(0, 10, $sourceIndex, 1, 0, 'middle');
        $map->addMapping(0, 20, $sourceIndex, 2, 0, 'trailing');

        $map->offsetColumns(0, 30, -15);
        $decoded = SourceMap::decodeVlq($map->writeVlq());
        $data = $map->toArray(null, false);

        $t->same('AAAAA,UACAC', $map->writeVlq());
        $t->same([0, 10], array_column($decoded, 'generatedColumn'));
        $t->same([0, 1], array_column($decoded, 'originalLine'));
        $t->same([0, 1], array_column($decoded, 'nameIndex'));
        $t->same(['first', 'middle', 'trailing'], $data['names']);
        $t->same(['.compiled{}'], $data['sourcesContent']);

        $beforeNoop = $map->writeVlq();
        $map->offsetColumns(0, 100, 7);
        $t->same($beforeNoop, $map->writeVlq());
    },
    'source map sorts out-of-order raw vlq generated columns before offsets' => static function (TestRunner $t): void {
        $raw = new SourceMap();
        $raw->addVlqMap(
            'UAAAA,RACAC',
            ['compiled.css'],
            ['.compiled{}'],
            ['later', 'earlier']
        );
        $decoded = SourceMap::decodeVlq($raw->writeVlq());

        $t->same('EACAC,QADAD', $raw->writeVlq());
        $t->same([2, 10], array_column($decoded, 'generatedColumn'));
        $t->same([1, 0], array_column($decoded, 'originalLine'));
        $t->same([1, 0], array_column($decoded, 'nameIndex'));
        $t->same(['later', 'earlier'], $raw->toArray(null, false)['names']);

        $positive = new SourceMap();
        $positive->addVlqMap(
            'UAAAA,RACAC',
            ['compiled.css'],
            ['.compiled{}'],
            ['later', 'earlier']
        );
        $positive->offsetColumns(0, 5, 3);
        $positiveDecoded = SourceMap::decodeVlq($positive->writeVlq());

        $t->same('EACAC,WADAD', $positive->writeVlq());
        $t->same([2, 13], array_column($positiveDecoded, 'generatedColumn'));
        $t->same([1, 0], array_column($positiveDecoded, 'nameIndex'));

        $negative = new SourceMap();
        $negative->addVlqMap(
            'UAAAA,RACAC',
            ['compiled.css'],
            ['.compiled{}'],
            ['later', 'earlier']
        );
        $negative->offsetColumns(0, 10, -8);
        $negativeDecoded = SourceMap::decodeVlq($negative->writeVlq());
        $negativeData = $negative->toArray(null, false);

        $t->same('EAAAA', $negative->writeVlq());
        $t->same([2], array_column($negativeDecoded, 'generatedColumn'));
        $t->same([0], array_column($negativeDecoded, 'originalLine'));
        $t->same([0], array_column($negativeDecoded, 'nameIndex'));
        $t->same(['later', 'earlier'], $negativeData['names']);
        $t->same(['.compiled{}'], $negativeData['sourcesContent']);
    },
    'source map applies upstream mapping-line sort side effects' => static function (TestRunner $t): void {
        $raw = new SourceMap();
        $raw->addVlqMap(
            'UAAAA,RACAC',
            ['compiled.css'],
            ['.compiled{}'],
            ['later', 'earlier']
        );

        $t->same([10, 2], array_column($raw->getMappings(), 'generatedColumn'));
        $t->same([0, 1], array_column($raw->getMappings(), 'originalLine'));
        $t->same([0, 1], array_column($raw->getMappings(), 'nameIndex'));
        $t->same('EACAC,QADAD', $raw->writeVlq());
        $t->same([2, 10], array_column($raw->getMappings(), 'generatedColumn'));
        $t->same([1, 0], array_column($raw->getMappings(), 'nameIndex'));

        $zeroOffset = new SourceMap();
        $zeroOffset->addVlqMap(
            'UAAAA,RACAC',
            ['compiled.css'],
            ['.compiled{}'],
            ['later', 'earlier']
        );
        $zeroOffset->offsetColumns(0, 0, 0);
        $t->same([2, 10], array_column($zeroOffset->getMappings(), 'generatedColumn'));

        $lookup = new SourceMap();
        $lookup->addVlqMap(
            'UAAAA,RACAC',
            ['compiled.css'],
            ['.compiled{}'],
            ['later', 'earlier']
        );
        $closest = $lookup->findClosestMapping(0, 8);
        $t->same(2, $closest['generatedColumn'] ?? null);
        $t->same(1, $closest['originalLine'] ?? null);
        $t->same(1, $closest['nameIndex'] ?? null);
        $t->same([2, 10], array_column($lookup->getMappings(), 'generatedColumn'));

        $offset = new SourceMap();
        $offset->addVlqMap(
            'UAAAA,RACAC',
            ['compiled.css'],
            ['.compiled{}'],
            ['later', 'earlier']
        );
        $offset->offsetColumns(0, 5, 3);
        $t->same([2, 13], array_column($offset->getMappings(), 'generatedColumn'));
        $t->same([1, 0], array_column($offset->getMappings(), 'nameIndex'));
        $t->same('EACAC,WADAD', $offset->writeVlq());
    },
    'source map rejects start-column overflow before upstream sort entrypoint' => static function (TestRunner $t): void {
        $map = new SourceMap();
        $map->addVlqMap(
            'UAAAA,RACAC',
            ['compiled.css'],
            ['.compiled{}'],
            ['later', 'earlier']
        );

        $beforeMappings = $map->getMappings();

        $t->same([10, 2], array_column($beforeMappings, 'generatedColumn'));
        $t->throws(InvalidArgumentException::class, static function () use ($map): void {
            $map->offsetColumns(0, 4294967295, 1);
        });
        $t->same($beforeMappings, $map->getMappings());
        $t->same('EACAC,QADAD', $map->writeVlq());
    },
    'source map preserves nested unsorted vlq line order until upstream sort entrypoints' => static function (TestRunner $t): void {
        $writeParent = new SourceMap();
        $writeChild = new SourceMap();
        $writeChild->addVlqMap(
            'UAAAA,RACAC',
            ['child.css'],
            ['.child{}'],
            ['later', 'earlier']
        );
        $writeParent->addSourceMap($writeChild, 2);

        $t->same([10, 2], array_column($writeParent->getMappings(), 'generatedColumn'));
        $t->same([0, 1], array_column($writeParent->getMappings(), 'originalLine'));
        $t->same([0, 1], array_column($writeParent->getMappings(), 'nameIndex'));
        $t->same([], $writeChild->getMappings());
        $t->same(';;EACAC,QADAD', $writeParent->writeVlq());
        $t->same([2, 10], array_column($writeParent->getMappings(), 'generatedColumn'));
        $t->same(['child.css'], $writeParent->toArray(null, false)['sources']);
        $t->same(['later', 'earlier'], $writeParent->toArray(null, false)['names']);

        $lookupParent = new SourceMap();
        $lookupChild = new SourceMap();
        $lookupChild->addVlqMap(
            'UAAAA,RACAC',
            ['child.css'],
            ['.child{}'],
            ['later', 'earlier']
        );
        $lookupParent->addSourceMap($lookupChild, 2);
        $closest = $lookupParent->findClosestMapping(2, 8);

        $t->same(2, $closest['generatedColumn'] ?? null);
        $t->same(1, $closest['originalLine'] ?? null);
        $t->same(1, $closest['nameIndex'] ?? null);
        $t->same([2, 10], array_column($lookupParent->getMappings(), 'generatedColumn'));
    },
    'source map preserves unsorted vlq line order through direct line offsets' => static function (TestRunner $t): void {
        $lineShift = new SourceMap();
        $lineShift->addVlqMap(
            'UAAAA,RACAC',
            ['line-shift.css'],
            ['.line-shift{}'],
            ['later', 'earlier']
        );
        $lineShift->offsetLines(0, 2);

        $t->same([2, 2], array_column($lineShift->getMappings(), 'generatedLine'));
        $t->same([10, 2], array_column($lineShift->getMappings(), 'generatedColumn'));
        $t->same([0, 1], array_column($lineShift->getMappings(), 'originalLine'));
        $t->same(';;EACAC,QADAD', $lineShift->writeVlq());
        $t->same([2, 10], array_column($lineShift->getMappings(), 'generatedColumn'));
        $t->same([1, 0], array_column($lineShift->getMappings(), 'nameIndex'));

        $negativeLineShift = new SourceMap();
        $negativeLineShift->addVlqMap(
            'UAAAA,RACAC',
            ['line-shift.css'],
            ['.line-shift{}'],
            ['later', 'earlier']
        );
        $negativeLineShift->offsetLines(0, 2);
        $negativeLineShift->offsetLines(2, -1);

        $t->same([1, 1], array_column($negativeLineShift->getMappings(), 'generatedLine'));
        $t->same([10, 2], array_column($negativeLineShift->getMappings(), 'generatedColumn'));

        $closest = $negativeLineShift->findClosestMapping(1, 8);

        $t->same(2, $closest['generatedColumn'] ?? null);
        $t->same(1, $closest['originalLine'] ?? null);
        $t->same([2, 10], array_column($negativeLineShift->getMappings(), 'generatedColumn'));
        $t->same(';EACAC,QADAD', $negativeLineShift->writeVlq());
        $t->same(['later', 'earlier'], $negativeLineShift->toArray(null, false)['names']);
    },
    'source map negative line offsets splice unsorted raw vlq lines before sorting' => static function (TestRunner $t): void {
        $map = new SourceMap();
        $removed = $map->addSource('removed-line.css');
        $map->setSourceContent($removed, ".removed-line{}\n");
        $map->addMapping(0, 0, $removed, 0, 0, 'removed-line');
        $map->addVlqMap(
            ';UACAC,RADAD;AAGAE',
            ['line-splice.css'],
            [".line-splice{}\n"],
            ['later', 'earlier', 'after']
        );

        $map->offsetLines(1, -1);
        $beforeWrite = $map->getMappings();

        $t->same([0, 0, 1], array_column($beforeWrite, 'generatedLine'));
        $t->same([10, 2, 0], array_column($beforeWrite, 'generatedColumn'));
        $t->same([1, 0, 3], array_column($beforeWrite, 'originalLine'));
        $t->same([2, 1, 3], array_column($beforeWrite, 'nameIndex'));
        $t->same('ECAAC,QACAC;AAEAC', $map->writeVlq());

        $afterWrite = $map->getMappings();
        $closest = $map->findClosestMapping(0, 8);
        $data = $map->toArray(null, false);

        $t->same([0, 0, 1], array_column($afterWrite, 'generatedLine'));
        $t->same([2, 10, 0], array_column($afterWrite, 'generatedColumn'));
        $t->same([0, 1, 3], array_column($afterWrite, 'originalLine'));
        $t->same(2, $closest['generatedColumn'] ?? null);
        $t->same(0, $closest['originalLine'] ?? null);
        $t->same(1, $closest['nameIndex'] ?? null);
        $t->same(['removed-line.css', 'line-splice.css'], $data['sources']);
        $t->same([".removed-line{}\n", ".line-splice{}\n"], $data['sourcesContent']);
        $t->same(['removed-line', 'later', 'earlier', 'after'], $data['names']);
    },
    'source map closest lookup follows upstream duplicate generated-column search' => static function (TestRunner $t): void {
        $inputMap = SourceMap::fromJson(
            '{"version":3,"mappings":"AAAAAA","sources":["compiled.css"],"sourcesContent":[".compiled{}"],"names":["rule"]}'
        );

        $exactDuplicate = $inputMap->findClosestMapping(0, 0);
        $afterLast = $inputMap->findClosestMapping(0, 1);

        $t->same(
            ['generatedLine' => 0, 'generatedColumn' => 0, 'sourceIndex' => null, 'originalLine' => null, 'originalColumn' => null, 'nameIndex' => null],
            $exactDuplicate
        );
        $t->same(0, $afterLast['generatedColumn'] ?? null);
        $t->same(0, $afterLast['sourceIndex'] ?? null);
        $t->same(0, $afterLast['nameIndex'] ?? null);

        $compiled = new SourceMap();
        $compiledSource = $compiled->addSource('cache/compiled.css');
        $compiled->setSourceContent($compiledSource, '.compiled{}');
        $compiled->addMapping(0, 0, $compiledSource, 0, 0, 'compiledRule');
        $compiled->extendWithSourceMap($inputMap);

        $decoded = SourceMap::decodeVlq($compiled->writeVlq());
        $data = $compiled->toArray(null, false);

        $t->same('A', $compiled->writeVlq());
        $t->same([null], array_column($decoded, 'sourceIndex'));
        $t->same([null], array_column($decoded, 'nameIndex'));
        $t->same(['cache/compiled.css', 'compiled.css'], $data['sources']);
        $t->same(['compiledRule', 'rule'], $data['names']);
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
    'source map rejects skipped raw vlq indexes after importing tables' => static function (TestRunner $t): void {
        $sourceOutOfRange = new SourceMap();
        $t->throws(OutOfBoundsException::class, static function () use ($sourceOutOfRange): void {
            $sourceOutOfRange->addVlqMap(
                'ACAA',
                ['known.css'],
                ['.known{}'],
                ['knownRule'],
                -1,
                0
            );
        });
        $t->same(['known.css'], $sourceOutOfRange->getSources());
        $t->same(['.known{}'], $sourceOutOfRange->getSourcesContent());
        $t->same(['knownRule'], $sourceOutOfRange->getNames());
        $t->same([], $sourceOutOfRange->getMappings());
        $t->same('', $sourceOutOfRange->writeVlq());

        $nameOutOfRange = new SourceMap();
        $t->throws(OutOfBoundsException::class, static function () use ($nameOutOfRange): void {
            $nameOutOfRange->addVlqMap(
                'AAAAC',
                ['named.css'],
                ['.named{}'],
                ['knownName'],
                -1,
                0
            );
        });
        $t->same(['named.css'], $nameOutOfRange->getSources());
        $t->same(['.named{}'], $nameOutOfRange->getSourcesContent());
        $t->same(['knownName'], $nameOutOfRange->getNames());
        $t->same([], $nameOutOfRange->getMappings());
        $t->same('', $nameOutOfRange->writeVlq());
    },
    'source map preserves prior offset mappings when raw vlq import later rejects indexes' => static function (TestRunner $t): void {
        $sourceOutOfRange = new SourceMap();
        $t->throws(OutOfBoundsException::class, static function () use ($sourceOutOfRange): void {
            $sourceOutOfRange->addVlqMap(
                'KAAA,ECAA',
                ['valid.css'],
                ['.valid{}'],
                [],
                2,
                3
            );
        });
        $sourceDecoded = SourceMap::decodeVlq($sourceOutOfRange->writeVlq());

        $t->same(';;QAAA', $sourceOutOfRange->writeVlq());
        $t->same([2], array_column($sourceDecoded, 'generatedLine'));
        $t->same([8], array_column($sourceDecoded, 'generatedColumn'));
        $t->same([0], array_column($sourceDecoded, 'sourceIndex'));
        $t->same([0], array_column($sourceDecoded, 'originalLine'));
        $t->same(['valid.css'], $sourceOutOfRange->getSources());
        $t->same(['.valid{}'], $sourceOutOfRange->getSourcesContent());

        $nameOutOfRange = new SourceMap();
        $t->throws(OutOfBoundsException::class, static function () use ($nameOutOfRange): void {
            $nameOutOfRange->addVlqMap(
                'KAAAA,EAAAC',
                ['named.css'],
                ['.named{}'],
                ['keptRule'],
                1,
                0
            );
        });
        $nameDecoded = SourceMap::decodeVlq($nameOutOfRange->writeVlq());

        $t->same(';KAAAA', $nameOutOfRange->writeVlq());
        $t->same([1], array_column($nameDecoded, 'generatedLine'));
        $t->same([5], array_column($nameDecoded, 'generatedColumn'));
        $t->same([0], array_column($nameDecoded, 'sourceIndex'));
        $t->same([0], array_column($nameDecoded, 'nameIndex'));
        $t->same(['named.css'], $nameOutOfRange->getSources());
        $t->same(['.named{}'], $nameOutOfRange->getSourcesContent());
        $t->same(['keptRule'], $nameOutOfRange->getNames());
    },
    'source map rejects non-list vlq import vectors like upstream' => static function (TestRunner $t): void {
        foreach ([
            '{"version":3,"mappings":"AAAA","sources":{"0":"file.css"},"names":[]}',
            '{"version":3,"mappings":"AAAA","sources":["file.css"],"sourcesContent":{"0":".file{}"},"names":[]}',
            '{"version":3,"mappings":"AAAAA","sources":["file.css"],"sourcesContent":[".file{}"],"names":{"0":"rule"}}',
        ] as $json) {
            $t->throws(InvalidArgumentException::class, static function () use ($json): void {
                SourceMap::fromJson($json);
            });
        }

        foreach ([
            [['source' => 'file.css'], ['.file{}'], []],
            [['file.css'], ['content' => '.file{}'], []],
            [['file.css'], ['.file{}'], ['name' => 'rule']],
        ] as [$sources, $sourcesContent, $names]) {
            $map = new SourceMap();
            $t->throws(InvalidArgumentException::class, static function () use ($map, $sources, $sourcesContent, $names): void {
                $map->addVlqMap('AAAAA', $sources, $sourcesContent, $names);
            });
        }
    },
    'source map rejects upstream missing json vectors and null direct vlq contents' => static function (TestRunner $t): void {
        foreach ([
            '{"version":3,"mappings":"A","names":[]}',
            '{"version":3,"mappings":"A","sources":[]}',
        ] as $json) {
            $t->throws(InvalidArgumentException::class, static function () use ($json): void {
                SourceMap::fromJson($json);
            });
        }

        foreach ([
            ['mappings' => 'A', 'names' => []],
            ['mappings' => 'A', 'sources' => []],
        ] as $data) {
            $t->throws(InvalidArgumentException::class, static function () use ($data): void {
                SourceMap::fromArray($data);
            });
        }

        $t->throws(InvalidArgumentException::class, static function (): void {
            $map = new SourceMap();
            $map->addVlqMap('A', ['file.css'], [null], []);
        });
    },
    'source map rejects upstream null sourcesContent vector before vlq import' => static function (TestRunner $t): void {
        $t->throws(InvalidArgumentException::class, static function (): void {
            SourceMap::fromJson('{"version":3,"mappings":"A","sources":[],"sourcesContent":null,"names":[]}');
        });
        $t->throws(InvalidArgumentException::class, static function (): void {
            SourceMap::fromArray(['version' => 3, 'mappings' => 'A', 'sources' => [], 'sourcesContent' => null, 'names' => []]);
        });
    },
    'source map ignores upstream raw v3 json version before vlq import' => static function (TestRunner $t): void {
        foreach ([
            '{"mappings":";C","sources":[],"names":[]}',
            '{"version":"3","mappings":";C","sources":[],"names":[]}',
            '{"version":300,"mappings":";C","sources":[],"names":[]}',
            '{"version":3,"mappings":";C","sources":[],"names":[]}',
        ] as $json) {
            $map = SourceMap::fromJson($json);
            $t->same(';C', $map->writeVlq());
            $t->same(
                [['generatedLine' => 1, 'generatedColumn' => 1, 'sourceIndex' => null, 'originalLine' => null, 'originalColumn' => null, 'nameIndex' => null]],
                $map->getMappings()
            );
        }

        foreach ([
            ['mappings' => ';C', 'sources' => [], 'names' => []],
            ['version' => '3', 'mappings' => ';C', 'sources' => [], 'names' => []],
            ['version' => 300, 'mappings' => ';C', 'sources' => [], 'names' => []],
            ['version' => 3, 'mappings' => ';C', 'sources' => [], 'names' => []],
        ] as $data) {
            $map = SourceMap::fromArray($data);
            $t->same(';C', $map->writeVlq());
            $t->same(
                [['generatedLine' => 1, 'generatedColumn' => 1, 'sourceIndex' => null, 'originalLine' => null, 'originalColumn' => null, 'nameIndex' => null]],
                $map->getMappings()
            );
        }
    },
    'source map rejects upstream invalid relative vlq decode offsets' => static function (TestRunner $t): void {
        $t->same(4294967295, SourceMap::decodeVlq('+/////H')[0]['generatedColumn']);

        foreach (['D', 'ADAA', 'AADA', 'AAAD', 'AAAAD', 'ggggggI', '//////////////D'] as $mappings) {
            $t->throws(InvalidArgumentException::class, static function () use ($mappings): void {
                SourceMap::decodeVlq($mappings);
            });
        }

        $t->throws(InvalidArgumentException::class, static function (): void {
            $map = new SourceMap();
            $map->addVlqMap('//////////////D', [], [], []);
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
    'source map round trips upstream buffer snapshots after offsets' => static function (TestRunner $t): void {
        $map = new SourceMap('/srv/www/site/wp-content/themes/example');
        $style = $map->addSource('/srv/www/site/wp-content/themes/example/style.css');
        $block = $map->addSource('blocks/card.css');
        $map->setSourceContent($style, ".theme{color:green}\n");
        $map->setSourceContent($block, ".card{color:red}\n");
        $map->addMapping(0, 0, $style, 1, 0, 'theme');
        $map->addGeneratedMapping(0, 18);
        $map->addMapping(1, 2, $block, 4, 3, 'card');
        $map->offsetColumns(0, 18, 4);
        $map->offsetLines(2, 2);

        $buffer = $map->toBuffer();
        $bufferData = json_decode($buffer, true, 512, JSON_THROW_ON_ERROR);
        $restored = SourceMap::fromBuffer('/srv/www/site/wp-content/themes/example', $buffer);

        $t->same('port-libs-lightningcss-sourcemap-buffer-v1', $bufferData['format']);
        $t->same(4, $bufferData['generatedLineCount']);
        $t->same($map->writeVlq(), $restored->writeVlq());
        $t->same($map->toArray(null, false), $restored->toArray(null, false));
        $t->same(';;', substr($restored->writeVlq(), -2));
        $t->same($style, $restored->addSource('/srv/www/site/wp-content/themes/example/style.css'));
        $t->same($block, $restored->getSourceIndex('file:///srv/www/site/wp-content/themes/example/blocks/card.css'));
        $t->same(".card{color:red}\n", $restored->getSourceContent($block));
        $t->same('card', $restored->getName(1));
        $t->same(
            [
                ['generatedLine' => 0, 'generatedColumn' => 0, 'sourceIndex' => 0, 'originalLine' => 1, 'originalColumn' => 0, 'nameIndex' => 0],
                ['generatedLine' => 0, 'generatedColumn' => 22, 'sourceIndex' => null, 'originalLine' => null, 'originalColumn' => null, 'nameIndex' => null],
                ['generatedLine' => 1, 'generatedColumn' => 2, 'sourceIndex' => 1, 'originalLine' => 4, 'originalColumn' => 3, 'nameIndex' => 1],
            ],
            $restored->getMappings()
        );

        $t->throws(InvalidArgumentException::class, static function (): void {
            SourceMap::fromBuffer('/', '{"format":"unknown","sources":[],"sourcesContent":[],"names":[],"mappings":[],"generatedLineCount":0}');
        });
        $t->throws(OutOfBoundsException::class, static function () use ($bufferData): void {
            $broken = $bufferData;
            $broken['mappings'][0]['sourceIndex'] = 99;
            SourceMap::fromBuffer('/', json_encode($broken, JSON_THROW_ON_ERROR));
        });
        $t->throws(InvalidArgumentException::class, static function () use ($bufferData): void {
            $broken = $bufferData;
            $broken['mappings'][1]['nameIndex'] = 0;
            SourceMap::fromBuffer('/', json_encode($broken, JSON_THROW_ON_ERROR));
        });
        $t->throws(InvalidArgumentException::class, static function () use ($bufferData): void {
            $broken = $bufferData;
            $broken['mappings'][1]['originalColumn'] = 3;
            SourceMap::fromBuffer('/', json_encode($broken, JSON_THROW_ON_ERROR));
        });
    },
    'source map preserves buffered unsorted raw vlq lines until upstream sort entrypoints' => static function (TestRunner $t): void {
        $raw = new SourceMap();
        $raw->addVlqMap(
            'UAAAA,RACAC',
            ['buffered.css'],
            ['.buffered{}'],
            ['later', 'earlier']
        );

        $buffer = $raw->toBuffer();
        $restored = SourceMap::fromBuffer('/', $buffer);

        $t->same([10, 2], array_column($raw->getMappings(), 'generatedColumn'));
        $t->same([10, 2], array_column($restored->getMappings(), 'generatedColumn'));
        $t->same([0, 1], array_column($restored->getMappings(), 'nameIndex'));
        $t->same('EACAC,QADAD', $restored->writeVlq());
        $t->same([2, 10], array_column($restored->getMappings(), 'generatedColumn'));
        $t->same(['later', 'earlier'], $restored->getNames());

        $offset = SourceMap::fromBuffer('/', $buffer);
        $offset->offsetColumns(0, 5, 3);
        $t->same([2, 13], array_column($offset->getMappings(), 'generatedColumn'));
        $t->same('EACAC,WADAD', $offset->writeVlq());

        $negative = SourceMap::fromBuffer('/', $buffer);
        $negative->offsetColumns(0, 10, -8);
        $t->same('EAAAA', $negative->writeVlq());
        $t->same([2], array_column($negative->getMappings(), 'generatedColumn'));
        $t->same(['.buffered{}'], $negative->getSourcesContent());

        $parent = new SourceMap();
        $entry = $parent->addSource('entry.css');
        $parent->setSourceContent($entry, ".entry{}\n");
        $parent->addMapping(2, 0, $entry, 0, 0, 'entry');
        $bufferedChild = SourceMap::fromBuffer('/', $buffer);

        $parent->addSourceMap($bufferedChild, 2);

        $t->same([10, 2], array_column($parent->getMappings(), 'generatedColumn'));
        $t->same([], $bufferedChild->getMappings());
        $t->same(';;ECCAE,QADAD', $parent->writeVlq());
        $t->same([2, 10], array_column($parent->getMappings(), 'generatedColumn'));
        $t->same(['entry.css', 'buffered.css'], $parent->getSources());
        $t->same(['entry', 'later', 'earlier'], $parent->getNames());
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
    'source map exposes upstream source name and mapping lookup APIs after offsets' => static function (TestRunner $t): void {
        $map = new SourceMap('/srv/www/site/wp-content/themes/example');
        $style = $map->addSource('style.css');
        $block = $map->addSource('/srv/www/site/wp-content/themes/example/blocks/card.css');
        $virtual = $map->addSource('theme://generated/editor.css');

        $t->same(
            [$style, $block, $virtual, $style],
            $map->addSources([
                '/srv/www/site/wp-content/themes/example/style.css',
                'file:///srv/www/site/wp-content/themes/example/blocks/card.css',
                'theme://generated/editor.css',
                './style.css',
            ])
        );
        $t->same($style, $map->getSourceIndex('/srv/www/site/wp-content/themes/example/style.css'));
        $t->same($block, $map->getSourceIndex('file:///srv/www/site/wp-content/themes/example/blocks/card.css'));
        $t->same(null, $map->getSourceIndex('missing.css'));
        $t->same('style.css', $map->getSource($style));
        $t->same(['style.css', 'blocks/card.css', 'theme://generated/editor.css'], $map->getSources());
        $t->throws(OutOfBoundsException::class, static function () use ($map): void {
            $map->getSource(99);
        });
        $t->throws(OutOfBoundsException::class, static function () use ($map, $style): void {
            $map->getSourceContent($style);
        });

        $map->setSourceContent($style, '.theme{color:green}');
        $map->setSourceContent($virtual, '.editor{outline:0}');
        $t->same('.theme{color:green}', $map->getSourceContent($style));
        $t->same('', $map->getSourceContent($block));
        $t->same('.editor{outline:0}', $map->getSourceContent($virtual));
        $t->same(['.theme{color:green}', '', '.editor{outline:0}'], $map->getSourcesContent());

        $footerName = $map->addName('theme-footer');
        $nameIndexes = $map->addNames(['block-cover', 'theme-footer', 'editor-inline']);
        $t->same([1, $footerName, 2], $nameIndexes);
        $t->same($footerName, $map->getNameIndex('theme-footer'));
        $t->same(null, $map->getNameIndex('unknown-name'));
        $t->same('block-cover', $map->getName(1));
        $t->same(['theme-footer', 'block-cover', 'editor-inline'], $map->getNames());
        $t->throws(OutOfBoundsException::class, static function () use ($map): void {
            $map->getName(99);
        });

        $map->addMapping(0, 0, $style, 1, 0, 'theme-footer');
        $map->addMapping(0, 20, $block, 4, 2, 'block-cover');
        $map->addGeneratedMapping(0, 30);
        $map->addMapping(1, 1, $virtual, 0, 0, 'editor-inline');
        $map->offsetColumns(0, 20, 5);
        $map->offsetLines(1, 2);

        $t->same(
            [
                ['generatedLine' => 0, 'generatedColumn' => 0, 'sourceIndex' => 0, 'originalLine' => 1, 'originalColumn' => 0, 'nameIndex' => 0],
                ['generatedLine' => 0, 'generatedColumn' => 25, 'sourceIndex' => 1, 'originalLine' => 4, 'originalColumn' => 2, 'nameIndex' => 1],
                ['generatedLine' => 0, 'generatedColumn' => 35, 'sourceIndex' => null, 'originalLine' => null, 'originalColumn' => null, 'nameIndex' => null],
                ['generatedLine' => 3, 'generatedColumn' => 1, 'sourceIndex' => 2, 'originalLine' => 0, 'originalColumn' => 0, 'nameIndex' => 2],
            ],
            $map->getMappings()
        );
        $t->same(['.theme{color:green}', '', '.editor{outline:0}'], $map->getSourcesContent());
    },
    'source map exposes upstream sourcesContent table after sparse writes and remaps' => static function (TestRunner $t): void {
        $sparse = new SourceMap();
        $sparse->addSource('blocks/first.css');
        $second = $sparse->addSource('blocks/second.css');
        $third = $sparse->addSource('blocks/third.css');
        $sparse->setSourceContent($third, ".third{}\n");

        $t->same(['', '', ".third{}\n"], $sparse->getSourcesContent());

        $sparse->setSourceContent($second, ".second{}\n");
        $t->same(['', ".second{}\n", ".third{}\n"], $sparse->getSourcesContent());

        $json = SourceMap::fromJson(
            '{"version":3,"mappings":"AAAA;AACA","sources":["raw.css","missing.css"],"sourcesContent":[".raw{}\n"],"names":[]}'
        );
        $t->same([".raw{}\n", ''], $json->getSourcesContent());

        $parent = new SourceMap();
        $child = new SourceMap();
        $skipped = $child->addSource('blocks/skipped.css');
        $kept = $child->addSource('blocks/kept.css');
        $unused = $child->addSource('blocks/unused.css');
        $child->setSourceContent($skipped, ".skipped{}\n");
        $child->setSourceContent($kept, ".kept{}\n");
        $child->setSourceContent($unused, ".unused{}\n");
        $child->addMapping(0, 0, $skipped, 0, 0, 'skippedRule');
        $child->addMapping(1, 4, $kept, 1, 2, 'keptRule');
        $child->addName('unusedRule');

        $parent->addSourceMap($child, -1);

        $t->same([".skipped{}\n", ".kept{}\n", ".unused{}\n"], $parent->getSourcesContent());
        $t->same([], $child->getSourcesContent());
        $t->same([1], array_column($parent->getMappings(), 'sourceIndex'));
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
    'source map rejects negative line offsets beyond upstream generated span' => static function (TestRunner $t): void {
        $map = new SourceMap();
        $sourceIndex = $map->addSource('theme.css');
        $map->addMapping(0, 0, $sourceIndex, 0, 0);

        $map->offsetLines(5, 2);
        $t->same('AAAA;;;;;;;', $map->writeVlq());

        $map->offsetLines(8, -2);
        $t->same('AAAA;;;;;', $map->writeVlq());

        $beforeOutOfRangeRemoval = $map->writeVlq();
        $t->throws(InvalidArgumentException::class, static function () use ($map): void {
            $map->offsetLines(7, -2);
        });
        $t->same($beforeOutOfRangeRemoval, $map->writeVlq());
    },
    'source map preserves upstream line spans when offsetting past eof' => static function (TestRunner $t): void {
        $map = new SourceMap();
        $sourceIndex = $map->addSource('far-lines.css');
        $map->addMapping(0, 0, $sourceIndex, 0, 0);

        $map->offsetLines(4, 2);
        $t->same('AAAA;;;;;;', $map->writeVlq());
        $t->same([0], array_column(SourceMap::decodeVlq($map->writeVlq()), 'generatedLine'));

        $map->addMapping(6, 2, $sourceIndex, 6, 0);
        $t->same('AAAA;;;;;;EAMA', $map->writeVlq());

        $map->offsetLines(5, -2);
        $decoded = SourceMap::decodeVlq($map->writeVlq());

        $t->same('AAAA;;;;EAMA', $map->writeVlq());
        $t->same([0, 4], array_column($decoded, 'generatedLine'));
        $t->same([0, 2], array_column($decoded, 'generatedColumn'));
        $t->same([0, 6], array_column($decoded, 'originalLine'));
    },
    'source map applies upstream leading vlq and nested line-offset spans' => static function (TestRunner $t): void {
        $lineStart = new SourceMap();
        $lineStartSource = $lineStart->addSource('line-start.css');
        $lineStart->addMapping(0, 0, $lineStartSource, 0, 0, 'top');
        $lineStart->addMapping(2, 4, $lineStartSource, 2, 1, 'later');

        $lineStart->offsetLines(0, 2);
        $lineStartDecoded = SourceMap::decodeVlq($lineStart->writeVlq());

        $t->same(';;AAAAA;;IAECC', $lineStart->writeVlq());
        $t->same([2, 4], array_column($lineStartDecoded, 'generatedLine'));
        $t->same([0, 4], array_column($lineStartDecoded, 'generatedColumn'));
        $t->same([0, 2], array_column($lineStartDecoded, 'originalLine'));
        $t->same([0, 1], array_column($lineStartDecoded, 'nameIndex'));

        $lineStart->offsetLines(2, -1);
        $lineStartDecoded = SourceMap::decodeVlq($lineStart->writeVlq());

        $t->same(';AAAAA;;IAECC', $lineStart->writeVlq());
        $t->same([1, 3], array_column($lineStartDecoded, 'generatedLine'));
        $t->same([0, 4], array_column($lineStartDecoded, 'generatedColumn'));

        $raw = new SourceMap();
        $raw->addVlqMap(';;AACA', ['raw.css'], ['.raw{}'], [], 2, 3);
        $rawDecoded = SourceMap::decodeVlq($raw->writeVlq());

        $t->same(';;;;GACA', $raw->writeVlq());
        $t->same([4], array_column($rawDecoded, 'generatedLine'));
        $t->same([3], array_column($rawDecoded, 'generatedColumn'));
        $t->same([1], array_column($rawDecoded, 'originalLine'));

        $parent = new SourceMap();
        $parentSource = $parent->addSource('parent.css');
        foreach ([0, 1, 2, 3] as $line) {
            $parent->addMapping($line, 0, $parentSource, $line, 0, 'parent' . $line);
        }

        $child = new SourceMap();
        $childSource = $child->addSource('child.css');
        $child->addMapping(0, 2, $childSource, 7, 1, 'child');
        $child->offsetLines(1, 2);
        $parent->addSourceMap($child, -1);
        $parentDecoded = SourceMap::decodeVlq($parent->writeVlq());
        $parentData = $parent->toArray(null, false);

        $t->same(';;AAEAE;AACAC', $parent->writeVlq());
        $t->same([2, 3], array_column($parentDecoded, 'generatedLine'));
        $t->same([2, 3], array_column($parentDecoded, 'originalLine'));
        $t->same([2, 3], array_column($parentDecoded, 'nameIndex'));
        $t->same(['parent.css', 'child.css'], $parentData['sources']);
        $t->same(['parent0', 'parent1', 'parent2', 'parent3', 'child'], $parentData['names']);
        $t->same([], $child->getSources());
    },
    'source map ignores column offsets on empty generated-line spans like upstream' => static function (TestRunner $t): void {
        $map = new SourceMap();
        $sourceIndex = $map->addSource('theme.css');
        $map->addMapping(0, 0, $sourceIndex, 0, 0);

        $map->offsetLines(1, 2);

        $t->same('AAAA;;', $map->writeVlq());
        $t->same([0], array_column(SourceMap::decodeVlq($map->writeVlq()), 'generatedLine'));
        $beforeColumnNoop = $map->writeVlq();
        $map->offsetColumns(1, 3, 2);
        $t->same($beforeColumnNoop, $map->writeVlq());
        $map->offsetColumns(1, 3, -4);
        $t->same($beforeColumnNoop, $map->writeVlq());
        $map->offsetColumns(2, 0, -1);
        $t->same($beforeColumnNoop, $map->writeVlq());
        $map->offsetColumns(5, 3, -4);
        $t->same($beforeColumnNoop, $map->writeVlq());
        $map->offsetColumns(5, 4294967295, 1);
        $t->same($beforeColumnNoop, $map->writeVlq());
        $t->throws(InvalidArgumentException::class, static function () use ($map): void {
            $map->offsetColumns(1, 4294967295, 1);
        });
        $t->same($beforeColumnNoop, $map->writeVlq());
    },
    'source map removes empty generated-line spans from upstream line offsets' => static function (TestRunner $t): void {
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
    'source map keeps empty generated-line spans after draining the only mapping line' => static function (TestRunner $t): void {
        $map = new SourceMap();
        $sourceIndex = $map->addSource('empty-span.css');
        $map->setSourceContent($sourceIndex, ".drained{}\n");
        $map->addMapping(2, 0, $sourceIndex, 2, 0, 'drained-rule');

        $map->offsetLines(3, 2);
        $t->same(';;AAEAA;;', $map->writeVlq());
        $t->same([2], array_column(SourceMap::decodeVlq($map->writeVlq()), 'generatedLine'));
        $t->same(['drained-rule'], $map->toArray(null, false)['names']);

        $map->offsetLines(3, -1);
        $roundTrip = SourceMap::fromBuffer('/', $map->toBuffer());

        $t->same(';;;', $map->writeVlq());
        $t->same([], $map->getMappings());
        $t->same(['empty-span.css'], $map->getSources());
        $t->same([".drained{}\n"], $map->getSourcesContent());
        $t->same(['drained-rule'], $map->getNames());
        $t->same(null, $map->findClosestMapping(2, 0));
        $t->same(';;;', $roundTrip->writeVlq());

        $map->offsetLines(4, -1);
        $t->same(';;', $map->writeVlq());
    },
    'source map keeps empty generated-line spans after column-offset drains mappings' => static function (TestRunner $t): void {
        $map = new SourceMap();
        $sourceIndex = $map->addSource('column-drain.css');
        $map->setSourceContent($sourceIndex, ".column-drain{}\n");
        $map->addMapping(2, 0, $sourceIndex, 0, 0, 'column-drain-rule');

        $map->offsetColumns(2, 1, -1);
        $roundTrip = SourceMap::fromBuffer('/', $map->toBuffer());

        $t->same(';;', $map->writeVlq());
        $t->same([], $map->getMappings());
        $t->same(['column-drain.css'], $map->getSources());
        $t->same([".column-drain{}\n"], $map->getSourcesContent());
        $t->same(['column-drain-rule'], $map->getNames());
        $t->same(null, $map->findClosestMapping(2, 0));
        $t->same(';;', $roundTrip->writeVlq());

        $prefixDrain = new SourceMap();
        $prefixSource = $prefixDrain->addSource('column-prefix-drain.css');
        $prefixDrain->setSourceContent($prefixSource, ".prefix-a{}\n.prefix-b{}\n");
        $prefixDrain->addMapping(2, 0, $prefixSource, 0, 0, 'prefix-a');
        $prefixDrain->addMapping(2, 10, $prefixSource, 1, 0, 'prefix-b');

        $prefixDrain->offsetColumns(2, 5, -5);
        $prefixDecoded = SourceMap::decodeVlq($prefixDrain->writeVlq());

        $t->same(';;KACAC', $prefixDrain->writeVlq());
        $t->same([2], array_column($prefixDecoded, 'generatedLine'));
        $t->same([5], array_column($prefixDecoded, 'generatedColumn'));
        $t->same([1], array_column($prefixDecoded, 'originalLine'));
        $t->same([1], array_column($prefixDecoded, 'nameIndex'));
        $t->same(['prefix-a', 'prefix-b'], $prefixDrain->getNames());
    },
    'source map merges column-drained empty child spans over parent lines' => static function (TestRunner $t): void {
        $parent = new SourceMap();
        $parentSource = $parent->addSource('parent.css');
        foreach ([0, 1, 2, 3] as $line) {
            $parent->addMapping($line, 0, $parentSource, $line, 0, 'parent' . $line);
        }

        $child = new SourceMap();
        $childSource = $child->addSource('column-drained-child.css');
        $child->setSourceContent($childSource, ".column-drained-child{}\n");
        $child->addMapping(2, 0, $childSource, 2, 0, 'column-drained-child-rule');
        $child->offsetColumns(2, 1, -1);

        $t->same(';;', $child->writeVlq());

        $parent->addSourceMap($child, 1);
        $decoded = SourceMap::decodeVlq($parent->writeVlq());
        $data = $parent->toArray(null, false);
        $roundTrip = SourceMap::fromBuffer('/', $parent->toBuffer());

        $t->same('AAAAA;;;', $parent->writeVlq());
        $t->same([0], array_column($decoded, 'generatedLine'));
        $t->same([0], array_column($decoded, 'generatedColumn'));
        $t->same([0], array_column($decoded, 'sourceIndex'));
        $t->same([0], array_column($decoded, 'originalLine'));
        $t->same([0], array_column($decoded, 'nameIndex'));
        $t->same(['parent.css', 'column-drained-child.css'], $data['sources']);
        $t->same(['', ".column-drained-child{}\n"], $data['sourcesContent']);
        $t->same(['parent0', 'parent1', 'parent2', 'parent3', 'column-drained-child-rule'], $data['names']);
        $t->same(null, $parent->findClosestMapping(2, 0));
        $t->same('AAAAA;;;', $roundTrip->writeVlq());
        $t->same([], $child->getSources());
        $t->same([], $child->getNames());
        $t->same('', $child->writeVlq());
    },
    'source map merges buffered column-drained child spans with negative offsets' => static function (TestRunner $t): void {
        $child = new SourceMap();
        $childSource = $child->addSource('buffered-column-drained-child.css');
        $child->setSourceContent($childSource, ".buffered{}\n");
        $child->addMapping(2, 0, $childSource, 2, 0, 'buffered-child-rule');
        $child->offsetColumns(2, 1, -1);

        $restoredChild = SourceMap::fromBuffer('/', $child->toBuffer());
        $t->same(';;', $child->writeVlq());
        $t->same(';;', $restoredChild->writeVlq());

        $parent = new SourceMap();
        $parentSource = $parent->addSource('parent.css');
        foreach ([0, 1, 2, 3] as $line) {
            $parent->addMapping($line, 0, $parentSource, $line, 0, 'parent' . $line);
        }

        $parent->addSourceMap($restoredChild, -1);
        $decoded = SourceMap::decodeVlq($parent->writeVlq());
        $data = $parent->toArray(null, false);
        $roundTrip = SourceMap::fromBuffer('/', $parent->toBuffer());

        $t->same(';;AAEAE;AACAC', $parent->writeVlq());
        $t->same([2, 3], array_column($decoded, 'generatedLine'));
        $t->same([0, 0], array_column($decoded, 'generatedColumn'));
        $t->same([0, 0], array_column($decoded, 'sourceIndex'));
        $t->same([2, 3], array_column($decoded, 'originalLine'));
        $t->same([2, 3], array_column($decoded, 'nameIndex'));
        $t->same(['parent.css', 'buffered-column-drained-child.css'], $data['sources']);
        $t->same(['', ".buffered{}\n"], $data['sourcesContent']);
        $t->same(['parent0', 'parent1', 'parent2', 'parent3', 'buffered-child-rule'], $data['names']);
        $t->same(null, $parent->findClosestMapping(0, 0));
        $t->same(2, $parent->findClosestMapping(2, 0)['originalLine'] ?? null);
        $t->same(';;AAEAE;AACAC', $roundTrip->writeVlq());
        $t->same([], $restoredChild->getSources());
        $t->same('', $restoredChild->writeVlq());
        $t->same(['buffered-column-drained-child.css'], $child->getSources());
        $t->same(['buffered-child-rule'], $child->getNames());
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
    'source map replaces parent mappings with leading empty child offset spans' => static function (TestRunner $t): void {
        $parent = new SourceMap();
        $entry = $parent->addSource('entry.css');
        foreach ([0, 1, 2, 3, 4] as $line) {
            $parent->addMapping($line, 0, $entry, $line, 0, 'parent' . $line);
        }

        $child = new SourceMap();
        $childSource = $child->addSource('child.css');
        $child->setSourceContent($childSource, ".child{}\n");
        $child->addMapping(0, 3, $childSource, 7, 1, 'childRule');
        $child->offsetLines(0, 2);

        $t->same(';;GAOCA', $child->writeVlq());

        $parent->addSourceMap($child, 1);
        $decoded = SourceMap::decodeVlq($parent->writeVlq());
        $data = $parent->toArray(null, false);

        $t->same('AAAAA;;;GCOCK;ADHDD', $parent->writeVlq());
        $t->same([0, 3, 4], array_column($decoded, 'generatedLine'));
        $t->same([0, 3, 0], array_column($decoded, 'generatedColumn'));
        $t->same([0, 1, 0], array_column($decoded, 'sourceIndex'));
        $t->same([0, 7, 4], array_column($decoded, 'originalLine'));
        $t->same([0, 5, 4], array_column($decoded, 'nameIndex'));
        $t->same(['entry.css', 'child.css'], $data['sources']);
        $t->same(['', ".child{}\n"], $data['sourcesContent']);
        $t->same(['parent0', 'parent1', 'parent2', 'parent3', 'parent4', 'childRule'], $data['names']);
        $t->same([], $child->getSources());
        $t->same('', $child->writeVlq());
    },
    'source map consumes nested source maps after upstream add_sourcemap merge' => static function (TestRunner $t): void {
        $parent = new SourceMap();
        $entry = $parent->addSource('entry.css');
        $parent->setSourceContent($entry, ".entry{}\n");
        $parent->addMapping(0, 0, $entry, 0, 0, 'parentRule');

        $child = new SourceMap();
        $childSource = $child->addSource('child.css');
        $child->setSourceContent($childSource, ".child{}\n");
        $child->addMapping(0, 4, $childSource, 2, 1, 'childRule');

        $parent->addSourceMap($child, 1);
        $firstMerge = $parent->toArray(null, false);
        $firstDecoded = SourceMap::decodeVlq($firstMerge['mappings']);

        $t->same([], $child->getSources());
        $t->same([], $child->getNames());
        $t->same([], $child->getMappings());
        $t->same([], $child->toArray(null, false)['sourcesContent']);
        $t->same('', $child->writeVlq());

        $parent->addSourceMap($child, 3);

        $t->same($firstMerge, $parent->toArray(null, false));
        $t->same([0, 1], array_column($firstDecoded, 'generatedLine'));
        $t->same([0, 4], array_column($firstDecoded, 'generatedColumn'));
        $t->same([0, 1], array_column($firstDecoded, 'sourceIndex'));
        $t->same([0, 2], array_column($firstDecoded, 'originalLine'));
        $t->same(['entry.css', 'child.css'], $firstMerge['sources']);
        $t->same([".entry{}\n", ".child{}\n"], $firstMerge['sourcesContent']);
        $t->same(['parentRule', 'childRule'], $firstMerge['names']);
    },
    'source map preserves child source tables when line offsets skip mappings' => static function (TestRunner $t): void {
        $parent = new SourceMap();

        $child = new SourceMap();
        $skippedSource = $child->addSource('blocks/skipped.css');
        $unusedSource = $child->addSource('blocks/unused.css');
        $child->setSourceContent($skippedSource, ".skipped{}\n");
        $child->setSourceContent($unusedSource, ".unused{}\n");
        $child->addMapping(0, 0, $skippedSource, 2, 1, 'skippedRule');
        $child->addName('unusedName');

        $parent->addSourceMap($child, -1);
        $data = $parent->toArray(null, false);

        $t->same('', $parent->writeVlq());
        $t->same(['blocks/skipped.css', 'blocks/unused.css'], $data['sources']);
        $t->same([".skipped{}\n", ".unused{}\n"], $data['sourcesContent']);
        $t->same(['skippedRule', 'unusedName'], $data['names']);
        $t->same([], $parent->getMappings());
        $t->same([], $child->getSources());
        $t->same([], $child->getNames());
        $t->same('', $child->writeVlq());
    },
    'source map preserves partial skipped child tables during upstream offset merge' => static function (TestRunner $t): void {
        $parent = new SourceMap();

        $child = new SourceMap();
        $skippedSource = $child->addSource('blocks/skipped.css');
        $keptSource = $child->addSource('blocks/kept.css');
        $unusedSource = $child->addSource('blocks/unused.css');
        $child->setSourceContent($skippedSource, ".skipped{}\n");
        $child->setSourceContent($keptSource, ".kept{}\n");
        $child->setSourceContent($unusedSource, ".unused{}\n");
        $child->addMapping(0, 0, $skippedSource, 0, 0, 'skippedRule');
        $child->addMapping(1, 4, $keptSource, 1, 2, 'keptRule');
        $child->addName('unusedName');

        $parent->addSourceMap($child, -1);
        $decoded = SourceMap::decodeVlq($parent->writeVlq());
        $data = $parent->toArray(null, false);

        $t->same('ICCEC', $parent->writeVlq());
        $t->same([0], array_column($decoded, 'generatedLine'));
        $t->same([4], array_column($decoded, 'generatedColumn'));
        $t->same([1], array_column($decoded, 'sourceIndex'));
        $t->same([1], array_column($decoded, 'originalLine'));
        $t->same([2], array_column($decoded, 'originalColumn'));
        $t->same([1], array_column($decoded, 'nameIndex'));
        $t->same(['blocks/skipped.css', 'blocks/kept.css', 'blocks/unused.css'], $data['sources']);
        $t->same([".skipped{}\n", ".kept{}\n", ".unused{}\n"], $data['sourcesContent']);
        $t->same(['skippedRule', 'keptRule', 'unusedName'], $data['names']);
        $t->same([], $child->getSources());
        $t->same([], $child->getNames());
        $t->same('', $child->writeVlq());
    },
    'source map preserves generated-only child lines during upstream offset merge' => static function (TestRunner $t): void {
        $parent = new SourceMap();
        $entry = $parent->addSource('entry.css');
        foreach ([0, 1, 2, 3] as $line) {
            $parent->addMapping($line, 0, $entry, $line, 0, 'parent' . $line);
        }

        $child = new SourceMap();
        $childSource = $child->addSource('child-generated.css');
        $child->setSourceContent($childSource, ".child{}\n");
        $child->addGeneratedMapping(0, 4);
        $child->addMapping(1, 6, $childSource, 3, 2, 'childRule');
        $child->addGeneratedMapping(2, 8);

        $parent->addSourceMap($child, -1);
        $decoded = SourceMap::decodeVlq($parent->writeVlq());
        $data = $parent->toArray(null, false);

        $t->same('MCGEI;Q;ADDFF;AACAC', $parent->writeVlq());
        $t->same([0, 1, 2, 3], array_column($decoded, 'generatedLine'));
        $t->same([6, 8, 0, 0], array_column($decoded, 'generatedColumn'));
        $t->same([1, null, 0, 0], array_column($decoded, 'sourceIndex'));
        $t->same([3, null, 2, 3], array_column($decoded, 'originalLine'));
        $t->same([2, null, 0, 0], array_column($decoded, 'originalColumn'));
        $t->same([4, null, 2, 3], array_column($decoded, 'nameIndex'));
        $t->same(['entry.css', 'child-generated.css'], $data['sources']);
        $t->same(['', ".child{}\n"], $data['sourcesContent']);
        $t->same(['parent0', 'parent1', 'parent2', 'parent3', 'childRule'], $data['names']);
        $t->same([], $child->getSources());
        $t->same([], $child->getNames());
        $t->same('', $child->writeVlq());
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
    'source map empty maps keep upstream lone carriage returns inside lines' => static function (TestRunner $t): void {
        $map = new SourceMap();
        $map->addEmptyMap('legacy.css', "a\rb\nc\r\nd\re", 1);
        $decoded = SourceMap::decodeVlq($map->writeVlq());
        $data = $map->toArray(null, false);

        $t->same(';AAAA;AACA;AACA', $map->writeVlq());
        $t->same([1, 2, 3], array_column($decoded, 'generatedLine'));
        $t->same([0, 1, 2], array_column($decoded, 'originalLine'));
        $t->same([0, 0, 0], array_column($decoded, 'sourceIndex'));
        $t->same(["a\rb\nc\r\nd\re"], $data['sourcesContent']);

        $trailingCarriageReturn = new SourceMap();
        $trailingCarriageReturn->addEmptyMap('trailing-cr.css', "a\rb\r");
        $trailingDecoded = SourceMap::decodeVlq($trailingCarriageReturn->writeVlq());

        $t->same('AAAA', $trailingCarriageReturn->writeVlq());
        $t->same([0], array_column($trailingDecoded, 'generatedLine'));
        $t->same([0], array_column($trailingDecoded, 'originalLine'));
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
    'source map rejects shifted generated-column overflow before mutation' => static function (TestRunner $t): void {
        $map = new SourceMap();
        $sourceIndex = $map->addSource('theme.css');
        $maxUnsigned32 = 4294967295;
        $map->addMapping(0, 2, $sourceIndex, 0, 0, 'safeRule');
        $map->addMapping(0, $maxUnsigned32, $sourceIndex, 1, 0, 'maxRule');

        $beforeMappings = $map->getMappings();
        $beforeVlq = $map->writeVlq();

        $t->throws(InvalidArgumentException::class, static function () use ($map): void {
            $map->offsetColumns(0, 0, 1);
        });
        $t->same($beforeMappings, $map->getMappings());
        $t->same($beforeVlq, $map->writeVlq());
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
