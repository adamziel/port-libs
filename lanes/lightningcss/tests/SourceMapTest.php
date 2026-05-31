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
];
