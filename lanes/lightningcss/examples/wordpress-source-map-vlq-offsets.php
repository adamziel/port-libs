<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LightningCSS\SourceMap;

$entrySource = <<<'CSS'
@import "blocks.css";
.theme-footer {
  color: green;
}
CSS;

$blockSource = <<<'CSS'
/*! Theme package license */
/*!
 * Block editor stylesheet generated from theme.json
 * Keep comments for distribution compliance.
 */
.wp-block-cover {
  color: yellow;
}
.wp-block-cover .wp-block-button {
  margin: 1rem;
}
CSS;

$firstRule = '.wp-block-cover{color:#ff0}';
$secondRule = '.wp-block-cover .wp-block-button{margin:1rem}';
$layerOpen = '@layer theme.blocks{';
$layerClose = '}';
$footerRule = '.theme-footer{color:green}';
$code = "/*! Theme package license */\n"
    . "/*!\n"
    . " * Block editor stylesheet generated from theme.json\n"
    . " * Keep comments for distribution compliance.\n"
    . " */\n"
    . $layerOpen
    . $firstRule
    . $secondRule
    . $layerClose
    . $footerRule;

$blockMap = new SourceMap();
$blockSourceIndex = $blockMap->addSource('wp-content/themes/example/blocks.css');
$blockMap->setSourceContent($blockSourceIndex, $blockSource);
$blockMap->addPrinterMapping(0, 0, $blockSourceIndex, 5, 1);
$blockMap->addPrinterMapping(0, strlen($firstRule), $blockSourceIndex, 8, 1);
$blockMap->offsetColumns(0, 0, strlen($layerOpen));
$blockMap->offsetLines(0, 5);

$map = new SourceMap();
$entrySourceIndex = $map->addSource('wp-content/themes/example/style.css');
$map->setSourceContent($entrySourceIndex, $entrySource);
$map->addMapping(5, 0, $entrySourceIndex, 0, 0);
$map->addSourceMap($blockMap);
$map->addMappingWithOffset(
    0,
    strlen($layerOpen) + strlen($firstRule) + strlen($secondRule) + strlen($layerClose),
    $entrySourceIndex,
    1,
    0,
    5,
    0
);
$mapBeforeConsumedReplay = $map->toJson(null, false);
$map->addSourceMap($blockMap, 9);
$consumedNestedReplayNoop = $mapBeforeConsumedReplay === $map->toJson(null, false);
$consumedNestedMap = $blockMap->toJson(null, false);

$themeJsonMap = new SourceMap();
$themeJsonMap->addEmptyMap(
    'wp-content/themes/example/theme-json.css',
    ":root {\n  --wp--style--global--content-size: 720px;\n}\n",
    1
);

$inlineEditorMap = new SourceMap();
$inlineEditorSource = $inlineEditorMap->addSource('wp-content/themes/example/editor-inline.css');
$inlineEditorMap->setSourceContent($inlineEditorSource, ".wp-block-spacer {\n  margin-top: 1rem;\n}\n");
$inlineEditorMap->addMapping(0, 0, $inlineEditorSource, 0, 0);
$inlineEditorMap->offsetLines(1, 2);

$emptyLineOffsetMap = new SourceMap();
$emptyLineOffsetSource = $emptyLineOffsetMap->addSource('wp-content/themes/example/empty-line-offset.css');
$emptyLineOffsetMap->setSourceContent($emptyLineOffsetSource, ".wp-block-empty-line-offset {}\n");
$emptyLineOffsetMap->addMapping(0, 0, $emptyLineOffsetSource, 0, 0);
$emptyLineOffsetMap->offsetLines(1, 2);
$emptyLineOffsetBeforeColumnNoop = $emptyLineOffsetMap->toJson(null, false);
$emptyLineOffsetMap->offsetColumns(1, 3, 2);
$emptyLineColumnOffsetGuard = false;
try {
    $emptyLineOffsetMap->offsetColumns(1, 3, -4);
} catch (InvalidArgumentException) {
    $emptyLineColumnOffsetGuard = true;
}
$emptyLineOffsetMap->offsetColumns(5, 3, -4);
$bufferRoundTripMap = SourceMap::fromBuffer('/', $emptyLineOffsetMap->toBuffer());

$generatedOnlyOffsetMap = new SourceMap();
$generatedOnlyOffsetSource = $generatedOnlyOffsetMap->addSource('wp-content/themes/example/generated-offset.css');
$generatedOnlyOffsetMap->setSourceContent($generatedOnlyOffsetSource, ".wp-block-generated-offset{display:block}\n");
$generatedOnlyOffsetMap->addGeneratedMappingWithOffset(0, 5, 2, 7);
$generatedOnlyOffsetMap->addMappingWithOffset(0, 0, $generatedOnlyOffsetSource, 0, 0, 2, 18, 'generated-offset-rule');
$generatedOnlyOffsetMap->addGeneratedMappingWithOffset(1, 2, 2, 0);

$themeJsonSource = <<<'JSON'
{
  "version": 3,
  "settings": {
    "color": {
      "palette": [
        { "slug": "primary", "color": "#06c" }
      ]
    }
  },
  "styles": {
    "blocks": {
      "core/spacer": { "spacing": { "margin": { "top": "1rem" } } }
    }
  }
}
JSON;

$coverThemeJsonRule = '.wp-block-cover{color:var(--wp--preset--color--primary)}';
$spacerThemeJsonRule = '.wp-block-spacer{margin-top:1rem}';
$generatedThemeJsonMap = new SourceMap();
$generatedThemeJson = $generatedThemeJsonMap->addSource('wp-content/cache/theme-json.generated.css');
$generatedThemeJsonMap->setSourceContent($generatedThemeJson, $coverThemeJsonRule . $spacerThemeJsonRule);
$generatedThemeJsonMap->addMapping(0, 0, $generatedThemeJson, 0, 0, 'coverRule');
$generatedThemeJsonMap->addMapping(0, strlen($coverThemeJsonRule), $generatedThemeJson, 0, strlen($coverThemeJsonRule), 'spacerRule');

$themeJsonInputMap = new SourceMap();
$themeJsonSourceIndex = $themeJsonInputMap->addSource('wp-content/themes/example/theme.json');
$themeJsonInputMap->setSourceContent($themeJsonSourceIndex, $themeJsonSource);
$themeJsonInputMap->addMapping(0, 0, $themeJsonSourceIndex, 6, 8, 'settings.color.primary');
$themeJsonInputMap->addMapping(0, strlen($coverThemeJsonRule), $themeJsonSourceIndex, 12, 6, 'styles.blocks.core/spacer.spacing.margin.top');
$generatedThemeJsonMap->extendWithSourceMap($themeJsonInputMap);

$projectRootMap = new SourceMap('/srv/www/example');
$projectStyle = $projectRootMap->addSource('/srv/www/example/wp-content/themes/example/style.css');
$projectBlocks = $projectRootMap->addSource('file:///srv/www/example/wp-content/themes/example/./blocks.css');
$projectVirtual = $projectRootMap->addSource('theme://generated/editor.css');
$projectRootMap->setSourceContent($projectStyle, $entrySource);
$projectRootMap->setSourceContent($projectBlocks, $blockSource);
$projectRootMap->setSourceContent($projectVirtual, '.wp-block-cover{outline:2px solid currentColor}');
$projectRootMap->addMapping(0, 0, $projectStyle, 1, 0, 'theme-footer');
$projectRootMap->addMapping(0, strlen($footerRule), $projectBlocks, 5, 0, 'block-cover');
$projectRootMap->addMapping(1, 0, $projectVirtual, 0, 0, 'virtual-editor-rule');

$dataUrlMap = new SourceMap('/srv/www/example');
$dataUrlSource = $dataUrlMap->addSource('/srv/www/example/wp-content/themes/example/inline-critical.css');
$dataUrlMap->setSourceContent($dataUrlSource, ".critical{display:block}\n");
$dataUrlMap->addMapping(1, 1, $dataUrlSource, 0, 0, 'critical-rule');
$dataUrl = $dataUrlMap->toDataUrl('/');
$dataUrlRoundTrip = SourceMap::fromDataUrl($dataUrl, '/srv/www/example');

$negativeOffsetRawMap = new SourceMap();
$negativeOffsetRawMap->addVlqMap(
    'AAEIA;ACGEC',
    [
        'wp-content/themes/example/source-map-prelude.css',
        'wp-content/themes/example/blocks/cover.css',
    ],
    [
        '.prelude{}',
        '.wp-block-cover{}',
    ],
    [
        'prelude-rule',
        'cover-rule',
    ],
    -1,
    4
);

$streamingRawVlqMap = new SourceMap();
$streamingRawVlqMap->addVlqMap(
    'AAAAAA;C',
    ['wp-content/themes/example/source-map-stream.css'],
    ['.wp-block-source-map-stream{}'],
    ['stream-rule'],
    2,
    4
);

$duplicateColumnPositiveMap = new SourceMap();
$duplicateColumnPositiveMap->addVlqMap(
    'AAAAAA,C',
    ['wp-content/themes/example/source-map-duplicate-column.css'],
    ['.wp-block-duplicate-column{}'],
    ['duplicate-column-rule']
);
$duplicateColumnPositiveMap->offsetColumns(0, 0, 5);

$duplicateColumnNegativeMap = new SourceMap();
$duplicateColumnNegativeMap->addVlqMap(
    'AAAAAA,K',
    ['wp-content/themes/example/source-map-duplicate-column.css'],
    ['.wp-block-duplicate-column{}'],
    ['duplicate-column-rule']
);
$duplicateColumnNegativeMap->offsetColumns(0, 5, -5);

$duplicateLookupInputMap = SourceMap::fromJson(
    '{"version":3,"mappings":"AAAAAA","sources":["wp-content/themes/example/source-map-duplicate-lookup.scss"],"sourcesContent":[".wp-block-duplicate-lookup{}"],"names":["duplicate-lookup-rule"]}'
);
$duplicateLookupExact = $duplicateLookupInputMap->findClosestMapping(0, 0);
$duplicateLookupAfterLast = $duplicateLookupInputMap->findClosestMapping(0, 1);
$duplicateLookupExtendedMap = new SourceMap();
$duplicateLookupCompiled = $duplicateLookupExtendedMap->addSource('wp-content/cache/duplicate-lookup.css');
$duplicateLookupExtendedMap->setSourceContent($duplicateLookupCompiled, '.wp-block-duplicate-lookup{color:red}');
$duplicateLookupExtendedMap->addMapping(0, 0, $duplicateLookupCompiled, 0, 0, 'compiled-duplicate-lookup');
$duplicateLookupExtendedMap->extendWithSourceMap($duplicateLookupInputMap);

$maxUnsignedVlqDecode = SourceMap::decodeVlq('+/////H')[0]['generatedColumn'];
$invalidRelativeVlqGuard = true;
foreach (['D', 'ADAA', 'AADA', 'AAAD', 'AAAAD', 'ggggggI', '//////////////D'] as $invalidVlqMapping) {
    try {
        SourceMap::decodeVlq($invalidVlqMapping);
        $invalidRelativeVlqGuard = false;
        break;
    } catch (InvalidArgumentException) {
        continue;
    }
}

$lookup = [
    'sourceIndexes' => $projectRootMap->addSources([
        '/srv/www/example/wp-content/themes/example/style.css',
        'file:///srv/www/example/wp-content/themes/example/./blocks.css',
        'theme://generated/editor.css',
    ]),
    'blockIndex' => $projectRootMap->getSourceIndex('file:///srv/www/example/wp-content/themes/example/blocks.css'),
    'missingSource' => $projectRootMap->getSourceIndex('wp-content/themes/example/missing.css'),
    'sources' => $projectRootMap->getSources(),
    'blockContent' => $projectRootMap->getSourceContent($projectBlocks),
    'nameIndexes' => $projectRootMap->addNames(['theme-footer', 'block-cover', 'virtual-editor-rule']),
    'virtualName' => $projectRootMap->getNameIndex('virtual-editor-rule'),
    'names' => $projectRootMap->getNames(),
    'mappings' => $projectRootMap->getMappings(),
];

$actual = [
    'css' => $code,
    'map' => $map->toJson(null, false),
    'consumedNestedMap' => $consumedNestedMap,
    'consumedNestedReplayNoop' => $consumedNestedReplayNoop,
    'emptyMap' => $themeJsonMap->toJson(null, false),
    'lineSpanMap' => $inlineEditorMap->toJson(null, false),
    'emptyLineColumnOffsetMap' => $emptyLineOffsetMap->toJson(null, false),
    'emptyLineColumnOffsetGuard' => $emptyLineColumnOffsetGuard && $emptyLineOffsetBeforeColumnNoop === $emptyLineOffsetMap->toJson(null, false),
    'bufferRoundTripMap' => $bufferRoundTripMap->toJson(null, false),
    'generatedOnlyOffsetMap' => $generatedOnlyOffsetMap->toJson(null, false),
    'extendedInputMap' => $generatedThemeJsonMap->toJson(null, false),
    'projectRootMap' => $projectRootMap->toJson(null, false),
    'dataUrl' => $dataUrl,
    'dataUrlRoundTrip' => $dataUrlRoundTrip->toJson('/'),
    'negativeOffsetRawMap' => $negativeOffsetRawMap->toJson(null, false),
    'streamingRawVlqMap' => $streamingRawVlqMap->toJson(null, false),
    'duplicateColumnPositiveMap' => $duplicateColumnPositiveMap->toJson(null, false),
    'duplicateColumnNegativeMap' => $duplicateColumnNegativeMap->toJson(null, false),
    'duplicateLookupExact' => $duplicateLookupExact,
    'duplicateLookupAfterLast' => $duplicateLookupAfterLast,
    'duplicateLookupExtendedMap' => $duplicateLookupExtendedMap->toJson(null, false),
    'maxUnsignedVlqDecode' => $maxUnsignedVlqDecode,
    'invalidRelativeVlqGuard' => $invalidRelativeVlqGuard,
    'lookup' => $lookup,
];

if (($argv[1] ?? null) === '--self-test') {
    $expected = [
        'css' => $code,
        'map' => '{"version":3,"mappings":";;;;;oBCKA,2BAGA,8CDPA","sources":["wp-content/themes/example/style.css","wp-content/themes/example/blocks.css"],"sourcesContent":["@import \"blocks.css\";\n.theme-footer {\n  color: green;\n}","/*! Theme package license */\n/*!\n * Block editor stylesheet generated from theme.json\n * Keep comments for distribution compliance.\n */\n.wp-block-cover {\n  color: yellow;\n}\n.wp-block-cover .wp-block-button {\n  margin: 1rem;\n}"],"names":[]}',
        'consumedNestedMap' => '{"version":3,"mappings":"","sources":[],"sourcesContent":[],"names":[]}',
        'consumedNestedReplayNoop' => true,
        'emptyMap' => '{"version":3,"mappings":";AAAA;AACA;AACA","sources":["wp-content/themes/example/theme-json.css"],"sourcesContent":[":root {\n  --wp--style--global--content-size: 720px;\n}\n"],"names":[]}',
        'lineSpanMap' => '{"version":3,"mappings":"AAAA;;","sources":["wp-content/themes/example/editor-inline.css"],"sourcesContent":[".wp-block-spacer {\n  margin-top: 1rem;\n}\n"],"names":[]}',
        'emptyLineColumnOffsetMap' => '{"version":3,"mappings":"AAAA;;","sources":["wp-content/themes/example/empty-line-offset.css"],"sourcesContent":[".wp-block-empty-line-offset {}\n"],"names":[]}',
        'emptyLineColumnOffsetGuard' => true,
        'bufferRoundTripMap' => '{"version":3,"mappings":"AAAA;;","sources":["wp-content/themes/example/empty-line-offset.css"],"sourcesContent":[".wp-block-empty-line-offset {}\n"],"names":[]}',
        'generatedOnlyOffsetMap' => '{"version":3,"mappings":";;Y,MAAAA;E","sources":["wp-content/themes/example/generated-offset.css"],"sourcesContent":[".wp-block-generated-offset{display:block}\n"],"names":["generated-offset-rule"]}',
        'extendedInputMap' => '{"version":3,"mappings":"ACMQE,wDAMFC","sources":["wp-content/cache/theme-json.generated.css","wp-content/themes/example/theme.json"],"sourcesContent":[".wp-block-cover{color:var(--wp--preset--color--primary)}.wp-block-spacer{margin-top:1rem}","{\n  \"version\": 3,\n  \"settings\": {\n    \"color\": {\n      \"palette\": [\n        { \"slug\": \"primary\", \"color\": \"#06c\" }\n      ]\n    }\n  },\n  \"styles\": {\n    \"blocks\": {\n      \"core/spacer\": { \"spacing\": { \"margin\": { \"top\": \"1rem\" } } }\n    }\n  }\n}"],"names":["coverRule","spacerRule","settings.color.primary","styles.blocks.core/spacer.spacing.margin.top"]}',
        'projectRootMap' => '{"version":3,"mappings":"AACAA,0BCIAC;ACLAC","sources":["wp-content/themes/example/style.css","wp-content/themes/example/blocks.css","theme://generated/editor.css"],"sourcesContent":["@import \"blocks.css\";\n.theme-footer {\n  color: green;\n}","/*! Theme package license */\n/*!\n * Block editor stylesheet generated from theme.json\n * Keep comments for distribution compliance.\n */\n.wp-block-cover {\n  color: yellow;\n}\n.wp-block-cover .wp-block-button {\n  margin: 1rem;\n}",".wp-block-cover{outline:2px solid currentColor}"],"names":["theme-footer","block-cover","virtual-editor-rule"]}',
        'dataUrl' => 'data:application/json;charset=utf-8;base64,eyJ2ZXJzaW9uIjozLCJzb3VyY2VSb290IjoiLyIsIm1hcHBpbmdzIjoiO0NBQUFBIiwic291cmNlcyI6WyJ3cC1jb250ZW50L3RoZW1lcy9leGFtcGxlL2lubGluZS1jcml0aWNhbC5jc3MiXSwic291cmNlc0NvbnRlbnQiOlsiLmNyaXRpY2Fse2Rpc3BsYXk6YmxvY2t9XG4iXSwibmFtZXMiOlsiY3JpdGljYWwtcnVsZSJdfQ==',
        'dataUrlRoundTrip' => '{"version":3,"sourceRoot":"/","mappings":";CAAAA","sources":["wp-content/themes/example/inline-critical.css"],"sourcesContent":[".critical{display:block}\n"],"names":["critical-rule"]}',
        'negativeOffsetRawMap' => '{"version":3,"mappings":"ICKMC","sources":["wp-content/themes/example/source-map-prelude.css","wp-content/themes/example/blocks/cover.css"],"sourcesContent":[".prelude{}",".wp-block-cover{}"],"names":["prelude-rule","cover-rule"]}',
        'streamingRawVlqMap' => '{"version":3,"mappings":";;IAAAA,A;K","sources":["wp-content/themes/example/source-map-stream.css"],"sourcesContent":[".wp-block-source-map-stream{}"],"names":["stream-rule"]}',
        'duplicateColumnPositiveMap' => '{"version":3,"mappings":"AAAAA,K,C","sources":["wp-content/themes/example/source-map-duplicate-column.css"],"sourcesContent":[".wp-block-duplicate-column{}"],"names":["duplicate-column-rule"]}',
        'duplicateColumnNegativeMap' => '{"version":3,"mappings":"AAAAA,A","sources":["wp-content/themes/example/source-map-duplicate-column.css"],"sourcesContent":[".wp-block-duplicate-column{}"],"names":["duplicate-column-rule"]}',
        'duplicateLookupExact' => ['generatedLine' => 0, 'generatedColumn' => 0, 'sourceIndex' => null, 'originalLine' => null, 'originalColumn' => null, 'nameIndex' => null],
        'duplicateLookupAfterLast' => ['generatedLine' => 0, 'generatedColumn' => 0, 'sourceIndex' => 0, 'originalLine' => 0, 'originalColumn' => 0, 'nameIndex' => 0],
        'duplicateLookupExtendedMap' => '{"version":3,"mappings":"A","sources":["wp-content/cache/duplicate-lookup.css","wp-content/themes/example/source-map-duplicate-lookup.scss"],"sourcesContent":[".wp-block-duplicate-lookup{color:red}",".wp-block-duplicate-lookup{}"],"names":["compiled-duplicate-lookup","duplicate-lookup-rule"]}',
        'maxUnsignedVlqDecode' => 4294967295,
        'invalidRelativeVlqGuard' => true,
        'lookup' => [
            'sourceIndexes' => [0, 1, 2],
            'blockIndex' => 1,
            'missingSource' => null,
            'sources' => ['wp-content/themes/example/style.css', 'wp-content/themes/example/blocks.css', 'theme://generated/editor.css'],
            'blockContent' => $blockSource,
            'nameIndexes' => [0, 1, 2],
            'virtualName' => 2,
            'names' => ['theme-footer', 'block-cover', 'virtual-editor-rule'],
            'mappings' => [
                ['generatedLine' => 0, 'generatedColumn' => 0, 'sourceIndex' => 0, 'originalLine' => 1, 'originalColumn' => 0, 'nameIndex' => 0],
                ['generatedLine' => 0, 'generatedColumn' => strlen($footerRule), 'sourceIndex' => 1, 'originalLine' => 5, 'originalColumn' => 0, 'nameIndex' => 1],
                ['generatedLine' => 1, 'generatedColumn' => 0, 'sourceIndex' => 2, 'originalLine' => 0, 'originalColumn' => 0, 'nameIndex' => 2],
            ],
        ],
    ];

    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected source-map output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo $code . "\n";
echo $actual['map'] . "\n";
