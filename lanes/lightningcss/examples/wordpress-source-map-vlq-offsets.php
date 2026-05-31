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

$actual = [
    'css' => $code,
    'map' => $map->toJson(null, false),
    'emptyMap' => $themeJsonMap->toJson(null, false),
    'lineSpanMap' => $inlineEditorMap->toJson(null, false),
    'extendedInputMap' => $generatedThemeJsonMap->toJson(null, false),
    'projectRootMap' => $projectRootMap->toJson(null, false),
];

if (($argv[1] ?? null) === '--self-test') {
    $expected = [
        'css' => $code,
        'map' => '{"version":3,"mappings":";;;;;oBCKA,2BAGA,8CDPA","sources":["wp-content/themes/example/style.css","wp-content/themes/example/blocks.css"],"sourcesContent":["@import \"blocks.css\";\n.theme-footer {\n  color: green;\n}","/*! Theme package license */\n/*!\n * Block editor stylesheet generated from theme.json\n * Keep comments for distribution compliance.\n */\n.wp-block-cover {\n  color: yellow;\n}\n.wp-block-cover .wp-block-button {\n  margin: 1rem;\n}"],"names":[]}',
        'emptyMap' => '{"version":3,"mappings":";AAAA;AACA;AACA","sources":["wp-content/themes/example/theme-json.css"],"sourcesContent":[":root {\n  --wp--style--global--content-size: 720px;\n}\n"],"names":[]}',
        'lineSpanMap' => '{"version":3,"mappings":"AAAA;;","sources":["wp-content/themes/example/editor-inline.css"],"sourcesContent":[".wp-block-spacer {\n  margin-top: 1rem;\n}\n"],"names":[]}',
        'extendedInputMap' => '{"version":3,"mappings":"ACMQE,wDAMFC","sources":["wp-content/cache/theme-json.generated.css","wp-content/themes/example/theme.json"],"sourcesContent":[".wp-block-cover{color:var(--wp--preset--color--primary)}.wp-block-spacer{margin-top:1rem}","{\n  \"version\": 3,\n  \"settings\": {\n    \"color\": {\n      \"palette\": [\n        { \"slug\": \"primary\", \"color\": \"#06c\" }\n      ]\n    }\n  },\n  \"styles\": {\n    \"blocks\": {\n      \"core/spacer\": { \"spacing\": { \"margin\": { \"top\": \"1rem\" } } }\n    }\n  }\n}"],"names":["coverRule","spacerRule","settings.color.primary","styles.blocks.core/spacer.spacing.margin.top"]}',
        'projectRootMap' => '{"version":3,"mappings":"AACAA,0BCIAC;ACLAC","sources":["wp-content/themes/example/style.css","wp-content/themes/example/blocks.css","theme://generated/editor.css"],"sourcesContent":["@import \"blocks.css\";\n.theme-footer {\n  color: green;\n}","/*! Theme package license */\n/*!\n * Block editor stylesheet generated from theme.json\n * Keep comments for distribution compliance.\n */\n.wp-block-cover {\n  color: yellow;\n}\n.wp-block-cover .wp-block-button {\n  margin: 1rem;\n}",".wp-block-cover{outline:2px solid currentColor}"],"names":["theme-footer","block-cover","virtual-editor-rule"]}',
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
