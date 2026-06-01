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

$skippedNestedParent = new SourceMap();
$skippedNestedChild = new SourceMap();
$skippedNestedBlock = $skippedNestedChild->addSource('wp-content/themes/example/blocks/skipped-nested.css');
$skippedNestedUnused = $skippedNestedChild->addSource('wp-content/themes/example/blocks/unused-nested.css');
$skippedNestedChild->setSourceContent($skippedNestedBlock, ".wp-block-skipped-nested{}\n");
$skippedNestedChild->setSourceContent($skippedNestedUnused, ".wp-block-unused-nested{}\n");
$skippedNestedChild->addMapping(0, 0, $skippedNestedBlock, 2, 1, 'skipped-nested-rule');
$skippedNestedChild->addName('unused-nested-name');
$skippedNestedParent->addSourceMap($skippedNestedChild, -1);
$skippedNestedChildConsumed = $skippedNestedChild->toJson(null, false);

$partialSkippedNestedParent = new SourceMap();
$partialSkippedNestedChild = new SourceMap();
$partialSkippedSource = $partialSkippedNestedChild->addSource('wp-content/themes/example/blocks/skipped-partial.css');
$partialKeptSource = $partialSkippedNestedChild->addSource('wp-content/themes/example/blocks/kept-partial.css');
$partialUnusedSource = $partialSkippedNestedChild->addSource('wp-content/themes/example/blocks/unused-partial.css');
$partialSkippedNestedChild->setSourceContent($partialSkippedSource, ".wp-block-skipped-partial{}\n");
$partialSkippedNestedChild->setSourceContent($partialKeptSource, ".wp-block-kept-partial{}\n");
$partialSkippedNestedChild->setSourceContent($partialUnusedSource, ".wp-block-unused-partial{}\n");
$partialSkippedNestedChild->addMapping(0, 0, $partialSkippedSource, 0, 0, 'skipped-partial-rule');
$partialSkippedNestedChild->addMapping(1, 4, $partialKeptSource, 1, 2, 'kept-partial-rule');
$partialSkippedNestedChild->addName('unused-partial-name');
$partialSkippedNestedParent->addSourceMap($partialSkippedNestedChild, -1);
$partialSkippedNestedChildConsumed = $partialSkippedNestedChild->toJson(null, false);

$generatedOnlyChildOffsetParent = new SourceMap();
$generatedOnlyChildOffsetParentSource = $generatedOnlyChildOffsetParent->addSource('wp-content/themes/example/source-map-generated-child-parent.css');
foreach ([0, 1, 2, 3] as $line) {
    $generatedOnlyChildOffsetParent->addMapping($line, 0, $generatedOnlyChildOffsetParentSource, $line, 0, 'generated-child-parent-' . $line);
}
$generatedOnlyChildOffsetChild = new SourceMap();
$generatedOnlyChildOffsetChildSource = $generatedOnlyChildOffsetChild->addSource('wp-content/themes/example/source-map-generated-child.css');
$generatedOnlyChildOffsetChild->setSourceContent($generatedOnlyChildOffsetChildSource, ".wp-block-generated-child{}\n");
$generatedOnlyChildOffsetChild->addGeneratedMapping(0, 4);
$generatedOnlyChildOffsetChild->addMapping(1, 6, $generatedOnlyChildOffsetChildSource, 3, 2, 'generated-child-rule');
$generatedOnlyChildOffsetChild->addGeneratedMapping(2, 8);
$generatedOnlyChildOffsetParent->addSourceMap($generatedOnlyChildOffsetChild, -1);
$generatedOnlyChildOffsetChildConsumed = $generatedOnlyChildOffsetChild->toJson(null, false);
$generatedOnlyChildOffsetClosest = $generatedOnlyChildOffsetParent->findClosestMapping(1, 8);

$themeJsonMap = new SourceMap();
$themeJsonMap->addEmptyMap(
    'wp-content/themes/example/theme-json.css',
    ":root {\n  --wp--style--global--content-size: 720px;\n}\n",
    1
);

$carriageReturnEmptyMap = new SourceMap();
$carriageReturnEmptyMap->addEmptyMap(
    'wp-content/themes/example/legacy-cr.css',
    ".wp-block-legacy\r.wp-block-modern\n.wp-block-crlf\r\n.wp-block-tail\r.rule",
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
$emptyLineOffsetMap->offsetColumns(1, 3, -4);
$emptyLineOffsetMap->offsetColumns(2, 0, -1);
$emptyLineOffsetMap->offsetColumns(5, 3, -4);
$emptyLineOffsetMap->offsetColumns(5, 4294967295, 1);
$emptyLineColumnOffsetNoop = $emptyLineOffsetBeforeColumnNoop === $emptyLineOffsetMap->toJson(null, false);
$emptyLineColumnOverflowGuard = false;
try {
    $emptyLineOffsetMap->offsetColumns(1, 4294967295, 1);
} catch (InvalidArgumentException) {
    $emptyLineColumnOverflowGuard = true;
}
$bufferRoundTripMap = SourceMap::fromBuffer('/', $emptyLineOffsetMap->toBuffer());

$negativeLinePastSpanMap = new SourceMap();
$negativeLinePastSpanSource = $negativeLinePastSpanMap->addSource('wp-content/themes/example/line-span.css');
$negativeLinePastSpanMap->setSourceContent($negativeLinePastSpanSource, ".wp-block-line-span {}\n");
$negativeLinePastSpanMap->addMapping(0, 0, $negativeLinePastSpanSource, 0, 0);
$negativeLinePastSpanMap->offsetLines(5, 2);
$negativeLinePastSpanBeforeGuard = $negativeLinePastSpanMap->toJson(null, false);
$negativeLinePastSpanGuard = false;
try {
    $negativeLinePastSpanMap->offsetLines(9, -2);
} catch (InvalidArgumentException) {
    $negativeLinePastSpanGuard = true;
}

$farLineOffsetMap = new SourceMap();
$farLineOffsetSource = $farLineOffsetMap->addSource('wp-content/themes/example/far-line-offset.css');
$farLineOffsetMap->setSourceContent($farLineOffsetSource, ".wp-block-far-line-offset {}\n");
$farLineOffsetMap->addMapping(0, 0, $farLineOffsetSource, 0, 0);
$farLineOffsetMap->offsetLines(4, 2);
$farLineOffsetMap->addMapping(6, 2, $farLineOffsetSource, 6, 0);
$farLineOffsetMap->offsetLines(5, -2);

$lineStartOffsetMap = new SourceMap();
$lineStartOffsetSource = $lineStartOffsetMap->addSource('wp-content/themes/example/line-start-offset.css');
$lineStartOffsetMap->setSourceContent($lineStartOffsetSource, ".wp-block-line-start-offset {}\n.wp-block-line-start-later {}\n");
$lineStartOffsetMap->addMapping(0, 0, $lineStartOffsetSource, 0, 0, 'line-start-top');
$lineStartOffsetMap->addMapping(2, 4, $lineStartOffsetSource, 2, 1, 'line-start-later');
$lineStartOffsetMap->offsetLines(0, 2);
$lineStartOffsetInsertedMap = $lineStartOffsetMap->toJson(null, false);
$lineStartOffsetMap->offsetLines(2, -1);

$drainedEmptySpanMap = new SourceMap();
$drainedEmptySpanSource = $drainedEmptySpanMap->addSource('wp-content/themes/example/drained-empty-span.css');
$drainedEmptySpanMap->setSourceContent($drainedEmptySpanSource, ".wp-block-drained-empty-span{}\n");
$drainedEmptySpanMap->addMapping(2, 0, $drainedEmptySpanSource, 2, 0, 'drained-empty-span-rule');
$drainedEmptySpanMap->offsetLines(3, 2);
$drainedEmptySpanBeforeDrain = $drainedEmptySpanMap->toJson(null, false);
$drainedEmptySpanMap->offsetLines(3, -1);
$drainedEmptySpanRoundTrip = SourceMap::fromBuffer('/', $drainedEmptySpanMap->toBuffer());

$columnDrainedEmptySpanMap = new SourceMap();
$columnDrainedEmptySpanSource = $columnDrainedEmptySpanMap->addSource('wp-content/themes/example/column-drained-empty-span.css');
$columnDrainedEmptySpanMap->setSourceContent($columnDrainedEmptySpanSource, ".wp-block-column-drained{}\n");
$columnDrainedEmptySpanMap->addMapping(2, 0, $columnDrainedEmptySpanSource, 0, 0, 'column-drained-rule');
$columnDrainedEmptySpanMap->offsetColumns(2, 1, -1);
$columnDrainedEmptySpanRoundTrip = SourceMap::fromBuffer('/', $columnDrainedEmptySpanMap->toBuffer());
$columnDrainedEmptySpanClosest = $columnDrainedEmptySpanMap->findClosestMapping(2, 0);

$columnPrefixDrainMap = new SourceMap();
$columnPrefixDrainSource = $columnPrefixDrainMap->addSource('wp-content/themes/example/column-prefix-drain.css');
$columnPrefixDrainMap->setSourceContent($columnPrefixDrainSource, ".wp-block-prefix-a{}\n.wp-block-prefix-b{}\n");
$columnPrefixDrainMap->addMapping(2, 0, $columnPrefixDrainSource, 0, 0, 'prefix-a-rule');
$columnPrefixDrainMap->addMapping(2, 10, $columnPrefixDrainSource, 1, 0, 'prefix-b-rule');
$columnPrefixDrainMap->offsetColumns(2, 5, -5);

$middleColumnDrainedSpanMap = new SourceMap();
$middleColumnDrainedSpanSource = $middleColumnDrainedSpanMap->addSource('wp-content/themes/example/source-map-middle-column-drain.css');
$middleColumnDrainedSpanMap->setSourceContent($middleColumnDrainedSpanSource, ".wp-block-before-drain{}\n.wp-block-drained-middle{}\n.wp-block-after-drain{}\n");
$middleColumnDrainedSpanMap->addMapping(0, 0, $middleColumnDrainedSpanSource, 0, 0, 'middle-drain-before');
$middleColumnDrainedSpanMap->addMapping(1, 0, $middleColumnDrainedSpanSource, 1, 0, 'middle-drain-removed');
$middleColumnDrainedSpanMap->addMapping(2, 4, $middleColumnDrainedSpanSource, 2, 2, 'middle-drain-after');
$middleColumnDrainedSpanMap->offsetColumns(1, 1, -1);
$middleColumnDrainedSpanRoundTrip = SourceMap::fromBuffer('/', $middleColumnDrainedSpanMap->toBuffer());
$middleColumnDrainedSpanClosest = $middleColumnDrainedSpanMap->findClosestMapping(1, 0);

$mergedColumnDrainedChildParent = new SourceMap();
$mergedColumnDrainedParentSource = $mergedColumnDrainedChildParent->addSource('wp-content/themes/example/source-map-merge-parent.css');
foreach ([0, 1, 2, 3] as $line) {
    $mergedColumnDrainedChildParent->addMapping($line, 0, $mergedColumnDrainedParentSource, $line, 0, 'merged-parent-' . $line);
}
$mergedColumnDrainedChild = new SourceMap();
$mergedColumnDrainedChildSource = $mergedColumnDrainedChild->addSource('wp-content/themes/example/column-drained-child.css');
$mergedColumnDrainedChild->setSourceContent($mergedColumnDrainedChildSource, ".wp-block-column-drained-child{}\n");
$mergedColumnDrainedChild->addMapping(2, 0, $mergedColumnDrainedChildSource, 2, 0, 'merged-column-drain-child-rule');
$mergedColumnDrainedChild->offsetColumns(2, 1, -1);
$mergedColumnDrainedChildBeforeMerge = $mergedColumnDrainedChild->toJson(null, false);
$mergedColumnDrainedChildParent->addSourceMap($mergedColumnDrainedChild, 1);
$mergedColumnDrainedChildConsumed = $mergedColumnDrainedChild->toJson(null, false);
$mergedColumnDrainedChildClosest = $mergedColumnDrainedChildParent->findClosestMapping(2, 0);

$bufferedNegativeColumnDrainedParent = new SourceMap();
$bufferedNegativeColumnDrainedParentSource = $bufferedNegativeColumnDrainedParent->addSource('wp-content/themes/example/source-map-buffered-negative-parent.css');
foreach ([0, 1, 2, 3] as $line) {
    $bufferedNegativeColumnDrainedParent->addMapping($line, 0, $bufferedNegativeColumnDrainedParentSource, $line, 0, 'buffered-negative-parent-' . $line);
}
$bufferedNegativeColumnDrainedChild = new SourceMap();
$bufferedNegativeColumnDrainedChildSource = $bufferedNegativeColumnDrainedChild->addSource('wp-content/themes/example/buffered-negative-column-drained-child.css');
$bufferedNegativeColumnDrainedChild->setSourceContent($bufferedNegativeColumnDrainedChildSource, ".wp-block-buffered-negative-column-drained{}\n");
$bufferedNegativeColumnDrainedChild->addMapping(2, 0, $bufferedNegativeColumnDrainedChildSource, 2, 0, 'buffered-negative-column-drain-child-rule');
$bufferedNegativeColumnDrainedChild->offsetColumns(2, 1, -1);
$bufferedNegativeColumnDrainedChildBeforeMerge = $bufferedNegativeColumnDrainedChild->toJson(null, false);
$bufferedNegativeColumnDrainedRestoredChild = SourceMap::fromBuffer('/', $bufferedNegativeColumnDrainedChild->toBuffer());
$bufferedNegativeColumnDrainedParent->addSourceMap($bufferedNegativeColumnDrainedRestoredChild, -1);
$bufferedNegativeColumnDrainedRestoredChildConsumed = $bufferedNegativeColumnDrainedRestoredChild->toJson(null, false);
$bufferedNegativeColumnDrainedClosest = $bufferedNegativeColumnDrainedParent->findClosestMapping(0, 0);

$rawLeadingSemicolonOffsetMap = new SourceMap();
$rawLeadingSemicolonOffsetMap->addVlqMap(
    ';;AACA',
    ['wp-content/themes/example/source-map-leading-semicolon.css'],
    ['.wp-block-leading-semicolon{}'],
    [],
    2,
    3
);

$negativeChildLineOffsetParent = new SourceMap();
$negativeChildLineOffsetSource = $negativeChildLineOffsetParent->addSource('wp-content/themes/example/negative-child-parent.css');
for ($line = 0; $line < 4; $line++) {
    $negativeChildLineOffsetParent->addMapping($line, 0, $negativeChildLineOffsetSource, $line, 0, 'negative-child-parent-' . $line);
}
$negativeChildLineOffsetChild = new SourceMap();
$negativeChildLineOffsetChildSource = $negativeChildLineOffsetChild->addSource('wp-content/themes/example/negative-child.css');
$negativeChildLineOffsetChild->addMapping(0, 2, $negativeChildLineOffsetChildSource, 7, 1, 'negative-child-rule');
$negativeChildLineOffsetChild->offsetLines(1, 2);
$negativeChildLineOffsetParent->addSourceMap($negativeChildLineOffsetChild, -1);
$negativeChildLineOffsetChildConsumed = $negativeChildLineOffsetChild->toJson(null, false);

$leadingEmptyChildOffsetParent = new SourceMap();
$leadingEmptyChildOffsetParentSource = $leadingEmptyChildOffsetParent->addSource('wp-content/themes/example/source-map-leading-child-parent.css');
for ($line = 0; $line < 5; $line++) {
    $leadingEmptyChildOffsetParent->addMapping($line, 0, $leadingEmptyChildOffsetParentSource, $line, 0, 'leading-parent-line-' . $line);
}
$leadingEmptyChildOffsetChild = new SourceMap();
$leadingEmptyChildOffsetChildSource = $leadingEmptyChildOffsetChild->addSource('wp-content/themes/example/source-map-leading-child.css');
$leadingEmptyChildOffsetChild->setSourceContent($leadingEmptyChildOffsetChildSource, ".wp-block-leading-child{}\n");
$leadingEmptyChildOffsetChild->addMapping(0, 3, $leadingEmptyChildOffsetChildSource, 7, 1, 'leading-child-rule');
$leadingEmptyChildOffsetChild->offsetLines(0, 2);
$leadingEmptyChildOffsetParent->addSourceMap($leadingEmptyChildOffsetChild, 1);
$leadingEmptyChildOffsetChildConsumed = $leadingEmptyChildOffsetChild->toJson(null, false);

$negativeLeadingEmptyChildOffsetParent = new SourceMap();
$negativeLeadingEmptyChildOffsetParentSource = $negativeLeadingEmptyChildOffsetParent->addSource('wp-content/themes/example/source-map-negative-leading-parent.css');
for ($line = 0; $line < 4; $line++) {
    $negativeLeadingEmptyChildOffsetParent->addMapping($line, 0, $negativeLeadingEmptyChildOffsetParentSource, $line, 0, 'negative-leading-parent-' . $line);
}
$negativeLeadingEmptyChildOffsetChild = new SourceMap();
$negativeLeadingEmptyChildOffsetChildSource = $negativeLeadingEmptyChildOffsetChild->addSource('wp-content/themes/example/source-map-negative-leading-child.css');
$negativeLeadingEmptyChildOffsetChild->setSourceContent($negativeLeadingEmptyChildOffsetChildSource, ".wp-block-negative-leading-child{}\n");
$negativeLeadingEmptyChildOffsetChild->addMapping(0, 6, $negativeLeadingEmptyChildOffsetChildSource, 4, 2, 'negative-leading-child-rule');
$negativeLeadingEmptyChildOffsetChild->offsetLines(0, 2);
$negativeLeadingEmptyChildOffsetChildBeforeMerge = $negativeLeadingEmptyChildOffsetChild->toJson(null, false);
$negativeLeadingEmptyChildOffsetRestored = SourceMap::fromBuffer('/', $negativeLeadingEmptyChildOffsetChild->toBuffer());
$negativeLeadingEmptyChildOffsetParent->addSourceMap($negativeLeadingEmptyChildOffsetRestored, -1);
$negativeLeadingEmptyChildOffsetChildConsumed = $negativeLeadingEmptyChildOffsetRestored->toJson(null, false);
$negativeLeadingEmptyChildOffsetClearedClosest = $negativeLeadingEmptyChildOffsetParent->findClosestMapping(0, 0);
$negativeLeadingEmptyChildOffsetKeptClosest = $negativeLeadingEmptyChildOffsetParent->findClosestMapping(1, 6);

$generatedOnlyOffsetMap = new SourceMap();
$generatedOnlyOffsetSource = $generatedOnlyOffsetMap->addSource('wp-content/themes/example/generated-offset.css');
$generatedOnlyOffsetMap->setSourceContent($generatedOnlyOffsetSource, ".wp-block-generated-offset{display:block}\n");
$generatedOnlyOffsetMap->addGeneratedMappingWithOffset(0, 5, 2, 7);
$generatedOnlyOffsetMap->addMappingWithOffset(0, 0, $generatedOnlyOffsetSource, 0, 0, 2, 18, 'generated-offset-rule');
$generatedOnlyOffsetMap->addGeneratedMappingWithOffset(1, 2, 2, 0);

$mappingRecordOffsetMap = new SourceMap();
$mappingRecordOffsetSource = $mappingRecordOffsetMap->addSource('wp-content/themes/example/source-map-record-offset.css');
$mappingRecordOffsetMap->setSourceContent($mappingRecordOffsetSource, ".wp-block-record-offset{}\n");
$mappingRecordOffsetName = $mappingRecordOffsetMap->addName('record-offset-rule');
$mappingRecordOffsetMap->addMappingRecordWithOffset(
    [
        'generatedLine' => 0,
        'generatedColumn' => 2,
        'sourceIndex' => $mappingRecordOffsetSource,
        'originalLine' => 4,
        'originalColumn' => 3,
        'nameIndex' => $mappingRecordOffsetName,
    ],
    2,
    5
);
$mappingRecordOffsetMap->addMappingRecordWithOffset(
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

$dedupedRawVlqOffsetMap = new SourceMap('/srv/www/site/wp-content/themes/example');
$dedupedRawVlqShared = $dedupedRawVlqOffsetMap->addSource('/srv/www/site/wp-content/themes/example/shared.css');
$dedupedRawVlqOffsetMap->setSourceContent($dedupedRawVlqShared, '.wp-block-shared{color:old}');
$dedupedRawVlqOffsetMap->addName('shared-block-rule');
$dedupedRawVlqOffsetMap->addVlqMap(
    'AAAAA,ICEGC;ACACC',
    [
        '/srv/www/site/wp-content/themes/example/shared.css',
        './blocks/card.css',
        'shared.css',
    ],
    [
        '.wp-block-shared{color:green}',
        '.wp-block-card{color:red}',
        '.wp-block-shared{color:rebeccapurple}',
    ],
    [
        'shared-block-rule',
        'card-block-rule',
        'shared-block-rule',
    ],
    2,
    3
);

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

$skippedSameLineRawVlqMap = new SourceMap();
$skippedSameLineRawVlqMap->addVlqMap(
    'AAAAA,CAAC;ACCEC',
    [
        'wp-content/themes/example/source-map-same-line-prelude.css',
        'wp-content/themes/example/blocks/same-line-cover.css',
    ],
    [
        '.wp-block-same-line-prelude{}',
        '.wp-block-same-line-cover{}',
    ],
    [
        'same-line-prelude-rule',
        'same-line-cover-rule',
    ],
    -1,
    3
);

$skippedGeneratedOnlyRawVlqMap = new SourceMap();
$skippedGeneratedOnlyRawVlqMap->addVlqMap(
    'AAAAA,E;ACECC',
    [
        'wp-content/themes/example/source-map-generated-state-prelude.css',
        'wp-content/themes/example/blocks/generated-state-cover.css',
    ],
    [
        '.wp-block-generated-state-prelude{}',
        '.wp-block-generated-state-cover{}',
    ],
    [
        'generated-state-prelude-rule',
        'generated-state-cover-rule',
    ],
    -1,
    5
);

$fullySkippedRawVlqMap = new SourceMap();
$fullySkippedRawVlqMap->addVlqMap(
    'AAAAA;AACA',
    [
        'wp-content/themes/example/source-map-skipped.scss',
        'wp-content/themes/example/source-map-unused.scss',
    ],
    [
        '.wp-block-skipped{color:red}',
        '.wp-block-unused{color:blue}',
    ],
    [
        'skipped-rule',
        'unused-rule',
    ],
    -3,
    0
);

$separatorOnlyRawVlqMap = new SourceMap();
$separatorOnlyRawVlqMap->addVlqMap(
    ';;;,,',
    ['wp-content/themes/example/source-map-empty-separators.css'],
    ['.wp-block-empty-separators{}'],
    ['empty-separators-rule'],
    4,
    9
);
$separatorOnlyRawVlqParent = new SourceMap();
$separatorOnlyRawVlqEntry = $separatorOnlyRawVlqParent->addSource('wp-content/themes/example/source-map-entry.css');
$separatorOnlyRawVlqParent->setSourceContent($separatorOnlyRawVlqEntry, ".wp-block-source-map-entry{}\n");
$separatorOnlyRawVlqParent->addMapping(0, 0, $separatorOnlyRawVlqEntry, 0, 0, 'source-map-entry-rule');
$separatorOnlyRawVlqParent->addSourceMap($separatorOnlyRawVlqMap, 2);
$separatorOnlyRawVlqChildConsumed = $separatorOnlyRawVlqMap->toJson(null, false);

$skippedInvalidSourceIndexMap = new SourceMap();
$skippedInvalidSourceIndexGuard = false;
try {
    $skippedInvalidSourceIndexMap->addVlqMap(
        'ACAA',
        ['wp-content/themes/example/source-map-skipped-invalid-source.css'],
        ['.wp-block-skipped-invalid-source{}'],
        ['skipped-invalid-source-rule'],
        -1,
        0
    );
} catch (OutOfBoundsException) {
    $skippedInvalidSourceIndexGuard = true;
}

$skippedInvalidNameIndexMap = new SourceMap();
$skippedInvalidNameIndexGuard = false;
try {
    $skippedInvalidNameIndexMap->addVlqMap(
        'AAAAC',
        ['wp-content/themes/example/source-map-skipped-invalid-name.css'],
        ['.wp-block-skipped-invalid-name{}'],
        ['skipped-invalid-known-name'],
        -1,
        0
    );
} catch (OutOfBoundsException) {
    $skippedInvalidNameIndexGuard = true;
}

$partialInvalidSourceIndexMap = new SourceMap();
$partialInvalidSourceIndexGuard = false;
try {
    $partialInvalidSourceIndexMap->addVlqMap(
        'KAAA,ECAA',
        ['wp-content/themes/example/source-map-partial-invalid-source.css'],
        ['.wp-block-partial-invalid-source{}'],
        [],
        2,
        3
    );
} catch (OutOfBoundsException) {
    $partialInvalidSourceIndexGuard = true;
}

$partialInvalidNameIndexMap = new SourceMap();
$partialInvalidNameIndexGuard = false;
try {
    $partialInvalidNameIndexMap->addVlqMap(
        'KAAAA,EAAAC',
        ['wp-content/themes/example/source-map-partial-invalid-name.css'],
        ['.wp-block-partial-invalid-name{}'],
        ['partial-invalid-name-rule'],
        1,
        0
    );
} catch (OutOfBoundsException) {
    $partialInvalidNameIndexGuard = true;
}

$negativeColumnGeneratedOnlyMap = new SourceMap();
$negativeColumnGeneratedOnlyMap->addVlqMap(
    'K,I;O',
    ['wp-content/themes/example/source-map-negative-column.css'],
    ['.wp-block-negative-column{}'],
    [],
    0,
    -3
);

$negativeColumnSourceBackedMap = new SourceMap();
$negativeColumnSourceBackedMap->addVlqMap(
    'KAAA,IACA;OACA',
    ['wp-content/themes/example/source-map-negative-column.css'],
    ['.wp-block-negative-column{}'],
    [],
    0,
    -3
);

$negativeColumnSkippedLineMap = new SourceMap();
$negativeColumnSkippedLineMap->addVlqMap(
    'KAAA;OACA',
    ['wp-content/themes/example/source-map-negative-column.css'],
    ['.wp-block-negative-column{}'],
    [],
    -1,
    -3
);

$negativeColumnOffsetGuard = false;
try {
    $negativeColumnGuardMap = new SourceMap();
    $negativeColumnGuardMap->addVlqMap('A', [], [], [], 0, -1);
} catch (InvalidArgumentException) {
    $negativeColumnOffsetGuard = true;
}

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

$tripleDuplicateLookupMap = new SourceMap();
$tripleDuplicateLookupMap->addVlqMap(
    'AAAAAA,A,CACAC',
    ['wp-content/themes/example/source-map-triple-duplicate.css'],
    ['.wp-block-triple-duplicate{}'],
    ['triple-first-rule', 'triple-second-rule']
);
$tripleDuplicateExact = $tripleDuplicateLookupMap->findClosestMapping(0, 0);
$tripleDuplicateAfterDuplicates = $tripleDuplicateLookupMap->findClosestMapping(0, 1);

$tripleDuplicatePositiveMap = new SourceMap();
$tripleDuplicatePositiveMap->addVlqMap(
    'AAAAAA,A,CACAC',
    ['wp-content/themes/example/source-map-triple-duplicate.css'],
    ['.wp-block-triple-duplicate{}'],
    ['triple-first-rule', 'triple-second-rule']
);
$tripleDuplicatePositiveMap->offsetColumns(0, 0, 5);

$tripleDuplicateNegativeMap = new SourceMap();
$tripleDuplicateNegativeMap->addVlqMap(
    'AAAAAA,A,KACAC',
    ['wp-content/themes/example/source-map-triple-duplicate.css'],
    ['.wp-block-triple-duplicate{}'],
    ['triple-first-rule', 'triple-shifted-rule']
);
$tripleDuplicateNegativeMap->offsetColumns(0, 5, -5);
$tripleDuplicateNegativeClosest = $tripleDuplicateNegativeMap->findClosestMapping(0, 0);

$duplicateBoundaryBufferedRawMap = new SourceMap();
$duplicateBoundaryBufferedRawMap->addVlqMap(
    'AAAAAA,CACAC',
    ['wp-content/themes/example/source-map-duplicate-buffered.css'],
    ['.wp-block-duplicate-buffered{}'],
    ['duplicate-buffered-first', 'duplicate-buffered-second']
);
$duplicateBoundaryBufferedBuffer = $duplicateBoundaryBufferedRawMap->toBuffer();
$duplicateBoundaryBufferedRestored = SourceMap::fromBuffer('/', $duplicateBoundaryBufferedBuffer);
$duplicateBoundaryBufferedReadOrder = array_column($duplicateBoundaryBufferedRestored->getMappings(), 'generatedColumn');
$duplicateBoundaryBufferedRestored->offsetColumns(0, 0, 5);

$duplicateBoundaryNestedParent = new SourceMap();
$duplicateBoundaryNestedParentSource = $duplicateBoundaryNestedParent->addSource('wp-content/themes/example/source-map-duplicate-buffered-parent.css');
$duplicateBoundaryNestedParent->setSourceContent($duplicateBoundaryNestedParentSource, ".wp-block-duplicate-buffered-parent{}\n");
$duplicateBoundaryNestedParent->addMapping(0, 0, $duplicateBoundaryNestedParentSource, 0, 0, 'duplicate-buffered-parent-top');
$duplicateBoundaryNestedParent->addMapping(1, 4, $duplicateBoundaryNestedParentSource, 1, 2, 'duplicate-buffered-parent-replaced');
$duplicateBoundaryNestedChild = SourceMap::fromBuffer('/', $duplicateBoundaryBufferedBuffer);
$duplicateBoundaryNestedParent->addSourceMap($duplicateBoundaryNestedChild, 1);
$duplicateBoundaryNestedReadOrderBeforeOffset = array_column(array_slice($duplicateBoundaryNestedParent->getMappings(), 1), 'generatedColumn');
$duplicateBoundaryNestedParent->offsetColumns(1, 0, 3);
$duplicateBoundaryNestedChildConsumed = $duplicateBoundaryNestedChild->toJson(null, false);

$duplicateBoundaryNegativeMap = SourceMap::fromJson(
    '{"version":3,"mappings":"AAAAA,EACAC,AACAC,GACAC","sources":["wp-content/themes/example/source-map-duplicate-boundary.css"],"sourcesContent":[".wp-block-duplicate-boundary{}"],"names":["first-boundary-rule","start-boundary-a","start-boundary-b","shifted-boundary-rule"]}'
);
$duplicateBoundaryNegativeMap->offsetColumns(0, 5, -3);
$duplicateBoundaryNegativeClosest = $duplicateBoundaryNegativeMap->findClosestMapping(0, 2);

$duplicateShiftBoundaryNegativeMap = SourceMap::fromJson(
    '{"version":3,"mappings":"AAAAA,IACAC,CACAC,AACAC,KACAC","sources":["wp-content/themes/example/source-map-duplicate-shift-boundary.css"],"sourcesContent":[".wp-block-duplicate-shift{}\n"],"names":["anchor-rule","drained-window-rule","drained-first-boundary","shifted-second-boundary","after-shift-boundary"]}'
);
$duplicateShiftBoundaryNegativeReadOrder = array_column($duplicateShiftBoundaryNegativeMap->getMappings(), 'generatedColumn');
$duplicateShiftBoundaryNegativeMap->offsetColumns(0, 5, -3);
$duplicateShiftBoundaryNegativeClosest = $duplicateShiftBoundaryNegativeMap->findClosestMapping(0, 5);
$duplicateShiftBoundaryNegativeRoundTrip = SourceMap::fromBuffer('/', $duplicateShiftBoundaryNegativeMap->toBuffer());

$trailingNegativeWindowMap = new SourceMap();
$trailingNegativeWindowSource = $trailingNegativeWindowMap->addSource('wp-content/themes/example/source-map-trailing-window.css');
$trailingNegativeWindowMap->setSourceContent($trailingNegativeWindowSource, '.wp-block-trailing-window{}');
$trailingNegativeWindowMap->addMapping(0, 0, $trailingNegativeWindowSource, 0, 0, 'trailing-window-first');
$trailingNegativeWindowMap->addMapping(0, 10, $trailingNegativeWindowSource, 1, 0, 'trailing-window-middle');
$trailingNegativeWindowMap->addMapping(0, 20, $trailingNegativeWindowSource, 2, 0, 'trailing-window-drained');
$trailingNegativeWindowMap->offsetColumns(0, 30, -15);
$trailingNegativeWindowColumns = array_column(SourceMap::decodeVlq($trailingNegativeWindowMap->writeVlq()), 'generatedColumn');
$trailingNegativeWindowNames = $trailingNegativeWindowMap->getNames();
$trailingNegativeWindowBeforeNoop = $trailingNegativeWindowMap->toJson(null, false);
$trailingNegativeWindowMap->offsetColumns(0, 100, 7);
$trailingNegativeWindowNoop = $trailingNegativeWindowBeforeNoop === $trailingNegativeWindowMap->toJson(null, false);

$unsortedTrailingNegativeWindowMap = new SourceMap();
$unsortedTrailingNegativeWindowMap->addVlqMap(
    'UAAAA,RACAC',
    ['wp-content/themes/example/source-map-unsorted-trailing-window.css'],
    ['.wp-block-unsorted-trailing-window{}'],
    ['later-trailing-window-rule', 'earlier-trailing-window-rule']
);
$unsortedTrailingNegativeWindowReadOrder = array_column($unsortedTrailingNegativeWindowMap->getMappings(), 'generatedColumn');
$unsortedTrailingNegativeWindowMap->offsetColumns(0, 20, -15);
$unsortedTrailingNegativeWindowColumns = array_column(SourceMap::decodeVlq($unsortedTrailingNegativeWindowMap->writeVlq()), 'generatedColumn');
$unsortedTrailingNegativeWindowClosest = $unsortedTrailingNegativeWindowMap->findClosestMapping(0, 20);

$unsortedRawVlqMap = new SourceMap();
$unsortedRawVlqMap->addVlqMap(
    'UAAAA,RACAC',
    ['wp-content/themes/example/source-map-unsorted-columns.css'],
    ['.wp-block-unsorted-columns{}'],
    ['later-rule', 'earlier-rule']
);

$unsortedRawVlqPositiveOffsetMap = new SourceMap();
$unsortedRawVlqPositiveOffsetMap->addVlqMap(
    'UAAAA,RACAC',
    ['wp-content/themes/example/source-map-unsorted-columns.css'],
    ['.wp-block-unsorted-columns{}'],
    ['later-rule', 'earlier-rule']
);
$unsortedRawVlqPositiveOffsetMap->offsetColumns(0, 5, 3);

$unsortedRawVlqNegativeOffsetMap = new SourceMap();
$unsortedRawVlqNegativeOffsetMap->addVlqMap(
    'UAAAA,RACAC',
    ['wp-content/themes/example/source-map-unsorted-columns.css'],
    ['.wp-block-unsorted-columns{}'],
    ['later-rule', 'earlier-rule']
);
$unsortedRawVlqNegativeOffsetMap->offsetColumns(0, 10, -8);

$unsortedRawVlqSortSideEffectMap = new SourceMap();
$unsortedRawVlqSortSideEffectMap->addVlqMap(
    'UAAAA,RACAC',
    ['wp-content/themes/example/source-map-unsorted-columns.css'],
    ['.wp-block-unsorted-columns{}'],
    ['later-rule', 'earlier-rule']
);
$unsortedRawVlqReadOrderBeforeWrite = array_column($unsortedRawVlqSortSideEffectMap->getMappings(), 'generatedColumn');
$unsortedRawVlqSortSideEffectMap->writeVlq();
$unsortedRawVlqReadOrderAfterWrite = array_column($unsortedRawVlqSortSideEffectMap->getMappings(), 'generatedColumn');

$unsortedRawVlqZeroOffsetMap = new SourceMap();
$unsortedRawVlqZeroOffsetMap->addVlqMap(
    'UAAAA,RACAC',
    ['wp-content/themes/example/source-map-unsorted-columns.css'],
    ['.wp-block-unsorted-columns{}'],
    ['later-rule', 'earlier-rule']
);
$unsortedRawVlqZeroOffsetMap->offsetColumns(0, 0, 0);
$unsortedRawVlqZeroOffsetOrder = array_column($unsortedRawVlqZeroOffsetMap->getMappings(), 'generatedColumn');

$unsortedRawVlqLookupMap = new SourceMap();
$unsortedRawVlqLookupMap->addVlqMap(
    'UAAAA,RACAC',
    ['wp-content/themes/example/source-map-unsorted-columns.css'],
    ['.wp-block-unsorted-columns{}'],
    ['later-rule', 'earlier-rule']
);
$unsortedRawVlqLookupClosest = $unsortedRawVlqLookupMap->findClosestMapping(0, 8);
$unsortedRawVlqLookupOrder = array_column($unsortedRawVlqLookupMap->getMappings(), 'generatedColumn');

$nestedUnsortedVlqParent = new SourceMap();
$nestedUnsortedVlqChild = new SourceMap();
$nestedUnsortedVlqChild->addVlqMap(
    'UAAAA,RACAC',
    ['wp-content/themes/example/source-map-nested-unsorted.css'],
    ['.wp-block-nested-unsorted{}'],
    ['later-rule', 'earlier-rule']
);
$nestedUnsortedVlqParent->addSourceMap($nestedUnsortedVlqChild, 2);
$nestedUnsortedVlqReadOrderBeforeWrite = array_column($nestedUnsortedVlqParent->getMappings(), 'generatedColumn');
$nestedUnsortedVlqMap = $nestedUnsortedVlqParent->toJson(null, false);
$nestedUnsortedVlqReadOrderAfterWrite = array_column($nestedUnsortedVlqParent->getMappings(), 'generatedColumn');
$nestedUnsortedVlqChildConsumed = $nestedUnsortedVlqChild->toJson(null, false);

$lineOffsetUnsortedVlqMap = new SourceMap();
$lineOffsetUnsortedVlqMap->addVlqMap(
    'UAAAA,RACAC',
    ['wp-content/themes/example/source-map-line-offset-unsorted.css'],
    ['.wp-block-line-offset-unsorted{}'],
    ['later-line-offset-rule', 'earlier-line-offset-rule']
);
$lineOffsetUnsortedVlqMap->offsetLines(0, 2);
$lineOffsetUnsortedVlqReadOrderBeforeWrite = array_column($lineOffsetUnsortedVlqMap->getMappings(), 'generatedColumn');
$lineOffsetUnsortedVlqJson = $lineOffsetUnsortedVlqMap->toJson(null, false);
$lineOffsetUnsortedVlqReadOrderAfterWrite = array_column($lineOffsetUnsortedVlqMap->getMappings(), 'generatedColumn');

$middleInsertLineOffsetMap = new SourceMap();
$middleInsertLineOffsetEntry = $middleInsertLineOffsetMap->addSource('wp-content/themes/example/source-map-middle-insert-entry.css');
$middleInsertLineOffsetMap->setSourceContent($middleInsertLineOffsetEntry, ".wp-block-middle-insert-entry{}\n");
$middleInsertLineOffsetMap->addMapping(0, 0, $middleInsertLineOffsetEntry, 0, 0, 'middle-insert-entry-rule');
$middleInsertLineOffsetMap->addVlqMap(
    ';UAAAA,RACAC;AAEAC',
    ['wp-content/themes/example/source-map-middle-insert.css'],
    [".wp-block-middle-insert-later{}\n.wp-block-middle-insert-earlier{}\n.wp-block-middle-insert-after{}\n"],
    ['middle-insert-later-rule', 'middle-insert-earlier-rule', 'middle-insert-after-rule']
);
$middleInsertLineOffsetMap->offsetLines(1, 2);
$middleInsertLineOffsetReadOrderBeforeWrite = array_column($middleInsertLineOffsetMap->getMappings(), 'generatedColumn');
$middleInsertLineOffsetJson = $middleInsertLineOffsetMap->toJson(null, false);
$middleInsertLineOffsetReadOrderAfterWrite = array_column($middleInsertLineOffsetMap->getMappings(), 'generatedColumn');
$middleInsertLineOffsetClosest = $middleInsertLineOffsetMap->findClosestMapping(3, 8);
$middleInsertLineOffsetRoundTrip = SourceMap::fromBuffer('/', $middleInsertLineOffsetMap->toBuffer());

$negativeLineOffsetUnsortedVlqMap = new SourceMap();
$negativeLineOffsetUnsortedVlqMap->addVlqMap(
    'UAAAA,RACAC',
    ['wp-content/themes/example/source-map-negative-line-offset-unsorted.css'],
    ['.wp-block-negative-line-offset-unsorted{}'],
    ['later-negative-line-offset-rule', 'earlier-negative-line-offset-rule']
);
$negativeLineOffsetUnsortedVlqMap->offsetLines(0, 2);
$negativeLineOffsetUnsortedVlqMap->offsetLines(2, -1);
$negativeLineOffsetUnsortedVlqReadOrderBeforeLookup = array_column($negativeLineOffsetUnsortedVlqMap->getMappings(), 'generatedColumn');
$negativeLineOffsetUnsortedVlqClosest = $negativeLineOffsetUnsortedVlqMap->findClosestMapping(1, 8);
$negativeLineOffsetUnsortedVlqJson = $negativeLineOffsetUnsortedVlqMap->toJson(null, false);

$negativeLineSpliceUnsortedVlqMap = new SourceMap();
$negativeLineSpliceRemovedSource = $negativeLineSpliceUnsortedVlqMap->addSource('wp-content/themes/example/source-map-line-splice-removed.css');
$negativeLineSpliceUnsortedVlqMap->setSourceContent($negativeLineSpliceRemovedSource, ".wp-block-line-splice-removed{}\n");
$negativeLineSpliceUnsortedVlqMap->addMapping(0, 0, $negativeLineSpliceRemovedSource, 0, 0, 'removed-line-splice-rule');
$negativeLineSpliceUnsortedVlqMap->addVlqMap(
    ';UACAC,RADAD;AAGAE',
    ['wp-content/themes/example/source-map-line-splice-unsorted.css'],
    [".wp-block-line-splice-unsorted{}\n"],
    ['later-line-splice-rule', 'earlier-line-splice-rule', 'after-line-splice-rule']
);
$negativeLineSpliceUnsortedVlqMap->offsetLines(1, -1);
$negativeLineSpliceUnsortedVlqReadOrderBeforeWrite = array_column($negativeLineSpliceUnsortedVlqMap->getMappings(), 'generatedColumn');
$negativeLineSpliceUnsortedVlqJson = $negativeLineSpliceUnsortedVlqMap->toJson(null, false);
$negativeLineSpliceUnsortedVlqReadOrderAfterWrite = array_column($negativeLineSpliceUnsortedVlqMap->getMappings(), 'generatedColumn');
$negativeLineSpliceUnsortedVlqClosest = $negativeLineSpliceUnsortedVlqMap->findClosestMapping(0, 8);

$bufferedUnsortedRawVlqMap = new SourceMap();
$bufferedUnsortedRawVlqMap->addVlqMap(
    'UAAAA,RACAC',
    ['wp-content/themes/example/source-map-buffered-unsorted.css'],
    ['.wp-block-buffered-unsorted{}'],
    ['later-buffered-rule', 'earlier-buffered-rule']
);
$bufferedUnsortedRawVlqBuffer = $bufferedUnsortedRawVlqMap->toBuffer();
$bufferedUnsortedRawVlqRestored = SourceMap::fromBuffer('/', $bufferedUnsortedRawVlqBuffer);
$bufferedUnsortedRawVlqReadOrderBeforeWrite = array_column($bufferedUnsortedRawVlqRestored->getMappings(), 'generatedColumn');
$bufferedUnsortedRawVlqJson = $bufferedUnsortedRawVlqRestored->toJson(null, false);
$bufferedUnsortedRawVlqReadOrderAfterWrite = array_column($bufferedUnsortedRawVlqRestored->getMappings(), 'generatedColumn');

$bufferedUnsortedRawVlqPositiveOffsetMap = SourceMap::fromBuffer('/', $bufferedUnsortedRawVlqBuffer);
$bufferedUnsortedRawVlqPositiveOffsetMap->offsetColumns(0, 5, 3);

$bufferedUnsortedRawVlqNegativeOffsetMap = SourceMap::fromBuffer('/', $bufferedUnsortedRawVlqBuffer);
$bufferedUnsortedRawVlqNegativeOffsetMap->offsetColumns(0, 10, -8);

$bufferedUnsortedChildParent = new SourceMap();
$bufferedUnsortedChildParentSource = $bufferedUnsortedChildParent->addSource('wp-content/themes/example/source-map-buffered-parent.css');
$bufferedUnsortedChildParent->setSourceContent($bufferedUnsortedChildParentSource, ".wp-block-buffered-parent{}\n");
$bufferedUnsortedChildParent->addMapping(2, 0, $bufferedUnsortedChildParentSource, 0, 0, 'buffered-parent-rule');
$bufferedUnsortedChild = SourceMap::fromBuffer('/', $bufferedUnsortedRawVlqBuffer);
$bufferedUnsortedChildParent->addSourceMap($bufferedUnsortedChild, 2);
$bufferedUnsortedChildReadOrderBeforeWrite = array_column($bufferedUnsortedChildParent->getMappings(), 'generatedColumn');
$bufferedUnsortedChildParentMap = $bufferedUnsortedChildParent->toJson(null, false);
$bufferedUnsortedChildConsumed = $bufferedUnsortedChild->toJson(null, false);

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

$afterLastGeneratedOnlyInputMap = SourceMap::fromJson(
    '{"version":3,"mappings":"A,KAAA","sources":["wp-content/themes/example/source-map-after-last.scss"],"sourcesContent":[".wp-block-after-last{}"],"names":[]}'
);
$afterLastGeneratedOnlyClosest = $afterLastGeneratedOnlyInputMap->findClosestMapping(0, 99);
$afterLastGeneratedOnlyExact = $afterLastGeneratedOnlyInputMap->findClosestMapping(0, 5);
$afterLastGeneratedOnlyInputMap->offsetColumns(0, 5, 3);
$afterLastGeneratedOnlyOffsetClosest = $afterLastGeneratedOnlyInputMap->findClosestMapping(0, 99);
$afterLastGeneratedOnlyOffsetExact = $afterLastGeneratedOnlyInputMap->findClosestMapping(0, 8);
$afterLastGeneratedOnlyExtendedMap = new SourceMap();
$afterLastGeneratedOnlyCompiled = $afterLastGeneratedOnlyExtendedMap->addSource('wp-content/cache/source-map-after-last.css');
$afterLastGeneratedOnlyExtendedMap->setSourceContent($afterLastGeneratedOnlyCompiled, '.wp-block-after-last{color:red}');
$afterLastGeneratedOnlyExtendedMap->addMapping(0, 0, $afterLastGeneratedOnlyCompiled, 0, 99, 'compiled-after-last');
$afterLastGeneratedOnlyExtendedMap->extendWithSourceMap(SourceMap::fromJson(
    '{"version":3,"mappings":"A,KAAA","sources":["wp-content/themes/example/source-map-after-last.scss"],"sourcesContent":[".wp-block-after-last{}"],"names":[]}'
));

$beforeFirstInputMap = SourceMap::fromJson(
    '{"version":3,"mappings":"UAECA,UACCC","sources":["wp-content/themes/example/source-map-before-first.scss"],"sourcesContent":[".wp-block-before{}\n.wp-block-after{}\n"],"names":["before-first-rule","before-second-rule"]}'
);
$beforeFirstClosest = $beforeFirstInputMap->findClosestMapping(0, 5);
$beforeFirstInputMap->offsetColumns(0, 10, 4);
$beforeFirstOffsetClosest = $beforeFirstInputMap->findClosestMapping(0, 13);
$beforeFirstOffsetExact = $beforeFirstInputMap->findClosestMapping(0, 14);
$beforeFirstExtendedMap = new SourceMap();
$beforeFirstCompiled = $beforeFirstExtendedMap->addSource('wp-content/cache/source-map-before-first.css');
$beforeFirstExtendedMap->setSourceContent($beforeFirstCompiled, '.wp-block-before{color:red}.wp-block-after{color:blue}');
$beforeFirstExtendedMap->addMapping(0, 0, $beforeFirstCompiled, 0, 13, 'compiled-before-first');
$beforeFirstExtendedMap->extendWithSourceMap($beforeFirstInputMap);

$maxUnsignedVlqDecode = SourceMap::decodeVlq('+/////H')[0]['generatedColumn'];
$shiftedColumnOverflowMap = new SourceMap();
$shiftedColumnOverflowSource = $shiftedColumnOverflowMap->addSource('wp-content/themes/example/source-map-shifted-column-overflow.css');
$shiftedColumnOverflowMap->setSourceContent($shiftedColumnOverflowSource, ".wp-block-shifted-column-overflow{}\n");
$shiftedColumnOverflowMap->addMapping(0, 2, $shiftedColumnOverflowSource, 0, 0, 'safe-shifted-column');
$shiftedColumnOverflowMap->addMapping(0, 4294967295, $shiftedColumnOverflowSource, 1, 0, 'max-shifted-column');
$shiftedColumnOverflowBeforeGuard = $shiftedColumnOverflowMap->toJson(null, false);
$shiftedColumnOverflowGuard = false;
try {
    $shiftedColumnOverflowMap->offsetColumns(0, 0, 1);
} catch (InvalidArgumentException) {
    $shiftedColumnOverflowGuard = true;
}

$unsortedColumnOverflowMap = new SourceMap();
$unsortedColumnOverflowMap->addVlqMap(
    'UAAAA,RACAC',
    ['wp-content/themes/example/source-map-unsorted-overflow.css'],
    ['.wp-block-unsorted-overflow{}'],
    ['later-overflow-rule', 'earlier-overflow-rule']
);
$unsortedColumnOverflowBeforeGuard = array_column($unsortedColumnOverflowMap->getMappings(), 'generatedColumn');
$unsortedColumnOverflowGuard = false;
try {
    $unsortedColumnOverflowMap->offsetColumns(0, 4294967295, 1);
} catch (InvalidArgumentException) {
    $unsortedColumnOverflowGuard = true;
}
$unsortedColumnOverflowAfterGuard = array_column($unsortedColumnOverflowMap->getMappings(), 'generatedColumn');

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

$invalidVlqVectorGuard = true;
foreach ([
    static fn (): SourceMap => SourceMap::fromJson('{"version":3,"mappings":"AAAA","sources":{"source":"wp-content/themes/example/style.css"},"names":[]}'),
    static fn (): SourceMap => SourceMap::fromJson('{"version":3,"mappings":"AAAA","sources":["wp-content/themes/example/style.css"],"sourcesContent":{"content":".theme{}"},"names":[]}'),
    static fn (): SourceMap => SourceMap::fromJson('{"version":3,"mappings":"AAAAA","sources":["wp-content/themes/example/style.css"],"sourcesContent":[".theme{}"],"names":{"name":"theme-rule"}}'),
    static function (): SourceMap {
        $map = new SourceMap();
        $map->addVlqMap('AAAAA', ['source' => 'wp-content/themes/example/style.css'], ['.theme{}'], []);

        return $map;
    },
    static function (): SourceMap {
        $map = new SourceMap();
        $map->addVlqMap('AAAAA', ['wp-content/themes/example/style.css'], ['content' => '.theme{}'], []);

        return $map;
    },
    static function (): SourceMap {
        $map = new SourceMap();
        $map->addVlqMap('AAAAA', ['wp-content/themes/example/style.css'], ['.theme{}'], ['name' => 'theme-rule']);

        return $map;
    },
] as $invalidVlqVector) {
    try {
        $invalidVlqVector();
        $invalidVlqVectorGuard = false;
        break;
    } catch (InvalidArgumentException) {
        continue;
    }
}

$missingVlqVectorGuard = true;
foreach ([
    static fn (): SourceMap => SourceMap::fromJson('{"version":3,"mappings":"A","names":[]}'),
    static fn (): SourceMap => SourceMap::fromJson('{"version":3,"mappings":"A","sources":[]}'),
    static fn (): SourceMap => SourceMap::fromArray(['mappings' => 'A', 'names' => []]),
    static fn (): SourceMap => SourceMap::fromArray(['mappings' => 'A', 'sources' => []]),
    static function (): SourceMap {
        $map = new SourceMap();
        $map->addVlqMap('A', ['wp-content/themes/example/style.css'], [null], []);

        return $map;
    },
] as $missingVlqVector) {
    try {
        $missingVlqVector();
        $missingVlqVectorGuard = false;
        break;
    } catch (InvalidArgumentException) {
        continue;
    }
}

$nullSourcesContentVectorGuard = true;
foreach ([
    static fn (): SourceMap => SourceMap::fromJson('{"version":3,"mappings":"A","sources":[],"sourcesContent":null,"names":[]}'),
    static fn (): SourceMap => SourceMap::fromArray(['version' => 3, 'mappings' => 'A', 'sources' => [], 'sourcesContent' => null, 'names' => []]),
] as $nullSourcesContentVector) {
    try {
        $nullSourcesContentVector();
        $nullSourcesContentVectorGuard = false;
        break;
    } catch (InvalidArgumentException) {
        continue;
    }
}

$ignoredVersionMaps = [];
foreach ([
    static fn (): SourceMap => SourceMap::fromJson('{"mappings":";C","sources":[],"names":[]}'),
    static fn (): SourceMap => SourceMap::fromJson('{"version":"3","mappings":";C","sources":[],"names":[]}'),
    static fn (): SourceMap => SourceMap::fromJson('{"version":300,"mappings":";C","sources":[],"names":[]}'),
    static fn (): SourceMap => SourceMap::fromArray(['mappings' => ';C', 'sources' => [], 'names' => []]),
    static fn (): SourceMap => SourceMap::fromArray(['version' => '3', 'mappings' => ';C', 'sources' => [], 'names' => []]),
    static fn (): SourceMap => SourceMap::fromArray(['version' => 300, 'mappings' => ';C', 'sources' => [], 'names' => []]),
] as $ignoredVersionMap) {
    $ignoredVersionMaps[] = $ignoredVersionMap()->writeVlq();
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
    'sourcesContent' => $projectRootMap->getSourcesContent(),
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
    'skippedNestedSourceTableMap' => $skippedNestedParent->toJson(null, false),
    'skippedNestedChildConsumed' => $skippedNestedChildConsumed,
    'partialSkippedNestedSourceTableMap' => $partialSkippedNestedParent->toJson(null, false),
    'partialSkippedNestedChildConsumed' => $partialSkippedNestedChildConsumed,
    'generatedOnlyChildOffsetMap' => $generatedOnlyChildOffsetParent->toJson(null, false),
    'generatedOnlyChildOffsetChildConsumed' => $generatedOnlyChildOffsetChildConsumed,
    'generatedOnlyChildOffsetClosest' => $generatedOnlyChildOffsetClosest,
    'emptyMap' => $themeJsonMap->toJson(null, false),
    'carriageReturnEmptyMap' => $carriageReturnEmptyMap->toJson(null, false),
    'lineSpanMap' => $inlineEditorMap->toJson(null, false),
    'emptyLineColumnOffsetMap' => $emptyLineOffsetMap->toJson(null, false),
    'emptyLineColumnOffsetNoop' => $emptyLineColumnOffsetNoop,
    'emptyLineColumnOverflowGuard' => $emptyLineColumnOverflowGuard && $emptyLineOffsetBeforeColumnNoop === $emptyLineOffsetMap->toJson(null, false),
    'bufferRoundTripMap' => $bufferRoundTripMap->toJson(null, false),
    'negativeLinePastSpanGuard' => $negativeLinePastSpanGuard && $negativeLinePastSpanBeforeGuard === $negativeLinePastSpanMap->toJson(null, false),
    'farLineOffsetMap' => $farLineOffsetMap->toJson(null, false),
    'lineStartOffsetInsertedMap' => $lineStartOffsetInsertedMap,
    'lineStartOffsetMap' => $lineStartOffsetMap->toJson(null, false),
    'drainedEmptySpanBeforeDrain' => $drainedEmptySpanBeforeDrain,
    'drainedEmptySpanMap' => $drainedEmptySpanMap->toJson(null, false),
    'drainedEmptySpanRoundTrip' => $drainedEmptySpanRoundTrip->toJson(null, false),
    'columnDrainedEmptySpanMap' => $columnDrainedEmptySpanMap->toJson(null, false),
    'columnDrainedEmptySpanRoundTrip' => $columnDrainedEmptySpanRoundTrip->toJson(null, false),
    'columnDrainedEmptySpanClosest' => $columnDrainedEmptySpanClosest,
    'columnPrefixDrainMap' => $columnPrefixDrainMap->toJson(null, false),
    'middleColumnDrainedSpanMap' => $middleColumnDrainedSpanMap->toJson(null, false),
    'middleColumnDrainedSpanRoundTrip' => $middleColumnDrainedSpanRoundTrip->toJson(null, false),
    'middleColumnDrainedSpanClosest' => $middleColumnDrainedSpanClosest,
    'mergedColumnDrainedChildBeforeMerge' => $mergedColumnDrainedChildBeforeMerge,
    'mergedColumnDrainedChildParentMap' => $mergedColumnDrainedChildParent->toJson(null, false),
    'mergedColumnDrainedChildConsumed' => $mergedColumnDrainedChildConsumed,
    'mergedColumnDrainedChildClosest' => $mergedColumnDrainedChildClosest,
    'bufferedNegativeColumnDrainedChildBeforeMerge' => $bufferedNegativeColumnDrainedChildBeforeMerge,
    'bufferedNegativeColumnDrainedParentMap' => $bufferedNegativeColumnDrainedParent->toJson(null, false),
    'bufferedNegativeColumnDrainedRestoredChildConsumed' => $bufferedNegativeColumnDrainedRestoredChildConsumed,
    'bufferedNegativeColumnDrainedClosest' => $bufferedNegativeColumnDrainedClosest,
    'rawLeadingSemicolonOffsetMap' => $rawLeadingSemicolonOffsetMap->toJson(null, false),
    'negativeChildLineOffsetMap' => $negativeChildLineOffsetParent->toJson(null, false),
    'negativeChildLineOffsetChildConsumed' => $negativeChildLineOffsetChildConsumed,
    'leadingEmptyChildOffsetMap' => $leadingEmptyChildOffsetParent->toJson(null, false),
    'leadingEmptyChildOffsetChildConsumed' => $leadingEmptyChildOffsetChildConsumed,
    'negativeLeadingEmptyChildOffsetBeforeMerge' => $negativeLeadingEmptyChildOffsetChildBeforeMerge,
    'negativeLeadingEmptyChildOffsetMap' => $negativeLeadingEmptyChildOffsetParent->toJson(null, false),
    'negativeLeadingEmptyChildOffsetChildConsumed' => $negativeLeadingEmptyChildOffsetChildConsumed,
    'negativeLeadingEmptyChildOffsetClearedClosest' => $negativeLeadingEmptyChildOffsetClearedClosest,
    'negativeLeadingEmptyChildOffsetKeptClosest' => $negativeLeadingEmptyChildOffsetKeptClosest,
    'generatedOnlyOffsetMap' => $generatedOnlyOffsetMap->toJson(null, false),
    'mappingRecordOffsetMap' => $mappingRecordOffsetMap->toJson(null, false),
    'dedupedRawVlqOffsetMap' => $dedupedRawVlqOffsetMap->toJson(null, false),
    'extendedInputMap' => $generatedThemeJsonMap->toJson(null, false),
    'projectRootMap' => $projectRootMap->toJson(null, false),
    'dataUrl' => $dataUrl,
    'dataUrlRoundTrip' => $dataUrlRoundTrip->toJson('/'),
    'negativeOffsetRawMap' => $negativeOffsetRawMap->toJson(null, false),
    'skippedSameLineRawVlqMap' => $skippedSameLineRawVlqMap->toJson(null, false),
    'skippedGeneratedOnlyRawVlqMap' => $skippedGeneratedOnlyRawVlqMap->toJson(null, false),
    'fullySkippedRawVlqMap' => $fullySkippedRawVlqMap->toJson(null, false),
    'separatorOnlyRawVlqParent' => $separatorOnlyRawVlqParent->toJson(null, false),
    'separatorOnlyRawVlqChildConsumed' => $separatorOnlyRawVlqChildConsumed,
    'skippedInvalidSourceIndexGuard' => $skippedInvalidSourceIndexGuard,
    'skippedInvalidSourceIndexMap' => $skippedInvalidSourceIndexMap->toJson(null, false),
    'skippedInvalidNameIndexGuard' => $skippedInvalidNameIndexGuard,
    'skippedInvalidNameIndexMap' => $skippedInvalidNameIndexMap->toJson(null, false),
    'partialInvalidSourceIndexGuard' => $partialInvalidSourceIndexGuard,
    'partialInvalidSourceIndexMap' => $partialInvalidSourceIndexMap->toJson(null, false),
    'partialInvalidNameIndexGuard' => $partialInvalidNameIndexGuard,
    'partialInvalidNameIndexMap' => $partialInvalidNameIndexMap->toJson(null, false),
    'negativeColumnGeneratedOnlyMap' => $negativeColumnGeneratedOnlyMap->toJson(null, false),
    'negativeColumnSourceBackedMap' => $negativeColumnSourceBackedMap->toJson(null, false),
    'negativeColumnSkippedLineMap' => $negativeColumnSkippedLineMap->toJson(null, false),
    'negativeColumnOffsetGuard' => $negativeColumnOffsetGuard,
    'streamingRawVlqMap' => $streamingRawVlqMap->toJson(null, false),
    'duplicateColumnPositiveMap' => $duplicateColumnPositiveMap->toJson(null, false),
    'duplicateColumnNegativeMap' => $duplicateColumnNegativeMap->toJson(null, false),
    'tripleDuplicateExact' => $tripleDuplicateExact,
    'tripleDuplicateAfterDuplicates' => $tripleDuplicateAfterDuplicates,
    'tripleDuplicatePositiveMap' => $tripleDuplicatePositiveMap->toJson(null, false),
    'tripleDuplicateNegativeMap' => $tripleDuplicateNegativeMap->toJson(null, false),
    'tripleDuplicateNegativeClosest' => $tripleDuplicateNegativeClosest,
    'duplicateBoundaryBufferedReadOrder' => $duplicateBoundaryBufferedReadOrder,
    'duplicateBoundaryBufferedOffsetMap' => $duplicateBoundaryBufferedRestored->toJson(null, false),
    'duplicateBoundaryNestedReadOrderBeforeOffset' => $duplicateBoundaryNestedReadOrderBeforeOffset,
    'duplicateBoundaryNestedOffsetMap' => $duplicateBoundaryNestedParent->toJson(null, false),
    'duplicateBoundaryNestedChildConsumed' => $duplicateBoundaryNestedChildConsumed,
    'duplicateBoundaryNegativeMap' => $duplicateBoundaryNegativeMap->toJson(null, false),
    'duplicateBoundaryNegativeClosest' => $duplicateBoundaryNegativeClosest,
    'duplicateShiftBoundaryNegativeReadOrder' => $duplicateShiftBoundaryNegativeReadOrder,
    'duplicateShiftBoundaryNegativeMap' => $duplicateShiftBoundaryNegativeMap->toJson(null, false),
    'duplicateShiftBoundaryNegativeClosest' => $duplicateShiftBoundaryNegativeClosest,
    'duplicateShiftBoundaryNegativeRoundTrip' => $duplicateShiftBoundaryNegativeRoundTrip->toJson(null, false),
    'trailingNegativeWindowMap' => $trailingNegativeWindowMap->toJson(null, false),
    'trailingNegativeWindowColumns' => $trailingNegativeWindowColumns,
    'trailingNegativeWindowNames' => $trailingNegativeWindowNames,
    'trailingNegativeWindowNoop' => $trailingNegativeWindowNoop,
    'unsortedTrailingNegativeWindowReadOrder' => $unsortedTrailingNegativeWindowReadOrder,
    'unsortedTrailingNegativeWindowMap' => $unsortedTrailingNegativeWindowMap->toJson(null, false),
    'unsortedTrailingNegativeWindowColumns' => $unsortedTrailingNegativeWindowColumns,
    'unsortedTrailingNegativeWindowClosest' => $unsortedTrailingNegativeWindowClosest,
    'unsortedRawVlqMap' => $unsortedRawVlqMap->toJson(null, false),
    'unsortedRawVlqPositiveOffsetMap' => $unsortedRawVlqPositiveOffsetMap->toJson(null, false),
    'unsortedRawVlqNegativeOffsetMap' => $unsortedRawVlqNegativeOffsetMap->toJson(null, false),
    'unsortedRawVlqReadOrderBeforeWrite' => $unsortedRawVlqReadOrderBeforeWrite,
    'unsortedRawVlqReadOrderAfterWrite' => $unsortedRawVlqReadOrderAfterWrite,
    'unsortedRawVlqZeroOffsetOrder' => $unsortedRawVlqZeroOffsetOrder,
    'unsortedRawVlqLookupClosest' => $unsortedRawVlqLookupClosest,
    'unsortedRawVlqLookupOrder' => $unsortedRawVlqLookupOrder,
    'nestedUnsortedVlqReadOrderBeforeWrite' => $nestedUnsortedVlqReadOrderBeforeWrite,
    'nestedUnsortedVlqMap' => $nestedUnsortedVlqMap,
    'nestedUnsortedVlqReadOrderAfterWrite' => $nestedUnsortedVlqReadOrderAfterWrite,
    'nestedUnsortedVlqChildConsumed' => $nestedUnsortedVlqChildConsumed,
    'lineOffsetUnsortedVlqReadOrderBeforeWrite' => $lineOffsetUnsortedVlqReadOrderBeforeWrite,
    'lineOffsetUnsortedVlqMap' => $lineOffsetUnsortedVlqJson,
    'lineOffsetUnsortedVlqReadOrderAfterWrite' => $lineOffsetUnsortedVlqReadOrderAfterWrite,
    'middleInsertLineOffsetReadOrderBeforeWrite' => $middleInsertLineOffsetReadOrderBeforeWrite,
    'middleInsertLineOffsetMap' => $middleInsertLineOffsetJson,
    'middleInsertLineOffsetReadOrderAfterWrite' => $middleInsertLineOffsetReadOrderAfterWrite,
    'middleInsertLineOffsetClosest' => $middleInsertLineOffsetClosest,
    'middleInsertLineOffsetRoundTrip' => $middleInsertLineOffsetRoundTrip->toJson(null, false),
    'negativeLineOffsetUnsortedVlqReadOrderBeforeLookup' => $negativeLineOffsetUnsortedVlqReadOrderBeforeLookup,
    'negativeLineOffsetUnsortedVlqClosest' => $negativeLineOffsetUnsortedVlqClosest,
    'negativeLineOffsetUnsortedVlqMap' => $negativeLineOffsetUnsortedVlqJson,
    'negativeLineSpliceUnsortedVlqReadOrderBeforeWrite' => $negativeLineSpliceUnsortedVlqReadOrderBeforeWrite,
    'negativeLineSpliceUnsortedVlqMap' => $negativeLineSpliceUnsortedVlqJson,
    'negativeLineSpliceUnsortedVlqReadOrderAfterWrite' => $negativeLineSpliceUnsortedVlqReadOrderAfterWrite,
    'negativeLineSpliceUnsortedVlqClosest' => $negativeLineSpliceUnsortedVlqClosest,
    'bufferedUnsortedRawVlqReadOrderBeforeWrite' => $bufferedUnsortedRawVlqReadOrderBeforeWrite,
    'bufferedUnsortedRawVlqMap' => $bufferedUnsortedRawVlqJson,
    'bufferedUnsortedRawVlqReadOrderAfterWrite' => $bufferedUnsortedRawVlqReadOrderAfterWrite,
    'bufferedUnsortedRawVlqPositiveOffsetMap' => $bufferedUnsortedRawVlqPositiveOffsetMap->toJson(null, false),
    'bufferedUnsortedRawVlqNegativeOffsetMap' => $bufferedUnsortedRawVlqNegativeOffsetMap->toJson(null, false),
    'bufferedUnsortedChildReadOrderBeforeWrite' => $bufferedUnsortedChildReadOrderBeforeWrite,
    'bufferedUnsortedChildParentMap' => $bufferedUnsortedChildParentMap,
    'bufferedUnsortedChildConsumed' => $bufferedUnsortedChildConsumed,
    'duplicateLookupExact' => $duplicateLookupExact,
    'duplicateLookupAfterLast' => $duplicateLookupAfterLast,
    'duplicateLookupExtendedMap' => $duplicateLookupExtendedMap->toJson(null, false),
    'afterLastGeneratedOnlyClosest' => $afterLastGeneratedOnlyClosest,
    'afterLastGeneratedOnlyExact' => $afterLastGeneratedOnlyExact,
    'afterLastGeneratedOnlyOffsetMap' => $afterLastGeneratedOnlyInputMap->toJson(null, false),
    'afterLastGeneratedOnlyOffsetClosest' => $afterLastGeneratedOnlyOffsetClosest,
    'afterLastGeneratedOnlyOffsetExact' => $afterLastGeneratedOnlyOffsetExact,
    'afterLastGeneratedOnlyExtendedMap' => $afterLastGeneratedOnlyExtendedMap->toJson(null, false),
    'beforeFirstSourceBackedClosest' => $beforeFirstClosest,
    'beforeFirstSourceBackedOffsetMap' => $beforeFirstInputMap->toJson(null, false),
    'beforeFirstSourceBackedOffsetClosest' => $beforeFirstOffsetClosest,
    'beforeFirstSourceBackedOffsetExact' => $beforeFirstOffsetExact,
    'beforeFirstSourceBackedExtendedMap' => $beforeFirstExtendedMap->toJson(null, false),
    'maxUnsignedVlqDecode' => $maxUnsignedVlqDecode,
    'shiftedColumnOverflowGuard' => $shiftedColumnOverflowGuard && $shiftedColumnOverflowBeforeGuard === $shiftedColumnOverflowMap->toJson(null, false),
    'unsortedColumnOverflowBeforeGuard' => $unsortedColumnOverflowBeforeGuard,
    'unsortedColumnOverflowAfterGuard' => $unsortedColumnOverflowAfterGuard,
    'unsortedColumnOverflowGuard' => $unsortedColumnOverflowGuard,
    'invalidRelativeVlqGuard' => $invalidRelativeVlqGuard,
    'invalidVlqVectorGuard' => $invalidVlqVectorGuard,
    'missingVlqVectorGuard' => $missingVlqVectorGuard,
    'nullSourcesContentVectorGuard' => $nullSourcesContentVectorGuard,
    'ignoredVersionMaps' => $ignoredVersionMaps,
    'lookup' => $lookup,
];

if (($argv[1] ?? null) === '--self-test') {
    $expected = [
        'css' => $code,
        'map' => '{"version":3,"mappings":";;;;;oBCKA,2BAGA,8CDPA","sources":["wp-content/themes/example/style.css","wp-content/themes/example/blocks.css"],"sourcesContent":["@import \"blocks.css\";\n.theme-footer {\n  color: green;\n}","/*! Theme package license */\n/*!\n * Block editor stylesheet generated from theme.json\n * Keep comments for distribution compliance.\n */\n.wp-block-cover {\n  color: yellow;\n}\n.wp-block-cover .wp-block-button {\n  margin: 1rem;\n}"],"names":[]}',
        'consumedNestedMap' => '{"version":3,"mappings":"","sources":[],"sourcesContent":[],"names":[]}',
        'consumedNestedReplayNoop' => true,
        'skippedNestedSourceTableMap' => '{"version":3,"mappings":"","sources":["wp-content/themes/example/blocks/skipped-nested.css","wp-content/themes/example/blocks/unused-nested.css"],"sourcesContent":[".wp-block-skipped-nested{}\n",".wp-block-unused-nested{}\n"],"names":["skipped-nested-rule","unused-nested-name"]}',
        'skippedNestedChildConsumed' => '{"version":3,"mappings":"","sources":[],"sourcesContent":[],"names":[]}',
        'partialSkippedNestedSourceTableMap' => '{"version":3,"mappings":"ICCEC","sources":["wp-content/themes/example/blocks/skipped-partial.css","wp-content/themes/example/blocks/kept-partial.css","wp-content/themes/example/blocks/unused-partial.css"],"sourcesContent":[".wp-block-skipped-partial{}\n",".wp-block-kept-partial{}\n",".wp-block-unused-partial{}\n"],"names":["skipped-partial-rule","kept-partial-rule","unused-partial-name"]}',
        'partialSkippedNestedChildConsumed' => '{"version":3,"mappings":"","sources":[],"sourcesContent":[],"names":[]}',
        'generatedOnlyChildOffsetMap' => '{"version":3,"mappings":"MCGEI;Q;ADDFF;AACAC","sources":["wp-content/themes/example/source-map-generated-child-parent.css","wp-content/themes/example/source-map-generated-child.css"],"sourcesContent":["",".wp-block-generated-child{}\n"],"names":["generated-child-parent-0","generated-child-parent-1","generated-child-parent-2","generated-child-parent-3","generated-child-rule"]}',
        'generatedOnlyChildOffsetChildConsumed' => '{"version":3,"mappings":"","sources":[],"sourcesContent":[],"names":[]}',
        'generatedOnlyChildOffsetClosest' => ['generatedLine' => 1, 'generatedColumn' => 8, 'sourceIndex' => null, 'originalLine' => null, 'originalColumn' => null, 'nameIndex' => null],
        'emptyMap' => '{"version":3,"mappings":";AAAA;AACA;AACA","sources":["wp-content/themes/example/theme-json.css"],"sourcesContent":[":root {\n  --wp--style--global--content-size: 720px;\n}\n"],"names":[]}',
        'carriageReturnEmptyMap' => '{"version":3,"mappings":";AAAA;AACA;AACA","sources":["wp-content/themes/example/legacy-cr.css"],"sourcesContent":[".wp-block-legacy\r.wp-block-modern\n.wp-block-crlf\r\n.wp-block-tail\r.rule"],"names":[]}',
        'lineSpanMap' => '{"version":3,"mappings":"AAAA;;","sources":["wp-content/themes/example/editor-inline.css"],"sourcesContent":[".wp-block-spacer {\n  margin-top: 1rem;\n}\n"],"names":[]}',
        'emptyLineColumnOffsetMap' => '{"version":3,"mappings":"AAAA;;","sources":["wp-content/themes/example/empty-line-offset.css"],"sourcesContent":[".wp-block-empty-line-offset {}\n"],"names":[]}',
        'emptyLineColumnOffsetNoop' => true,
        'emptyLineColumnOverflowGuard' => true,
        'bufferRoundTripMap' => '{"version":3,"mappings":"AAAA;;","sources":["wp-content/themes/example/empty-line-offset.css"],"sourcesContent":[".wp-block-empty-line-offset {}\n"],"names":[]}',
        'negativeLinePastSpanGuard' => true,
        'farLineOffsetMap' => '{"version":3,"mappings":"AAAA;;;;EAMA","sources":["wp-content/themes/example/far-line-offset.css"],"sourcesContent":[".wp-block-far-line-offset {}\n"],"names":[]}',
        'lineStartOffsetInsertedMap' => '{"version":3,"mappings":";;AAAAA;;IAECC","sources":["wp-content/themes/example/line-start-offset.css"],"sourcesContent":[".wp-block-line-start-offset {}\n.wp-block-line-start-later {}\n"],"names":["line-start-top","line-start-later"]}',
        'lineStartOffsetMap' => '{"version":3,"mappings":";AAAAA;;IAECC","sources":["wp-content/themes/example/line-start-offset.css"],"sourcesContent":[".wp-block-line-start-offset {}\n.wp-block-line-start-later {}\n"],"names":["line-start-top","line-start-later"]}',
        'drainedEmptySpanBeforeDrain' => '{"version":3,"mappings":";;AAEAA;;","sources":["wp-content/themes/example/drained-empty-span.css"],"sourcesContent":[".wp-block-drained-empty-span{}\n"],"names":["drained-empty-span-rule"]}',
        'drainedEmptySpanMap' => '{"version":3,"mappings":";;;","sources":["wp-content/themes/example/drained-empty-span.css"],"sourcesContent":[".wp-block-drained-empty-span{}\n"],"names":["drained-empty-span-rule"]}',
        'drainedEmptySpanRoundTrip' => '{"version":3,"mappings":";;;","sources":["wp-content/themes/example/drained-empty-span.css"],"sourcesContent":[".wp-block-drained-empty-span{}\n"],"names":["drained-empty-span-rule"]}',
        'columnDrainedEmptySpanMap' => '{"version":3,"mappings":";;","sources":["wp-content/themes/example/column-drained-empty-span.css"],"sourcesContent":[".wp-block-column-drained{}\n"],"names":["column-drained-rule"]}',
        'columnDrainedEmptySpanRoundTrip' => '{"version":3,"mappings":";;","sources":["wp-content/themes/example/column-drained-empty-span.css"],"sourcesContent":[".wp-block-column-drained{}\n"],"names":["column-drained-rule"]}',
        'columnDrainedEmptySpanClosest' => null,
        'columnPrefixDrainMap' => '{"version":3,"mappings":";;KACAC","sources":["wp-content/themes/example/column-prefix-drain.css"],"sourcesContent":[".wp-block-prefix-a{}\n.wp-block-prefix-b{}\n"],"names":["prefix-a-rule","prefix-b-rule"]}',
        'middleColumnDrainedSpanMap' => '{"version":3,"mappings":"AAAAA;;IAEEE","sources":["wp-content/themes/example/source-map-middle-column-drain.css"],"sourcesContent":[".wp-block-before-drain{}\n.wp-block-drained-middle{}\n.wp-block-after-drain{}\n"],"names":["middle-drain-before","middle-drain-removed","middle-drain-after"]}',
        'middleColumnDrainedSpanRoundTrip' => '{"version":3,"mappings":"AAAAA;;IAEEE","sources":["wp-content/themes/example/source-map-middle-column-drain.css"],"sourcesContent":[".wp-block-before-drain{}\n.wp-block-drained-middle{}\n.wp-block-after-drain{}\n"],"names":["middle-drain-before","middle-drain-removed","middle-drain-after"]}',
        'middleColumnDrainedSpanClosest' => null,
        'mergedColumnDrainedChildBeforeMerge' => '{"version":3,"mappings":";;","sources":["wp-content/themes/example/column-drained-child.css"],"sourcesContent":[".wp-block-column-drained-child{}\n"],"names":["merged-column-drain-child-rule"]}',
        'mergedColumnDrainedChildParentMap' => '{"version":3,"mappings":"AAAAA;;;","sources":["wp-content/themes/example/source-map-merge-parent.css","wp-content/themes/example/column-drained-child.css"],"sourcesContent":["",".wp-block-column-drained-child{}\n"],"names":["merged-parent-0","merged-parent-1","merged-parent-2","merged-parent-3","merged-column-drain-child-rule"]}',
        'mergedColumnDrainedChildConsumed' => '{"version":3,"mappings":"","sources":[],"sourcesContent":[],"names":[]}',
        'mergedColumnDrainedChildClosest' => null,
        'bufferedNegativeColumnDrainedChildBeforeMerge' => '{"version":3,"mappings":";;","sources":["wp-content/themes/example/buffered-negative-column-drained-child.css"],"sourcesContent":[".wp-block-buffered-negative-column-drained{}\n"],"names":["buffered-negative-column-drain-child-rule"]}',
        'bufferedNegativeColumnDrainedParentMap' => '{"version":3,"mappings":";;AAEAE;AACAC","sources":["wp-content/themes/example/source-map-buffered-negative-parent.css","wp-content/themes/example/buffered-negative-column-drained-child.css"],"sourcesContent":["",".wp-block-buffered-negative-column-drained{}\n"],"names":["buffered-negative-parent-0","buffered-negative-parent-1","buffered-negative-parent-2","buffered-negative-parent-3","buffered-negative-column-drain-child-rule"]}',
        'bufferedNegativeColumnDrainedRestoredChildConsumed' => '{"version":3,"mappings":"","sources":[],"sourcesContent":[],"names":[]}',
        'bufferedNegativeColumnDrainedClosest' => null,
        'rawLeadingSemicolonOffsetMap' => '{"version":3,"mappings":";;;;GACA","sources":["wp-content/themes/example/source-map-leading-semicolon.css"],"sourcesContent":[".wp-block-leading-semicolon{}"],"names":[]}',
        'negativeChildLineOffsetMap' => '{"version":3,"mappings":";;AAEAE;AACAC","sources":["wp-content/themes/example/negative-child-parent.css","wp-content/themes/example/negative-child.css"],"sourcesContent":[],"names":["negative-child-parent-0","negative-child-parent-1","negative-child-parent-2","negative-child-parent-3","negative-child-rule"]}',
        'negativeChildLineOffsetChildConsumed' => '{"version":3,"mappings":"","sources":[],"sourcesContent":[],"names":[]}',
        'leadingEmptyChildOffsetMap' => '{"version":3,"mappings":"AAAAA;;;GCOCK;ADHDD","sources":["wp-content/themes/example/source-map-leading-child-parent.css","wp-content/themes/example/source-map-leading-child.css"],"sourcesContent":["",".wp-block-leading-child{}\n"],"names":["leading-parent-line-0","leading-parent-line-1","leading-parent-line-2","leading-parent-line-3","leading-parent-line-4","leading-child-rule"]}',
        'leadingEmptyChildOffsetChildConsumed' => '{"version":3,"mappings":"","sources":[],"sourcesContent":[],"names":[]}',
        'negativeLeadingEmptyChildOffsetBeforeMerge' => '{"version":3,"mappings":";;MAIEA","sources":["wp-content/themes/example/source-map-negative-leading-child.css"],"sourcesContent":[".wp-block-negative-leading-child{}\n"],"names":["negative-leading-child-rule"]}',
        'negativeLeadingEmptyChildOffsetMap' => '{"version":3,"mappings":";MCIEI;ADFFF;AACAC","sources":["wp-content/themes/example/source-map-negative-leading-parent.css","wp-content/themes/example/source-map-negative-leading-child.css"],"sourcesContent":["",".wp-block-negative-leading-child{}\n"],"names":["negative-leading-parent-0","negative-leading-parent-1","negative-leading-parent-2","negative-leading-parent-3","negative-leading-child-rule"]}',
        'negativeLeadingEmptyChildOffsetChildConsumed' => '{"version":3,"mappings":"","sources":[],"sourcesContent":[],"names":[]}',
        'negativeLeadingEmptyChildOffsetClearedClosest' => null,
        'negativeLeadingEmptyChildOffsetKeptClosest' => ['generatedLine' => 1, 'generatedColumn' => 6, 'sourceIndex' => 1, 'originalLine' => 4, 'originalColumn' => 2, 'nameIndex' => 4],
        'generatedOnlyOffsetMap' => '{"version":3,"mappings":";;Y,MAAAA;E","sources":["wp-content/themes/example/generated-offset.css"],"sourcesContent":[".wp-block-generated-offset{display:block}\n"],"names":["generated-offset-rule"]}',
        'mappingRecordOffsetMap' => '{"version":3,"mappings":";;OAIGA;M","sources":["wp-content/themes/example/source-map-record-offset.css"],"sourcesContent":[".wp-block-record-offset{}\n"],"names":["record-offset-rule"]}',
        'dedupedRawVlqOffsetMap' => '{"version":3,"mappings":";;GAAAA,ICEGC;GDACD","sources":["shared.css","blocks/card.css"],"sourcesContent":[".wp-block-shared{color:rebeccapurple}",".wp-block-card{color:red}"],"names":["shared-block-rule","card-block-rule"]}',
        'extendedInputMap' => '{"version":3,"mappings":"ACMQE,wDAMFC","sources":["wp-content/cache/theme-json.generated.css","wp-content/themes/example/theme.json"],"sourcesContent":[".wp-block-cover{color:var(--wp--preset--color--primary)}.wp-block-spacer{margin-top:1rem}","{\n  \"version\": 3,\n  \"settings\": {\n    \"color\": {\n      \"palette\": [\n        { \"slug\": \"primary\", \"color\": \"#06c\" }\n      ]\n    }\n  },\n  \"styles\": {\n    \"blocks\": {\n      \"core/spacer\": { \"spacing\": { \"margin\": { \"top\": \"1rem\" } } }\n    }\n  }\n}"],"names":["coverRule","spacerRule","settings.color.primary","styles.blocks.core/spacer.spacing.margin.top"]}',
        'projectRootMap' => '{"version":3,"mappings":"AACAA,0BCIAC;ACLAC","sources":["wp-content/themes/example/style.css","wp-content/themes/example/blocks.css","theme://generated/editor.css"],"sourcesContent":["@import \"blocks.css\";\n.theme-footer {\n  color: green;\n}","/*! Theme package license */\n/*!\n * Block editor stylesheet generated from theme.json\n * Keep comments for distribution compliance.\n */\n.wp-block-cover {\n  color: yellow;\n}\n.wp-block-cover .wp-block-button {\n  margin: 1rem;\n}",".wp-block-cover{outline:2px solid currentColor}"],"names":["theme-footer","block-cover","virtual-editor-rule"]}',
        'dataUrl' => 'data:application/json;charset=utf-8;base64,eyJ2ZXJzaW9uIjozLCJzb3VyY2VSb290IjoiLyIsIm1hcHBpbmdzIjoiO0NBQUFBIiwic291cmNlcyI6WyJ3cC1jb250ZW50L3RoZW1lcy9leGFtcGxlL2lubGluZS1jcml0aWNhbC5jc3MiXSwic291cmNlc0NvbnRlbnQiOlsiLmNyaXRpY2Fse2Rpc3BsYXk6YmxvY2t9XG4iXSwibmFtZXMiOlsiY3JpdGljYWwtcnVsZSJdfQ==',
        'dataUrlRoundTrip' => '{"version":3,"sourceRoot":"/","mappings":";CAAAA","sources":["wp-content/themes/example/inline-critical.css"],"sourcesContent":[".critical{display:block}\n"],"names":["critical-rule"]}',
        'negativeOffsetRawMap' => '{"version":3,"mappings":"ICKMC","sources":["wp-content/themes/example/source-map-prelude.css","wp-content/themes/example/blocks/cover.css"],"sourcesContent":[".prelude{}",".wp-block-cover{}"],"names":["prelude-rule","cover-rule"]}',
        'skippedSameLineRawVlqMap' => '{"version":3,"mappings":"GCCGC","sources":["wp-content/themes/example/source-map-same-line-prelude.css","wp-content/themes/example/blocks/same-line-cover.css"],"sourcesContent":[".wp-block-same-line-prelude{}",".wp-block-same-line-cover{}"],"names":["same-line-prelude-rule","same-line-cover-rule"]}',
        'skippedGeneratedOnlyRawVlqMap' => '{"version":3,"mappings":"KCECC","sources":["wp-content/themes/example/source-map-generated-state-prelude.css","wp-content/themes/example/blocks/generated-state-cover.css"],"sourcesContent":[".wp-block-generated-state-prelude{}",".wp-block-generated-state-cover{}"],"names":["generated-state-prelude-rule","generated-state-cover-rule"]}',
        'fullySkippedRawVlqMap' => '{"version":3,"mappings":"","sources":["wp-content/themes/example/source-map-skipped.scss","wp-content/themes/example/source-map-unused.scss"],"sourcesContent":[".wp-block-skipped{color:red}",".wp-block-unused{color:blue}"],"names":["skipped-rule","unused-rule"]}',
        'separatorOnlyRawVlqParent' => '{"version":3,"mappings":"AAAAA","sources":["wp-content/themes/example/source-map-entry.css","wp-content/themes/example/source-map-empty-separators.css"],"sourcesContent":[".wp-block-source-map-entry{}\n",".wp-block-empty-separators{}"],"names":["source-map-entry-rule","empty-separators-rule"]}',
        'separatorOnlyRawVlqChildConsumed' => '{"version":3,"mappings":"","sources":[],"sourcesContent":[],"names":[]}',
        'skippedInvalidSourceIndexGuard' => true,
        'skippedInvalidSourceIndexMap' => '{"version":3,"mappings":"","sources":["wp-content/themes/example/source-map-skipped-invalid-source.css"],"sourcesContent":[".wp-block-skipped-invalid-source{}"],"names":["skipped-invalid-source-rule"]}',
        'skippedInvalidNameIndexGuard' => true,
        'skippedInvalidNameIndexMap' => '{"version":3,"mappings":"","sources":["wp-content/themes/example/source-map-skipped-invalid-name.css"],"sourcesContent":[".wp-block-skipped-invalid-name{}"],"names":["skipped-invalid-known-name"]}',
        'partialInvalidSourceIndexGuard' => true,
        'partialInvalidSourceIndexMap' => '{"version":3,"mappings":";;QAAA","sources":["wp-content/themes/example/source-map-partial-invalid-source.css"],"sourcesContent":[".wp-block-partial-invalid-source{}"],"names":[]}',
        'partialInvalidNameIndexGuard' => true,
        'partialInvalidNameIndexMap' => '{"version":3,"mappings":";KAAAA","sources":["wp-content/themes/example/source-map-partial-invalid-name.css"],"sourcesContent":[".wp-block-partial-invalid-name{}"],"names":["partial-invalid-name-rule"]}',
        'negativeColumnGeneratedOnlyMap' => '{"version":3,"mappings":"E,I;I","sources":["wp-content/themes/example/source-map-negative-column.css"],"sourcesContent":[".wp-block-negative-column{}"],"names":[]}',
        'negativeColumnSourceBackedMap' => '{"version":3,"mappings":"EAAA,IACA;IACA","sources":["wp-content/themes/example/source-map-negative-column.css"],"sourcesContent":[".wp-block-negative-column{}"],"names":[]}',
        'negativeColumnSkippedLineMap' => '{"version":3,"mappings":"IACA","sources":["wp-content/themes/example/source-map-negative-column.css"],"sourcesContent":[".wp-block-negative-column{}"],"names":[]}',
        'negativeColumnOffsetGuard' => true,
        'streamingRawVlqMap' => '{"version":3,"mappings":";;IAAAA,A;K","sources":["wp-content/themes/example/source-map-stream.css"],"sourcesContent":[".wp-block-source-map-stream{}"],"names":["stream-rule"]}',
        'duplicateColumnPositiveMap' => '{"version":3,"mappings":"AAAAA,K,C","sources":["wp-content/themes/example/source-map-duplicate-column.css"],"sourcesContent":[".wp-block-duplicate-column{}"],"names":["duplicate-column-rule"]}',
        'duplicateColumnNegativeMap' => '{"version":3,"mappings":"AAAAA,A","sources":["wp-content/themes/example/source-map-duplicate-column.css"],"sourcesContent":[".wp-block-duplicate-column{}"],"names":["duplicate-column-rule"]}',
        'tripleDuplicateExact' => ['generatedLine' => 0, 'generatedColumn' => 0, 'sourceIndex' => null, 'originalLine' => null, 'originalColumn' => null, 'nameIndex' => null],
        'tripleDuplicateAfterDuplicates' => ['generatedLine' => 0, 'generatedColumn' => 1, 'sourceIndex' => 0, 'originalLine' => 1, 'originalColumn' => 0, 'nameIndex' => 1],
        'tripleDuplicatePositiveMap' => '{"version":3,"mappings":"AAAAA,A,K,CACAC","sources":["wp-content/themes/example/source-map-triple-duplicate.css"],"sourcesContent":[".wp-block-triple-duplicate{}"],"names":["triple-first-rule","triple-second-rule"]}',
        'tripleDuplicateNegativeMap' => '{"version":3,"mappings":"AAAAA,A,AACAC","sources":["wp-content/themes/example/source-map-triple-duplicate.css"],"sourcesContent":[".wp-block-triple-duplicate{}"],"names":["triple-first-rule","triple-shifted-rule"]}',
        'tripleDuplicateNegativeClosest' => ['generatedLine' => 0, 'generatedColumn' => 0, 'sourceIndex' => 0, 'originalLine' => 1, 'originalColumn' => 0, 'nameIndex' => 1],
        'duplicateBoundaryBufferedReadOrder' => [0, 0, 1],
        'duplicateBoundaryBufferedOffsetMap' => '{"version":3,"mappings":"AAAAA,K,CACAC","sources":["wp-content/themes/example/source-map-duplicate-buffered.css"],"sourcesContent":[".wp-block-duplicate-buffered{}"],"names":["duplicate-buffered-first","duplicate-buffered-second"]}',
        'duplicateBoundaryNestedReadOrderBeforeOffset' => [0, 0, 1],
        'duplicateBoundaryNestedOffsetMap' => '{"version":3,"mappings":"AAAAA;ACAAE,G,CACAC","sources":["wp-content/themes/example/source-map-duplicate-buffered-parent.css","wp-content/themes/example/source-map-duplicate-buffered.css"],"sourcesContent":[".wp-block-duplicate-buffered-parent{}\n",".wp-block-duplicate-buffered{}"],"names":["duplicate-buffered-parent-top","duplicate-buffered-parent-replaced","duplicate-buffered-first","duplicate-buffered-second"]}',
        'duplicateBoundaryNestedChildConsumed' => '{"version":3,"mappings":"","sources":[],"sourcesContent":[],"names":[]}',
        'duplicateBoundaryNegativeMap' => '{"version":3,"mappings":"AAAAA,EACAC,AAEAE","sources":["wp-content/themes/example/source-map-duplicate-boundary.css"],"sourcesContent":[".wp-block-duplicate-boundary{}"],"names":["first-boundary-rule","start-boundary-a","start-boundary-b","shifted-boundary-rule"]}',
        'duplicateBoundaryNegativeClosest' => ['generatedLine' => 0, 'generatedColumn' => 2, 'sourceIndex' => 0, 'originalLine' => 3, 'originalColumn' => 0, 'nameIndex' => 3],
        'duplicateShiftBoundaryNegativeReadOrder' => [0, 4, 5, 5, 10],
        'duplicateShiftBoundaryNegativeMap' => '{"version":3,"mappings":"AAAAA,EAGAG,KACAC","sources":["wp-content/themes/example/source-map-duplicate-shift-boundary.css"],"sourcesContent":[".wp-block-duplicate-shift{}\n"],"names":["anchor-rule","drained-window-rule","drained-first-boundary","shifted-second-boundary","after-shift-boundary"]}',
        'duplicateShiftBoundaryNegativeClosest' => ['generatedLine' => 0, 'generatedColumn' => 2, 'sourceIndex' => 0, 'originalLine' => 3, 'originalColumn' => 0, 'nameIndex' => 3],
        'duplicateShiftBoundaryNegativeRoundTrip' => '{"version":3,"mappings":"AAAAA,EAGAG,KACAC","sources":["wp-content/themes/example/source-map-duplicate-shift-boundary.css"],"sourcesContent":[".wp-block-duplicate-shift{}\n"],"names":["anchor-rule","drained-window-rule","drained-first-boundary","shifted-second-boundary","after-shift-boundary"]}',
        'trailingNegativeWindowMap' => '{"version":3,"mappings":"AAAAA,UACAC","sources":["wp-content/themes/example/source-map-trailing-window.css"],"sourcesContent":[".wp-block-trailing-window{}"],"names":["trailing-window-first","trailing-window-middle","trailing-window-drained"]}',
        'trailingNegativeWindowColumns' => [0, 10],
        'trailingNegativeWindowNames' => ['trailing-window-first', 'trailing-window-middle', 'trailing-window-drained'],
        'trailingNegativeWindowNoop' => true,
        'unsortedTrailingNegativeWindowReadOrder' => [10, 2],
        'unsortedTrailingNegativeWindowMap' => '{"version":3,"mappings":"EACAC","sources":["wp-content/themes/example/source-map-unsorted-trailing-window.css"],"sourcesContent":[".wp-block-unsorted-trailing-window{}"],"names":["later-trailing-window-rule","earlier-trailing-window-rule"]}',
        'unsortedTrailingNegativeWindowColumns' => [2],
        'unsortedTrailingNegativeWindowClosest' => ['generatedLine' => 0, 'generatedColumn' => 0, 'sourceIndex' => 0, 'originalLine' => 1, 'originalColumn' => 0, 'nameIndex' => 1],
        'unsortedRawVlqMap' => '{"version":3,"mappings":"EACAC,QADAD","sources":["wp-content/themes/example/source-map-unsorted-columns.css"],"sourcesContent":[".wp-block-unsorted-columns{}"],"names":["later-rule","earlier-rule"]}',
        'unsortedRawVlqPositiveOffsetMap' => '{"version":3,"mappings":"EACAC,WADAD","sources":["wp-content/themes/example/source-map-unsorted-columns.css"],"sourcesContent":[".wp-block-unsorted-columns{}"],"names":["later-rule","earlier-rule"]}',
        'unsortedRawVlqNegativeOffsetMap' => '{"version":3,"mappings":"EAAAA","sources":["wp-content/themes/example/source-map-unsorted-columns.css"],"sourcesContent":[".wp-block-unsorted-columns{}"],"names":["later-rule","earlier-rule"]}',
        'unsortedRawVlqReadOrderBeforeWrite' => [10, 2],
        'unsortedRawVlqReadOrderAfterWrite' => [2, 10],
        'unsortedRawVlqZeroOffsetOrder' => [2, 10],
        'unsortedRawVlqLookupClosest' => ['generatedLine' => 0, 'generatedColumn' => 2, 'sourceIndex' => 0, 'originalLine' => 1, 'originalColumn' => 0, 'nameIndex' => 1],
        'unsortedRawVlqLookupOrder' => [2, 10],
        'nestedUnsortedVlqReadOrderBeforeWrite' => [10, 2],
        'nestedUnsortedVlqMap' => '{"version":3,"mappings":";;EACAC,QADAD","sources":["wp-content/themes/example/source-map-nested-unsorted.css"],"sourcesContent":[".wp-block-nested-unsorted{}"],"names":["later-rule","earlier-rule"]}',
        'nestedUnsortedVlqReadOrderAfterWrite' => [2, 10],
        'nestedUnsortedVlqChildConsumed' => '{"version":3,"mappings":"","sources":[],"sourcesContent":[],"names":[]}',
        'lineOffsetUnsortedVlqReadOrderBeforeWrite' => [10, 2],
        'lineOffsetUnsortedVlqMap' => '{"version":3,"mappings":";;EACAC,QADAD","sources":["wp-content/themes/example/source-map-line-offset-unsorted.css"],"sourcesContent":[".wp-block-line-offset-unsorted{}"],"names":["later-line-offset-rule","earlier-line-offset-rule"]}',
        'lineOffsetUnsortedVlqReadOrderAfterWrite' => [2, 10],
        'middleInsertLineOffsetReadOrderBeforeWrite' => [0, 10, 2, 0],
        'middleInsertLineOffsetMap' => '{"version":3,"mappings":"AAAAA;;;ECCAE,QADAD;AAGAE","sources":["wp-content/themes/example/source-map-middle-insert-entry.css","wp-content/themes/example/source-map-middle-insert.css"],"sourcesContent":[".wp-block-middle-insert-entry{}\n",".wp-block-middle-insert-later{}\n.wp-block-middle-insert-earlier{}\n.wp-block-middle-insert-after{}\n"],"names":["middle-insert-entry-rule","middle-insert-later-rule","middle-insert-earlier-rule","middle-insert-after-rule"]}',
        'middleInsertLineOffsetReadOrderAfterWrite' => [0, 2, 10, 0],
        'middleInsertLineOffsetClosest' => ['generatedLine' => 3, 'generatedColumn' => 2, 'sourceIndex' => 1, 'originalLine' => 1, 'originalColumn' => 0, 'nameIndex' => 2],
        'middleInsertLineOffsetRoundTrip' => '{"version":3,"mappings":"AAAAA;;;ECCAE,QADAD;AAGAE","sources":["wp-content/themes/example/source-map-middle-insert-entry.css","wp-content/themes/example/source-map-middle-insert.css"],"sourcesContent":[".wp-block-middle-insert-entry{}\n",".wp-block-middle-insert-later{}\n.wp-block-middle-insert-earlier{}\n.wp-block-middle-insert-after{}\n"],"names":["middle-insert-entry-rule","middle-insert-later-rule","middle-insert-earlier-rule","middle-insert-after-rule"]}',
        'negativeLineOffsetUnsortedVlqReadOrderBeforeLookup' => [10, 2],
        'negativeLineOffsetUnsortedVlqClosest' => ['generatedLine' => 1, 'generatedColumn' => 2, 'sourceIndex' => 0, 'originalLine' => 1, 'originalColumn' => 0, 'nameIndex' => 1],
        'negativeLineOffsetUnsortedVlqMap' => '{"version":3,"mappings":";EACAC,QADAD","sources":["wp-content/themes/example/source-map-negative-line-offset-unsorted.css"],"sourcesContent":[".wp-block-negative-line-offset-unsorted{}"],"names":["later-negative-line-offset-rule","earlier-negative-line-offset-rule"]}',
        'negativeLineSpliceUnsortedVlqReadOrderBeforeWrite' => [10, 2, 0],
        'negativeLineSpliceUnsortedVlqMap' => '{"version":3,"mappings":"ECAAC,QACAC;AAEAC","sources":["wp-content/themes/example/source-map-line-splice-removed.css","wp-content/themes/example/source-map-line-splice-unsorted.css"],"sourcesContent":[".wp-block-line-splice-removed{}\n",".wp-block-line-splice-unsorted{}\n"],"names":["removed-line-splice-rule","later-line-splice-rule","earlier-line-splice-rule","after-line-splice-rule"]}',
        'negativeLineSpliceUnsortedVlqReadOrderAfterWrite' => [2, 10, 0],
        'negativeLineSpliceUnsortedVlqClosest' => ['generatedLine' => 0, 'generatedColumn' => 2, 'sourceIndex' => 1, 'originalLine' => 0, 'originalColumn' => 0, 'nameIndex' => 1],
        'bufferedUnsortedRawVlqReadOrderBeforeWrite' => [10, 2],
        'bufferedUnsortedRawVlqMap' => '{"version":3,"mappings":"EACAC,QADAD","sources":["wp-content/themes/example/source-map-buffered-unsorted.css"],"sourcesContent":[".wp-block-buffered-unsorted{}"],"names":["later-buffered-rule","earlier-buffered-rule"]}',
        'bufferedUnsortedRawVlqReadOrderAfterWrite' => [2, 10],
        'bufferedUnsortedRawVlqPositiveOffsetMap' => '{"version":3,"mappings":"EACAC,WADAD","sources":["wp-content/themes/example/source-map-buffered-unsorted.css"],"sourcesContent":[".wp-block-buffered-unsorted{}"],"names":["later-buffered-rule","earlier-buffered-rule"]}',
        'bufferedUnsortedRawVlqNegativeOffsetMap' => '{"version":3,"mappings":"EAAAA","sources":["wp-content/themes/example/source-map-buffered-unsorted.css"],"sourcesContent":[".wp-block-buffered-unsorted{}"],"names":["later-buffered-rule","earlier-buffered-rule"]}',
        'bufferedUnsortedChildReadOrderBeforeWrite' => [10, 2],
        'bufferedUnsortedChildParentMap' => '{"version":3,"mappings":";;ECCAE,QADAD","sources":["wp-content/themes/example/source-map-buffered-parent.css","wp-content/themes/example/source-map-buffered-unsorted.css"],"sourcesContent":[".wp-block-buffered-parent{}\n",".wp-block-buffered-unsorted{}"],"names":["buffered-parent-rule","later-buffered-rule","earlier-buffered-rule"]}',
        'bufferedUnsortedChildConsumed' => '{"version":3,"mappings":"","sources":[],"sourcesContent":[],"names":[]}',
        'duplicateLookupExact' => ['generatedLine' => 0, 'generatedColumn' => 0, 'sourceIndex' => null, 'originalLine' => null, 'originalColumn' => null, 'nameIndex' => null],
        'duplicateLookupAfterLast' => ['generatedLine' => 0, 'generatedColumn' => 0, 'sourceIndex' => 0, 'originalLine' => 0, 'originalColumn' => 0, 'nameIndex' => 0],
        'duplicateLookupExtendedMap' => '{"version":3,"mappings":"A","sources":["wp-content/cache/duplicate-lookup.css","wp-content/themes/example/source-map-duplicate-lookup.scss"],"sourcesContent":[".wp-block-duplicate-lookup{color:red}",".wp-block-duplicate-lookup{}"],"names":["compiled-duplicate-lookup","duplicate-lookup-rule"]}',
        'afterLastGeneratedOnlyClosest' => ['generatedLine' => 0, 'generatedColumn' => 0, 'sourceIndex' => null, 'originalLine' => null, 'originalColumn' => null, 'nameIndex' => null],
        'afterLastGeneratedOnlyExact' => ['generatedLine' => 0, 'generatedColumn' => 5, 'sourceIndex' => 0, 'originalLine' => 0, 'originalColumn' => 0, 'nameIndex' => null],
        'afterLastGeneratedOnlyOffsetMap' => '{"version":3,"mappings":"A,QAAA","sources":["wp-content/themes/example/source-map-after-last.scss"],"sourcesContent":[".wp-block-after-last{}"],"names":[]}',
        'afterLastGeneratedOnlyOffsetClosest' => ['generatedLine' => 0, 'generatedColumn' => 0, 'sourceIndex' => null, 'originalLine' => null, 'originalColumn' => null, 'nameIndex' => null],
        'afterLastGeneratedOnlyOffsetExact' => ['generatedLine' => 0, 'generatedColumn' => 8, 'sourceIndex' => 0, 'originalLine' => 0, 'originalColumn' => 0, 'nameIndex' => null],
        'afterLastGeneratedOnlyExtendedMap' => '{"version":3,"mappings":"A","sources":["wp-content/cache/source-map-after-last.css","wp-content/themes/example/source-map-after-last.scss"],"sourcesContent":[".wp-block-after-last{color:red}",".wp-block-after-last{}"],"names":["compiled-after-last"]}',
        'beforeFirstSourceBackedClosest' => ['generatedLine' => 0, 'generatedColumn' => 0, 'sourceIndex' => 0, 'originalLine' => 2, 'originalColumn' => 1, 'nameIndex' => 0],
        'beforeFirstSourceBackedOffsetMap' => '{"version":3,"mappings":"cAECA,UACCC","sources":["wp-content/themes/example/source-map-before-first.scss"],"sourcesContent":[".wp-block-before{}\\n.wp-block-after{}\\n"],"names":["before-first-rule","before-second-rule"]}',
        'beforeFirstSourceBackedOffsetClosest' => ['generatedLine' => 0, 'generatedColumn' => 0, 'sourceIndex' => 0, 'originalLine' => 2, 'originalColumn' => 1, 'nameIndex' => 0],
        'beforeFirstSourceBackedOffsetExact' => ['generatedLine' => 0, 'generatedColumn' => 14, 'sourceIndex' => 0, 'originalLine' => 2, 'originalColumn' => 1, 'nameIndex' => 0],
        'beforeFirstSourceBackedExtendedMap' => '{"version":3,"mappings":"ACECC","sources":["wp-content/cache/source-map-before-first.css","wp-content/themes/example/source-map-before-first.scss"],"sourcesContent":[".wp-block-before{color:red}.wp-block-after{color:blue}",".wp-block-before{}\\n.wp-block-after{}\\n"],"names":["compiled-before-first","before-first-rule","before-second-rule"]}',
        'maxUnsignedVlqDecode' => 4294967295,
        'shiftedColumnOverflowGuard' => true,
        'unsortedColumnOverflowBeforeGuard' => [10, 2],
        'unsortedColumnOverflowAfterGuard' => [10, 2],
        'unsortedColumnOverflowGuard' => true,
        'invalidRelativeVlqGuard' => true,
        'invalidVlqVectorGuard' => true,
        'missingVlqVectorGuard' => true,
        'nullSourcesContentVectorGuard' => true,
        'ignoredVersionMaps' => [';C', ';C', ';C', ';C', ';C', ';C'],
        'lookup' => [
            'sourceIndexes' => [0, 1, 2],
            'blockIndex' => 1,
            'missingSource' => null,
            'sources' => ['wp-content/themes/example/style.css', 'wp-content/themes/example/blocks.css', 'theme://generated/editor.css'],
            'blockContent' => $blockSource,
            'sourcesContent' => [$entrySource, $blockSource, '.wp-block-cover{outline:2px solid currentColor}'],
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
