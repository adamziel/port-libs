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
];
