<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\TableGeometry;
use PortLibs\Pandoc\WordPressBlockWriter;

$readerHandoffDocument = (new MarkdownReader())->read(implode("\n", [
    '| Source | Count | State |',
    '|:-------|------:|:----:|',
    '| Posts | 42 | Ready |',
    '| Media | 7 | Review |',
    '',
    ': Reader packet import metrics',
]));
$readerHandoffTables = array_values(array_filter(
    $readerHandoffDocument->children,
    static fn (AstNode $node): bool => $node->type === 'table'
));
$visualRowHeadColspanDocument = (new MarkdownReader())->read(<<<'HTML'
<table id="visual-rowhead-grid" data-source="html-reader">
<caption>Visual row head review</caption>
<thead>
<tr><th colspan="2">Source</th><th>Status</th></tr>
</thead>
<tbody data-section="body">
<tr><th colspan="2">Posts and pages</th><td>Ready</td></tr>
<tr><th colspan="2">Media assets</th><td>Review</td></tr>
</tbody>
</table>
HTML);
$visualRowHeadColspanTables = array_values(array_filter(
    $visualRowHeadColspanDocument->children,
    static fn (AstNode $node): bool => $node->type === 'table'
));
$multiBodyRowHeadDocument = (new MarkdownReader())->read(<<<'HTML'
<table id="multi-rowhead-grid" data-source="html-reader">
<caption>Multiple body row-head review</caption>
<thead>
<tr><th>Group</th><th>Item</th><th>Status</th></tr>
</thead>
<tbody id="posts-body">
<tr><th colspan="2">Posts</th><td>Ready</td></tr>
<tr><th colspan="2">Pages</th><td>Review</td></tr>
</tbody>
<tbody id="media-body">
<tr><th>Images</th><td>7</td><td>Review</td></tr>
<tr><th>Video</th><td>2</td><td>Ready</td></tr>
</tbody>
</table>
HTML);
$multiBodyRowHeadTables = array_values(array_filter(
    $multiBodyRowHeadDocument->children,
    static fn (AstNode $node): bool => $node->type === 'table'
));
$rowspanZeroDocument = (new MarkdownReader())->read(<<<'HTML'
<table id="rowspan-zero-grid" data-source="html-reader">
<tbody id="posts-body">
<tr data-row="posts-total"><th rowspan="0" align="left">Posts</th><td align="right">42</td></tr>
<tr data-row="posts-media"><td align="right">7</td><td>Needs media</td></tr>
<tr data-row="posts-review"><td align="right">3</td><td>Review</td></tr>
</tbody>
<tbody id="pages-body">
<tr data-row="pages-total"><th>Pages</th><td align="right">5</td><td>Ready</td></tr>
</tbody>
</table>
HTML);
$rowspanZeroTables = array_values(array_filter(
    $rowspanZeroDocument->children,
    static fn (AstNode $node): bool => $node->type === 'table'
));
$colgroupAlignmentDocument = (new MarkdownReader())->read(<<<'HTML'
<table id="colgroup-alignment-grid" data-source="html-reader">
<caption>Colgroup alignment review</caption>
<colgroup data-source="legacy-doc">
<col span="2" style="width: 25%; text-align: right; vertical-align: bottom" data-origin="col-a" />
<col width="50%" align="center" valign="top" data-origin="col-b" />
</colgroup>
<thead>
<tr><th>Scope</th><th>Items</th><th>State</th></tr>
</thead>
<tbody>
<tr><td>Posts</td><td>42</td><td>Ready</td></tr>
<tr><td>Media</td><td>7</td><td>Review</td></tr>
</tbody>
</table>
HTML);
$colgroupAlignmentTables = array_values(array_filter(
    $colgroupAlignmentDocument->children,
    static fn (AstNode $node): bool => $node->type === 'table'
));
$columnBackgroundDocument = (new MarkdownReader())->read(<<<'HTML'
<table id="column-background-grid" data-source="html-reader">
<caption>Column background review</caption>
<colgroup data-source="legacy-doc" bgcolor="#FFF4CC" style="background-color: #e6ffed; background-image:url(javascript:alert(1))">
<col span="2" width="25%" data-origin="metric-columns" />
<col width="50%" bgcolor="yellow" style="background-color: rgb(230, 255, 237); background-image:url(javascript:alert(1))" data-origin="state-column" />
</colgroup>
<thead>
<tr><th>Scope</th><th>Items</th><th>State</th></tr>
</thead>
<tbody>
<tr><td>Posts</td><td>42</td><td>Ready</td></tr>
</tbody>
</table>
HTML);
$columnBackgroundTables = array_values(array_filter(
    $columnBackgroundDocument->children,
    static fn (AstNode $node): bool => $node->type === 'table'
));
$columnBorderPresentationDocument = (new MarkdownReader())->read(<<<'HTML'
<table id="column-border-grid" data-source="html-reader">
<caption>Column border review</caption>
<colgroup data-source="legacy-doc" style="border-color: #336699; border-style: dashed; border-width: 2px; border-image:url(javascript:alert(1))">
<col span="2" width="25%" data-origin="metric-columns" />
<col width="50%" style="border-right: thick double green; border-bottom-width: 3px; border-bottom-style: dotted; border-bottom-color: #123; border-image:url(javascript:alert(1))" data-origin="state-column" />
</colgroup>
<thead>
<tr><th>Scope</th><th>Items</th><th>State</th></tr>
</thead>
<tbody>
<tr><td>Posts</td><td>42</td><td>Ready</td></tr>
</tbody>
</table>
HTML);
$columnBorderPresentationTables = array_values(array_filter(
    $columnBorderPresentationDocument->children,
    static fn (AstNode $node): bool => $node->type === 'table'
));
$decimalAlignmentDocument = (new MarkdownReader())->read(<<<'HTML'
<table id="decimal-alignment-grid" data-source="html-reader">
<caption>Decimal alignment review</caption>
<colgroup data-source="legacy-doc" align="char" char="." charoff="2">
<col span="2" width="25%" data-origin="amount-columns" />
<col width="50%" align="char" char="," charoff="1" data-origin="rate-column" />
</colgroup>
<thead>
<tr><th>Source</th><th>Amount</th><th>Rate</th></tr>
</thead>
<tbody>
<tr><td>Posts</td><td>42.50</td><td>1,25</td></tr>
</tbody>
</table>
HTML);
$decimalAlignmentTables = array_values(array_filter(
    $decimalAlignmentDocument->children,
    static fn (AstNode $node): bool => $node->type === 'table'
));
$cellDecimalAlignmentDocument = (new MarkdownReader())->read(<<<'HTML'
<table id="cell-decimal-alignment-grid" data-source="html-reader">
<caption>Cell decimal alignment review</caption>
<thead>
<tr><th align="char" char="." charoff="2">Amount</th><th>Status</th></tr>
</thead>
<tbody>
<tr><td align="char" char="." charoff="1">42.50</td><td>Ready</td></tr>
<tr><td align="char" char="," charoff="3">7,25</td><td>Review</td></tr>
</tbody>
</table>
HTML);
$cellDecimalAlignmentTables = array_values(array_filter(
    $cellDecimalAlignmentDocument->children,
    static fn (AstNode $node): bool => $node->type === 'table'
));
$colgroupMismatchDocument = (new MarkdownReader())->read(<<<'HTML'
<table id="colgroup-underdeclared-grid" data-source="html-reader">
<caption>Colgroup mismatch review</caption>
<colgroup data-source="legacy-doc">
<col span="2" style="width: 20%; text-align: right" data-origin="declared-pair" />
</colgroup>
<thead>
<tr><th>Scope</th><th>Items</th><th>State</th></tr>
</thead>
<tbody>
<tr><td>Posts</td><td>42</td><td>Ready</td></tr>
</tbody>
</table>
HTML);
$colgroupMismatchTables = array_values(array_filter(
    $colgroupMismatchDocument->children,
    static fn (AstNode $node): bool => $node->type === 'table'
));
$malformedColumnSpanDocument = (new MarkdownReader())->read(<<<'HTML'
<table id="malformed-column-span-grid" data-source="html-reader">
<caption>Malformed column span review</caption>
<colgroup span="0" style="width: 25%; text-align: right" data-origin="group-zero"></colgroup>
<colgroup data-origin="colgroup-explicit">
<col span="two" width="50%" align="center" data-origin="col-two" />
<col span="-2" style="width: 25%; text-align: left" data-origin="col-negative" />
</colgroup>
<tbody>
<tr><td>Posts</td><td>Ready</td><td>Review</td></tr>
</tbody>
</table>
HTML);
$malformedColumnSpanTables = array_values(array_filter(
    $malformedColumnSpanDocument->children,
    static fn (AstNode $node): bool => $node->type === 'table'
));
$inheritedAlignmentDocument = (new MarkdownReader())->read(<<<'HTML'
<table id="inherited-alignment-grid" data-source="html-reader">
<caption>Inherited alignment review</caption>
<thead align="center">
<tr><th>Scope</th><th style="text-align: right">Items</th><th>State</th></tr>
</thead>
<tbody style="text-align: right" data-section="body">
<tr data-row="posts"><th>Posts</th><td>42</td><td align="center">Ready</td></tr>
<tr style="text-align: left" data-row="media"><th>Media</th><td>7</td><td>Review</td></tr>
</tbody>
<tfoot align="center">
<tr><td>Total</td><td>49</td><td>Review</td></tr>
</tfoot>
</table>
HTML);
$inheritedAlignmentTables = array_values(array_filter(
    $inheritedAlignmentDocument->children,
    static fn (AstNode $node): bool => $node->type === 'table'
));
$verticalAlignmentDocument = (new MarkdownReader())->read(<<<'HTML'
<table id="vertical-alignment-grid" data-source="html-reader">
<caption>Vertical alignment review</caption>
<thead valign="top">
<tr><th>Scope</th><th style="vertical-align: bottom">State</th></tr>
</thead>
<tbody data-section="body" valign="baseline">
<tr><td valign="middle">Posts</td><td>Ready</td></tr>
<tr style="vertical-align: top"><td>Total</td><td>Review</td></tr>
</tbody>
</table>
HTML);
$verticalAlignmentTables = array_values(array_filter(
    $verticalAlignmentDocument->children,
    static fn (AstNode $node): bool => $node->type === 'table'
));
$legacyFrameDocument = (new MarkdownReader())->read(<<<'HTML'
<table id="legacy-frame-grid" data-source="html-reader" frame="void" rules="groups" border="1">
<caption>Legacy frame review</caption>
<thead>
<tr><th>Scope</th><th>State</th></tr>
</thead>
<tbody>
<tr><td>Posts</td><td>Ready</td></tr>
</tbody>
</table>
HTML);
$legacyFrameTables = array_values(array_filter(
    $legacyFrameDocument->children,
    static fn (AstNode $node): bool => $node->type === 'table'
));
$legacySpacingDocument = (new MarkdownReader())->read(<<<'HTML'
<table id="legacy-spacing-grid" data-source="html-reader" cellpadding="6" cellspacing="2">
<caption>Legacy spacing review</caption>
<thead>
<tr><th>Scope</th><th>State</th></tr>
</thead>
<tbody>
<tr><td>Posts</td><td>Ready</td></tr>
</tbody>
</table>
HTML);
$legacySpacingTables = array_values(array_filter(
    $legacySpacingDocument->children,
    static fn (AstNode $node): bool => $node->type === 'table'
));
$cellNoWrapDocument = (new MarkdownReader())->read(<<<'HTML'
<table id="cell-nowrap-grid" data-source="html-reader">
<caption>Cell nowrap review</caption>
<thead>
<tr><th nowrap="nowrap">Source label</th><th>Status</th><th>Wrap</th></tr>
</thead>
<tbody>
<tr><td>Posts</td><td nowrap="nowrap">Long unbroken review value</td><td nowrap="false">Review wraps</td></tr>
</tbody>
</table>
HTML);
$cellNoWrapTables = array_values(array_filter(
    $cellNoWrapDocument->children,
    static fn (AstNode $node): bool => $node->type === 'table'
));
$cellDimensionDocument = (new MarkdownReader())->read(<<<'HTML'
<table id="cell-dimension-grid" data-source="html-reader">
<caption>Cell dimension review</caption>
<thead>
<tr><th width="120">Source</th><th style="width:40%; height:35%">Status</th><th>Wrap</th></tr>
</thead>
<tbody>
<tr><td height="32">Posts</td><td width="50%" height="44">Ready</td><td width="0">Ignored</td></tr>
</tbody>
</table>
HTML);
$cellDimensionTables = array_values(array_filter(
    $cellDimensionDocument->children,
    static fn (AstNode $node): bool => $node->type === 'table'
));
$rowBackgroundDocument = (new MarkdownReader())->read(<<<'HTML'
<table id="row-background-grid" data-source="html-reader">
<caption>Row background review</caption>
<thead>
<tr bgcolor="#FFF4CC"><th>Source</th><th>Status</th></tr>
</thead>
<tbody>
<tr style="background-color: #e6ffed"><td>Posts</td><td>Ready</td></tr>
<tr bgcolor="yellow" style="background-color: rgb(230, 255, 237)"><td>Media</td><td>Review</td></tr>
</tbody>
</table>
HTML);
$rowBackgroundTables = array_values(array_filter(
    $rowBackgroundDocument->children,
    static fn (AstNode $node): bool => $node->type === 'table'
));
$rowBorderPresentationDocument = (new MarkdownReader())->read(<<<'HTML'
<table id="row-border-presentation-grid" data-source="html-reader">
<caption>Row border presentation review</caption>
<thead>
<tr style="border-color: #336699; border-style: dashed; border-width: 2px"><th>Source</th><th>Status</th></tr>
</thead>
<tbody>
<tr style="border-right: thick double green"><td>Posts</td><td>Ready</td></tr>
<tr style="border-bottom-width: 3px; border-bottom-style: dotted; border-bottom-color: #123"><td>Media</td><td>Review</td></tr>
</tbody>
</table>
HTML);
$rowBorderPresentationTables = array_values(array_filter(
    $rowBorderPresentationDocument->children,
    static fn (AstNode $node): bool => $node->type === 'table'
));
$sectionPresentationDocument = (new MarkdownReader())->read(<<<'HTML'
<table id="section-presentation-grid" data-source="html-reader">
<caption>Section presentation review</caption>
<thead id="section-head" style="background-color: #FFF4CC; border-bottom: 2px solid #336699">
<tr><th>Source</th><th>Status</th></tr>
</thead>
<tbody id="section-body" bgcolor="yellow" style="border-color: #336699; border-style: dashed; border-width: 2px">
<tr><td>Posts</td><td>Ready</td></tr>
</tbody>
<tfoot id="section-foot" style="background-color: rgb(230, 255, 237); border-top-width: 3px; border-top-style: dotted; border-top-color: #123">
<tr><td>Total</td><td>Review</td></tr>
</tfoot>
</table>
HTML);
$sectionPresentationTables = array_values(array_filter(
    $sectionPresentationDocument->children,
    static fn (AstNode $node): bool => $node->type === 'table'
));
$cellBackgroundDocument = (new MarkdownReader())->read(<<<'HTML'
<table id="cell-background-grid" data-source="html-reader">
<caption>Cell background review</caption>
<thead>
<tr><th bgcolor="#FFF4CC">Source</th><th>Status</th></tr>
</thead>
<tbody>
<tr><td style="background-color: #e6ffed">Posts</td><td bgcolor="yellow" style="background-color: rgb(230, 255, 237)">Ready</td></tr>
<tr><td>Media</td><td>Review</td></tr>
</tbody>
</table>
HTML);
$cellBackgroundTables = array_values(array_filter(
    $cellBackgroundDocument->children,
    static fn (AstNode $node): bool => $node->type === 'table'
));
$cellBorderPresentationDocument = (new MarkdownReader())->read(<<<'HTML'
<table id="cell-border-presentation-grid" data-source="html-reader">
<caption>Cell border presentation review</caption>
<thead>
<tr><th style="border-color: #336699; border-style: dashed; border-width: 2px">Source</th><th>Status</th></tr>
</thead>
<tbody>
<tr><td style="border-color: rgb(51, 102, 153); border-style: solid">Posts</td><td style="border-width: thin medium thick 2px">Ready</td></tr>
<tr><td>Media</td><td>Review</td></tr>
</tbody>
</table>
HTML);
$cellBorderPresentationTables = array_values(array_filter(
    $cellBorderPresentationDocument->children,
    static fn (AstNode $node): bool => $node->type === 'table'
));
$cellSideBorderPresentationDocument = (new MarkdownReader())->read(<<<'HTML'
<table id="cell-side-border-grid" data-source="html-reader">
<caption>Cell side border review</caption>
<thead>
<tr><th style="border-top: 2px dashed #336699; border-left: 1pt solid red">Source</th><th>Status</th></tr>
</thead>
<tbody>
<tr><td style="border-right: thick double green">Posts</td><td style="border-bottom-width: 3px; border-bottom-style: dotted; border-bottom-color: #123">Ready</td></tr>
<tr><td>Media</td><td>Review</td></tr>
</tbody>
</table>
HTML);
$cellSideBorderPresentationTables = array_values(array_filter(
    $cellSideBorderPresentationDocument->children,
    static fn (AstNode $node): bool => $node->type === 'table'
));
$duplicateSourceIdDocument = (new MarkdownReader())->read(<<<'HTML'
<table id="duplicate-source-id-grid" data-source="html-reader">
<caption>Duplicate source id review</caption>
<thead id="duplicate-source-section">
<tr id="duplicate-source-row"><th id="source-scope">Scope</th><th id="source-state">State</th></tr>
</thead>
<tbody id="duplicate-source-section">
<tr id="duplicate-source-row"><td id="duplicate-source-cell">Posts</td><td id="duplicate-source-cell">Ready</td></tr>
</tbody>
</table>
HTML);
$duplicateSourceIdTables = array_values(array_filter(
    $duplicateSourceIdDocument->children,
    static fn (AstNode $node): bool => $node->type === 'table'
));
$backgroundColorDocument = (new MarkdownReader())->read(<<<'HTML'
<table id="background-color-grid" data-source="html-reader" bgcolor="#FFF4CC" style="background-color: #e6ffed; background-image:url(javascript:alert(1))">
<caption>Background color review</caption>
<thead>
<tr><th>Scope</th><th>State</th></tr>
</thead>
<tbody>
<tr><td>Posts</td><td>Ready</td></tr>
</tbody>
</table>
HTML);
$backgroundColorTables = array_values(array_filter(
    $backgroundColorDocument->children,
    static fn (AstNode $node): bool => $node->type === 'table'
));
$layoutWidthDocument = (new MarkdownReader())->read(<<<'HTML'
<table id="layout-width-grid" data-source="html-reader" width="80%">
<caption>Layout width review</caption>
<thead>
<tr><th>Scope</th><th>State</th></tr>
</thead>
<tbody>
<tr><td>Posts</td><td>Ready</td></tr>
</tbody>
</table>
HTML);
$layoutWidthTables = array_values(array_filter(
    $layoutWidthDocument->children,
    static fn (AstNode $node): bool => $node->type === 'table'
));
$layoutHeightDocument = (new MarkdownReader())->read(<<<'HTML'
<table id="layout-height-grid" data-source="html-reader" height="320">
<caption>Layout height review</caption>
<thead>
<tr><th>Scope</th><th>State</th></tr>
</thead>
<tbody>
<tr><td>Posts</td><td>Ready</td></tr>
</tbody>
</table>
HTML);
$layoutHeightTables = array_values(array_filter(
    $layoutHeightDocument->children,
    static fn (AstNode $node): bool => $node->type === 'table'
));
$layoutModeDocument = (new MarkdownReader())->read(<<<'HTML'
<table id="layout-mode-grid" data-source="html-reader" style="table-layout: fixed; background-image:url(javascript:alert(1))">
<caption>Table layout mode review</caption>
<thead>
<tr><th>Scope</th><th>State</th></tr>
</thead>
<tbody>
<tr><td>Posts</td><td>Ready</td></tr>
</tbody>
</table>
HTML);
$layoutModeTables = array_values(array_filter(
    $layoutModeDocument->children,
    static fn (AstNode $node): bool => $node->type === 'table'
));
$borderCollapseDocument = (new MarkdownReader())->read(<<<'HTML'
<table id="border-collapse-grid" data-source="html-reader" style="border-collapse: collapse; background-image:url(javascript:alert(1))">
<caption>Border collapse review</caption>
<thead>
<tr><th>Scope</th><th>State</th></tr>
</thead>
<tbody>
<tr><td>Posts</td><td>Ready</td></tr>
</tbody>
</table>
HTML);
$borderCollapseTables = array_values(array_filter(
    $borderCollapseDocument->children,
    static fn (AstNode $node): bool => $node->type === 'table'
));
$borderPresentationDocument = (new MarkdownReader())->read(<<<'HTML'
<table id="border-presentation-grid" data-source="html-reader" style="border-color: #336699; border-style: dashed; border-width: 2px; border-image:url(javascript:alert(1))">
<caption>Border presentation review</caption>
<thead>
<tr><th>Scope</th><th>State</th></tr>
</thead>
<tbody>
<tr><td>Posts</td><td>Ready</td></tr>
</tbody>
</table>
HTML);
$borderPresentationTables = array_values(array_filter(
    $borderPresentationDocument->children,
    static fn (AstNode $node): bool => $node->type === 'table'
));
$placementAlignmentDocument = (new MarkdownReader())->read(<<<'HTML'
<table id="placement-align-grid" data-source="html-reader" align="center">
<caption>Placement alignment review</caption>
<thead>
<tr><th>Scope</th><th>State</th></tr>
</thead>
<tbody>
<tr><td>Posts</td><td>Ready</td></tr>
</tbody>
</table>
<table id="placement-align-invalid" align="middle">
<tbody>
<tr><td>Invalid</td><td>Dropped</td></tr>
</tbody>
</table>
HTML);
$placementAlignmentTables = array_values(array_filter(
    $placementAlignmentDocument->children,
    static fn (AstNode $node): bool => $node->type === 'table'
));
$directionalityDocument = (new MarkdownReader())->read(<<<'HTML'
<table id="directionality-grid" data-source="html-reader" dir="rtl">
<caption>Directionality review</caption>
<thead dir="ltr">
<tr dir="rtl"><th>Scope</th><th dir="auto">State</th></tr>
</thead>
<tbody dir="rtl" data-section="body">
<tr><th>Posts</th><td>جاهز</td></tr>
<tr dir="ltr"><th>Media</th><td dir="auto">Review</td></tr>
</tbody>
</table>
HTML);
$directionalityTables = array_values(array_filter(
    $directionalityDocument->children,
    static fn (AstNode $node): bool => $node->type === 'table'
));
$localizationDocument = (new MarkdownReader())->read(<<<'HTML'
<table id="localized-source-grid" data-source="html-reader" lang="ar-eg" xml:lang="ar-EG" translate="no">
<caption lang="en">Localized source review</caption>
<thead lang="en">
<tr lang="fr"><th lang="fr">Portée</th><th translate="yes">State</th></tr>
</thead>
<tbody lang="ar" translate="no" data-section="body">
<tr><th>منشورات</th><td>جاهز</td></tr>
<tr lang="en" translate="yes"><th>Media</th><td lang="en-US" translate="no">Review</td></tr>
</tbody>
</table>
HTML);
$localizationTables = array_values(array_filter(
    $localizationDocument->children,
    static fn (AstNode $node): bool => $node->type === 'table'
));
$captionSourceDocument = (new MarkdownReader())->read(<<<'HTML'
<table>
<caption id="caption-source-handoff" class="source-caption" data-origin="html-reader" aria-label="Caption source" style="caption-side: bottom" onclick="blocked()">Caption source handoff</caption>
<tbody>
<tr><th>Scope</th><td>Ready</td></tr>
</tbody>
</table>
HTML);
$captionSourceTables = array_values(array_filter(
    $captionSourceDocument->children,
    static fn (AstNode $node): bool => $node->type === 'table'
));
$sideCaptionDocument = (new MarkdownReader())->read(<<<'HTML'
<table id="side-caption-grid" data-source="html-reader">
<caption id="side-caption" class="caption-title" data-origin="html-reader" style="caption-side: left; color: green" onclick="blocked()">Side <em>caption</em></caption>
<tbody>
<tr><th>Scope</th><td>Ready</td></tr>
</tbody>
</table>
HTML);
$sideCaptionTables = array_values(array_filter(
    $sideCaptionDocument->children,
    static fn (AstNode $node): bool => $node->type === 'table'
));
$legacyCaptionAlignDocument = (new MarkdownReader())->read(<<<'HTML'
<table id="legacy-caption-align-top-grid" data-source="html-reader">
<caption id="legacy-caption-align-top" class="caption-title" data-origin="legacy-doc" align="top" onclick="blocked()">Legacy <em>top</em> align caption</caption>
<tbody>
<tr><th>Scope</th><td>Ready</td></tr>
</tbody>
</table>
<table id="legacy-caption-align-side-grid" data-source="html-reader">
<caption id="legacy-caption-align-side" class="caption-title" data-origin="legacy-doc" align="right" onclick="blocked()">Legacy <em>side</em> align caption</caption>
<tbody>
<tr><th>Scope</th><td>Review</td></tr>
</tbody>
</table>
HTML);
$legacyCaptionAlignTables = array_values(array_filter(
    $legacyCaptionAlignDocument->children,
    static fn (AstNode $node): bool => $node->type === 'table'
));
$summarySourceDocument = (new MarkdownReader())->read(<<<'HTML'
<table id="summary-source-grid" summary="Legacy source table describes post counts by import state." data-source="html-reader">
<caption>Summary source review</caption>
<thead>
<tr><th>Scope</th><th>Items</th><th>State</th></tr>
</thead>
<tbody>
<tr><td>Posts</td><td>42</td><td>Ready</td></tr>
</tbody>
</table>
HTML);
$summarySourceTables = array_values(array_filter(
    $summarySourceDocument->children,
    static fn (AstNode $node): bool => $node->type === 'table'
));
$axisSourceDocument = (new MarkdownReader())->read(<<<'HTML'
<table id="axis-source-grid" data-source="html-reader">
<caption>Axis source review</caption>
<thead>
<tr><th id="axis-document" axis="document, import" scope="col">Document</th><th id="axis-state" axis="state review" scope="col">State</th></tr>
</thead>
<tbody>
<tr><th id="axis-posts" axis="content-type" scope="row">Posts</th><td headers="axis-document axis-state axis-posts">Ready</td></tr>
</tbody>
</table>
HTML);
$axisSourceTables = array_values(array_filter(
    $axisSourceDocument->children,
    static fn (AstNode $node): bool => $node->type === 'table'
));
$autoScopeDocument = (new MarkdownReader())->read(<<<'HTML'
<table id="auto-scope-grid" data-source="html-reader">
<caption>Auto scope review</caption>
<thead>
<tr><th id="auto-document" scope="auto">Document</th><th id="auto-state" scope="auto">State</th></tr>
</thead>
<tbody>
<tr><th id="auto-posts" scope="auto">Posts</th><td>Ready</td></tr>
</tbody>
</table>
HTML);
$autoScopeTables = array_values(array_filter(
    $autoScopeDocument->children,
    static fn (AstNode $node): bool => $node->type === 'table'
));
$captionMetadataTables = [
    new AstNode('table', [
        'caption' => 'Long caption for reviewer',
        'captionInlines' => [
            new AstNode('text', ['text' => 'Long ']),
            new AstNode('emph', [], [new AstNode('text', ['text' => 'caption'])]),
            new AstNode('text', ['text' => ' for ']),
            new AstNode('link', ['url' => 'https://example.test/review', 'title' => 'Review'], [
                new AstNode('text', ['text' => 'reviewer']),
            ]),
        ],
        'shortCaption' => 'Queue short',
        'shortCaptionInlines' => [
            new AstNode('text', ['text' => 'Queue ']),
            new AstNode('strong', [], [new AstNode('text', ['text' => 'short'])]),
        ],
        'alignments' => ['left', 'right'],
    ], [
        new AstNode('table_head', [], [
            new AstNode('table_row', [], [
                new AstNode('table_cell', ['text' => 'Scope'], [new AstNode('text', ['text' => 'Scope'])]),
                new AstNode('table_cell', ['text' => 'State'], [new AstNode('text', ['text' => 'State'])]),
            ]),
        ]),
        new AstNode('table_body', [], [
            new AstNode('table_row', [], [
                new AstNode('table_cell', ['text' => 'Posts'], [new AstNode('text', ['text' => 'Posts'])]),
                new AstNode('table_cell', ['text' => 'Ready'], [new AstNode('text', ['text' => 'Ready'])]),
            ]),
        ]),
    ]),
];

$blockCaptionTable = new AstNode('table', [
    'caption' => 'Fallback block caption text',
    'captionBlocks' => [
        new AstNode('paragraph', [], [
            new AstNode('text', ['text' => 'Block ']),
            new AstNode('strong', [], [new AstNode('text', ['text' => 'caption'])]),
            new AstNode('text', ['text' => ' for reviewer']),
        ]),
        new AstNode('bullet_list', [], [
            new AstNode('list_item', [], [
                new AstNode('paragraph', [], [new AstNode('text', ['text' => 'Queue note'])]),
            ]),
        ]),
    ],
    'alignments' => ['left', 'right'],
], [
    new AstNode('table_head', [], [
        new AstNode('table_row', [], [
            new AstNode('table_cell', ['text' => 'Scope'], [new AstNode('text', ['text' => 'Scope'])]),
            new AstNode('table_cell', ['text' => 'State'], [new AstNode('text', ['text' => 'State'])]),
        ]),
    ]),
    new AstNode('table_body', [], [
        new AstNode('table_row', [], [
            new AstNode('table_cell', ['text' => 'Posts'], [new AstNode('text', ['text' => 'Posts'])]),
            new AstNode('table_cell', ['text' => 'Ready'], [new AstNode('text', ['text' => 'Ready'])]),
        ]),
    ]),
]);

$overfullWidthTable = new AstNode('table', [
    'caption' => 'Overfull source width audit',
    'alignments' => ['left', 'right', 'center'],
    'widths' => [0.6, 0.6, 0.3],
], [
    new AstNode('table_head', [], [
        new AstNode('table_row', [], [
            new AstNode('table_cell', ['text' => 'Scope'], [new AstNode('text', ['text' => 'Scope'])]),
            new AstNode('table_cell', ['text' => 'Items'], [new AstNode('text', ['text' => 'Items'])]),
            new AstNode('table_cell', ['text' => 'State'], [new AstNode('text', ['text' => 'State'])]),
        ]),
    ]),
    new AstNode('table_body', [], [
        new AstNode('table_row', [], [
            new AstNode('table_cell', ['text' => 'Posts'], [new AstNode('text', ['text' => 'Posts'])]),
            new AstNode('table_cell', ['text' => '42'], [new AstNode('text', ['text' => '42'])]),
            new AstNode('table_cell', ['text' => 'Ready'], [new AstNode('text', ['text' => 'Ready'])]),
        ]),
    ]),
]);

$underfullWidthTable = new AstNode('table', [
    'caption' => 'Underfull source width audit',
    'alignments' => ['left', 'right', 'center'],
    'widths' => [0.2, 0.3, 0.4],
], [
    new AstNode('table_head', [], [
        new AstNode('table_row', [], [
            new AstNode('table_cell', ['text' => 'Scope'], [new AstNode('text', ['text' => 'Scope'])]),
            new AstNode('table_cell', ['text' => 'Items'], [new AstNode('text', ['text' => 'Items'])]),
            new AstNode('table_cell', ['text' => 'State'], [new AstNode('text', ['text' => 'State'])]),
        ]),
    ]),
    new AstNode('table_body', [], [
        new AstNode('table_row', [], [
            new AstNode('table_cell', ['text' => 'Posts'], [new AstNode('text', ['text' => 'Posts'])]),
            new AstNode('table_cell', ['text' => '42'], [new AstNode('text', ['text' => '42'])]),
            new AstNode('table_cell', ['text' => 'Ready'], [new AstNode('text', ['text' => 'Ready'])]),
        ]),
    ]),
]);

$invalidWidthTable = new AstNode('table', [
    'caption' => 'Invalid source width audit',
    'alignments' => ['left', 'right', 'center', 'default'],
    'widths' => [0.25, 'auto', -0.1, null],
], [
    new AstNode('table_head', [], [
        new AstNode('table_row', [], [
            new AstNode('table_cell', ['text' => 'Scope'], [new AstNode('text', ['text' => 'Scope'])]),
            new AstNode('table_cell', ['text' => 'Items'], [new AstNode('text', ['text' => 'Items'])]),
            new AstNode('table_cell', ['text' => 'State'], [new AstNode('text', ['text' => 'State'])]),
            new AstNode('table_cell', ['text' => 'Notes'], [new AstNode('text', ['text' => 'Notes'])]),
        ]),
    ]),
    new AstNode('table_body', [], [
        new AstNode('table_row', [], [
            new AstNode('table_cell', ['text' => 'Posts'], [new AstNode('text', ['text' => 'Posts'])]),
            new AstNode('table_cell', ['text' => '42'], [new AstNode('text', ['text' => '42'])]),
            new AstNode('table_cell', ['text' => 'Ready'], [new AstNode('text', ['text' => 'Ready'])]),
            new AstNode('table_cell', ['text' => 'Review widths'], [new AstNode('text', ['text' => 'Review widths'])]),
        ]),
    ]),
]);

$alignmentAliasTable = new AstNode('table', [
    'caption' => 'Pandoc alignment constructor audit',
    'alignments' => ['AlignLeft', 'AlignRight', 'AlignCenter', 'AlignDefault'],
], [
    new AstNode('table_head', [], [
        new AstNode('table_row', [], [
            new AstNode('table_cell', ['text' => 'Field'], [new AstNode('text', ['text' => 'Field'])]),
            new AstNode('table_cell', ['text' => 'Count'], [new AstNode('text', ['text' => 'Count'])]),
            new AstNode('table_cell', ['text' => 'State'], [new AstNode('text', ['text' => 'State'])]),
            new AstNode('table_cell', ['text' => 'Notes'], [new AstNode('text', ['text' => 'Notes'])]),
        ]),
    ]),
    new AstNode('table_body', [], [
        new AstNode('table_row', [], [
            new AstNode('table_cell', ['text' => 'Posts'], [new AstNode('text', ['text' => 'Posts'])]),
            new AstNode('table_cell', ['text' => '42'], [new AstNode('text', ['text' => '42'])]),
            new AstNode('table_cell', ['text' => 'Ready'], [new AstNode('text', ['text' => 'Ready'])]),
            new AstNode('table_cell', [
                'text' => 'Needs alt text',
                'align' => 'align-right',
            ], [new AstNode('text', ['text' => 'Needs alt text'])]),
        ]),
    ]),
]);

$malformedSpanTable = new AstNode('table', [
    'caption' => 'Malformed source span review',
    'alignments' => ['left', 'right', 'center'],
    'id' => 'malformed-source-span-grid',
], [
    new AstNode('table_body', [], [
        new AstNode('table_row', [], [
            new AstNode('table_cell', [
                'text' => 'Posts',
                'colspan' => '0',
                'rowspan' => 'many',
            ], [new AstNode('text', ['text' => 'Posts'])]),
            new AstNode('table_cell', ['text' => '42'], [new AstNode('text', ['text' => '42'])]),
            new AstNode('table_cell', ['text' => 'Ready'], [new AstNode('text', ['text' => 'Ready'])]),
        ]),
        new AstNode('table_row', [], [
            new AstNode('table_cell', [
                'text' => 'Media',
                'rowspan' => -3,
            ], [new AstNode('text', ['text' => 'Media'])]),
            new AstNode('table_cell', ['text' => '7'], [new AstNode('text', ['text' => '7'])]),
            new AstNode('table_cell', ['text' => 'Review'], [new AstNode('text', ['text' => 'Review'])]),
        ]),
    ]),
]);

$blockContentTable = new AstNode('table', [
    'caption' => 'Cell block content audit',
    'alignments' => ['left', 'right'],
], [
    new AstNode('table_head', [], [
        new AstNode('table_row', [], [
            new AstNode('table_cell', ['text' => 'Packet'], [new AstNode('text', ['text' => 'Packet'])]),
            new AstNode('table_cell', ['text' => 'State'], [new AstNode('text', ['text' => 'State'])]),
        ]),
    ]),
    new AstNode('table_body', [], [
        new AstNode('table_row', [], [
            new AstNode('table_cell', ['text' => 'Review source'], [
                new AstNode('paragraph', [], [
                    new AstNode('text', ['text' => 'Review ']),
                    new AstNode('emph', [], [new AstNode('text', ['text' => 'source'])]),
                ]),
                new AstNode('bullet_list', [], [
                    new AstNode('list_item', [], [
                        new AstNode('paragraph', [], [new AstNode('text', ['text' => 'Image alt text'])]),
                    ]),
                    new AstNode('list_item', [], [
                        new AstNode('paragraph', [], [
                            new AstNode('strong', [], [new AstNode('text', ['text' => 'Resolve captions'])]),
                        ]),
                    ]),
                ]),
            ]),
            new AstNode('table_cell', ['text' => 'Ready'], [new AstNode('text', ['text' => 'Ready'])]),
        ]),
    ]),
]);

$latexNestedTable = new AstNode('table', [
    'caption' => 'Nested LaTeX audit',
    'alignments' => ['left', 'right'],
    'widths' => [0.5, 0.5],
], [
    new AstNode('table_head'),
    new AstNode('table_body', [], [
        new AstNode('table_row', [], [
            new AstNode('table_cell', ['text' => 'Inner scope'], [new AstNode('text', ['text' => 'Inner scope'])]),
            new AstNode('table_cell', ['text' => '42'], [new AstNode('text', ['text' => '42'])]),
        ]),
    ]),
]);

$latexRequirementTable = new AstNode('table', [
    'caption' => 'LaTeX table requirement audit',
    'alignments' => ['left', 'right', 'center'],
], [
    new AstNode('table_head', [], [
        new AstNode('table_row', [], [
            new AstNode('table_cell', ['text' => 'Document', 'colspan' => 2], [new AstNode('text', ['text' => 'Document'])]),
            new AstNode('table_cell', ['text' => 'State'], [new AstNode('text', ['text' => 'State'])]),
        ]),
    ]),
    new AstNode('table_body', [], [
        new AstNode('table_row', [], [
            new AstNode('table_cell', ['text' => 'Posts', 'rowspan' => 2], [new AstNode('text', ['text' => 'Posts'])]),
            new AstNode('table_cell', ['text' => 'Review source', 'colspan' => 2], [
                new AstNode('paragraph', [], [
                    new AstNode('text', ['text' => 'Review ']),
                    new AstNode('emph', [], [new AstNode('text', ['text' => 'source'])]),
                ]),
                new AstNode('bullet_list', [], [
                    new AstNode('list_item', [], [
                        new AstNode('paragraph', [], [new AstNode('text', ['text' => 'Resolve media'])]),
                    ]),
                ]),
            ]),
        ]),
        new AstNode('table_row', [], [
            new AstNode('table_cell', ['text' => 'Ready'], [new AstNode('text', ['text' => 'Ready'])]),
            new AstNode('table_cell', ['text' => 'Nested packet'], [
                new AstNode('paragraph', [], [new AstNode('text', ['text' => 'Nested packet'])]),
                $latexNestedTable,
            ]),
        ]),
    ]),
]);

$latexFooterTable = new AstNode('table', [
    'caption' => 'LaTeX footer audit',
    'alignments' => ['left', 'right'],
], [
    new AstNode('table_head', [], [
        new AstNode('table_row', [], [
            new AstNode('table_cell', ['text' => 'Scope'], [new AstNode('text', ['text' => 'Scope'])]),
            new AstNode('table_cell', ['text' => 'State'], [new AstNode('text', ['text' => 'State'])]),
        ]),
    ]),
    new AstNode('table_body', [], [
        new AstNode('table_row', [], [
            new AstNode('table_cell', ['text' => 'Posts'], [new AstNode('text', ['text' => 'Posts'])]),
            new AstNode('table_cell', ['text' => 'Ready'], [new AstNode('text', ['text' => 'Ready'])]),
        ]),
    ]),
    new AstNode('table_foot', [], [
        new AstNode('table_row', [], [
            new AstNode('table_cell', ['text' => 'Total'], [new AstNode('text', ['text' => 'Total'])]),
            new AstNode('table_cell', ['text' => 'Ready'], [new AstNode('text', ['text' => 'Ready'])]),
        ]),
    ]),
]);

$astAttributeTable = new AstNode('table', [
    'caption' => 'Native AST attribute audit',
    'alignments' => ['left', 'right'],
    'id' => 'native-ast-attr-grid',
    'classes' => ['source-table', 'needs-review'],
    'attributes' => [
        'data-pandoc-source' => 'native-ast',
        'aria-label' => 'Native AST source attributes',
        'onclick' => 'blocked',
    ],
], [
    new AstNode('table_head', [
        'id' => 'native-ast-head',
        'attributes' => [
            'data-section-role' => 'head',
        ],
    ], [
        new AstNode('table_row', [
            'attributes' => [
                'data-row-role' => 'head',
            ],
        ], [
            new AstNode('table_cell', [
                'text' => 'Scope',
                'attributes' => [
                    'data-field' => 'scope',
                    'onmouseover' => 'blocked',
                ],
            ], [new AstNode('text', ['text' => 'Scope'])]),
            new AstNode('table_cell', [
                'text' => 'State',
                'attributes' => [
                    'data-field' => 'state',
                ],
            ], [new AstNode('text', ['text' => 'State'])]),
        ]),
    ]),
    new AstNode('table_body', [
        'id' => 'native-ast-body',
        'rowHeadColumns' => 1,
        'attributes' => [
            'data-section-role' => 'body',
        ],
    ], [
        new AstNode('table_row', [
            'attributes' => [
                'data-row-role' => 'body',
            ],
        ], [
            new AstNode('table_cell', [
                'text' => 'Posts',
                'attributes' => [
                    'data-field' => 'posts',
                ],
            ], [new AstNode('text', ['text' => 'Posts'])]),
            new AstNode('table_cell', [
                'text' => 'Ready',
                'attributes' => [
                    'data-field' => 'ready',
                    'aria-label' => 'Ready state',
                ],
            ], [new AstNode('text', ['text' => 'Ready'])]),
        ]),
    ]),
]);

$sourceRowgroupScopeTable = new AstNode('table', [
    'caption' => 'Source rowgroup accessibility grid',
    'alignments' => ['left', 'right', 'center'],
    'accessibilityHeaders' => true,
    'accessibilityIdPrefix' => 'Source Rowgroup Grid',
], [
    new AstNode('table_head', [], [
        new AstNode('table_row', [], [
            new AstNode('table_cell', [
                'text' => 'Scope',
                'header' => true,
                'htmlAttributes' => [
                    'id' => 'source-rg-scope',
                    'scope' => 'col',
                ],
            ], [new AstNode('text', ['text' => 'Scope'])]),
            new AstNode('table_cell', [
                'text' => 'Count',
                'header' => true,
                'htmlAttributes' => [
                    'id' => 'source-rg-count',
                    'scope' => 'col',
                ],
            ], [new AstNode('text', ['text' => 'Count'])]),
            new AstNode('table_cell', [
                'text' => 'State',
                'header' => true,
                'htmlAttributes' => [
                    'id' => 'source-rg-state',
                    'scope' => 'col',
                ],
            ], [new AstNode('text', ['text' => 'State'])]),
        ]),
    ]),
    new AstNode('table_body', ['htmlAttributes' => ['id' => 'media-body']], [
        new AstNode('table_row', [], [
            new AstNode('table_cell', [
                'text' => 'Media',
                'header' => true,
                'htmlAttributes' => [
                    'id' => 'source-media-group',
                    'scope' => 'rowgroup',
                ],
            ], [new AstNode('text', ['text' => 'Media'])]),
            new AstNode('table_cell', ['text' => '7'], [new AstNode('text', ['text' => '7'])]),
            new AstNode('table_cell', ['text' => 'Needs alt'], [new AstNode('text', ['text' => 'Needs alt'])]),
        ]),
        new AstNode('table_row', [], [
            new AstNode('table_cell', ['text' => 'Images'], [new AstNode('text', ['text' => 'Images'])]),
            new AstNode('table_cell', ['text' => '3'], [new AstNode('text', ['text' => '3'])]),
            new AstNode('table_cell', ['text' => 'Review'], [new AstNode('text', ['text' => 'Review'])]),
        ]),
    ]),
    new AstNode('table_body', ['htmlAttributes' => ['id' => 'pages-body']], [
        new AstNode('table_row', [], [
            new AstNode('table_cell', ['text' => 'Pages'], [new AstNode('text', ['text' => 'Pages'])]),
            new AstNode('table_cell', ['text' => '5'], [new AstNode('text', ['text' => '5'])]),
            new AstNode('table_cell', ['text' => 'Ready'], [new AstNode('text', ['text' => 'Ready'])]),
        ]),
    ]),
]);

$sourceColgroupScopeTable = new AstNode('table', [
    'caption' => 'Source colgroup accessibility grid',
    'alignments' => ['left', 'right', 'center'],
    'widths' => [1 / 3, 1 / 3, 1 / 3],
    'accessibilityHeaders' => true,
    'accessibilityIdPrefix' => 'Source Colgroup Grid',
    'columnSources' => [
        [
            'kind' => 'colgroup',
            'column' => 0,
            'colgroupIndex' => 0,
            'sourceSpan' => 2,
            'spanOffset' => 0,
            'colgroupAttributes' => [
                'htmlAttributes' => [
                    'id' => 'source-import-columns',
                    'data-origin' => 'legacy-doc',
                ],
            ],
        ],
        [
            'kind' => 'colgroup',
            'column' => 1,
            'colgroupIndex' => 0,
            'sourceSpan' => 2,
            'spanOffset' => 1,
            'colgroupAttributes' => [
                'htmlAttributes' => [
                    'id' => 'source-import-columns',
                    'data-origin' => 'legacy-doc',
                ],
            ],
        ],
        [
            'kind' => 'colgroup',
            'column' => 2,
            'colgroupIndex' => 1,
            'sourceSpan' => 1,
            'spanOffset' => 0,
            'colgroupAttributes' => [
                'htmlAttributes' => [
                    'id' => 'source-state-column',
                    'data-origin' => 'legacy-doc',
                ],
            ],
        ],
    ],
], [
    new AstNode('table_head', [], [
        new AstNode('table_row', [], [
            new AstNode('table_cell', [
                'text' => 'Import scope',
                'header' => true,
                'htmlAttributes' => [
                    'id' => 'source-import-scope',
                    'scope' => 'colgroup',
                ],
            ], [new AstNode('text', ['text' => 'Import scope'])]),
            new AstNode('table_cell', [
                'text' => 'Items',
                'header' => true,
                'htmlAttributes' => [
                    'id' => 'source-items',
                    'scope' => 'col',
                ],
            ], [new AstNode('text', ['text' => 'Items'])]),
            new AstNode('table_cell', [
                'text' => 'State',
                'header' => true,
                'htmlAttributes' => [
                    'id' => 'source-state',
                    'scope' => 'col',
                ],
            ], [new AstNode('text', ['text' => 'State'])]),
        ]),
    ]),
    new AstNode('table_body', [], [
        new AstNode('table_row', [], [
            new AstNode('table_cell', ['text' => 'Posts'], [new AstNode('text', ['text' => 'Posts'])]),
            new AstNode('table_cell', ['text' => '42'], [new AstNode('text', ['text' => '42'])]),
            new AstNode('table_cell', ['text' => 'Ready'], [new AstNode('text', ['text' => 'Ready'])]),
        ]),
        new AstNode('table_row', [], [
            new AstNode('table_cell', ['text' => 'Media'], [new AstNode('text', ['text' => 'Media'])]),
            new AstNode('table_cell', ['text' => '7'], [new AstNode('text', ['text' => '7'])]),
            new AstNode('table_cell', ['text' => 'Review'], [new AstNode('text', ['text' => 'Review'])]),
        ]),
    ]),
]);

$sourceReferenceGeometryTable = new AstNode('table', [
    'caption' => 'Source header reference geometry audit',
    'alignments' => ['left', 'right', 'center'],
    'accessibilityHeaders' => true,
    'accessibilityIdPrefix' => 'Reference Geometry Grid',
], [
    new AstNode('table_head', [], [
        new AstNode('table_row', [], [
            new AstNode('table_cell', [
                'text' => 'Migration scope',
                'colspan' => 2,
                'htmlAttributes' => [
                    'id' => 'source-scope-span',
                    'scope' => 'colgroup',
                ],
            ], [new AstNode('text', ['text' => 'Migration scope'])]),
            new AstNode('table_cell', [
                'text' => 'State',
                'htmlAttributes' => [
                    'id' => 'source-state-span',
                    'scope' => 'col',
                ],
            ], [new AstNode('text', ['text' => 'State'])]),
        ]),
    ]),
    new AstNode('table_body', ['rowHeadColumns' => 1], [
        new AstNode('table_row', [], [
            new AstNode('table_cell', [
                'text' => 'Posts',
                'rowspan' => 2,
                'htmlAttributes' => [
                    'id' => 'source-posts-group',
                    'scope' => 'rowgroup',
                ],
            ], [new AstNode('text', ['text' => 'Posts'])]),
            new AstNode('table_cell', [
                'text' => '42',
                'htmlAttributes' => [
                    'headers' => 'source-scope-span source-posts-group',
                ],
            ], [new AstNode('text', ['text' => '42'])]),
            new AstNode('table_cell', [
                'text' => 'Ready',
                'htmlAttributes' => [
                    'headers' => 'source-state-span source-posts-group',
                ],
            ], [new AstNode('text', ['text' => 'Ready'])]),
        ]),
        new AstNode('table_row', [], [
            new AstNode('table_cell', [
                'text' => '7',
                'htmlAttributes' => [
                    'headers' => 'source-scope-span source-posts-group',
                ],
            ], [new AstNode('text', ['text' => '7'])]),
            new AstNode('table_cell', [
                'text' => 'Review',
                'htmlAttributes' => [
                    'headers' => 'source-state-span source-posts-group',
                ],
            ], [new AstNode('text', ['text' => 'Review'])]),
        ]),
    ]),
]);

$invalidSourceScopeTable = new AstNode('table', [
    'caption' => 'Invalid source scope accessibility grid',
    'alignments' => ['left', 'right'],
    'accessibilityHeaders' => true,
    'accessibilityIdPrefix' => 'Invalid Scope Grid',
], [
    new AstNode('table_head', [], [
        new AstNode('table_row', [], [
            new AstNode('table_cell', [
                'text' => 'Document',
                'htmlAttributes' => [
                    'id' => 'invalid-scope-document',
                    'scope' => 'columnish',
                ],
            ], [new AstNode('text', ['text' => 'Document'])]),
            new AstNode('table_cell', [
                'text' => 'State',
                'htmlAttributes' => [
                    'id' => 'invalid-scope-state',
                    'scope' => 'col',
                ],
            ], [new AstNode('text', ['text' => 'State'])]),
        ]),
    ]),
    new AstNode('table_body', [], [
        new AstNode('table_row', [], [
            new AstNode('table_cell', ['text' => 'Posts'], [new AstNode('text', ['text' => 'Posts'])]),
            new AstNode('table_cell', ['text' => 'Ready'], [new AstNode('text', ['text' => 'Ready'])]),
        ]),
    ]),
]);

$abbreviatedHeaderTable = new AstNode('table', [
    'caption' => 'Abbreviated header review',
    'alignments' => ['left', 'right'],
    'accessibilityHeaders' => true,
    'accessibilityIdPrefix' => 'Abbr Grid',
], [
    new AstNode('table_head', [], [
        new AstNode('table_row', [], [
            new AstNode('table_cell', [
                'text' => 'Document',
                'htmlAttributes' => [
                    'id' => 'abbr-document-source',
                    'abbr' => 'Doc',
                ],
            ], [new AstNode('text', ['text' => 'Document'])]),
            new AstNode('table_cell', [
                'text' => 'Status',
                'attributes' => [
                    'abbr' => 'St',
                ],
            ], [new AstNode('text', ['text' => 'Status'])]),
        ]),
    ]),
    new AstNode('table_body', [], [
        new AstNode('table_row', [], [
            new AstNode('table_cell', ['text' => 'Migration packet'], [new AstNode('text', ['text' => 'Migration packet'])]),
            new AstNode('table_cell', ['text' => 'Ready'], [new AstNode('text', ['text' => 'Ready'])]),
        ]),
    ]),
]);

$duplicateHeaderTable = new AstNode('table', [
    'caption' => 'Duplicate source header id audit',
    'alignments' => ['left', 'right', 'center'],
    'accessibilityHeaders' => true,
    'accessibilityIdPrefix' => 'Duplicate Header Grid',
], [
    new AstNode('table_head', [], [
        new AstNode('table_row', [], [
            new AstNode('table_cell', [
                'text' => 'Document A',
                'htmlAttributes' => [
                    'id' => 'duplicate-document',
                    'scope' => 'col',
                ],
            ], [new AstNode('text', ['text' => 'Document A'])]),
            new AstNode('table_cell', [
                'text' => 'Document B',
                'htmlAttributes' => [
                    'id' => 'duplicate-document',
                    'scope' => 'col',
                ],
            ], [new AstNode('text', ['text' => 'Document B'])]),
            new AstNode('table_cell', [
                'text' => 'State',
                'htmlAttributes' => [
                    'id' => 'duplicate-state',
                    'scope' => 'col',
                    'headers' => 'duplicate-document',
                ],
            ], [new AstNode('text', ['text' => 'State'])]),
        ]),
    ]),
    new AstNode('table_body', [], [
        new AstNode('table_row', [], [
            new AstNode('table_cell', ['text' => 'Posts'], [new AstNode('text', ['text' => 'Posts'])]),
            new AstNode('table_cell', [
                'text' => '42',
                'htmlAttributes' => [
                    'headers' => 'duplicate-document missing-document',
                ],
            ], [new AstNode('text', ['text' => '42'])]),
            new AstNode('table_cell', ['text' => 'Ready'], [new AstNode('text', ['text' => 'Ready'])]),
        ]),
    ]),
]);

$duplicateHeaderTokenTable = new AstNode('table', [
    'caption' => 'Duplicate source headers token audit',
    'alignments' => ['left', 'right', 'center'],
    'accessibilityHeaders' => true,
    'accessibilityIdPrefix' => 'Duplicate Token Grid',
], [
    new AstNode('table_head', [], [
        new AstNode('table_row', [], [
            new AstNode('table_cell', [
                'text' => 'Document',
                'htmlAttributes' => [
                    'id' => 'dup-token-document',
                    'scope' => 'col',
                ],
            ], [new AstNode('text', ['text' => 'Document'])]),
            new AstNode('table_cell', [
                'text' => 'Count',
                'htmlAttributes' => [
                    'id' => 'dup-token-count',
                    'scope' => 'col',
                ],
            ], [new AstNode('text', ['text' => 'Count'])]),
            new AstNode('table_cell', [
                'text' => 'State',
                'htmlAttributes' => [
                    'id' => 'dup-token-state',
                    'scope' => 'col',
                    'headers' => 'dup-token-document dup-token-document',
                ],
            ], [new AstNode('text', ['text' => 'State'])]),
        ]),
    ]),
    new AstNode('table_body', [], [
        new AstNode('table_row', [], [
            new AstNode('table_cell', ['text' => 'Posts'], [new AstNode('text', ['text' => 'Posts'])]),
            new AstNode('table_cell', [
                'text' => '42',
                'htmlAttributes' => [
                    'headers' => 'dup-token-document dup-token-count dup-token-count',
                ],
            ], [new AstNode('text', ['text' => '42'])]),
            new AstNode('table_cell', ['text' => 'Ready'], [new AstNode('text', ['text' => 'Ready'])]),
        ]),
    ]),
]);

$emptyReviewTable = new AstNode('table', [
    'caption' => 'Empty import table audit',
], [
    new AstNode('table_head'),
    new AstNode('table_body', [
        'htmlAttributes' => [
            'id' => 'empty-body',
        ],
    ]),
]);

$document = new AstNode('document', [], [
    new AstNode('table', [
        'caption' => 'Migration review grid',
        'alignments' => ['left', 'right', 'center', 'default'],
        'widths' => [0.25, 0.25, 0.25, 0.25],
    ], [
        new AstNode('table_head', [], [
            new AstNode('table_row', [], [
                new AstNode('table_cell', ['text' => 'Scope', 'colspan' => 2], [new AstNode('text', ['text' => 'Scope'])]),
                new AstNode('table_cell', ['text' => 'Status'], [new AstNode('text', ['text' => 'Status'])]),
            ]),
        ]),
        new AstNode('table_body', ['rowHeadColumns' => 1], [
            new AstNode('table_row', [], [
                new AstNode('table_cell', ['text' => 'Posts', 'rowspan' => 2], [new AstNode('text', ['text' => 'Posts'])]),
                new AstNode('table_cell', ['text' => '42'], [new AstNode('text', ['text' => '42'])]),
                new AstNode('table_cell', ['text' => 'Ready'], [new AstNode('text', ['text' => 'Ready'])]),
            ]),
            new AstNode('table_row', [], [
                new AstNode('table_cell', ['text' => '7'], [new AstNode('text', ['text' => '7'])]),
                new AstNode('table_cell', ['text' => 'Review'], [new AstNode('text', ['text' => 'Review'])]),
            ]),
        ]),
    ]),
    new AstNode('table', [
        'caption' => 'Section boundary review',
        'alignments' => ['left', 'right'],
    ], [
        new AstNode('table_head', [], [
            new AstNode('table_row', [], [
                new AstNode('table_cell', ['text' => 'Scope', 'rowspan' => 2], [new AstNode('text', ['text' => 'Scope'])]),
                new AstNode('table_cell', ['text' => 'Status'], [new AstNode('text', ['text' => 'Status'])]),
            ]),
        ]),
        new AstNode('table_body', [], [
            new AstNode('table_row', [], [
                new AstNode('table_cell', ['text' => 'Pages'], [new AstNode('text', ['text' => 'Pages'])]),
                new AstNode('table_cell', ['text' => 'Needs review'], [new AstNode('text', ['text' => 'Needs review'])]),
            ]),
        ]),
    ]),
    new AstNode('table', [
        'caption' => 'Declared column overflow review',
        'alignments' => ['left', 'right'],
        'widths' => [0.5, 0.5],
    ], [
        new AstNode('table_head', [], [
            new AstNode('table_row', [], [
                new AstNode('table_cell', ['text' => 'Scope'], [new AstNode('text', ['text' => 'Scope'])]),
                new AstNode('table_cell', ['text' => 'Status'], [new AstNode('text', ['text' => 'Status'])]),
            ]),
        ]),
        new AstNode('table_body', ['rowHeadColumns' => 1], [
            new AstNode('table_row', [], [
                new AstNode('table_cell', ['text' => 'Posts', 'rowspan' => 2], [new AstNode('text', ['text' => 'Posts'])]),
                new AstNode('table_cell', ['text' => 'Ready'], [new AstNode('text', ['text' => 'Ready'])]),
            ]),
            new AstNode('table_row', [], [
                new AstNode('table_cell', ['text' => 'Needs media'], [new AstNode('text', ['text' => 'Needs media'])]),
                new AstNode('table_cell', ['text' => 'Overflow note'], [new AstNode('text', ['text' => 'Overflow note'])]),
            ]),
            new AstNode('table_row', [], [
                new AstNode('table_cell', ['text' => 'Full width audit note', 'colspan' => 3], [new AstNode('text', ['text' => 'Full width audit note'])]),
            ]),
        ]),
    ]),
    new AstNode('table', [
        'caption' => 'Body-local head row review',
        'alignments' => ['left', 'right', 'center'],
    ], [
        new AstNode('table_head', [], [
            new AstNode('table_row', [], [
                new AstNode('table_cell', ['text' => 'Document'], [new AstNode('text', ['text' => 'Document'])]),
                new AstNode('table_cell', ['text' => 'Items'], [new AstNode('text', ['text' => 'Items'])]),
                new AstNode('table_cell', ['text' => 'State'], [new AstNode('text', ['text' => 'State'])]),
            ]),
        ]),
        new AstNode('table_body', [
            'rowHeadColumns' => 1,
            'headRows' => [
                new AstNode('table_row', [], [
                    new AstNode('table_cell', ['text' => 'Batch'], [new AstNode('text', ['text' => 'Batch'])]),
                    new AstNode('table_cell', ['text' => 'Queue'], [new AstNode('text', ['text' => 'Queue'])]),
                    new AstNode('table_cell', ['text' => 'Decision'], [new AstNode('text', ['text' => 'Decision'])]),
                ]),
            ],
        ], [
            new AstNode('table_row', [], [
                new AstNode('table_cell', ['text' => 'Posts', 'rowspan' => 2], [new AstNode('text', ['text' => 'Posts'])]),
                new AstNode('table_cell', ['text' => '42'], [new AstNode('text', ['text' => '42'])]),
                new AstNode('table_cell', ['text' => 'Review'], [new AstNode('text', ['text' => 'Review'])]),
            ]),
            new AstNode('table_row', [], [
                new AstNode('table_cell', ['text' => '7'], [new AstNode('text', ['text' => '7'])]),
                new AstNode('table_cell', ['text' => 'Import'], [new AstNode('text', ['text' => 'Import'])]),
            ]),
        ]),
    ]),
    new AstNode('table', [
        'caption' => 'Malformed overlap review',
        'alignments' => ['left', 'right'],
    ], [
        new AstNode('table_body', ['rowHeadColumns' => 1], [
            new AstNode('table_row', [], [
                new AstNode('table_cell', ['text' => 'Posts', 'rowspan' => 2, 'colspan' => 2], [new AstNode('text', ['text' => 'Posts'])]),
            ]),
            new AstNode('table_row', [], [
                new AstNode('table_cell', ['text' => 'Unexpected source cell'], [new AstNode('text', ['text' => 'Unexpected source cell'])]),
            ]),
        ]),
    ]),
    new AstNode('table', [
        'caption' => 'Accessible review grid',
        'alignments' => ['left', 'right', 'center'],
        'accessibilityHeaders' => true,
        'accessibilityIdPrefix' => 'Migration Grid',
    ], [
        new AstNode('table_head', [], [
            new AstNode('table_row', [], [
                new AstNode('table_cell', ['text' => 'Document', 'colspan' => 2], [new AstNode('text', ['text' => 'Document'])]),
                new AstNode('table_cell', ['text' => 'State'], [new AstNode('text', ['text' => 'State'])]),
            ]),
        ]),
        new AstNode('table_body', [
            'rowHeadColumns' => 1,
            'headRows' => [
                new AstNode('table_row', [], [
                    new AstNode('table_cell', ['text' => 'Batch'], [new AstNode('text', ['text' => 'Batch'])]),
                    new AstNode('table_cell', ['text' => 'Queue'], [new AstNode('text', ['text' => 'Queue'])]),
                    new AstNode('table_cell', ['text' => 'Decision'], [new AstNode('text', ['text' => 'Decision'])]),
                ]),
            ],
        ], [
            new AstNode('table_row', [], [
                new AstNode('table_cell', ['text' => 'Posts', 'rowspan' => 2], [new AstNode('text', ['text' => 'Posts'])]),
                new AstNode('table_cell', ['text' => '42'], [new AstNode('text', ['text' => '42'])]),
                new AstNode('table_cell', ['text' => 'Review'], [new AstNode('text', ['text' => 'Review'])]),
            ]),
            new AstNode('table_row', [], [
                new AstNode('table_cell', ['text' => '7'], [new AstNode('text', ['text' => '7'])]),
                new AstNode('table_cell', ['text' => 'Import'], [new AstNode('text', ['text' => 'Import'])]),
            ]),
        ]),
    ]),
    new AstNode('table', [
        'caption' => 'Source attributed grid',
        'alignments' => ['left', 'right'],
        'accessibilityHeaders' => true,
        'accessibilityIdPrefix' => 'Source Grid',
        'id' => 'source-grid',
        'classes' => ['wp-import', 'needs-review'],
        'attributes' => [
            'origin' => 'docx',
        ],
        'htmlAttributes' => [
            'id' => 'source-grid',
            'class' => 'wp-import needs-review',
            'data-origin' => 'docx',
            'aria-label' => 'Source attributed review grid',
        ],
    ], [
        new AstNode('table_head', [
            'htmlAttributes' => [
                'id' => 'source-grid-head',
                'data-section' => 'thead',
            ],
        ], [
            new AstNode('table_row', [
                'htmlAttributes' => [
                    'data-row' => 'source-head-1',
                ],
            ], [
                new AstNode('table_cell', [
                    'text' => 'Scope',
                    'htmlAttributes' => [
                        'id' => 'docx-source-scope',
                        'class' => 'source-cell',
                        'data-origin' => 'docx',
                    ],
                ], [new AstNode('text', ['text' => 'Scope'])]),
                new AstNode('table_cell', [
                    'text' => 'Status',
                    'id' => 'ast-status-source',
                    'classes' => ['ast-header'],
                ], [new AstNode('text', ['text' => 'Status'])]),
            ]),
        ]),
        new AstNode('table_body', [
            'htmlAttributes' => [
                'id' => 'source-grid-body',
                'data-section' => 'tbody',
            ],
        ], [
            new AstNode('table_row', [
                'htmlAttributes' => [
                    'data-row' => 'source-body-1',
                ],
            ], [
                new AstNode('table_cell', [
                    'text' => 'Posts',
                    'htmlAttributes' => [
                        'class' => 'body-source',
                        'data-origin' => 'docx',
                    ],
                ], [new AstNode('text', ['text' => 'Posts'])]),
                new AstNode('table_cell', [
                    'text' => 'Ready',
                    'htmlAttributes' => [
                        'headers' => 'legacy-status',
                        'data-origin' => 'docx',
                    ],
                ], [new AstNode('text', ['text' => 'Ready'])]),
            ]),
        ]),
    ]),
    new AstNode('table', [
        'caption' => 'Source scoped accessibility grid',
        'alignments' => ['left', 'right', 'center'],
        'accessibilityHeaders' => true,
        'accessibilityIdPrefix' => 'Source Scope Grid',
    ], [
        new AstNode('table_head', [], [
            new AstNode('table_row', [], [
                new AstNode('table_cell', [
                    'text' => 'Document',
                    'htmlAttributes' => [
                        'id' => 'source-document',
                        'scope' => 'col',
                    ],
                ], [new AstNode('text', ['text' => 'Document'])]),
                new AstNode('table_cell', [
                    'text' => 'Count',
                    'htmlAttributes' => [
                        'id' => 'source-count',
                        'scope' => 'col',
                    ],
                ], [new AstNode('text', ['text' => 'Count'])]),
                new AstNode('table_cell', [
                    'text' => 'State',
                    'htmlAttributes' => [
                        'id' => 'source-state',
                        'scope' => 'col',
                        'headers' => 'source-document',
                    ],
                ], [new AstNode('text', ['text' => 'State'])]),
            ]),
        ]),
        new AstNode('table_body', ['rowHeadColumns' => 1], [
            new AstNode('table_row', [], [
                new AstNode('table_cell', [
                    'text' => 'Posts',
                    'rowspan' => 2,
                    'htmlAttributes' => [
                        'id' => 'source-posts',
                        'scope' => 'row',
                    ],
                ], [new AstNode('text', ['text' => 'Posts'])]),
                new AstNode('table_cell', [
                    'text' => '42',
                    'htmlAttributes' => [
                        'headers' => 'legacy-count source-posts',
                    ],
                ], [new AstNode('text', ['text' => '42'])]),
                new AstNode('table_cell', ['text' => 'Ready'], [new AstNode('text', ['text' => 'Ready'])]),
            ]),
            new AstNode('table_row', [], [
                new AstNode('table_cell', ['text' => '7'], [new AstNode('text', ['text' => '7'])]),
                new AstNode('table_cell', ['text' => 'Review'], [new AstNode('text', ['text' => 'Review'])]),
            ]),
        ]),
    ]),
    new AstNode('table', [
        'caption' => 'Nested table packet review',
        'alignments' => ['left', 'right'],
    ], [
        new AstNode('table_head', [], [
            new AstNode('table_row', [], [
                new AstNode('table_cell', ['text' => 'Packet', 'header' => true], [new AstNode('text', ['text' => 'Packet'])]),
                new AstNode('table_cell', ['text' => 'State', 'header' => true], [new AstNode('text', ['text' => 'State'])]),
            ]),
        ]),
        new AstNode('table_body', [], [
            new AstNode('table_row', [], [
                new AstNode('table_cell', ['text' => 'Nested review packet'], [
                    new AstNode('paragraph', [], [new AstNode('text', ['text' => 'Nested review packet'])]),
                    new AstNode('table', [
                        'caption' => 'Nested queue audit',
                        'alignments' => ['left', 'right'],
                        'widths' => [0.5, 0.5],
                    ], [
                        new AstNode('table_head'),
                        new AstNode('table_body', [], [
                            new AstNode('table_row', [], [
                                new AstNode('table_cell', ['text' => 'Inner posts'], [new AstNode('text', ['text' => 'Inner posts'])]),
                                new AstNode('table_cell', ['text' => '42'], [new AstNode('text', ['text' => '42'])]),
                            ]),
                        ]),
                    ]),
                ]),
                new AstNode('table_cell', ['text' => 'Ready'], [new AstNode('text', ['text' => 'Ready'])]),
            ]),
        ]),
    ]),
    $abbreviatedHeaderTable,
    $duplicateHeaderTable,
    $duplicateHeaderTokenTable,
    $emptyReviewTable,
    new AstNode('table', [
        'caption' => 'Implicit source shift review',
        'id' => 'implicit-source-shift-grid',
    ], [
        new AstNode('table_body', [], [
            new AstNode('table_row', [], [
                new AstNode('table_cell', ['text' => 'Merged source', 'rowspan' => 2, 'colspan' => 2], [new AstNode('text', ['text' => 'Merged source'])]),
            ]),
            new AstNode('table_row', [], [
                new AstNode('table_cell', ['text' => 'Unexpected source cell'], [new AstNode('text', ['text' => 'Unexpected source cell'])]),
                new AstNode('table_cell', ['text' => 'Second conflict'], [new AstNode('text', ['text' => 'Second conflict'])]),
            ]),
        ]),
    ]),
    ...$rowspanZeroTables,
    ...$colgroupAlignmentTables,
    ...$columnBackgroundTables,
    ...$columnBorderPresentationTables,
    ...$decimalAlignmentTables,
    ...$cellDecimalAlignmentTables,
    ...$colgroupMismatchTables,
    ...$malformedColumnSpanTables,
    ...$inheritedAlignmentTables,
    ...$verticalAlignmentTables,
    ...$legacyFrameTables,
    ...$legacySpacingTables,
    ...$cellNoWrapTables,
    ...$cellDimensionTables,
    ...$rowBackgroundTables,
    ...$rowBorderPresentationTables,
    ...$sectionPresentationTables,
    ...$cellBackgroundTables,
    ...$cellBorderPresentationTables,
    ...$cellSideBorderPresentationTables,
    ...$duplicateSourceIdTables,
    ...$backgroundColorTables,
    ...$layoutWidthTables,
    ...$layoutHeightTables,
    ...$layoutModeTables,
    ...$borderCollapseTables,
    ...$borderPresentationTables,
    ...$placementAlignmentTables,
    ...$directionalityTables,
    ...$localizationTables,
    ...$readerHandoffTables,
    ...$visualRowHeadColspanTables,
    ...$multiBodyRowHeadTables,
    ...$captionSourceTables,
    ...$sideCaptionTables,
    ...$legacyCaptionAlignTables,
    ...$axisSourceTables,
    ...$autoScopeTables,
    ...$captionMetadataTables,
    $blockCaptionTable,
    $malformedSpanTable,
    $underfullWidthTable,
    $invalidWidthTable,
    $alignmentAliasTable,
    $blockContentTable,
    $latexRequirementTable,
    $latexFooterTable,
    $astAttributeTable,
    $sourceRowgroupScopeTable,
    $sourceColgroupScopeTable,
    $sourceReferenceGeometryTable,
    $invalidSourceScopeTable,
]);

$blocks = (new WordPressBlockWriter())->write($document);

if (($argv[1] ?? '') === '--self-test') {
    $overfullWidthPacket = TableGeometry::reviewPacket($overfullWidthTable, ['accessibility' => false]);
    if (($overfullWidthPacket['widthSummary']['normalizedWidths'] ?? null) !== [0.4, 0.4, 0.2]) {
        throw new RuntimeException('Table geometry self-test missing normalized overfull source width metadata');
    }
    if (($overfullWidthPacket['summary']['diagnosticCodes'] ?? null) !== ['table-widths-exceed-full-width']) {
        throw new RuntimeException('Table geometry self-test missing overfull source width diagnostic');
    }
    if (($overfullWidthPacket['columns'][0]['percentWidth'] ?? null) !== 60.0 || ($overfullWidthPacket['columns'][2]['normalizedWidth'] ?? null) !== 0.2) {
        throw new RuntimeException('Table geometry self-test missing per-column width percentages');
    }
    json_encode($overfullWidthPacket, JSON_THROW_ON_ERROR);

    $underfullWidthPacket = TableGeometry::reviewPacket($underfullWidthTable, ['accessibility' => false]);
    if (($underfullWidthPacket['summary']['diagnosticCodes'] ?? null) !== ['table-widths-underfill-full-width']) {
        throw new RuntimeException('Table geometry self-test missing underfull source width diagnostic');
    }
    if (($underfullWidthPacket['widthSummary']['underflowAmount'] ?? null) !== 0.1 || ($underfullWidthPacket['widthSummary']['normalizedWidths'] ?? null) !== [0.222222, 0.333333, 0.444444]) {
        throw new RuntimeException('Table geometry self-test missing normalized underfull source width metadata');
    }
    if (($underfullWidthPacket['columns'][1]['percentWidth'] ?? null) !== 30.0 || ($underfullWidthPacket['columns'][2]['normalizedWidth'] ?? null) !== 0.444444) {
        throw new RuntimeException('Table geometry self-test missing per-column underfull width percentages');
    }
    $underfullWidthBlock = '<figure class="wp-block-table"><table><colgroup><col style="width:20%"/><col style="width:30%"/><col style="width:40%"/></colgroup><thead><tr><th style="text-align:left">Scope</th><th style="text-align:right">Items</th><th style="text-align:center">State</th></tr></thead><tbody><tr><td style="text-align:left">Posts</td><td style="text-align:right">42</td><td style="text-align:center">Ready</td></tr></tbody></table><figcaption class="wp-element-caption">Underfull source width audit</figcaption></figure>';
    if (!str_contains($blocks, $underfullWidthBlock)) {
        throw new RuntimeException('Table geometry self-test missing underfull source width review table');
    }
    json_encode($underfullWidthPacket, JSON_THROW_ON_ERROR);

    $invalidWidthPacket = TableGeometry::reviewPacket($invalidWidthTable, ['accessibility' => false]);
    if (($invalidWidthPacket['summary']['diagnosticCodes'] ?? null) !== ['table-widths-have-invalid-values']) {
        throw new RuntimeException('Table geometry self-test missing invalid width diagnostic');
    }
    if (($invalidWidthPacket['widthSummary']['invalidWidthColumns'] ?? null) !== [1, 2]) {
        throw new RuntimeException('Table geometry self-test missing invalid width column summary');
    }
    if (($invalidWidthPacket['widthSummary']['invalidWidths'][0]['rawValue'] ?? null) !== 'auto' || ($invalidWidthPacket['widthSummary']['invalidWidths'][1]['rawValue'] ?? null) !== -0.1) {
        throw new RuntimeException('Table geometry self-test missing invalid width raw values');
    }
    if (($invalidWidthPacket['widthSummary']['validWidthColumns'] ?? null) !== [0] || ($invalidWidthPacket['widthSummary']['missingColumns'] ?? null) !== [1, 2, 3]) {
        throw new RuntimeException('Table geometry self-test missing valid/missing width provenance');
    }
    $invalidWidthBlock = '<figure class="wp-block-table"><table><thead><tr><th style="text-align:left">Scope</th><th style="text-align:right">Items</th><th style="text-align:center">State</th><th>Notes</th></tr></thead><tbody><tr><td style="text-align:left">Posts</td><td style="text-align:right">42</td><td style="text-align:center">Ready</td><td>Review widths</td></tr></tbody></table><figcaption class="wp-element-caption">Invalid source width audit</figcaption></figure>';
    if (!str_contains($blocks, $invalidWidthBlock)) {
        throw new RuntimeException('Table geometry self-test missing invalid source width review table');
    }
    json_encode($invalidWidthPacket, JSON_THROW_ON_ERROR);

    $alignmentAliasPacket = TableGeometry::reviewPacket($alignmentAliasTable, ['accessibility' => false]);
    if (array_map(static fn (array $column): string => $column['alignment'], $alignmentAliasPacket['columns'] ?? []) !== ['left', 'right', 'center', 'default']) {
        throw new RuntimeException('Table geometry self-test missing Pandoc alignment constructor normalization');
    }
    if (TableGeometry::alignments($alignmentAliasTable, 4) !== ['left', 'right', 'center', 'default']) {
        throw new RuntimeException('Table geometry self-test missing normalized table alignment aliases');
    }
    if (TableGeometry::cellAlignment($alignmentAliasTable, 3, $alignmentAliasTable->children[1]->children[0]->children[3]) !== 'right') {
        throw new RuntimeException('Table geometry self-test missing normalized cell alignment alias');
    }
    if (!str_contains($blocks, '<thead><tr><th style="text-align:left">Field</th><th style="text-align:right">Count</th><th style="text-align:center">State</th><th>Notes</th></tr></thead>')) {
        throw new RuntimeException('Table geometry self-test missing normalized constructor alignments in WordPress header output');
    }
    if (!str_contains($blocks, '<tbody><tr><td style="text-align:left">Posts</td><td style="text-align:right">42</td><td style="text-align:center">Ready</td><td style="text-align:right">Needs alt text</td></tr></tbody>')) {
        throw new RuntimeException('Table geometry self-test missing normalized constructor alignments in WordPress body output');
    }
    if (!str_contains($blocks, '<figcaption class="wp-element-caption">Pandoc alignment constructor audit</figcaption>')) {
        throw new RuntimeException('Table geometry self-test missing Pandoc alignment constructor audit caption');
    }
    json_encode($alignmentAliasPacket, JSON_THROW_ON_ERROR);

    $migrationGrids = TableGeometry::sectionGrids($document->children[0]);
    $columnSpecs = TableGeometry::columnSpecs($document->children[0], 5);
    $cellCoverage = TableGeometry::cellCoverage($document->children[0]);
    if (array_map(static fn (array $spec): string => $spec['alignment'], $columnSpecs) !== ['left', 'right', 'center', 'default', 'default']) {
        throw new RuntimeException('Table geometry self-test missing normalized column alignment specs');
    }
    if (array_map(static fn (array $spec): ?float => $spec['width'], $columnSpecs) !== [0.25, 0.25, 0.25, 0.25, null]) {
        throw new RuntimeException('Table geometry self-test missing normalized column width specs');
    }
    if (array_map(static fn (array $spec): bool => $spec['declared'], $columnSpecs) !== [true, true, true, true, false]) {
        throw new RuntimeException('Table geometry self-test missing implicit column spec marker');
    }

    if (($migrationGrids[0]['rows'][0][1]['kind'] ?? null) !== 'covered' || ($migrationGrids[0]['rows'][0][1]['covering'] ?? null) !== 'colspan') {
        throw new RuntimeException('Table geometry self-test missing head colspan covered-slot report');
    }
    if (($migrationGrids[0]['rows'][0][3]['kind'] ?? null) !== 'missing') {
        throw new RuntimeException('Table geometry self-test missing head trailing missing-slot report');
    }
    if (($migrationGrids[1]['rows'][1][0]['kind'] ?? null) !== 'covered' || ($migrationGrids[1]['rows'][1][0]['covering'] ?? null) !== 'rowspan') {
        throw new RuntimeException('Table geometry self-test missing body rowspan covered-slot report');
    }
    if (($migrationGrids[1]['rows'][1][3]['kind'] ?? null) !== 'missing') {
        throw new RuntimeException('Table geometry self-test missing body trailing missing-slot report');
    }
    $expectedOccupiedSlots = [
        ['row' => 0, 'column' => 0, 'covering' => 'anchor'],
        ['row' => 1, 'column' => 0, 'covering' => 'rowspan'],
    ];
    if (($migrationGrids[1]['rows'][0][0]['occupiedSlots'] ?? null) !== $expectedOccupiedSlots) {
        throw new RuntimeException('Table geometry self-test missing anchor-cell occupied-slot report');
    }
    if (($cellCoverage[0]['section'] ?? null) !== 'head' || ($cellCoverage[0]['columns'] ?? null) !== [0, 1]) {
        throw new RuntimeException('Table geometry self-test missing head cell visual coverage report');
    }
    if (($cellCoverage[0]['columnAlignments'] ?? null) !== ['left', 'right'] || ($cellCoverage[0]['widths'] ?? null) !== [0.25, 0.25]) {
        throw new RuntimeException('Table geometry self-test missing covered column specs');
    }
    if (
        ($cellCoverage[0]['normalizedWidths'] ?? null) !== [0.25, 0.25]
        || ($cellCoverage[0]['percentWidths'] ?? null) !== [25.0, 25.0]
        || ($cellCoverage[0]['widthTotal'] ?? null) !== 0.5
        || ($cellCoverage[0]['normalizedWidthTotal'] ?? null) !== 0.5
        || ($cellCoverage[0]['percentWidthTotal'] ?? null) !== 50.0
        || ($cellCoverage[0]['hasCompleteWidths'] ?? null) !== true
    ) {
        throw new RuntimeException('Table geometry self-test missing normalized cell span width metadata');
    }
    if (($cellCoverage[2]['section'] ?? null) !== 'body' || ($cellCoverage[2]['rowspan'] ?? null) !== 2 || ($cellCoverage[2]['columns'] ?? null) !== [0]) {
        throw new RuntimeException('Table geometry self-test missing rowspanned body cell coverage report');
    }
    if (($cellCoverage[2]['occupiedSlots'] ?? null) !== $expectedOccupiedSlots) {
        throw new RuntimeException('Table geometry self-test missing rowspanned coverage occupied-slot report');
    }
    if (($cellCoverage[5]['sourceCell'] ?? null) !== 0 || ($cellCoverage[5]['sourceColumn'] ?? null) !== 0 || ($cellCoverage[5]['column'] ?? null) !== 1) {
        throw new RuntimeException('Table geometry self-test missing source-to-visual coverage coordinates');
    }
    $writerDowngrades = TableGeometry::writerDowngradeDiagnostics($document->children[0], 'markdown');
    if (array_map(static fn (array $diagnostic): string => $diagnostic['code'], $writerDowngrades) !== ['markdown-column-widths-approximated', 'markdown-row-headers-flattened', 'markdown-colspan-flattened', 'markdown-rowspan-flattened']) {
        throw new RuntimeException('Table geometry self-test missing Markdown writer downgrade diagnostics');
    }
    if (($writerDowngrades[1]['reason'] ?? null) !== 'row-headers' || ($writerDowngrades[1]['rowHeaderReferenceCount'] ?? null) !== 2) {
        throw new RuntimeException('Table geometry self-test missing Markdown row-header writer diagnostics');
    }
    $rstWriterRequirements = TableGeometry::writerDowngradeDiagnostics($document->children[0], 'rst-grid-table');
    if (
        array_map(static fn (array $diagnostic): string => $diagnostic['code'], $rstWriterRequirements) !== ['rst-grid-table-required', 'rst-grid-table-required']
        || ($rstWriterRequirements[0]['requiredFeature'] ?? null) !== 'grid-table'
        || ($rstWriterRequirements[0]['requiredSlots'] ?? null) !== [['row' => 0, 'column' => 1, 'covering' => 'colspan']]
        || ($rstWriterRequirements[1]['requiredSlots'] ?? null) !== [['row' => 1, 'column' => 0, 'covering' => 'rowspan']]
    ) {
        throw new RuntimeException('Table geometry self-test missing RST grid-table writer requirement diagnostics');
    }
    $migrationPacket = TableGeometry::reviewPacket($document->children[0], ['idPrefix' => 'Migration Grid']);
    if (($migrationPacket['summary']['writerDowngradeCount'] ?? null) !== 4 || ($migrationPacket['summary']['writerDowngradeCodes'] ?? null) !== ['markdown-column-widths-approximated', 'markdown-row-headers-flattened', 'markdown-colspan-flattened', 'markdown-rowspan-flattened']) {
        throw new RuntimeException('Table geometry self-test missing review-packet writer downgrade summary');
    }
    if (($migrationPacket['writerDowngrades']['markdown'][1]['rows'][0]['headerIds'] ?? null) !== ['migration-grid-body-r1c1']) {
        throw new RuntimeException('Table geometry self-test missing review-packet row-header writer report');
    }
    if (($migrationPacket['writerDowngrades']['markdown'][2]['flattenedSlots'] ?? null) !== [['row' => 0, 'column' => 1, 'covering' => 'colspan']]) {
        throw new RuntimeException('Table geometry self-test missing flattened span slot report');
    }
    if (($migrationPacket['coverage'][0]['normalizedWidths'] ?? null) !== [0.25, 0.25] || ($migrationPacket['coverage'][0]['percentWidthTotal'] ?? null) !== 50.0) {
        throw new RuntimeException('Table geometry self-test missing review-packet cell span width metadata');
    }
    if (($migrationPacket['sections'][0]['summary']['rowVisualWidths'] ?? null) !== [3] || ($migrationPacket['sections'][0]['summary']['rowSlotCounts'] ?? null) !== [4]) {
        throw new RuntimeException('Table geometry self-test missing section row occupancy widths');
    }
    if (($migrationPacket['sections'][1]['summary']['rowVisualWidths'] ?? null) !== [3, 3] || ($migrationPacket['sections'][1]['summary']['missingRowCount'] ?? null) !== 2) {
        throw new RuntimeException('Table geometry self-test missing body row occupancy summary');
    }
    if (
        ($migrationPacket['summary']['completeRectangle'] ?? null) !== false
        || ($migrationPacket['summary']['incompleteRowCount'] ?? null) !== 3
        || ($migrationPacket['summary']['missingRowCount'] ?? null) !== 3
        || ($migrationPacket['summary']['maxVisualWidth'] ?? null) !== 3
    ) {
        throw new RuntimeException('Table geometry self-test missing packet row occupancy rollup');
    }
    $migrationPlaceholderBlocks = (new WordPressBlockWriter(['preserveTableMissingCells' => true]))->write(new AstNode('document', [], [$document->children[0]]));
    $migrationCoveredSlotBlocks = (new WordPressBlockWriter(['preserveTableCoveredSlots' => true]))->write(new AstNode('document', [], [$document->children[0]]));
    if (str_contains($blocks, 'data-pandoc-missing-cell="true"')) {
        throw new RuntimeException('Table geometry self-test unexpectedly changed default WordPress missing-cell output');
    }
    if (str_contains($blocks, 'data-pandoc-covered-slots=')) {
        throw new RuntimeException('Table geometry self-test unexpectedly changed default WordPress covered-slot output');
    }
    if (substr_count($migrationPlaceholderBlocks, 'data-pandoc-missing-cell="true"') !== 3) {
        throw new RuntimeException('Table geometry self-test missing opt-in WordPress missing-cell placeholders');
    }
    if (!str_contains($migrationPlaceholderBlocks, '<td data-pandoc-missing-cell="true" data-pandoc-missing-row="1" data-pandoc-missing-column="3" aria-hidden="true"></td>')) {
        throw new RuntimeException('Table geometry self-test missing body-row placeholder coordinates in WordPress output');
    }
    if (str_contains($migrationPlaceholderBlocks, 'data-pandoc-missing-column="0"') || str_contains($migrationPlaceholderBlocks, 'data-pandoc-missing-column="1"')) {
        throw new RuntimeException('Table geometry self-test treated covered span slots as missing-cell placeholders');
    }
    if (substr_count($migrationCoveredSlotBlocks, 'data-pandoc-span-anchor="true"') !== 2) {
        throw new RuntimeException('Table geometry self-test missing opt-in WordPress span-anchor covered-slot metadata');
    }
    if (!str_contains($migrationCoveredSlotBlocks, 'data-pandoc-covered-slots="0:1:colspan"')) {
        throw new RuntimeException('Table geometry self-test missing colspan covered-slot replay coordinates');
    }
    if (!str_contains($migrationCoveredSlotBlocks, 'data-pandoc-covered-slots="1:0:rowspan"')) {
        throw new RuntimeException('Table geometry self-test missing rowspan covered-slot replay coordinates');
    }
    if (str_contains($migrationCoveredSlotBlocks, 'data-pandoc-missing-cell="true"')) {
        throw new RuntimeException('Table geometry self-test mixed covered-slot replay with missing-cell placeholders by default');
    }
    $multiWriterPacket = TableGeometry::reviewPacket($document->children[0], [
        'idPrefix' => 'Migration Grid',
        'writers' => ['markdown', 'restructuredtext'],
    ]);
    if (
        ($multiWriterPacket['summary']['writerDowngradeCount'] ?? null) !== 6
        || ($multiWriterPacket['summary']['writerDowngradeCodes'] ?? null) !== ['markdown-column-widths-approximated', 'markdown-row-headers-flattened', 'markdown-colspan-flattened', 'markdown-rowspan-flattened', 'rst-grid-table-required']
        || ($multiWriterPacket['summary']['writerDowngradeWriters'] ?? null) !== ['markdown', 'rst']
    ) {
        throw new RuntimeException('Table geometry self-test missing multi-writer downgrade summary');
    }
    if (
        ($multiWriterPacket['writerDowngrades']['rst'][0]['requiredSlots'] ?? null) !== [['row' => 0, 'column' => 1, 'covering' => 'colspan']]
        || ($multiWriterPacket['writerDowngrades']['rst'][1]['requiredSlots'] ?? null) !== [['row' => 1, 'column' => 0, 'covering' => 'rowspan']]
    ) {
        throw new RuntimeException('Table geometry self-test missing RST grid-table required-slot report');
    }
    json_encode($multiWriterPacket, JSON_THROW_ON_ERROR);

    $markdownGridPacket = TableGeometry::reviewPacket($document->children[0], [
        'idPrefix' => 'Migration Grid',
        'writers' => ['markdown-grid-table'],
    ]);
    if (
        ($markdownGridPacket['summary']['writerDowngradeCodes'] ?? null) !== ['markdown-grid-table-required']
        || ($markdownGridPacket['writerDowngrades']['markdown-grid-table'][0]['requiredFeature'] ?? null) !== 'grid_tables'
        || ($markdownGridPacket['writerDowngrades']['markdown-grid-table'][0]['spanTypes'] ?? null) !== ['colspan', 'rowspan']
        || ($markdownGridPacket['writerDowngrades']['markdown-grid-table'][0]['requiredSlots'] ?? null) !== [
            ['section' => 'head', 'row' => 0, 'column' => 1, 'covering' => 'colspan'],
            ['section' => 'body', 'row' => 1, 'column' => 0, 'covering' => 'rowspan'],
        ]
    ) {
        throw new RuntimeException('Table geometry self-test missing Markdown grid-table extension requirement diagnostics');
    }
    json_encode($markdownGridPacket, JSON_THROW_ON_ERROR);

    $sectionDiagnostics = TableGeometry::diagnostics($document->children[1]);
    if (!str_contains($blocks, '<colgroup><col style="width:25%"/><col style="width:25%"/><col style="width:25%"/><col style="width:25%"/></colgroup>')) {
        throw new RuntimeException('Table geometry self-test missing trailing colspec width');
    }
    if (!str_contains($blocks, '<th colspan="2" style="text-align:left">Scope</th><th style="text-align:center">Status</th>')) {
        throw new RuntimeException('Table geometry self-test missing visual-column header alignment');
    }
    if (!str_contains($blocks, '<th rowspan="2" style="text-align:left">Posts</th><td style="text-align:right">42</td><td style="text-align:center">Ready</td>')) {
        throw new RuntimeException('Table geometry self-test missing rowspan body alignment');
    }
    if (($sectionDiagnostics[0]['code'] ?? null) !== 'rowspan-crosses-section-boundary') {
        throw new RuntimeException('Table geometry self-test missing section-boundary rowspan diagnostic');
    }
    if (!str_contains($blocks, '<thead><tr><th style="text-align:left">Scope</th><th style="text-align:right">Status</th></tr></thead><tbody><tr><td style="text-align:left">Pages</td><td style="text-align:right">Needs review</td></tr></tbody>')) {
        throw new RuntimeException('Table geometry self-test missing section-scoped rowspan clamp');
    }
    $overflowDiagnostics = TableGeometry::diagnostics($document->children[2]);
    if (($overflowDiagnostics[0]['code'] ?? null) !== 'cell-exceeds-declared-columns') {
        throw new RuntimeException('Table geometry self-test missing declared-column overflow diagnostic');
    }
    if (($overflowDiagnostics[0]['sourceCell'] ?? null) !== 1 || ($overflowDiagnostics[0]['sourceColumn'] ?? null) !== 1) {
        throw new RuntimeException('Table geometry self-test missing overflow source-cell coordinates');
    }
    if (($overflowDiagnostics[1]['colspan'] ?? null) !== 3) {
        throw new RuntimeException('Table geometry self-test missing over-wide colspan diagnostic');
    }
    if (($overflowDiagnostics[1]['sourceCell'] ?? null) !== 0 || ($overflowDiagnostics[1]['sourceColumn'] ?? null) !== 0) {
        throw new RuntimeException('Table geometry self-test missing colspan source-cell coordinates');
    }
    if (!str_contains($blocks, '<tr><td style="text-align:right">Needs media</td><td>Overflow note</td></tr><tr><th colspan="3" style="text-align:left">Full width audit note</th></tr>')) {
        throw new RuntimeException('Table geometry self-test dropped malformed declared-column overflow content');
    }

    $bodyHeadGroups = TableGeometry::sectionRowEntryGroups($document->children[3]);
    if (($bodyHeadGroups[1]['rowEntries'][0]['rowRole'] ?? null) !== 'body-head') {
        throw new RuntimeException('Table geometry self-test missing body-local head row role');
    }
    if (($bodyHeadGroups[1]['rowEntries'][1]['rowHeadColumns'] ?? null) !== 1) {
        throw new RuntimeException('Table geometry self-test missing body row-head column metadata');
    }
    $bodyHeadGrid = TableGeometry::sectionGrids($document->children[3]);
    if (($bodyHeadGrid[1]['rows'][0][2]['headerCell'] ?? null) !== true) {
        throw new RuntimeException('Table geometry self-test missing body-head visual header-cell marker');
    }
    if (($bodyHeadGrid[1]['rows'][2][0]['headerCell'] ?? null) !== true || ($bodyHeadGrid[1]['rows'][2][0]['covering'] ?? null) !== 'rowspan') {
        throw new RuntimeException('Table geometry self-test missing row-head covered-slot marker');
    }
    $bodyHeadCoverage = TableGeometry::cellCoverage($document->children[3]);
    if (($bodyHeadCoverage[3]['rowRole'] ?? null) !== 'body-head' || ($bodyHeadCoverage[3]['headerCell'] ?? null) !== true) {
        throw new RuntimeException('Table geometry self-test missing body-head coverage metadata');
    }
    if (($bodyHeadCoverage[6]['rowRole'] ?? null) !== 'body' || ($bodyHeadCoverage[6]['rowHeadColumns'] ?? null) !== 1 || ($bodyHeadCoverage[6]['headerCell'] ?? null) !== true) {
        throw new RuntimeException('Table geometry self-test missing row-head coverage metadata');
    }
    $bodyHeadPacket = TableGeometry::reviewPacket($document->children[3], ['accessibility' => false]);
    if (($bodyHeadPacket['summary']['rowGroupCount'] ?? null) !== 2 || ($bodyHeadPacket['summary']['hasBodyHeadRows'] ?? null) !== true) {
        throw new RuntimeException('Table geometry self-test missing body-local row-group summary');
    }
    if (($bodyHeadPacket['summary']['bodyHeadRowCount'] ?? null) !== 1 || ($bodyHeadPacket['summary']['rowHeadGroupCount'] ?? null) !== 1 || ($bodyHeadPacket['summary']['maxRowHeadColumns'] ?? null) !== 1) {
        throw new RuntimeException('Table geometry self-test missing body-local row-group counters');
    }
    if (
        ($bodyHeadPacket['summary']['rowGroupRanges'] ?? null) !== [
            ['section' => 'head', 'kind' => 'table-head', 'rowRange' => [0, 1], 'rowCount' => 1],
            ['section' => 'body', 'kind' => 'table-body', 'rowRange' => [1, 4], 'rowCount' => 3],
        ]
        || ($bodyHeadPacket['summary']['rowRoleCounts'] ?? null) !== ['head' => 1, 'body-head' => 1, 'body' => 2]
        || ($bodyHeadPacket['summary']['headerLikeRowCount'] ?? null) !== 2
        || ($bodyHeadPacket['summary']['dataLikeRowCount'] ?? null) !== 2
    ) {
        throw new RuntimeException('Table geometry self-test missing row-group range summary metadata');
    }
    if (($bodyHeadPacket['rowGroups'][1]['rowRoles'] ?? null) !== ['body-head', 'body'] || ($bodyHeadPacket['rowGroups'][1]['bodyHeadRowCount'] ?? null) !== 1) {
        throw new RuntimeException('Table geometry self-test missing body-local row-group roles');
    }
    if (
        ($bodyHeadPacket['rowGroups'][1]['rowRange'] ?? null) !== [1, 4]
        || ($bodyHeadPacket['rowGroups'][1]['globalRowStart'] ?? null) !== 1
        || ($bodyHeadPacket['rowGroups'][1]['globalRowEnd'] ?? null) !== 4
        || ($bodyHeadPacket['rowGroups'][1]['rowRoleCounts'] ?? null) !== ['body-head' => 1, 'body' => 2]
    ) {
        throw new RuntimeException('Table geometry self-test missing body-local row-group range metadata');
    }
    if (
        ($bodyHeadPacket['sections'][1]['rowRange'] ?? null) !== [1, 4]
        || ($bodyHeadPacket['sections'][1]['rows'][1]['globalRow'] ?? null) !== 2
        || ($bodyHeadPacket['sections'][1]['rows'][2]['slots'][0]['anchorGlobalRow'] ?? null) !== 2
        || ($bodyHeadPacket['coverage'][6]['globalRowRange'] ?? null) !== [2, 4]
        || ($bodyHeadPacket['coverage'][6]['globalRows'] ?? null) !== [2, 3]
        || ($bodyHeadPacket['summary']['globalRowCount'] ?? null) !== 4
        || ($bodyHeadPacket['summary']['maxGlobalRow'] ?? null) !== 3
    ) {
        throw new RuntimeException('Table geometry self-test missing global row coordinate metadata');
    }
    if (
        ($bodyHeadPacket['writerDowngrades']['markdown'][1]['code'] ?? null) !== 'markdown-body-head-rows-flattened'
        || ($bodyHeadPacket['writerDowngrades']['markdown'][1]['requiredFeature'] ?? null) !== 'body-local-header-row-boundaries'
        || ($bodyHeadPacket['writerDowngrades']['markdown'][1]['bodyHeadRowCount'] ?? null) !== 1
    ) {
        throw new RuntimeException('Table geometry self-test missing body-local head row writer diagnostics');
    }
    $bodyHeadRowHeaderPacket = TableGeometry::reviewPacket($document->children[3], ['idPrefix' => 'Body Head Grid']);
    if (
        ($bodyHeadRowHeaderPacket['rowHeaderMap']['summary']['dataRowCount'] ?? null) !== 2
        || ($bodyHeadRowHeaderPacket['rowHeaderMap']['summary']['rowHeaderReferenceCount'] ?? null) !== 2
        || ($bodyHeadRowHeaderPacket['rowHeaderMap']['rows'][0]['headerTexts'] ?? null) !== ['Posts']
        || ($bodyHeadRowHeaderPacket['rowHeaderMap']['rows'][1]['headerIds'] ?? null) !== ['body-head-grid-body-r2c1']
        || ($bodyHeadRowHeaderPacket['summary']['hasRowHeaders'] ?? null) !== true
        || ($bodyHeadRowHeaderPacket['summary']['hasRowspanRowHeaders'] ?? null) !== true
    ) {
        throw new RuntimeException('Table geometry self-test missing row-header map review metadata');
    }
    if (($bodyHeadRowHeaderPacket['writerDowngrades']['markdown'][0]['code'] ?? null) !== 'markdown-row-headers-flattened') {
        throw new RuntimeException('Table geometry self-test missing body-local row-header writer diagnostics');
    }
    if (($bodyHeadRowHeaderPacket['writerDowngrades']['markdown'][1]['code'] ?? null) !== 'markdown-body-head-rows-flattened') {
        throw new RuntimeException('Table geometry self-test missing row-header plus body-head writer diagnostics');
    }
    json_encode($bodyHeadRowHeaderPacket, JSON_THROW_ON_ERROR);
    if (!str_contains($blocks, '<tbody><tr><th style="text-align:left">Batch</th><th style="text-align:right">Queue</th><th style="text-align:center">Decision</th></tr><tr><th rowspan="2" style="text-align:left">Posts</th><td style="text-align:right">42</td><td style="text-align:center">Review</td></tr><tr><td style="text-align:right">7</td><td style="text-align:center">Import</td></tr></tbody>')) {
        throw new RuntimeException('Table geometry self-test missing body-local head rows in WordPress tbody output');
    }
    $overlapDiagnostics = TableGeometry::diagnostics($document->children[4]);
    if (($overlapDiagnostics[0]['code'] ?? null) !== 'cell-overlaps-rowspan') {
        throw new RuntimeException('Table geometry self-test missing rowspanned overlap diagnostic');
    }
    if (($overlapDiagnostics[0]['column'] ?? null) !== 2 || ($overlapDiagnostics[0]['sourceColumn'] ?? null) !== 0 || ($overlapDiagnostics[0]['overlapColumns'] ?? null) !== [0]) {
        throw new RuntimeException('Table geometry self-test missing overlap source-cell coordinates');
    }
    if (($overlapDiagnostics[0]['coveredBy'][0]['colspan'] ?? null) !== 2 || ($overlapDiagnostics[0]['declaredColumns'] ?? null) !== 2) {
        throw new RuntimeException('Table geometry self-test missing overlap anchor metadata');
    }
    if (!str_contains($blocks, '<figcaption class="wp-element-caption">Malformed overlap review</figcaption>')) {
        throw new RuntimeException('Table geometry self-test missing malformed overlap review table');
    }
    $accessibleHeaders = TableGeometry::accessibilityAttributes($document->children[5], 'Migration Grid');
    if (($accessibleHeaders['body:1:1:1']['headers'] ?? null) !== ['migration-grid-head-r1c1', 'migration-grid-body-r1c2', 'migration-grid-body-r2c1']) {
        throw new RuntimeException('Table geometry self-test missing computed accessible header relationships');
    }
    if (!str_contains($blocks, '<th id="migration-grid-head-r1c1" scope="colgroup" colspan="2" style="text-align:left">Document</th><th id="migration-grid-head-r1c3" scope="col" style="text-align:center">State</th>')) {
        throw new RuntimeException('Table geometry self-test missing accessible header scope attributes');
    }
    if (!str_contains($blocks, '<td headers="migration-grid-head-r1c1 migration-grid-body-r1c2 migration-grid-body-r2c1" style="text-align:right">42</td>')) {
        throw new RuntimeException('Table geometry self-test missing accessible data-cell headers attributes');
    }
    $reviewPacket = TableGeometry::reviewPacket($document->children[5], ['idPrefix' => 'Migration Grid']);
    if (($reviewPacket['summary']['cellCount'] ?? null) !== 10 || ($reviewPacket['summary']['coveredSlotCount'] ?? null) !== 2) {
        throw new RuntimeException('Table geometry self-test missing serializable review-packet summary');
    }
    if (($reviewPacket['sections'][1]['rows'][0]['rowRole'] ?? null) !== 'body-head' || ($reviewPacket['sections'][1]['rows'][1]['rowHeadColumns'] ?? null) !== 1) {
        throw new RuntimeException('Table geometry self-test missing review-packet row roles');
    }
    if (($reviewPacket['coverage'][5]['text'] ?? null) !== 'Posts' || array_key_exists('node', $reviewPacket['coverage'][5])) {
        throw new RuntimeException('Table geometry self-test missing serializable review-packet coverage text');
    }
    if (($reviewPacket['coverage'][5]['occupiedSlots'] ?? null) !== [
        ['row' => 1, 'column' => 0, 'covering' => 'anchor'],
        ['row' => 2, 'column' => 0, 'covering' => 'rowspan'],
    ]) {
        throw new RuntimeException('Table geometry self-test missing review-packet occupied slots');
    }
    if (($reviewPacket['accessibility']['body:1:1:1']['headers'] ?? null) !== ['migration-grid-head-r1c1', 'migration-grid-body-r1c2', 'migration-grid-body-r2c1']) {
        throw new RuntimeException('Table geometry self-test missing review-packet accessibility relationships');
    }
    if (($reviewPacket['headerAssociations']['summary']['associationCount'] ?? null) !== 12) {
        throw new RuntimeException('Table geometry self-test missing review-packet header association count');
    }
    if (($reviewPacket['headerAssociations']['summary']['headerScopes'] ?? null) !== ['colgroup', 'col', 'rowgroup']) {
        throw new RuntimeException('Table geometry self-test missing review-packet header association scopes');
    }
    if (($reviewPacket['headerAssociations']['dataCells'][0]['headers'] ?? null) !== ['migration-grid-head-r1c1', 'migration-grid-body-r1c2', 'migration-grid-body-r2c1']) {
        throw new RuntimeException('Table geometry self-test missing first data-cell header association packet');
    }
    if (($reviewPacket['summary']['headerAssociationCount'] ?? null) !== 12 || ($reviewPacket['summary']['associatedDataCellCount'] ?? null) !== 4) {
        throw new RuntimeException('Table geometry self-test missing review-packet header association summary');
    }
    if (
        ($reviewPacket['rowMatrix']['summary']['rowCount'] ?? null) !== 4
        || ($reviewPacket['rowMatrix']['summary']['dataCellCount'] ?? null) !== 4
        || ($reviewPacket['rowMatrix']['summary']['associatedDataCellCount'] ?? null) !== 4
        || ($reviewPacket['rowMatrix']['rows'][2]['dataCells'][0]['headers'] ?? null) !== ['migration-grid-head-r1c1', 'migration-grid-body-r1c2', 'migration-grid-body-r2c1']
        || ($reviewPacket['rowMatrix']['rows'][3]['coveredSlots'][0]['anchorKey'] ?? null) !== 'body:1:0:0'
        || ($reviewPacket['summary']['rowMatrixAssociatedDataCellCount'] ?? null) !== 4
    ) {
        throw new RuntimeException('Table geometry self-test missing row-oriented matrix header handoff');
    }
    json_encode($reviewPacket, JSON_THROW_ON_ERROR);

    $sourceAccessibility = TableGeometry::accessibilityAttributes($document->children[6], 'Source Grid');
    if (($sourceAccessibility['head:0:0:0']['id'] ?? null) !== 'docx-source-scope') {
        throw new RuntimeException('Table geometry self-test missing source header cell id in accessibility handoff');
    }
    if (($sourceAccessibility['head:0:1:1']['id'] ?? null) !== 'ast-status-source') {
        throw new RuntimeException('Table geometry self-test missing AST header cell id in accessibility handoff');
    }
    if (($sourceAccessibility['body:0:0:0']['headers'] ?? null) !== ['docx-source-scope']) {
        throw new RuntimeException('Table geometry self-test missing source header id reference on data cell');
    }
    if (!str_contains($blocks, '<th scope="col" id="docx-source-scope" class="source-cell" data-origin="docx" style="text-align:left">Scope</th>')) {
        throw new RuntimeException('Table geometry self-test missing source table cell attributes');
    }
    if (!str_contains($blocks, '<th scope="col" id="ast-status-source" class="ast-header" style="text-align:right">Status</th>')) {
        throw new RuntimeException('Table geometry self-test missing AST table cell attributes');
    }
    if (!str_contains($blocks, '<td headers="docx-source-scope" class="body-source" data-origin="docx" style="text-align:left">Posts</td>')) {
        throw new RuntimeException('Table geometry self-test missing source-id headers handoff');
    }
    if (!str_contains($blocks, '<td headers="legacy-status" data-origin="docx" style="text-align:right">Ready</td>')) {
        throw new RuntimeException('Table geometry self-test missing source headers override preservation');
    }
    $sourceAttributePacket = TableGeometry::reviewPacket($document->children[6], ['idPrefix' => 'Source Grid']);
    if (($sourceAttributePacket['sourceAttributes']['id'] ?? null) !== 'source-grid' || ($sourceAttributePacket['sourceAttributes']['classes'] ?? null) !== ['wp-import', 'needs-review']) {
        throw new RuntimeException('Table geometry self-test missing table source attribute packet');
    }
    if (($sourceAttributePacket['sections'][0]['sourceAttributes']['htmlAttributes']['data-section'] ?? null) !== 'thead') {
        throw new RuntimeException('Table geometry self-test missing section source attribute packet');
    }
    if (($sourceAttributePacket['sections'][0]['rows'][0]['sourceAttributes']['htmlAttributes']['data-row'] ?? null) !== 'source-head-1') {
        throw new RuntimeException('Table geometry self-test missing row source attribute packet');
    }
    if (($sourceAttributePacket['coverage'][0]['sourceAttributes']['id'] ?? null) !== 'docx-source-scope') {
        throw new RuntimeException('Table geometry self-test missing cell source attribute packet');
    }
    if (($sourceAttributePacket['headerAssociations']['summary']['sourceHeaderOverrideCount'] ?? null) !== 1) {
        throw new RuntimeException('Table geometry self-test missing source header override association count');
    }
    if (($sourceAttributePacket['headerAssociations']['dataCells'][1]['sourceHeaders'] ?? null) !== ['legacy-status']) {
        throw new RuntimeException('Table geometry self-test missing source header override association packet');
    }
    json_encode($sourceAttributePacket, JSON_THROW_ON_ERROR);

    $astAttributePacket = TableGeometry::reviewPacket($astAttributeTable, ['accessibility' => false]);
    if (($astAttributePacket['sourceAttributes']['attributes']['data-pandoc-source'] ?? null) !== 'native-ast') {
        throw new RuntimeException('Table geometry self-test missing native AST table attribute packet');
    }
    if (($astAttributePacket['sections'][0]['sourceAttributes']['attributes']['data-section-role'] ?? null) !== 'head') {
        throw new RuntimeException('Table geometry self-test missing native AST section attribute packet');
    }
    if (($astAttributePacket['sections'][1]['rows'][0]['sourceAttributes']['attributes']['data-row-role'] ?? null) !== 'body') {
        throw new RuntimeException('Table geometry self-test missing native AST row attribute packet');
    }
    if (($astAttributePacket['coverage'][2]['sourceAttributes']['attributes']['data-field'] ?? null) !== 'posts') {
        throw new RuntimeException('Table geometry self-test missing native AST cell attribute packet');
    }
    if (!str_contains($blocks, '<table id="native-ast-attr-grid" class="source-table needs-review" data-pandoc-source="native-ast" aria-label="Native AST source attributes">')) {
        throw new RuntimeException('Table geometry self-test missing native AST table attributes in WordPress output');
    }
    if (!str_contains($blocks, '<thead id="native-ast-head" data-section-role="head"><tr data-row-role="head"><th data-field="scope" style="text-align:left">Scope</th><th data-field="state" style="text-align:right">State</th></tr></thead>')) {
        throw new RuntimeException('Table geometry self-test missing native AST head attributes in WordPress output');
    }
    if (!str_contains($blocks, '<tbody id="native-ast-body" data-section-role="body"><tr data-row-role="body"><th data-field="posts" style="text-align:left">Posts</th><td data-field="ready" aria-label="Ready state" style="text-align:right">Ready</td></tr></tbody>')) {
        throw new RuntimeException('Table geometry self-test missing native AST body attributes in WordPress output');
    }
    if (str_contains($blocks, 'onclick=') || str_contains($blocks, 'onmouseover=')) {
        throw new RuntimeException('Table geometry self-test leaked unsafe native AST event attributes');
    }
    json_encode($astAttributePacket, JSON_THROW_ON_ERROR);

    $astAttributeWriterPacket = TableGeometry::reviewPacket($astAttributeTable, [
        'accessibility' => false,
        'writers' => ['pipe-table', 'asciidoctor', 'xelatex'],
    ]);
    if (
        ($astAttributeWriterPacket['summary']['writerDowngradeCount'] ?? null) !== 6
        || ($astAttributeWriterPacket['summary']['writerDowngradeCodes'] ?? null) !== [
            'markdown-table-source-attributes-require-raw-html',
            'markdown-row-headers-flattened',
            'asciidoc-table-source-attributes-require-raw-html',
            'asciidoc-row-headers-review-required',
            'latex-table-source-attributes-review-required',
            'latex-row-headers-review-required',
        ]
        || ($astAttributeWriterPacket['summary']['writerDowngradeWriters'] ?? null) !== ['asciidoc', 'latex', 'markdown']
    ) {
        throw new RuntimeException('Table geometry self-test missing native AST source attribute writer downgrade packet');
    }
    if (($astAttributeWriterPacket['writerDowngrades']['markdown'][0]['attributeCount'] ?? null) !== 13) {
        throw new RuntimeException('Table geometry self-test missing native AST source attribute writer attribute count');
    }
    if (($astAttributeWriterPacket['writerDowngrades']['asciidoc'][0]['requiredFeature'] ?? null) !== 'raw-html-table-attributes') {
        throw new RuntimeException('Table geometry self-test missing AsciiDoc source attribute writer requirement');
    }
    if (($astAttributeWriterPacket['writerDowngrades']['latex'][0]['requiredFeature'] ?? null) !== 'table-attribute-review-comments') {
        throw new RuntimeException('Table geometry self-test missing LaTeX source attribute writer requirement');
    }
    if (($astAttributeWriterPacket['writerDowngrades']['markdown'][1]['requiredFeature'] ?? null) !== 'pipe-table-row-header-semantics') {
        throw new RuntimeException('Table geometry self-test missing native AST row-header writer requirement');
    }
    json_encode($astAttributeWriterPacket, JSON_THROW_ON_ERROR);

    $sourceScopeAccessibility = TableGeometry::accessibilityAttributes($document->children[7], 'Source Scope Grid');
    if (($sourceScopeAccessibility['body:0:0:0']['scope'] ?? null) !== 'row') {
        throw new RuntimeException('Table geometry self-test missing source scope override in accessibility handoff');
    }
    if (($sourceScopeAccessibility['body:0:1:1']['headers'] ?? null) !== ['legacy-count', 'source-posts']) {
        throw new RuntimeException('Table geometry self-test missing source headers override in accessibility handoff');
    }
    if (in_array('source-posts', $sourceScopeAccessibility['body:1:0:0']['headers'] ?? [], true)) {
        throw new RuntimeException('Table geometry self-test treated source scope=row as rowgroup across rowspan');
    }
    $sourceScopePacket = TableGeometry::reviewPacket($document->children[7], ['idPrefix' => 'Source Scope Grid']);
    if (
        ($sourceScopePacket['headerAssociations']['summary']['sourceHeaderReferenceCount'] ?? null) !== 3
        || ($sourceScopePacket['headerAssociations']['summary']['sourceHeaderResolvedReferenceCount'] ?? null) !== 2
        || ($sourceScopePacket['headerAssociations']['summary']['sourceHeaderUnresolvedReferenceCount'] ?? null) !== 1
        || ($sourceScopePacket['headerAssociations']['summary']['unresolvedSourceHeaderReferences'] ?? null) !== ['legacy-count']
    ) {
        throw new RuntimeException('Table geometry self-test missing source headers reference resolution audit');
    }
    if (($sourceScopePacket['headerAssociations']['dataCells'][0]['sourceHeaderReferences'][1]['targetKey'] ?? null) !== 'body:0:0:0') {
        throw new RuntimeException('Table geometry self-test missing resolved source row-header reference target');
    }
    if (($sourceScopePacket['headerAssociations']['headerCells'][2]['sourceHeaderReferences'][0]['targetKey'] ?? null) !== 'head:0:0:0') {
        throw new RuntimeException('Table geometry self-test missing resolved source column-header reference target');
    }
    $sourceScopeWriterPacket = TableGeometry::reviewPacket($document->children[7], [
        'idPrefix' => 'Source Scope Grid',
        'writers' => ['markdown', 'asciidoctor', 'xelatex', 'wordpress'],
    ]);
    if (($sourceScopeWriterPacket['writerDowngrades']['markdown'][1]['code'] ?? null) !== 'markdown-source-headers-require-raw-html') {
        throw new RuntimeException('Table geometry self-test missing source headers Markdown writer requirement');
    }
    if (($sourceScopeWriterPacket['writerDowngrades']['markdown'][1]['unresolvedReferences'] ?? null) !== ['legacy-count']) {
        throw new RuntimeException('Table geometry self-test missing unresolved source headers writer audit');
    }
    if (($sourceScopeWriterPacket['writerDowngrades']['asciidoc'][1]['requiredFeature'] ?? null) !== 'source-header-reference-review') {
        throw new RuntimeException('Table geometry self-test missing source headers AsciiDoc writer requirement');
    }
    if (($sourceScopeWriterPacket['writerDowngrades']['latex'][1]['requiredFeature'] ?? null) !== 'table-header-reference-comments') {
        throw new RuntimeException('Table geometry self-test missing source headers LaTeX writer requirement');
    }
    if (($sourceScopeWriterPacket['writerDowngrades']['wordpress'] ?? null) !== []) {
        throw new RuntimeException('Table geometry self-test should preserve source headers for WordPress output without writer downgrade');
    }
    json_encode($sourceScopeWriterPacket, JSON_THROW_ON_ERROR);
    if (!str_contains($blocks, '<th id="source-posts" scope="row" rowspan="2" style="text-align:left">Posts</th><td headers="legacy-count source-posts" style="text-align:right">42</td><td headers="source-state source-posts" style="text-align:center">Ready</td>')) {
        throw new RuntimeException('Table geometry self-test missing source scope and headers WordPress output');
    }
    if (!str_contains($blocks, '<tr><td headers="source-count" style="text-align:right">7</td><td headers="source-state" style="text-align:center">Review</td></tr>')) {
        throw new RuntimeException('Table geometry self-test missing source scoped second-row headers output');
    }

    $sourceRowgroupAccessibility = TableGeometry::accessibilityAttributes($sourceRowgroupScopeTable, 'Source Rowgroup Grid');
    if (($sourceRowgroupAccessibility['body:1:0:0']['headers'] ?? null) !== ['source-rg-scope', 'source-media-group']) {
        throw new RuntimeException('Table geometry self-test missing source rowgroup header on later tbody row');
    }
    if (in_array('source-media-group', $sourceRowgroupAccessibility['body1:0:0:0']['headers'] ?? [], true)) {
        throw new RuntimeException('Table geometry self-test allowed source rowgroup header to cross tbody boundary');
    }
    $sourceRowgroupPacket = TableGeometry::reviewPacket($sourceRowgroupScopeTable, ['idPrefix' => 'Source Rowgroup Grid']);
    if (
        ($sourceRowgroupPacket['headerAssociations']['summary']['associationCount'] ?? null) !== 13
        || ($sourceRowgroupPacket['rowHeaderMap']['summary']['labeledDataRowCount'] ?? null) !== 2
        || ($sourceRowgroupPacket['rowHeaderMap']['summary']['unlabeledDataRowCount'] ?? null) !== 1
        || ($sourceRowgroupPacket['rowHeaderMap']['summary']['hasRowspanRowHeaders'] ?? null) !== false
        || ($sourceRowgroupPacket['headerAssociations']['headerCells'][3]['sourceScope'] ?? null) !== 'rowgroup'
    ) {
        throw new RuntimeException('Table geometry self-test missing source rowgroup review-packet associations');
    }
    if (!str_contains($blocks, '<tbody id="media-body"><tr><th id="source-media-group" scope="rowgroup" style="text-align:left">Media</th><td headers="source-rg-count source-media-group" style="text-align:right">7</td><td headers="source-rg-state source-media-group" style="text-align:center">Needs alt</td></tr><tr><td headers="source-rg-scope source-media-group" style="text-align:left">Images</td><td headers="source-rg-count source-media-group" style="text-align:right">3</td><td headers="source-rg-state source-media-group" style="text-align:center">Review</td></tr></tbody>')) {
        throw new RuntimeException('Table geometry self-test missing source rowgroup WordPress header relationships');
    }
    if (!str_contains($blocks, '<tbody id="pages-body"><tr><td headers="source-rg-scope" style="text-align:left">Pages</td><td headers="source-rg-count" style="text-align:right">5</td><td headers="source-rg-state" style="text-align:center">Ready</td></tr></tbody>')) {
        throw new RuntimeException('Table geometry self-test missing isolated second tbody source rowgroup output');
    }
    json_encode($sourceRowgroupPacket, JSON_THROW_ON_ERROR);

    $sourceColgroupAccessibility = TableGeometry::accessibilityAttributes($sourceColgroupScopeTable, 'Source Colgroup Grid');
    if (($sourceColgroupAccessibility['head:0:0:0']['columns'] ?? null) !== [0, 1]) {
        throw new RuntimeException('Table geometry self-test missing source colgroup header columns');
    }
    if (($sourceColgroupAccessibility['body:0:1:1']['headers'] ?? null) !== ['source-import-scope', 'source-items']) {
        throw new RuntimeException('Table geometry self-test missing source colgroup header relationship on grouped column');
    }
    $sourceColgroupPacket = TableGeometry::reviewPacket($sourceColgroupScopeTable, ['idPrefix' => 'Source Colgroup Grid']);
    if (
        ($sourceColgroupPacket['headerAssociations']['summary']['associationCount'] ?? null) !== 8
        || ($sourceColgroupPacket['headerAssociations']['headerCells'][0]['sourceScope'] ?? null) !== 'colgroup'
        || ($sourceColgroupPacket['headerAssociations']['headerCells'][0]['sourceColumnGroup']['columns'] ?? null) !== [0, 1]
        || ($sourceColgroupPacket['rowMatrix']['rows'][0]['headerCells'][0]['sourceColumnGroup']['source']['colgroupAttributes']['htmlAttributes']['id'] ?? null) !== 'source-import-columns'
    ) {
        throw new RuntimeException('Table geometry self-test missing source colgroup review-packet associations');
    }
    if (!str_contains($blocks, '<td headers="source-import-scope" style="text-align:left">Posts</td><td headers="source-import-scope source-items" style="text-align:right">42</td><td headers="source-state" style="text-align:center">Ready</td>')) {
        throw new RuntimeException('Table geometry self-test missing source colgroup WordPress header relationships');
    }
    json_encode($sourceColgroupPacket, JSON_THROW_ON_ERROR);

    $sourceReferenceGeometryPacket = TableGeometry::reviewPacket($sourceReferenceGeometryTable, ['idPrefix' => 'Reference Geometry Grid']);
    $scopeReference = $sourceReferenceGeometryPacket['headerAssociations']['dataCells'][0]['sourceHeaderReferences'][0] ?? [];
    $rowgroupReference = $sourceReferenceGeometryPacket['headerAssociations']['dataCells'][0]['sourceHeaderReferences'][1] ?? [];
    if (
        ($sourceReferenceGeometryPacket['summary']['sourceHeaderReferenceCount'] ?? null) !== 8
        || ($sourceReferenceGeometryPacket['summary']['sourceHeaderUnresolvedReferenceCount'] ?? null) !== 0
        || ($scopeReference['targetColspan'] ?? null) !== 2
        || ($scopeReference['targetSourceRowRange'] ?? null) !== [0, 1]
        || ($scopeReference['targetGlobalRowRange'] ?? null) !== [0, 1]
    ) {
        throw new RuntimeException('Table geometry self-test missing source header reference column-span target metadata');
    }
    if (
        ($rowgroupReference['targetRowspan'] ?? null) !== 2
        || ($rowgroupReference['targetSourceRowRange'] ?? null) !== [0, 2]
        || ($rowgroupReference['targetGlobalRows'] ?? null) !== [1, 2]
        || ($sourceReferenceGeometryPacket['rowMatrix']['rows'][2]['dataCells'][0]['sourceHeaderReferences'][1]['targetGlobalRowRange'] ?? null) !== [1, 3]
    ) {
        throw new RuntimeException('Table geometry self-test missing source header reference row-span target metadata');
    }
    if (!str_contains($blocks, '<th id="source-scope-span" scope="colgroup" colspan="2" style="text-align:left">Migration scope</th><th id="source-state-span" scope="col" style="text-align:center">State</th>')) {
        throw new RuntimeException('Table geometry self-test missing source header reference span output');
    }
    if (!str_contains($blocks, '<th id="source-posts-group" scope="rowgroup" rowspan="2" style="text-align:left">Posts</th><td headers="source-scope-span source-posts-group" style="text-align:right">42</td><td headers="source-state-span source-posts-group" style="text-align:center">Ready</td>')) {
        throw new RuntimeException('Table geometry self-test missing source header reference rowgroup output');
    }
    json_encode($sourceReferenceGeometryPacket, JSON_THROW_ON_ERROR);

    $invalidSourceScopePacket = TableGeometry::reviewPacket($invalidSourceScopeTable, ['idPrefix' => 'Invalid Scope Grid']);
    if (
        ($invalidSourceScopePacket['summary']['diagnosticCodes'] ?? null) !== ['table-header-scope-invalid']
        || ($invalidSourceScopePacket['summary']['hasInvalidSourceScopes'] ?? null) !== true
        || ($invalidSourceScopePacket['summary']['invalidSourceScopeCount'] ?? null) !== 1
        || ($invalidSourceScopePacket['summary']['invalidSourceScopes'] ?? null) !== ['columnish']
        || ($invalidSourceScopePacket['diagnostics'][0]['fallbackScope'] ?? null) !== 'col'
        || ($invalidSourceScopePacket['headerAssociations']['headerCells'][0]['sourceScope'] ?? null) !== null
    ) {
        throw new RuntimeException('Table geometry self-test missing invalid source scope audit metadata');
    }
    if (!str_contains($blocks, '<th scope="col" id="invalid-scope-document" style="text-align:left">Document</th><th id="invalid-scope-state" scope="col" style="text-align:right">State</th>')) {
        throw new RuntimeException('Table geometry self-test missing invalid source scope fallback WordPress output');
    }
    if (str_contains($blocks, 'scope="columnish"')) {
        throw new RuntimeException('Table geometry self-test leaked invalid source scope into WordPress output');
    }
    json_encode($invalidSourceScopePacket, JSON_THROW_ON_ERROR);

    $duplicateHeaderPacket = TableGeometry::reviewPacket($duplicateHeaderTable, [
        'idPrefix' => 'Duplicate Header Grid',
        'writers' => ['markdown', 'asciidoctor', 'xelatex', 'wordpress'],
    ]);
    if (
        ($duplicateHeaderPacket['summary']['duplicateHeaderIdCount'] ?? null) !== 1
        || ($duplicateHeaderPacket['summary']['duplicateHeaderIds'] ?? null) !== ['duplicate-document']
        || ($duplicateHeaderPacket['summary']['sourceHeaderAmbiguousReferenceCount'] ?? null) !== 2
        || ($duplicateHeaderPacket['summary']['ambiguousSourceHeaderReferences'] ?? null) !== ['duplicate-document']
        || ($duplicateHeaderPacket['summary']['unresolvedSourceHeaderReferences'] ?? null) !== ['missing-document']
        || ($duplicateHeaderPacket['summary']['diagnosticCodes'] ?? null) !== ['table-header-id-duplicated']
    ) {
        throw new RuntimeException('Table geometry self-test missing duplicate source header id audit summary');
    }
    if (
        ($duplicateHeaderPacket['diagnostics'][0]['id'] ?? null) !== 'duplicate-document'
        || ($duplicateHeaderPacket['diagnostics'][0]['headerCellCount'] ?? null) !== 2
        || ($duplicateHeaderPacket['headerAssociations']['dataCells'][1]['sourceHeaderReferences'][0]['targetCount'] ?? null) !== 2
        || ($duplicateHeaderPacket['headerAssociations']['dataCells'][1]['sourceHeaderReferences'][0]['ambiguous'] ?? null) !== true
    ) {
        throw new RuntimeException('Table geometry self-test missing duplicate source header id target metadata');
    }
    if (
        ($duplicateHeaderPacket['writerDowngrades']['markdown'][0]['ambiguousReferenceCount'] ?? null) !== 2
        || ($duplicateHeaderPacket['writerDowngrades']['markdown'][0]['ambiguousReferences'] ?? null) !== ['duplicate-document']
        || ($duplicateHeaderPacket['writerDowngrades']['wordpress'] ?? null) !== []
    ) {
        throw new RuntimeException('Table geometry self-test missing duplicate source header writer handoff metadata');
    }
    if (!str_contains($blocks, '<th id="duplicate-document" scope="col" style="text-align:left">Document A</th><th id="duplicate-document" scope="col" style="text-align:right">Document B</th><th id="duplicate-state" scope="col" headers="duplicate-document" style="text-align:center">State</th>')) {
        throw new RuntimeException('Table geometry self-test missing duplicate source header WordPress output');
    }
    if (!str_contains($blocks, '<td headers="duplicate-document missing-document" style="text-align:right">42</td>')) {
        throw new RuntimeException('Table geometry self-test missing ambiguous source headers data-cell output');
    }
    json_encode($duplicateHeaderPacket, JSON_THROW_ON_ERROR);

    $duplicateHeaderTokenPacket = TableGeometry::reviewPacket($duplicateHeaderTokenTable, [
        'idPrefix' => 'Duplicate Token Grid',
        'writers' => ['markdown', 'asciidoctor', 'xelatex', 'wordpress'],
    ]);
    if (
        ($duplicateHeaderTokenPacket['summary']['diagnosticCodes'] ?? null) !== ['table-source-headers-duplicate-tokens']
        || ($duplicateHeaderTokenPacket['summary']['duplicateSourceHeaderTokenCount'] ?? null) !== 2
        || ($duplicateHeaderTokenPacket['summary']['duplicateSourceHeaderTokenCellCount'] ?? null) !== 2
        || ($duplicateHeaderTokenPacket['summary']['duplicateSourceHeaderTokens'] ?? null) !== ['dup-token-document', 'dup-token-count']
        || ($duplicateHeaderTokenPacket['summary']['sourceHeaderReferenceCount'] ?? null) !== 3
        || ($duplicateHeaderTokenPacket['headerAssociations']['summary']['sourceHeaderResolvedReferenceCount'] ?? null) !== 3
    ) {
        throw new RuntimeException('Table geometry self-test missing duplicate source headers token audit summary');
    }
    if (
        ($duplicateHeaderTokenPacket['diagnostics'][0]['cells'][0]['duplicateSourceHeaderTokens'] ?? null) !== ['dup-token-document']
        || ($duplicateHeaderTokenPacket['diagnostics'][0]['cells'][1]['duplicateSourceHeaderTokens'] ?? null) !== ['dup-token-count']
        || ($duplicateHeaderTokenPacket['headerAssociations']['dataCells'][1]['sourceHeaders'] ?? null) !== ['dup-token-document', 'dup-token-count']
        || count($duplicateHeaderTokenPacket['headerAssociations']['dataCells'][1]['sourceHeaderReferences'] ?? []) !== 2
    ) {
        throw new RuntimeException('Table geometry self-test missing duplicate source headers token cell metadata');
    }
    if (
        ($duplicateHeaderTokenPacket['writerDowngrades']['markdown'][0]['duplicateTokenCount'] ?? null) !== 2
        || ($duplicateHeaderTokenPacket['writerDowngrades']['markdown'][0]['duplicateTokens'] ?? null) !== ['dup-token-document', 'dup-token-count']
        || ($duplicateHeaderTokenPacket['writerDowngrades']['wordpress'] ?? null) !== []
    ) {
        throw new RuntimeException('Table geometry self-test missing duplicate source headers token writer metadata');
    }
    if (!str_contains($blocks, '<th id="dup-token-state" scope="col" headers="dup-token-document" style="text-align:center">State</th>')) {
        throw new RuntimeException('Table geometry self-test missing duplicate source header token header-cell output');
    }
    if (!str_contains($blocks, '<td headers="dup-token-document dup-token-count" style="text-align:right">42</td>')) {
        throw new RuntimeException('Table geometry self-test missing normalized duplicate source headers data-cell output');
    }
    if (str_contains($blocks, 'headers="dup-token-document dup-token-count dup-token-count"')) {
        throw new RuntimeException('Table geometry self-test leaked duplicate source headers tokens into WordPress output');
    }
    json_encode($duplicateHeaderTokenPacket, JSON_THROW_ON_ERROR);

    $duplicateSourceIdTable = $duplicateSourceIdTables[0] ?? null;
    $duplicateSourceIdPacket = $duplicateSourceIdTable instanceof AstNode
        ? TableGeometry::reviewPacket($duplicateSourceIdTable, [
            'accessibility' => false,
            'writers' => ['markdown', 'asciidoc', 'latex', 'wordpress'],
        ])
        : null;
    if (
        !is_array($duplicateSourceIdPacket)
        || ($duplicateSourceIdPacket['summary']['diagnosticCodes'] ?? null) !== ['table-source-id-duplicated']
        || ($duplicateSourceIdPacket['summary']['duplicateSourceIdCount'] ?? null) !== 3
        || ($duplicateSourceIdPacket['summary']['duplicateSourceIdLocationCount'] ?? null) !== 6
        || ($duplicateSourceIdPacket['summary']['duplicateSourceIds'] ?? null) !== ['duplicate-source-section', 'duplicate-source-row', 'duplicate-source-cell']
        || ($duplicateSourceIdPacket['summary']['duplicateSourceIdScopes'] ?? null) !== ['cell', 'row', 'section']
    ) {
        throw new RuntimeException('Table geometry self-test missing duplicate source id audit summary');
    }
    if (
        ($duplicateSourceIdPacket['duplicateSourceIds'][0]['scopes'] ?? null) !== ['section']
        || ($duplicateSourceIdPacket['duplicateSourceIds'][1]['scopes'] ?? null) !== ['row']
        || ($duplicateSourceIdPacket['duplicateSourceIds'][2]['scopes'] ?? null) !== ['cell']
        || array_map(static fn (array $location): string => (string) ($location['text'] ?? ''), $duplicateSourceIdPacket['duplicateSourceIds'][2]['locations'] ?? []) !== ['Posts', 'Ready']
    ) {
        throw new RuntimeException('Table geometry self-test missing duplicate source id location metadata');
    }
    if (
        ($duplicateSourceIdPacket['writerDowngrades']['markdown'][1]['code'] ?? null) !== 'markdown-source-ids-duplicated'
        || ($duplicateSourceIdPacket['writerDowngrades']['markdown'][1]['requiredFeature'] ?? null) !== 'raw-html-table-source-ids'
        || ($duplicateSourceIdPacket['writerDowngrades']['wordpress'] ?? null) !== []
    ) {
        throw new RuntimeException('Table geometry self-test missing duplicate source id writer diagnostics');
    }
    if (!str_contains($blocks, '<tbody id="duplicate-source-section"><tr id="duplicate-source-row"><td id="duplicate-source-cell">Posts</td><td id="duplicate-source-cell">Ready</td></tr></tbody>')) {
        throw new RuntimeException('Table geometry self-test missing duplicate source id WordPress output');
    }
    json_encode($duplicateSourceIdPacket, JSON_THROW_ON_ERROR);

    $nestedPacket = TableGeometry::reviewPacket($document->children[8], ['idPrefix' => 'Nested Packet']);
    if (($nestedPacket['summary']['nestedTableCount'] ?? null) !== 1 || ($nestedPacket['summary']['nestedTableCellCount'] ?? null) !== 1) {
        throw new RuntimeException('Table geometry self-test missing nested table summary counts');
    }
    if (($nestedPacket['sections'][1]['summary']['nestedTableCount'] ?? null) !== 1 || ($nestedPacket['sections'][1]['summary']['nestedTableCellCount'] ?? null) !== 1) {
        throw new RuntimeException('Table geometry self-test missing body-local nested table summary counts');
    }
    if (($nestedPacket['sections'][0]['summary']['hasNestedTables'] ?? null) !== false || ($nestedPacket['sections'][1]['summary']['hasNestedTables'] ?? null) !== true) {
        throw new RuntimeException('Table geometry self-test missing per-section nested table flags');
    }
    if (($nestedPacket['sections'][1]['summary']['nestedTableCaptions'] ?? null) !== ['Nested queue audit']) {
        throw new RuntimeException('Table geometry self-test missing per-section nested table captions');
    }
    if (($nestedPacket['coverage'][2]['nestedTables'][0]['caption'] ?? null) !== 'Nested queue audit') {
        throw new RuntimeException('Table geometry self-test missing nested table caption rollup');
    }
    if (($nestedPacket['coverage'][2]['nestedTables'][0]['cellCount'] ?? null) !== 2) {
        throw new RuntimeException('Table geometry self-test missing nested table cell-count rollup');
    }
    json_encode($nestedPacket, JSON_THROW_ON_ERROR);
    $asciidocNestedRequirements = TableGeometry::writerDowngradeDiagnostics($document->children[8], 'asciidoctor');
    if (
        array_map(static fn (array $diagnostic): string => $diagnostic['code'], $asciidocNestedRequirements) !== ['asciidoc-nested-table-raw-html-required']
        || ($asciidocNestedRequirements[0]['requiredFeature'] ?? null) !== 'raw-html-table-passthrough'
        || ($asciidocNestedRequirements[0]['nestedTableCaptions'] ?? null) !== ['Nested queue audit']
    ) {
        throw new RuntimeException('Table geometry self-test missing AsciiDoc nested-table writer requirement diagnostics');
    }
    $asciidocNestedPacket = TableGeometry::reviewPacket($document->children[8], [
        'idPrefix' => 'Nested Packet',
        'writers' => ['markdown', 'asciidoc'],
    ]);
    if (
        ($asciidocNestedPacket['summary']['writerDowngradeCount'] ?? null) !== 1
        || ($asciidocNestedPacket['summary']['writerDowngradeCodes'] ?? null) !== ['asciidoc-nested-table-raw-html-required']
        || ($asciidocNestedPacket['summary']['writerDowngradeWriters'] ?? null) !== ['asciidoc']
    ) {
        throw new RuntimeException('Table geometry self-test missing AsciiDoc nested-table review-packet summary');
    }
    json_encode($asciidocNestedPacket, JSON_THROW_ON_ERROR);
    if (!str_contains($blocks, '<figcaption class="wp-element-caption">Nested table packet review</figcaption>')) {
        throw new RuntimeException('Table geometry self-test missing nested table packet WordPress output');
    }

    $abbreviatedHeaderPacket = TableGeometry::reviewPacket($abbreviatedHeaderTable, ['idPrefix' => 'Abbr Grid']);
    $abbreviatedHeaderWriterPacket = TableGeometry::reviewPacket($abbreviatedHeaderTable, [
        'idPrefix' => 'Abbr Grid',
        'writers' => ['markdown', 'asciidoc', 'latex', 'wordpress'],
    ]);
    $abbreviationDiagnostics = [];
    foreach (['markdown', 'asciidoc', 'latex'] as $writer) {
        $matches = array_values(array_filter(
            $abbreviatedHeaderWriterPacket['writerDowngrades'][$writer] ?? [],
            static fn (array $diagnostic): bool => ($diagnostic['reason'] ?? null) === 'header-abbreviation'
        ));
        $abbreviationDiagnostics[$writer] = $matches[0] ?? [];
    }
    if (
        ($abbreviatedHeaderPacket['headerAssociations']['summary']['headerAbbreviationCount'] ?? null) !== 2
        || ($abbreviatedHeaderPacket['summary']['headerAbbreviationCount'] ?? null) !== 2
        || ($abbreviatedHeaderPacket['summary']['hasHeaderAbbreviations'] ?? null) !== true
    ) {
        throw new RuntimeException('Table geometry self-test missing source header abbreviation summary');
    }
    if (
        ($abbreviatedHeaderPacket['headerAssociations']['headerCells'][0]['abbr'] ?? null) !== 'Doc'
        || ($abbreviatedHeaderPacket['headerAssociations']['headerCells'][1]['abbr'] ?? null) !== 'St'
    ) {
        throw new RuntimeException('Table geometry self-test missing source header abbreviation records');
    }
    if (
        ($abbreviationDiagnostics['markdown']['code'] ?? null) !== 'markdown-header-abbreviation-require-raw-html'
        || ($abbreviationDiagnostics['markdown']['abbreviations'] ?? null) !== ['Doc', 'St']
        || ($abbreviationDiagnostics['asciidoc']['code'] ?? null) !== 'asciidoc-header-abbreviation-review-required'
        || ($abbreviationDiagnostics['latex']['code'] ?? null) !== 'latex-header-abbreviation-review-required'
        || ($abbreviatedHeaderWriterPacket['writerDowngrades']['wordpress'] ?? null) !== []
    ) {
        throw new RuntimeException('Table geometry self-test missing source header abbreviation writer metadata');
    }
    if (!str_contains($blocks, '<th scope="col" id="abbr-document-source" abbr="Doc" style="text-align:left">Document</th>')) {
        throw new RuntimeException('Table geometry self-test missing source HTML header abbreviation output');
    }
    if (!str_contains($blocks, '<th id="abbr-grid-head-r1c2" scope="col" abbr="St" style="text-align:right">Status</th>')) {
        throw new RuntimeException('Table geometry self-test missing source AST header abbreviation output');
    }
    json_encode($abbreviatedHeaderPacket, JSON_THROW_ON_ERROR);
    json_encode($abbreviatedHeaderWriterPacket, JSON_THROW_ON_ERROR);

    $sourceShiftTable = null;
    foreach ($document->children as $node) {
        if ($node->type === 'table' && $node->attr('id') === 'implicit-source-shift-grid') {
            $sourceShiftTable = $node;
            break;
        }
    }
    $sourceShiftPacket = $sourceShiftTable instanceof AstNode ? TableGeometry::reviewPacket($sourceShiftTable, ['accessibility' => false]) : null;
    if (
        !is_array($sourceShiftPacket)
        || ($sourceShiftPacket['summary']['hasSourceCoordinateShifts'] ?? null) !== true
        || ($sourceShiftPacket['summary']['sourceCoordinateShiftCount'] ?? null) !== 2
        || ($sourceShiftPacket['summary']['maxVisualShift'] ?? null) !== 2
    ) {
        throw new RuntimeException('Table geometry self-test missing source-to-visual shift summary');
    }
    if (
        ($sourceShiftPacket['coverage'][1]['sourceColumns'] ?? null) !== [0]
        || ($sourceShiftPacket['coverage'][1]['visualShift'] ?? null) !== 2
        || ($sourceShiftPacket['coverage'][2]['sourceEndColumn'] ?? null) !== 2
    ) {
        throw new RuntimeException('Table geometry self-test missing source-to-visual shift coverage metadata');
    }
    $sourceShiftRecords = is_array($sourceShiftPacket['sourceCoordinateShifts'] ?? null)
        ? $sourceShiftPacket['sourceCoordinateShifts']
        : [];
    if (
        count($sourceShiftRecords) !== 2
        || ($sourceShiftRecords[0]['text'] ?? null) !== 'Unexpected source cell'
        || ($sourceShiftRecords[1]['text'] ?? null) !== 'Second conflict'
        || ($sourceShiftRecords[0]['sourceColumns'] ?? null) !== [0]
        || ($sourceShiftRecords[1]['columns'] ?? null) !== [3]
        || ($sourceShiftRecords[0]['absoluteVisualShift'] ?? null) !== 2
    ) {
        throw new RuntimeException('Table geometry self-test missing source-to-visual shift audit records');
    }
    if (($sourceShiftPacket['summary']['diagnosticCodes'] ?? null) !== []) {
        throw new RuntimeException('Table geometry self-test incorrectly diagnosed normalized implicit source shifts');
    }
    if (!str_contains($blocks, '<table id="implicit-source-shift-grid"><tbody><tr><td colspan="2" rowspan="2">Merged source</td></tr><tr><td>Unexpected source cell</td><td>Second conflict</td></tr></tbody></table>')) {
        throw new RuntimeException('Table geometry self-test missing WordPress output for implicit source shift table');
    }
    json_encode($sourceShiftPacket, JSON_THROW_ON_ERROR);

    $rowspanZeroTable = null;
    foreach ($document->children as $node) {
        if ($node->type === 'table' && $node->attr('id') === 'rowspan-zero-grid') {
            $rowspanZeroTable = $node;
            break;
        }
    }
    $rowspanZeroPacket = $rowspanZeroTable instanceof AstNode ? $rowspanZeroTable->attr('tableGeometry') : null;
    if (!$rowspanZeroTable instanceof AstNode || TableGeometry::columnCount($rowspanZeroTable) !== 3 || $rowspanZeroTable->attr('widths') !== [1 / 3, 1 / 3, 1 / 3]) {
        throw new RuntimeException('Table geometry self-test missing HTML rowspan-zero visual column normalization');
    }
    if (!is_array($rowspanZeroPacket) || ($rowspanZeroPacket['coverage'][0]['rowspan'] ?? null) !== 3 || ($rowspanZeroPacket['coverage'][0]['rowspanToEnd'] ?? null) !== true) {
        throw new RuntimeException('Table geometry self-test missing HTML rowspan-zero coverage packet');
    }
    if (
        ($rowspanZeroPacket['coverage'][0]['sourceRowspanAttribute'] ?? null) !== 0
        || ($rowspanZeroPacket['coverage'][0]['sourceRowspanMode'] ?? null) !== 'to-section-end'
        || ($rowspanZeroPacket['summary']['rowspanToEndCellCount'] ?? null) !== 1
        || ($rowspanZeroPacket['summary']['rowspanToEndSections'] ?? null) !== ['body']
    ) {
        throw new RuntimeException('Table geometry self-test missing HTML rowspan-zero source provenance packet');
    }
    if (($rowspanZeroPacket['sections'][1]['summary']['coveredSlotCount'] ?? null) !== 2 || ($rowspanZeroPacket['sections'][2]['summary']['coveredSlotCount'] ?? null) !== 0) {
        throw new RuntimeException('Table geometry self-test let HTML rowspan-zero cross tbody boundaries');
    }
    if (($rowspanZeroPacket['summary']['bodyGroupCount'] ?? null) !== 2 || ($rowspanZeroPacket['summary']['hasMultipleBodyGroups'] ?? null) !== true || ($rowspanZeroPacket['summary']['bodyRowCount'] ?? null) !== 4) {
        throw new RuntimeException('Table geometry self-test missing HTML row-group summary');
    }
    if (($rowspanZeroPacket['rowGroups'][1]['sourceAttributes']['id'] ?? null) !== 'posts-body' || ($rowspanZeroPacket['rowGroups'][2]['sourceAttributes']['id'] ?? null) !== 'pages-body') {
        throw new RuntimeException('Table geometry self-test missing HTML row-group source attributes');
    }
    $rowspanZeroFlatGrid = TableGeometry::flatGrid($rowspanZeroTable);
    $rowspanZeroFallbacks = TableGeometry::flatGridFallbackDiagnostics($rowspanZeroTable);
    if (
        ($rowspanZeroFlatGrid['summary']['rowCount'] ?? null) !== 4
        || ($rowspanZeroFlatGrid['summary']['slotCount'] ?? null) !== 12
        || ($rowspanZeroFlatGrid['summary']['coveredSlotCount'] ?? null) !== 2
        || ($rowspanZeroFlatGrid['rows'][1]['cells'][0]['kind'] ?? null) !== 'covered'
        || ($rowspanZeroFlatGrid['rows'][1]['cells'][0]['covering'] ?? null) !== 'rowspan'
        || ($rowspanZeroFlatGrid['rows'][1]['cells'][0]['anchorText'] ?? null) !== 'Posts'
        || ($rowspanZeroPacket['flatGrid'] ?? null) !== $rowspanZeroFlatGrid
        || ($rowspanZeroPacket['summary']['flatGridCoveredSlotCount'] ?? null) !== 2
    ) {
        throw new RuntimeException('Table geometry self-test missing flat-grid fallback handoff metadata');
    }
    if (
        ($rowspanZeroFallbacks[0]['code'] ?? null) !== 'flat-grid-covered-slots-require-anchor-replay'
        || ($rowspanZeroFallbacks[1]['code'] ?? null) !== 'flat-grid-missing-slots-require-empty-placeholders'
        || count($rowspanZeroFallbacks) !== 2
        || ($rowspanZeroFallbacks[0]['slotCount'] ?? null) !== 2
        || ($rowspanZeroFallbacks[0]['requiredFeature'] ?? null) !== 'span-anchor-replay'
        || ($rowspanZeroFallbacks[1]['slotCount'] ?? null) !== 1
        || ($rowspanZeroFallbacks[1]['requiredFeature'] ?? null) !== 'empty-cell-placeholders'
        || ($rowspanZeroPacket['flatGridFallbacks'] ?? null) !== $rowspanZeroFallbacks
        || ($rowspanZeroPacket['summary']['flatGridFallbackCount'] ?? null) !== 2
        || ($rowspanZeroPacket['summary']['flatGridFallbackCoveredSlotCount'] ?? null) !== 2
        || ($rowspanZeroPacket['summary']['flatGridFallbackMissingSlotCount'] ?? null) !== 1
    ) {
        throw new RuntimeException('Table geometry self-test missing flat-grid visual-slot fallback diagnostics');
    }
    if (($rowspanZeroPacket['summary']['writerDowngradeCodes'] ?? null) !== ['markdown-column-widths-approximated', 'markdown-table-bodies-flattened', 'markdown-row-headers-flattened', 'markdown-rowspan-flattened']) {
        throw new RuntimeException('Table geometry self-test missing HTML rowspan-zero Markdown downgrade packet');
    }
    if (
        ($rowspanZeroPacket['writerDowngrades']['markdown'][1]['reason'] ?? null) !== 'multiple-table-bodies'
        || ($rowspanZeroPacket['writerDowngrades']['markdown'][1]['requiredFeature'] ?? null) !== 'body-row-group-boundaries'
        || ($rowspanZeroPacket['writerDowngrades']['markdown'][1]['bodySections'] ?? null) !== ['body', 'body1']
        || ($rowspanZeroPacket['writerDowngrades']['markdown'][1]['bodySectionRowCounts'] ?? null) !== [3, 1]
        || ($rowspanZeroPacket['writerDowngrades']['markdown'][1]['sectionRanges'] ?? null) !== [
            ['section' => 'body', 'rowRange' => [0, 3], 'rowCount' => 3, 'rowRole' => 'body'],
            ['section' => 'body1', 'rowRange' => [3, 4], 'rowCount' => 1, 'rowRole' => 'body'],
        ]
        || ($rowspanZeroPacket['writerDowngrades']['markdown'][1]['bodySectionRanges'] ?? null) !== [
            ['section' => 'body', 'rowRange' => [0, 3], 'rowCount' => 3, 'rowRole' => 'body'],
            ['section' => 'body1', 'rowRange' => [3, 4], 'rowCount' => 1, 'rowRole' => 'body'],
        ]
    ) {
        throw new RuntimeException('Table geometry self-test missing HTML rowspan-zero multiple body writer diagnostics');
    }
    if (!str_contains($blocks, '<tbody id="posts-body"><tr data-row="posts-total"><th rowspan="3" style="text-align:left">Posts</th><td style="text-align:right">42</td></tr><tr data-row="posts-media"><td style="text-align:right">7</td><td>Needs media</td></tr><tr data-row="posts-review"><td style="text-align:right">3</td><td>Review</td></tr></tbody><tbody id="pages-body"><tr data-row="pages-total"><th>Pages</th><td style="text-align:right">5</td><td>Ready</td></tr></tbody>')) {
        throw new RuntimeException('Table geometry self-test missing finite WordPress rowspan output for HTML rowspan-zero');
    }
    json_encode($rowspanZeroFlatGrid, JSON_THROW_ON_ERROR);
    json_encode($rowspanZeroPacket, JSON_THROW_ON_ERROR);

    $colgroupAlignmentTable = null;
    foreach ($document->children as $node) {
        if ($node->type === 'table' && $node->attr('id') === 'colgroup-alignment-grid') {
            $colgroupAlignmentTable = $node;
            break;
        }
    }
    $colgroupAlignmentPacket = $colgroupAlignmentTable instanceof AstNode ? $colgroupAlignmentTable->attr('tableGeometry') : null;
    if (
        !$colgroupAlignmentTable instanceof AstNode
        || $colgroupAlignmentTable->attr('alignments') !== ['right', 'right', 'center']
        || $colgroupAlignmentTable->attr('widths') !== [0.25, 0.25, 0.5]
        || ($colgroupAlignmentTable->children[0]->children[0]->children[0]->attr('valign') ?? null) !== 'bottom'
        || ($colgroupAlignmentTable->children[1]->children[0]->children[1]->attr('valign') ?? null) !== 'bottom'
        || ($colgroupAlignmentTable->children[1]->children[1]->children[2]->attr('valign') ?? null) !== 'top'
    ) {
        throw new RuntimeException('Table geometry self-test missing HTML colgroup span alignment width and vertical alignment expansion');
    }
    $columnSources = $colgroupAlignmentTable->attr('columnSources');
    if (!is_array($columnSources) || ($columnSources[1]['spanOffset'] ?? null) !== 1 || ($columnSources[2]['colIndex'] ?? null) !== 1) {
        throw new RuntimeException('Table geometry self-test missing HTML colgroup span provenance metadata');
    }
    if (
        ($columnSources[0]['colgroupAttributes']['htmlAttributes']['data-source'] ?? null) !== 'legacy-doc'
        || ($columnSources[0]['verticalAlignment'] ?? null) !== 'bottom'
        || ($columnSources[2]['verticalAlignment'] ?? null) !== 'top'
        || ($columnSources[2]['colAttributes']['htmlAttributes']['data-origin'] ?? null) !== 'col-b'
    ) {
        throw new RuntimeException('Table geometry self-test missing source colgroup/col attributes or vertical alignment in provenance metadata');
    }
    if (!is_array($colgroupAlignmentPacket) || ($colgroupAlignmentPacket['coverage'][4]['columnAlignments'] ?? null) !== ['right'] || ($colgroupAlignmentPacket['coverage'][4]['verticalAlignment'] ?? null) !== 'bottom' || ($colgroupAlignmentPacket['coverage'][5]['widths'] ?? null) !== [0.5] || ($colgroupAlignmentPacket['coverage'][5]['verticalAlignment'] ?? null) !== 'top') {
        throw new RuntimeException('Table geometry self-test missing colgroup metadata in review-packet coverage');
    }
    if (($colgroupAlignmentPacket['columns'][1]['source']['spanOffset'] ?? null) !== 1 || ($colgroupAlignmentPacket['coverage'][5]['columnSources'][0]['colAttributes']['htmlAttributes']['data-origin'] ?? null) !== 'col-b') {
        throw new RuntimeException('Table geometry self-test missing colgroup provenance in review-packet columns and coverage');
    }
    if (
        count($colgroupAlignmentPacket['columnGroups'] ?? []) !== 2
        || ($colgroupAlignmentPacket['columnGroups'][0]['columns'] ?? null) !== [0, 1]
        || ($colgroupAlignmentPacket['columnGroups'][0]['spanOffsets'] ?? null) !== [0, 1]
        || ($colgroupAlignmentPacket['columnGroups'][0]['source']['verticalAlignment'] ?? null) !== 'bottom'
        || ($colgroupAlignmentPacket['columnGroups'][0]['source']['colAttributes']['htmlAttributes']['data-origin'] ?? null) !== 'col-a'
        || ($colgroupAlignmentPacket['columnGroups'][1]['columns'] ?? null) !== [2]
        || ($colgroupAlignmentPacket['columnGroups'][1]['source']['verticalAlignment'] ?? null) !== 'top'
        || ($colgroupAlignmentPacket['summary']['columnGroupCount'] ?? null) !== 2
    ) {
        throw new RuntimeException('Table geometry self-test missing grouped colgroup source-span metadata');
    }
    if (!str_contains($blocks, '<table id="colgroup-alignment-grid" data-source="html-reader"><colgroup data-source="legacy-doc"><col data-origin="col-a" style="width:25%"/><col data-origin="col-a" style="width:25%"/><col data-origin="col-b" valign="top" style="width:50%"/></colgroup><thead><tr><th style="text-align:right; vertical-align:bottom">Scope</th><th style="text-align:right; vertical-align:bottom">Items</th><th style="text-align:center; vertical-align:top">State</th></tr></thead>')) {
        throw new RuntimeException('Table geometry self-test missing WordPress output for expanded colgroup alignment provenance');
    }
    $colgroupWriterPacket = TableGeometry::reviewPacket($colgroupAlignmentTable, [
        'accessibility' => false,
        'writers' => ['markdown', 'asciidoctor', 'xelatex', 'wordpress'],
    ]);
    if (
        ($colgroupWriterPacket['summary']['writerDowngradeCodes'] ?? null) !== [
            'markdown-column-widths-approximated',
            'markdown-colgroup-provenance-require-raw-html',
            'asciidoc-colgroup-provenance-review-required',
            'latex-colgroup-provenance-review-required',
        ]
        || ($colgroupWriterPacket['writerDowngrades']['markdown'][1]['source'] ?? null) !== 'pandoc-column-sources'
        || ($colgroupWriterPacket['writerDowngrades']['markdown'][1]['columnGroupCount'] ?? null) !== 2
        || ($colgroupWriterPacket['writerDowngrades']['markdown'][1]['sourceAttributeGroupCount'] ?? null) !== 2
        || ($colgroupWriterPacket['writerDowngrades']['markdown'][1]['groups'][0]['source']['colgroupAttributes']['htmlAttributes']['data-source'] ?? null) !== 'legacy-doc'
        || ($colgroupWriterPacket['writerDowngrades']['asciidoc'][0]['requiredFeature'] ?? null) !== 'colgroup-provenance-review'
        || ($colgroupWriterPacket['writerDowngrades']['latex'][0]['requiredFeature'] ?? null) !== 'colgroup-provenance-review'
        || ($colgroupWriterPacket['writerDowngrades']['wordpress'] ?? null) !== []
    ) {
        throw new RuntimeException('Table geometry self-test missing non-HTML colgroup provenance writer diagnostics');
    }
    json_encode($colgroupAlignmentPacket, JSON_THROW_ON_ERROR);
    json_encode($colgroupWriterPacket, JSON_THROW_ON_ERROR);

    $columnBackgroundTable = null;
    foreach ($document->children as $node) {
        if ($node->type === 'table' && $node->attr('id') === 'column-background-grid') {
            $columnBackgroundTable = $node;
            break;
        }
    }
    $columnBackgroundPacket = $columnBackgroundTable instanceof AstNode ? $columnBackgroundTable->attr('tableGeometry') : null;
    $columnBackgrounds = is_array($columnBackgroundPacket) && is_array($columnBackgroundPacket['columnBackgrounds'] ?? null)
        ? $columnBackgroundPacket['columnBackgrounds']
        : [];
    if (
        !$columnBackgroundTable instanceof AstNode
        || count($columnBackgrounds) !== 2
        || ($columnBackgroundPacket['summary']['hasColumnBackgrounds'] ?? null) !== true
        || ($columnBackgroundPacket['summary']['columnBackgroundColumns'] ?? null) !== [0, 1, 2]
        || ($columnBackgroundPacket['summary']['columnBackgroundColors'] ?? null) !== ['#e6ffed', 'rgb(230, 255, 237)']
        || ($columnBackgrounds[0]['sourceElement'] ?? null) !== 'colgroup'
        || ($columnBackgrounds[0]['columns'] ?? null) !== [0, 1]
        || ($columnBackgrounds[0]['legacyBackgroundColor'] ?? null) !== '#fff4cc'
        || ($columnBackgrounds[1]['sourceElement'] ?? null) !== 'col'
        || ($columnBackgrounds[1]['backgroundColor'] ?? null) !== 'rgb(230, 255, 237)'
    ) {
        throw new RuntimeException('Table geometry self-test missing column background handoff metadata');
    }
    $columnBackgroundWriterPacket = TableGeometry::reviewPacket($columnBackgroundTable, [
        'accessibility' => false,
        'writers' => ['markdown', 'asciidoctor', 'xelatex', 'wordpress'],
    ]);
    $columnBackgroundDiagnostics = array_values(array_filter(
        $columnBackgroundWriterPacket['writerDowngrades']['markdown'] ?? [],
        static fn (array $diagnostic): bool => ($diagnostic['reason'] ?? null) === 'column-background'
    ));
    if (
        count($columnBackgroundDiagnostics) !== 1
        || ($columnBackgroundDiagnostics[0]['code'] ?? null) !== 'markdown-column-background-require-raw-html'
        || ($columnBackgroundDiagnostics[0]['requiredFeature'] ?? null) !== 'raw-html-column-background'
        || ($columnBackgroundDiagnostics[0]['columns'] ?? null) !== [0, 1, 2]
        || ($columnBackgroundWriterPacket['writerDowngrades']['asciidoc'][1]['requiredFeature'] ?? null) !== 'column-background-review'
        || ($columnBackgroundWriterPacket['writerDowngrades']['latex'][1]['requiredFeature'] ?? null) !== 'table-column-background-comments'
        || ($columnBackgroundWriterPacket['writerDowngrades']['wordpress'] ?? null) !== []
    ) {
        throw new RuntimeException('Table geometry self-test missing column background writer diagnostics');
    }
    $columnBackgroundBlocks = (new WordPressBlockWriter())->write($columnBackgroundDocument);
    if (!str_contains($columnBackgroundBlocks, '<colgroup bgcolor="#FFF4CC" data-source="legacy-doc"><col data-origin="metric-columns" style="width:25%; background-color:#e6ffed"/><col data-origin="metric-columns" style="width:25%; background-color:#e6ffed"/><col bgcolor="yellow" data-origin="state-column" style="width:50%; background-color:rgb(230, 255, 237)"/></colgroup>')) {
        throw new RuntimeException('Table geometry self-test missing WordPress output for column background metadata');
    }
    if (str_contains($columnBackgroundBlocks, 'javascript:')) {
        throw new RuntimeException('Table geometry self-test rendered unsafe column background style');
    }
    json_encode($columnBackgroundPacket, JSON_THROW_ON_ERROR);
    json_encode($columnBackgroundWriterPacket, JSON_THROW_ON_ERROR);

    $columnBorderPresentationTable = null;
    foreach ($document->children as $node) {
        if ($node->type === 'table' && $node->attr('id') === 'column-border-grid') {
            $columnBorderPresentationTable = $node;
            break;
        }
    }
    $columnBorderPresentationPacket = $columnBorderPresentationTable instanceof AstNode ? $columnBorderPresentationTable->attr('tableGeometry') : null;
    $columnBorderPresentations = is_array($columnBorderPresentationPacket) && is_array($columnBorderPresentationPacket['columnBorderPresentations'] ?? null)
        ? $columnBorderPresentationPacket['columnBorderPresentations']
        : [];
    if (
        !$columnBorderPresentationTable instanceof AstNode
        || count($columnBorderPresentations) !== 2
        || ($columnBorderPresentationPacket['summary']['hasColumnBorderPresentations'] ?? null) !== true
        || ($columnBorderPresentationPacket['summary']['columnBorderPresentationColumns'] ?? null) !== [0, 1, 2]
        || ($columnBorderPresentationPacket['summary']['columnBorderPresentationColors'] ?? null) !== ['#336699']
        || ($columnBorderPresentationPacket['summary']['columnBorderPresentationEdgeCount'] ?? null) !== 2
        || ($columnBorderPresentationPacket['summary']['columnBorderPresentationEdges'] ?? null) !== ['right', 'bottom']
        || ($columnBorderPresentations[0]['sourceElement'] ?? null) !== 'colgroup'
        || ($columnBorderPresentations[0]['columns'] ?? null) !== [0, 1]
        || ($columnBorderPresentations[0]['borderWidth'] ?? null) !== '2px'
        || ($columnBorderPresentations[1]['sourceElement'] ?? null) !== 'col'
        || ($columnBorderPresentations[1]['borderEdges'][0]['value'] ?? null) !== 'thick double green'
        || ($columnBorderPresentations[1]['borderEdges'][1]['borderColor'] ?? null) !== '#112233'
    ) {
        throw new RuntimeException('Table geometry self-test missing column border presentation handoff metadata');
    }
    $columnBorderWriterPacket = TableGeometry::reviewPacket($columnBorderPresentationTable, [
        'accessibility' => false,
        'writers' => ['markdown', 'asciidoc', 'latex', 'wordpress'],
    ]);
    $columnBorderDiagnostics = array_values(array_filter(
        $columnBorderWriterPacket['writerDowngrades']['markdown'] ?? [],
        static fn (array $diagnostic): bool => ($diagnostic['reason'] ?? null) === 'column-border-presentation'
    ));
    if (
        count($columnBorderDiagnostics) !== 1
        || ($columnBorderDiagnostics[0]['code'] ?? null) !== 'markdown-column-border-presentation-require-raw-html'
        || ($columnBorderDiagnostics[0]['requiredFeature'] ?? null) !== 'raw-html-column-border-presentation'
        || ($columnBorderDiagnostics[0]['columns'] ?? null) !== [0, 1, 2]
        || ($columnBorderDiagnostics[0]['edges'] ?? null) !== ['right', 'bottom']
        || ($columnBorderWriterPacket['writerDowngrades']['asciidoc'][1]['requiredFeature'] ?? null) !== 'column-border-presentation-review'
        || ($columnBorderWriterPacket['writerDowngrades']['latex'][1]['requiredFeature'] ?? null) !== 'table-column-border-presentation-comments'
        || ($columnBorderWriterPacket['writerDowngrades']['wordpress'] ?? null) !== []
    ) {
        throw new RuntimeException('Table geometry self-test missing column border presentation writer diagnostics');
    }
    $columnBorderBlocks = (new WordPressBlockWriter())->write($columnBorderPresentationDocument);
    if (!str_contains($columnBorderBlocks, '<colgroup data-source="legacy-doc"><col data-origin="metric-columns" style="width:25%; border-color:#336699; border-style:dashed; border-width:2px"/><col data-origin="metric-columns" style="width:25%; border-color:#336699; border-style:dashed; border-width:2px"/><col data-origin="state-column" style="width:50%; border-right:thick double green; border-bottom-width:3px; border-bottom-style:dotted; border-bottom-color:#112233"/></colgroup>')) {
        throw new RuntimeException('Table geometry self-test missing WordPress output for column border presentation metadata');
    }
    if (str_contains($columnBorderBlocks, 'javascript:')) {
        throw new RuntimeException('Table geometry self-test rendered unsafe column border style');
    }
    json_encode($columnBorderPresentationPacket, JSON_THROW_ON_ERROR);
    json_encode($columnBorderWriterPacket, JSON_THROW_ON_ERROR);

    $decimalAlignmentTable = null;
    foreach ($document->children as $node) {
        if ($node->type === 'table' && $node->attr('id') === 'decimal-alignment-grid') {
            $decimalAlignmentTable = $node;
            break;
        }
    }
    $decimalAlignmentPacket = $decimalAlignmentTable instanceof AstNode ? $decimalAlignmentTable->attr('tableGeometry') : null;
    $decimalColumnSources = $decimalAlignmentTable instanceof AstNode && is_array($decimalAlignmentTable->attr('columnSources'))
        ? $decimalAlignmentTable->attr('columnSources')
        : [];
    if (
        !$decimalAlignmentTable instanceof AstNode
        || $decimalAlignmentTable->attr('alignments') !== ['default', 'default', 'default']
        || $decimalAlignmentTable->attr('widths') !== [0.25, 0.25, 0.5]
        || ($decimalColumnSources[0]['colgroupAttributes']['htmlAttributes']['align'] ?? null) !== 'char'
        || ($decimalColumnSources[0]['colgroupAttributes']['htmlAttributes']['char'] ?? null) !== '.'
        || ($decimalColumnSources[2]['colAttributes']['htmlAttributes']['char'] ?? null) !== ','
    ) {
        throw new RuntimeException('Table geometry self-test missing source HTML column decimal alignment metadata');
    }
    if (
        !is_array($decimalAlignmentPacket)
        || count($decimalAlignmentPacket['columnDecimalAlignments'] ?? []) !== 2
        || ($decimalAlignmentPacket['columnDecimalAlignments'][0]['columns'] ?? null) !== [0, 1]
        || ($decimalAlignmentPacket['columnDecimalAlignments'][0]['sourceElement'] ?? null) !== 'colgroup'
        || ($decimalAlignmentPacket['columnDecimalAlignments'][0]['char'] ?? null) !== '.'
        || ($decimalAlignmentPacket['columnDecimalAlignments'][0]['charoff'] ?? null) !== '2'
        || ($decimalAlignmentPacket['columnDecimalAlignments'][1]['columns'] ?? null) !== [2]
        || ($decimalAlignmentPacket['columnDecimalAlignments'][1]['sourceElement'] ?? null) !== 'col'
        || ($decimalAlignmentPacket['columnDecimalAlignments'][1]['char'] ?? null) !== ','
        || ($decimalAlignmentPacket['summary']['hasColumnDecimalAlignments'] ?? null) !== true
        || ($decimalAlignmentPacket['summary']['columnDecimalAlignmentColumns'] ?? null) !== [0, 1, 2]
        || ($decimalAlignmentPacket['summary']['columnDecimalAlignmentChars'] ?? null) !== ['.', ',']
    ) {
        throw new RuntimeException('Table geometry self-test missing decimal alignment review-packet summary');
    }
    $decimalWriterPacket = TableGeometry::reviewPacket($decimalAlignmentTable, [
        'accessibility' => false,
        'writers' => ['markdown', 'asciidoc', 'latex', 'wordpress'],
    ]);
    $decimalMarkdownDiagnostics = array_values(array_filter(
        $decimalWriterPacket['writerDowngrades']['markdown'] ?? [],
        static fn (array $diagnostic): bool => ($diagnostic['code'] ?? null) === 'markdown-column-char-alignment-require-raw-html'
    ));
    if (
        !in_array('markdown-column-char-alignment-require-raw-html', $decimalWriterPacket['summary']['writerDowngradeCodes'] ?? [], true)
        || !in_array('asciidoc-column-char-alignment-review-required', $decimalWriterPacket['summary']['writerDowngradeCodes'] ?? [], true)
        || !in_array('latex-column-char-alignment-review-required', $decimalWriterPacket['summary']['writerDowngradeCodes'] ?? [], true)
        || count($decimalMarkdownDiagnostics) !== 1
        || ($decimalMarkdownDiagnostics[0]['requiredFeature'] ?? null) !== 'raw-html-column-char-alignment'
        || ($decimalMarkdownDiagnostics[0]['columns'] ?? null) !== [0, 1, 2]
        || ($decimalWriterPacket['writerDowngrades']['wordpress'] ?? null) !== []
    ) {
        throw new RuntimeException('Table geometry self-test missing decimal alignment writer diagnostics');
    }
    if (!str_contains($blocks, '<table id="decimal-alignment-grid" data-source="html-reader"><colgroup align="char" char="." charoff="2" data-source="legacy-doc"><col data-origin="amount-columns" style="width:25%"/><col data-origin="amount-columns" style="width:25%"/><col align="char" char="," charoff="1" data-origin="rate-column" style="width:50%"/></colgroup><thead><tr><th>Source</th><th>Amount</th><th>Rate</th></tr></thead>')) {
        throw new RuntimeException('Table geometry self-test missing WordPress output for decimal alignment columns');
    }
    json_encode($decimalAlignmentPacket, JSON_THROW_ON_ERROR);
    json_encode($decimalWriterPacket, JSON_THROW_ON_ERROR);

    $cellDecimalAlignmentTable = null;
    foreach ($document->children as $node) {
        if ($node->type === 'table' && $node->attr('id') === 'cell-decimal-alignment-grid') {
            $cellDecimalAlignmentTable = $node;
            break;
        }
    }
    $cellDecimalAlignmentPacket = $cellDecimalAlignmentTable instanceof AstNode ? $cellDecimalAlignmentTable->attr('tableGeometry') : null;
    $cellDecimalHeader = $cellDecimalAlignmentTable instanceof AstNode ? ($cellDecimalAlignmentTable->children[0]->children[0]->children[0] ?? null) : null;
    $cellDecimalBody = $cellDecimalAlignmentTable instanceof AstNode ? ($cellDecimalAlignmentTable->children[1]->children[0]->children[0] ?? null) : null;
    if (
        !$cellDecimalAlignmentTable instanceof AstNode
        || !$cellDecimalHeader instanceof AstNode
        || !$cellDecimalBody instanceof AstNode
        || ($cellDecimalHeader->attr('htmlAttributes')['align'] ?? null) !== 'char'
        || ($cellDecimalHeader->attr('htmlAttributes')['charoff'] ?? null) !== '2'
        || ($cellDecimalBody->attr('htmlAttributes')['char'] ?? null) !== '.'
    ) {
        throw new RuntimeException('Table geometry self-test missing source HTML cell decimal alignment metadata');
    }
    if (
        !is_array($cellDecimalAlignmentPacket)
        || count($cellDecimalAlignmentPacket['cellDecimalAlignments'] ?? []) !== 3
        || ($cellDecimalAlignmentPacket['cellDecimalAlignments'][0]['source'] ?? null) !== 'html-table-cell-char-alignment'
        || ($cellDecimalAlignmentPacket['cellDecimalAlignments'][0]['section'] ?? null) !== 'head'
        || ($cellDecimalAlignmentPacket['cellDecimalAlignments'][0]['columns'] ?? null) !== [0]
        || ($cellDecimalAlignmentPacket['cellDecimalAlignments'][0]['text'] ?? null) !== 'Amount'
        || ($cellDecimalAlignmentPacket['cellDecimalAlignments'][2]['char'] ?? null) !== ','
        || ($cellDecimalAlignmentPacket['summary']['hasCellDecimalAlignments'] ?? null) !== true
        || ($cellDecimalAlignmentPacket['summary']['cellDecimalAlignmentCount'] ?? null) !== 3
        || ($cellDecimalAlignmentPacket['summary']['cellDecimalAlignmentColumns'] ?? null) !== [0]
        || ($cellDecimalAlignmentPacket['summary']['cellDecimalAlignmentChars'] ?? null) !== ['.', ',']
        || ($cellDecimalAlignmentPacket['summary']['cellDecimalAlignmentOffsets'] ?? null) !== ['2', '1', '3']
        || ($cellDecimalAlignmentPacket['coverage'][0]['decimalAlignment']['alignment'] ?? null) !== 'char'
        || ($cellDecimalAlignmentPacket['coverage'][4]['decimalAlignment']['char'] ?? null) !== ','
    ) {
        throw new RuntimeException('Table geometry self-test missing cell decimal alignment review-packet summary');
    }
    $cellDecimalWriterPacket = TableGeometry::reviewPacket($cellDecimalAlignmentTable, [
        'accessibility' => false,
        'writers' => ['markdown', 'asciidoc', 'latex', 'wordpress'],
    ]);
    $cellDecimalMarkdownDiagnostics = array_values(array_filter(
        $cellDecimalWriterPacket['writerDowngrades']['markdown'] ?? [],
        static fn (array $diagnostic): bool => ($diagnostic['code'] ?? null) === 'markdown-cell-char-alignment-require-raw-html'
    ));
    if (
        !in_array('markdown-cell-char-alignment-require-raw-html', $cellDecimalWriterPacket['summary']['writerDowngradeCodes'] ?? [], true)
        || !in_array('asciidoc-cell-char-alignment-review-required', $cellDecimalWriterPacket['summary']['writerDowngradeCodes'] ?? [], true)
        || !in_array('latex-cell-char-alignment-review-required', $cellDecimalWriterPacket['summary']['writerDowngradeCodes'] ?? [], true)
        || count($cellDecimalMarkdownDiagnostics) !== 1
        || ($cellDecimalMarkdownDiagnostics[0]['requiredFeature'] ?? null) !== 'raw-html-cell-char-alignment'
        || ($cellDecimalMarkdownDiagnostics[0]['columns'] ?? null) !== [0]
        || ($cellDecimalMarkdownDiagnostics[0]['cells'][2]['text'] ?? null) !== '7,25'
        || ($cellDecimalWriterPacket['writerDowngrades']['wordpress'] ?? null) !== []
    ) {
        throw new RuntimeException('Table geometry self-test missing cell decimal alignment writer diagnostics');
    }
    if (!str_contains($blocks, '<table id="cell-decimal-alignment-grid" data-source="html-reader"><colgroup><col style="width:50%"/><col style="width:50%"/></colgroup><thead><tr><th align="char" char="." charoff="2">Amount</th><th>Status</th></tr></thead><tbody><tr><td align="char" char="." charoff="1">42.50</td><td>Ready</td></tr><tr><td align="char" char="," charoff="3">7,25</td><td>Review</td></tr></tbody></table><figcaption class="wp-element-caption">Cell decimal alignment review</figcaption></figure>')) {
        throw new RuntimeException('Table geometry self-test missing WordPress output for decimal alignment cells');
    }
    json_encode($cellDecimalAlignmentPacket, JSON_THROW_ON_ERROR);
    json_encode($cellDecimalWriterPacket, JSON_THROW_ON_ERROR);

    $colgroupMismatchTable = null;
    foreach ($document->children as $node) {
        if ($node->type === 'table' && $node->attr('id') === 'colgroup-underdeclared-grid') {
            $colgroupMismatchTable = $node;
            break;
        }
    }
    $colgroupMismatchPacket = $colgroupMismatchTable instanceof AstNode ? $colgroupMismatchTable->attr('tableGeometry') : null;
    if (
        !$colgroupMismatchTable instanceof AstNode
        || $colgroupMismatchTable->attr('alignments') !== ['right', 'right', 'default']
        || $colgroupMismatchTable->attr('widths') !== [0.2, 0.2, null]
    ) {
        throw new RuntimeException('Table geometry self-test missing underdeclared HTML colgroup partial metadata');
    }
    if (
        !is_array($colgroupMismatchPacket)
        || ($colgroupMismatchPacket['summary']['diagnosticCodes'] ?? null) !== ['html-colgroup-underdeclares-columns']
        || ($colgroupMismatchPacket['diagnostics'][0]['missingColumns'] ?? null) !== [2]
    ) {
        throw new RuntimeException('Table geometry self-test missing underdeclared HTML colgroup diagnostics');
    }
    if (($colgroupMismatchPacket['columns'][1]['source']['colAttributes']['htmlAttributes']['data-origin'] ?? null) !== 'declared-pair') {
        throw new RuntimeException('Table geometry self-test missing partial colgroup source provenance');
    }
    if (isset($colgroupMismatchPacket['columns'][2]['source']) || isset($colgroupMismatchPacket['coverage'][5]['columnSources'])) {
        throw new RuntimeException('Table geometry self-test leaked partial colgroup provenance into missing source columns');
    }
    if (str_contains($blocks, '<table id="colgroup-underdeclared-grid" data-source="html-reader"><colgroup>')) {
        throw new RuntimeException('Table geometry self-test emitted a misleading colgroup for incomplete source widths');
    }
    if (!str_contains($blocks, '<table id="colgroup-underdeclared-grid" data-source="html-reader"><thead><tr><th style="text-align:right">Scope</th><th style="text-align:right">Items</th><th>State</th></tr></thead>')) {
        throw new RuntimeException('Table geometry self-test missing WordPress output for underdeclared colgroup metadata');
    }
    json_encode($colgroupMismatchPacket, JSON_THROW_ON_ERROR);

    $malformedColumnSpanTable = null;
    foreach ($document->children as $node) {
        if ($node->type === 'table' && $node->attr('id') === 'malformed-column-span-grid') {
            $malformedColumnSpanTable = $node;
            break;
        }
    }
    $malformedColumnSpanPacket = $malformedColumnSpanTable instanceof AstNode ? $malformedColumnSpanTable->attr('tableGeometry') : null;
    if (
        !$malformedColumnSpanTable instanceof AstNode
        || $malformedColumnSpanTable->attr('alignments') !== ['right', 'center', 'left']
        || $malformedColumnSpanTable->attr('widths') !== [0.25, 0.5, 0.25]
    ) {
        throw new RuntimeException('Table geometry self-test missing malformed HTML column span normalized metadata');
    }
    if (
        !is_array($malformedColumnSpanPacket)
        || ($malformedColumnSpanPacket['summary']['diagnosticCodes'] ?? null) !== ['html-column-span-normalized']
        || ($malformedColumnSpanPacket['summary']['normalizedColumnSpanCount'] ?? null) !== 3
        || ($malformedColumnSpanPacket['summary']['normalizedColumnSpanSourceElements'] ?? null) !== ['colgroup', 'col']
    ) {
        throw new RuntimeException('Table geometry self-test missing malformed HTML column span diagnostics');
    }
    if (
        array_map(static fn (array $diagnostic): mixed => $diagnostic['rawValue'] ?? null, $malformedColumnSpanPacket['diagnostics'] ?? []) !== ['0', 'two', '-2']
        || array_map(static fn (array $diagnostic): string => (string) ($diagnostic['sourceElement'] ?? ''), $malformedColumnSpanPacket['diagnostics'] ?? []) !== ['colgroup', 'col', 'col']
    ) {
        throw new RuntimeException('Table geometry self-test missing malformed HTML column span raw values');
    }
    if (
        ($malformedColumnSpanPacket['columnGroups'][0]['source']['colgroupAttributes']['htmlAttributes']['span'] ?? null) !== '0'
        || ($malformedColumnSpanPacket['columns'][1]['source']['colAttributes']['htmlAttributes']['span'] ?? null) !== 'two'
        || ($malformedColumnSpanPacket['columns'][2]['source']['colAttributes']['htmlAttributes']['span'] ?? null) !== '-2'
    ) {
        throw new RuntimeException('Table geometry self-test missing malformed HTML column span provenance');
    }
    if (!str_contains($blocks, '<table id="malformed-column-span-grid" data-source="html-reader"><colgroup data-origin="group-zero"><col style="width:25%"/></colgroup><colgroup data-origin="colgroup-explicit"><col data-origin="col-two" style="width:50%"/><col data-origin="col-negative" style="width:25%"/></colgroup><tbody><tr><td style="text-align:right">Posts</td><td style="text-align:center">Ready</td><td style="text-align:left">Review</td></tr></tbody></table>')) {
        throw new RuntimeException('Table geometry self-test missing normalized WordPress output for malformed HTML column spans');
    }
    if (str_contains($blocks, 'span="0"') || str_contains($blocks, 'span="two"') || str_contains($blocks, 'span="-2"')) {
        throw new RuntimeException('Table geometry self-test leaked malformed HTML column spans into WordPress output');
    }
    json_encode($malformedColumnSpanPacket, JSON_THROW_ON_ERROR);

    $inheritedAlignmentTable = null;
    foreach ($document->children as $node) {
        if ($node->type === 'table' && $node->attr('id') === 'inherited-alignment-grid') {
            $inheritedAlignmentTable = $node;
            break;
        }
    }
    $inheritedAlignmentPacket = $inheritedAlignmentTable instanceof AstNode ? $inheritedAlignmentTable->attr('tableGeometry') : null;
    if (
        !$inheritedAlignmentTable instanceof AstNode
        || $inheritedAlignmentTable->attr('alignments') !== ['default', 'default', 'default']
        || ($inheritedAlignmentTable->children[0]->children[0]->children[0]->attr('align') ?? null) !== 'center'
        || ($inheritedAlignmentTable->children[1]->children[0]->children[1]->attr('align') ?? null) !== 'right'
        || ($inheritedAlignmentTable->children[1]->children[1]->children[2]->attr('align') ?? null) !== 'left'
        || ($inheritedAlignmentTable->children[2]->children[0]->children[2]->attr('align') ?? null) !== 'center'
    ) {
        throw new RuntimeException('Table geometry self-test missing inherited HTML row group and row alignment metadata');
    }
    if (
        !is_array($inheritedAlignmentPacket)
        || array_map(static fn (array $coverage): string => (string) ($coverage['alignment'] ?? ''), $inheritedAlignmentPacket['coverage'] ?? []) !== [
            'center',
            'right',
            'center',
            'right',
            'right',
            'center',
            'left',
            'left',
            'left',
            'center',
            'center',
            'center',
        ]
        || ($inheritedAlignmentPacket['coverage'][3]['headerCell'] ?? null) !== true
        || ($inheritedAlignmentPacket['coverage'][3]['rowHeadColumns'] ?? null) !== 1
    ) {
        throw new RuntimeException('Table geometry self-test missing inherited alignment review-packet coverage');
    }
    if (!str_contains($blocks, '<table id="inherited-alignment-grid" data-source="html-reader"><colgroup><col style="width:33.3333%"/><col style="width:33.3333%"/><col style="width:33.3333%"/></colgroup><thead><tr><th style="text-align:center">Scope</th><th style="text-align:right">Items</th><th style="text-align:center">State</th></tr></thead><tbody data-section="body"><tr data-row="posts"><th style="text-align:right">Posts</th><td style="text-align:right">42</td><td style="text-align:center">Ready</td></tr><tr data-row="media"><th style="text-align:left">Media</th><td style="text-align:left">7</td><td style="text-align:left">Review</td></tr></tbody><tfoot><tr><td style="text-align:center">Total</td><td style="text-align:center">49</td><td style="text-align:center">Review</td></tr></tfoot></table>')) {
        throw new RuntimeException('Table geometry self-test missing WordPress output for inherited HTML alignment handoff');
    }
    json_encode($inheritedAlignmentPacket, JSON_THROW_ON_ERROR);

    $verticalAlignmentTable = null;
    foreach ($document->children as $node) {
        if ($node->type === 'table' && $node->attr('id') === 'vertical-alignment-grid') {
            $verticalAlignmentTable = $node;
            break;
        }
    }
    $verticalAlignmentPacket = $verticalAlignmentTable instanceof AstNode ? $verticalAlignmentTable->attr('tableGeometry') : null;
    if (
        !$verticalAlignmentTable instanceof AstNode
        || ($verticalAlignmentTable->children[0]->children[0]->children[0]->attr('valign') ?? null) !== 'top'
        || ($verticalAlignmentTable->children[0]->children[0]->children[1]->attr('valign') ?? null) !== 'bottom'
        || ($verticalAlignmentTable->children[1]->children[0]->children[0]->attr('valign') ?? null) !== 'middle'
        || ($verticalAlignmentTable->children[1]->children[0]->children[1]->attr('valign') ?? null) !== 'baseline'
    ) {
        throw new RuntimeException('Table geometry self-test missing HTML vertical alignment metadata');
    }
    if (
        !is_array($verticalAlignmentPacket)
        || array_map(static fn (array $coverage): string => (string) ($coverage['verticalAlignment'] ?? ''), $verticalAlignmentPacket['coverage'] ?? []) !== [
            'top',
            'bottom',
            'middle',
            'baseline',
            'top',
            'top',
        ]
        || ($verticalAlignmentPacket['sections'][1]['rows'][0]['slots'][1]['verticalAlignment'] ?? null) !== 'baseline'
    ) {
        throw new RuntimeException('Table geometry self-test missing vertical alignment review-packet coverage');
    }
    if (!str_contains($blocks, '<thead valign="top"><tr><th style="vertical-align:top">Scope</th><th style="vertical-align: bottom">State</th></tr></thead>') || !str_contains($blocks, '<tbody data-section="body" valign="baseline"><tr><td valign="middle">Posts</td><td style="vertical-align:baseline">Ready</td></tr><tr style="vertical-align: top"><td style="vertical-align:top">Total</td><td style="vertical-align:top">Review</td></tr></tbody>')) {
        throw new RuntimeException('Table geometry self-test missing WordPress output for vertical alignment handoff');
    }
    json_encode($verticalAlignmentPacket, JSON_THROW_ON_ERROR);

    $legacyFrameTable = null;
    foreach ($document->children as $node) {
        if ($node->type === 'table' && $node->attr('id') === 'legacy-frame-grid') {
            $legacyFrameTable = $node;
            break;
        }
    }
    $legacyFramePacket = $legacyFrameTable instanceof AstNode ? $legacyFrameTable->attr('tableGeometry') : null;
    $legacyFrameDowngrades = $legacyFrameTable instanceof AstNode ? TableGeometry::reviewPacket($legacyFrameTable, [
        'accessibility' => false,
        'writers' => ['markdown', 'asciidoc', 'latex'],
    ]) : [];
    $legacyFrameDiagnostics = [];
    foreach (['markdown', 'asciidoc', 'latex'] as $writer) {
        $matches = array_values(array_filter(
            $legacyFrameDowngrades['writerDowngrades'][$writer] ?? [],
            static fn (array $diagnostic): bool => ($diagnostic['reason'] ?? null) === 'table-frame'
        ));
        $legacyFrameDiagnostics[$writer] = $matches[0] ?? [];
    }
    if (
        !$legacyFrameTable instanceof AstNode
        || !is_array($legacyFramePacket)
        || ($legacyFramePacket['tableFrame']['attributes'] ?? null) !== [
            'border' => '1',
            'frame' => 'void',
            'rules' => 'groups',
        ]
        || ($legacyFramePacket['summary']['hasTableFrame'] ?? null) !== true
        || ($legacyFramePacket['summary']['tableFrame'] ?? null) !== 'void'
        || ($legacyFramePacket['summary']['tableRules'] ?? null) !== 'groups'
        || ($legacyFramePacket['summary']['tableBorder'] ?? null) !== '1'
        || ($legacyFrameDiagnostics['markdown']['code'] ?? null) !== 'markdown-table-frame-requires-raw-html'
        || ($legacyFrameDiagnostics['asciidoc']['code'] ?? null) !== 'asciidoc-table-frame-review-required'
        || ($legacyFrameDiagnostics['latex']['code'] ?? null) !== 'latex-table-frame-review-required'
    ) {
        throw new RuntimeException('Table geometry self-test missing legacy table frame/rules/border metadata');
    }
    if (!str_contains($blocks, '<table id="legacy-frame-grid" data-source="html-reader" border="1" frame="void" rules="groups">')) {
        throw new RuntimeException('Table geometry self-test missing WordPress legacy table frame/rules/border output');
    }
    json_encode($legacyFramePacket, JSON_THROW_ON_ERROR);
    json_encode($legacyFrameDowngrades, JSON_THROW_ON_ERROR);

    $legacySpacingTable = null;
    foreach ($document->children as $node) {
        if ($node->type === 'table' && $node->attr('id') === 'legacy-spacing-grid') {
            $legacySpacingTable = $node;
            break;
        }
    }
    $legacySpacingPacket = $legacySpacingTable instanceof AstNode ? $legacySpacingTable->attr('tableGeometry') : null;
    $legacySpacingDowngrades = $legacySpacingTable instanceof AstNode ? TableGeometry::reviewPacket($legacySpacingTable, [
        'accessibility' => false,
        'writers' => ['markdown', 'asciidoc', 'latex'],
    ]) : [];
    $legacySpacingDiagnostics = [];
    foreach (['markdown', 'asciidoc', 'latex'] as $writer) {
        $matches = array_values(array_filter(
            $legacySpacingDowngrades['writerDowngrades'][$writer] ?? [],
            static fn (array $diagnostic): bool => ($diagnostic['reason'] ?? null) === 'table-spacing'
        ));
        $legacySpacingDiagnostics[$writer] = $matches[0] ?? [];
    }
    if (
        !$legacySpacingTable instanceof AstNode
        || !is_array($legacySpacingPacket)
        || ($legacySpacingPacket['tableSpacing']['attributes'] ?? null) !== [
            'cellpadding' => '6',
            'cellspacing' => '2',
        ]
        || ($legacySpacingPacket['summary']['hasTableSpacing'] ?? null) !== true
        || ($legacySpacingPacket['summary']['tableCellPadding'] ?? null) !== '6'
        || ($legacySpacingPacket['summary']['tableCellSpacing'] ?? null) !== '2'
        || ($legacySpacingDiagnostics['markdown']['code'] ?? null) !== 'markdown-table-spacing-requires-raw-html'
        || ($legacySpacingDiagnostics['asciidoc']['code'] ?? null) !== 'asciidoc-table-spacing-review-required'
        || ($legacySpacingDiagnostics['latex']['code'] ?? null) !== 'latex-table-spacing-review-required'
    ) {
        throw new RuntimeException('Table geometry self-test missing legacy table cell spacing metadata');
    }
    if (!str_contains($blocks, '<table id="legacy-spacing-grid" data-source="html-reader" cellpadding="6" cellspacing="2">')) {
        throw new RuntimeException('Table geometry self-test missing WordPress legacy table cell spacing output');
    }
    json_encode($legacySpacingPacket, JSON_THROW_ON_ERROR);
    json_encode($legacySpacingDowngrades, JSON_THROW_ON_ERROR);

    $cellNoWrapTable = null;
    foreach ($document->children as $node) {
        if ($node->type === 'table' && $node->attr('id') === 'cell-nowrap-grid') {
            $cellNoWrapTable = $node;
            break;
        }
    }
    $cellNoWrapPacket = $cellNoWrapTable instanceof AstNode ? $cellNoWrapTable->attr('tableGeometry') : null;
    $cellNoWrapDowngrades = $cellNoWrapTable instanceof AstNode ? TableGeometry::reviewPacket($cellNoWrapTable, [
        'accessibility' => false,
        'writers' => ['markdown', 'asciidoc', 'latex'],
    ]) : [];
    $cellNoWrapDiagnostics = [];
    foreach (['markdown', 'asciidoc', 'latex'] as $writer) {
        $matches = array_values(array_filter(
            $cellNoWrapDowngrades['writerDowngrades'][$writer] ?? [],
            static fn (array $diagnostic): bool => ($diagnostic['reason'] ?? null) === 'cell-nowrap'
        ));
        $cellNoWrapDiagnostics[$writer] = $matches[0] ?? [];
    }
    if (
        !$cellNoWrapTable instanceof AstNode
        || !is_array($cellNoWrapPacket)
        || ($cellNoWrapPacket['summary']['hasCellNoWraps'] ?? null) !== true
        || ($cellNoWrapPacket['summary']['cellNoWrapCount'] ?? null) !== 2
        || ($cellNoWrapPacket['summary']['cellNoWrapColumns'] ?? null) !== [0, 1]
        || ($cellNoWrapPacket['summary']['cellNoWrapSections'] ?? null) !== ['head', 'body']
        || ($cellNoWrapPacket['cellNoWraps'][0]['htmlAttributes'] ?? null) !== ['nowrap' => 'nowrap']
        || ($cellNoWrapPacket['cellNoWraps'][1]['text'] ?? null) !== 'Long unbroken review value'
        || ($cellNoWrapDiagnostics['markdown']['code'] ?? null) !== 'markdown-cell-nowrap-require-raw-html'
        || ($cellNoWrapDiagnostics['asciidoc']['code'] ?? null) !== 'asciidoc-cell-nowrap-review-required'
        || ($cellNoWrapDiagnostics['latex']['code'] ?? null) !== 'latex-cell-nowrap-review-required'
    ) {
        throw new RuntimeException('Table geometry self-test missing HTML cell nowrap metadata');
    }
    if (
        !str_contains($blocks, '<th nowrap="nowrap">Source label</th><th>Status</th><th>Wrap</th>')
        || !str_contains($blocks, '<td>Posts</td><td nowrap="nowrap">Long unbroken review value</td><td>Review wraps</td>')
        || str_contains($blocks, 'nowrap="false"')
    ) {
        throw new RuntimeException('Table geometry self-test missing sanitized WordPress cell nowrap output');
    }
    json_encode($cellNoWrapPacket, JSON_THROW_ON_ERROR);
    json_encode($cellNoWrapDowngrades, JSON_THROW_ON_ERROR);

    $cellDimensionTable = null;
    foreach ($document->children as $node) {
        if ($node->type === 'table' && $node->attr('id') === 'cell-dimension-grid') {
            $cellDimensionTable = $node;
            break;
        }
    }
    $cellDimensionPacket = $cellDimensionTable instanceof AstNode ? $cellDimensionTable->attr('tableGeometry') : null;
    $cellDimensionDowngrades = $cellDimensionTable instanceof AstNode ? TableGeometry::reviewPacket($cellDimensionTable, [
        'accessibility' => false,
        'writers' => ['markdown', 'asciidoc', 'latex'],
    ]) : [];
    $cellDimensionDiagnostics = [];
    foreach (['markdown', 'asciidoc', 'latex'] as $writer) {
        $matches = array_values(array_filter(
            $cellDimensionDowngrades['writerDowngrades'][$writer] ?? [],
            static fn (array $diagnostic): bool => ($diagnostic['reason'] ?? null) === 'cell-dimensions'
        ));
        $cellDimensionDiagnostics[$writer] = $matches[0] ?? [];
    }
    if (
        !$cellDimensionTable instanceof AstNode
        || !is_array($cellDimensionPacket)
        || ($cellDimensionPacket['summary']['hasCellDimensions'] ?? null) !== true
        || ($cellDimensionPacket['summary']['cellDimensionCount'] ?? null) !== 4
        || ($cellDimensionPacket['summary']['cellDimensionColumns'] ?? null) !== [0, 1]
        || ($cellDimensionPacket['summary']['cellDimensionSections'] ?? null) !== ['head', 'body']
        || ($cellDimensionPacket['summary']['cellDimensionWidthTypes'] ?? null) !== ['pixels', 'percent']
        || ($cellDimensionPacket['summary']['cellDimensionHeightTypes'] ?? null) !== ['percent', 'pixels']
        || ($cellDimensionPacket['cellDimensions'][0]['attributes'] ?? null) !== ['width' => '120']
        || ($cellDimensionPacket['cellDimensions'][1]['attributes'] ?? null) !== ['height' => '35%', 'width' => '40%']
        || ($cellDimensionPacket['cellDimensions'][2]['attributes'] ?? null) !== ['height' => '32']
        || ($cellDimensionPacket['cellDimensions'][3]['attributes'] ?? null) !== ['height' => '44', 'width' => '50%']
        || ($cellDimensionDiagnostics['markdown']['code'] ?? null) !== 'markdown-cell-dimensions-require-raw-html'
        || ($cellDimensionDiagnostics['asciidoc']['code'] ?? null) !== 'asciidoc-cell-dimensions-review-required'
        || ($cellDimensionDiagnostics['latex']['code'] ?? null) !== 'latex-cell-dimensions-review-required'
    ) {
        throw new RuntimeException('Table geometry self-test missing HTML cell dimension metadata');
    }
    if (
        !str_contains($blocks, '<th width="120">Source</th><th style="width:40%; height:35%">Status</th><th>Wrap</th>')
        || !str_contains($blocks, '<td height="32">Posts</td><td width="50%" height="44">Ready</td><td>Ignored</td>')
        || str_contains($blocks, 'width="0"')
    ) {
        throw new RuntimeException('Table geometry self-test missing sanitized WordPress cell dimension output');
    }
    json_encode($cellDimensionPacket, JSON_THROW_ON_ERROR);
    json_encode($cellDimensionDowngrades, JSON_THROW_ON_ERROR);

    $rowBackgroundTable = null;
    foreach ($document->children as $node) {
        if ($node->type === 'table' && $node->attr('id') === 'row-background-grid') {
            $rowBackgroundTable = $node;
            break;
        }
    }
    $rowBackgroundPacket = $rowBackgroundTable instanceof AstNode ? $rowBackgroundTable->attr('tableGeometry') : null;
    $rowBackgroundDowngrades = $rowBackgroundTable instanceof AstNode ? TableGeometry::reviewPacket($rowBackgroundTable, [
        'accessibility' => false,
        'writers' => ['markdown', 'asciidoc', 'latex'],
    ]) : [];
    $rowBackgroundDiagnostics = [];
    foreach (['markdown', 'asciidoc', 'latex'] as $writer) {
        $matches = array_values(array_filter(
            $rowBackgroundDowngrades['writerDowngrades'][$writer] ?? [],
            static fn (array $diagnostic): bool => ($diagnostic['reason'] ?? null) === 'row-background'
        ));
        $rowBackgroundDiagnostics[$writer] = $matches[0] ?? [];
    }
    if (
        !$rowBackgroundTable instanceof AstNode
        || !is_array($rowBackgroundPacket)
        || ($rowBackgroundPacket['summary']['hasRowBackgrounds'] ?? null) !== true
        || ($rowBackgroundPacket['summary']['rowBackgroundCount'] ?? null) !== 3
        || ($rowBackgroundPacket['summary']['rowBackgroundRows'] ?? null) !== [0, 1]
        || ($rowBackgroundPacket['summary']['rowBackgroundGlobalRows'] ?? null) !== [0, 1, 2]
        || ($rowBackgroundPacket['summary']['rowBackgroundSections'] ?? null) !== ['head', 'body']
        || ($rowBackgroundPacket['summary']['rowBackgroundColors'] ?? null) !== ['#fff4cc', '#e6ffed', 'rgb(230, 255, 237)']
        || ($rowBackgroundPacket['rowBackgrounds'][0]['attributes'] ?? null) !== ['bgcolor' => '#fff4cc']
        || ($rowBackgroundPacket['rowBackgrounds'][2]['legacyBackgroundColor'] ?? null) !== 'yellow'
        || ($rowBackgroundDiagnostics['markdown']['code'] ?? null) !== 'markdown-row-background-require-raw-html'
        || ($rowBackgroundDiagnostics['asciidoc']['code'] ?? null) !== 'asciidoc-row-background-review-required'
        || ($rowBackgroundDiagnostics['latex']['code'] ?? null) !== 'latex-row-background-review-required'
    ) {
        throw new RuntimeException('Table geometry self-test missing HTML row background metadata');
    }
    if (
        !str_contains($blocks, '<tr bgcolor="#FFF4CC"><th>Source</th><th>Status</th></tr>')
        || !str_contains($blocks, '<tr style="background-color: #e6ffed"><td>Posts</td><td>Ready</td></tr>')
        || !str_contains($blocks, '<tr bgcolor="yellow" style="background-color: rgb(230, 255, 237)"><td>Media</td><td>Review</td></tr>')
    ) {
        throw new RuntimeException('Table geometry self-test missing WordPress row background output');
    }
    json_encode($rowBackgroundPacket, JSON_THROW_ON_ERROR);
    json_encode($rowBackgroundDowngrades, JSON_THROW_ON_ERROR);

    $rowBorderPresentationTable = null;
    foreach ($document->children as $node) {
        if ($node->type === 'table' && $node->attr('id') === 'row-border-presentation-grid') {
            $rowBorderPresentationTable = $node;
            break;
        }
    }
    $rowBorderPresentationPacket = $rowBorderPresentationTable instanceof AstNode ? $rowBorderPresentationTable->attr('tableGeometry') : null;
    $rowBorderPresentationDowngrades = $rowBorderPresentationTable instanceof AstNode ? TableGeometry::reviewPacket($rowBorderPresentationTable, [
        'accessibility' => false,
        'writers' => ['markdown', 'asciidoc', 'latex'],
    ]) : [];
    $rowBorderPresentationDiagnostics = [];
    foreach (['markdown', 'asciidoc', 'latex'] as $writer) {
        $matches = array_values(array_filter(
            $rowBorderPresentationDowngrades['writerDowngrades'][$writer] ?? [],
            static fn (array $diagnostic): bool => ($diagnostic['reason'] ?? null) === 'row-border-presentation'
        ));
        $rowBorderPresentationDiagnostics[$writer] = $matches[0] ?? [];
    }
    if (
        !$rowBorderPresentationTable instanceof AstNode
        || !is_array($rowBorderPresentationPacket)
        || ($rowBorderPresentationPacket['summary']['hasRowBorderPresentations'] ?? null) !== true
        || ($rowBorderPresentationPacket['summary']['rowBorderPresentationCount'] ?? null) !== 3
        || ($rowBorderPresentationPacket['summary']['rowBorderPresentationRows'] ?? null) !== [0, 1]
        || ($rowBorderPresentationPacket['summary']['rowBorderPresentationGlobalRows'] ?? null) !== [0, 1, 2]
        || ($rowBorderPresentationPacket['summary']['rowBorderPresentationSections'] ?? null) !== ['head', 'body']
        || ($rowBorderPresentationPacket['summary']['rowBorderPresentationColors'] ?? null) !== ['#336699']
        || ($rowBorderPresentationPacket['summary']['rowBorderPresentationStyles'] ?? null) !== ['dashed']
        || ($rowBorderPresentationPacket['summary']['rowBorderPresentationWidths'] ?? null) !== ['2px']
        || ($rowBorderPresentationPacket['summary']['rowBorderPresentationEdgeCount'] ?? null) !== 2
        || ($rowBorderPresentationPacket['summary']['rowBorderPresentationEdges'] ?? null) !== ['right', 'bottom']
        || ($rowBorderPresentationPacket['summary']['rowBorderPresentationEdgeColors'] ?? null) !== ['green', '#112233']
        || ($rowBorderPresentationPacket['summary']['rowBorderPresentationEdgeStyles'] ?? null) !== ['double', 'dotted']
        || ($rowBorderPresentationPacket['summary']['rowBorderPresentationEdgeWidths'] ?? null) !== ['thick', '3px']
        || ($rowBorderPresentationPacket['rowBorderPresentations'][0]['attributes'] ?? null) !== ['border-color' => '#336699', 'border-style' => 'dashed', 'border-width' => '2px']
        || ($rowBorderPresentationPacket['rowBorderPresentations'][1]['borderEdges'][0]['borderColor'] ?? null) !== 'green'
        || ($rowBorderPresentationPacket['rowBorderPresentations'][2]['borderEdges'][0]['attributes'] ?? null) !== ['border-bottom-color' => '#112233', 'border-bottom-style' => 'dotted', 'border-bottom-width' => '3px']
        || ($rowBorderPresentationDiagnostics['markdown']['code'] ?? null) !== 'markdown-row-border-presentation-require-raw-html'
        || ($rowBorderPresentationDiagnostics['asciidoc']['code'] ?? null) !== 'asciidoc-row-border-presentation-review-required'
        || ($rowBorderPresentationDiagnostics['latex']['code'] ?? null) !== 'latex-row-border-presentation-review-required'
        || ($rowBorderPresentationDiagnostics['markdown']['requiredFeature'] ?? null) !== 'raw-html-row-border-presentation'
    ) {
        throw new RuntimeException('Table geometry self-test missing HTML row border presentation metadata');
    }
    if (
        !str_contains($blocks, '<tr style="border-color: #336699; border-style: dashed; border-width: 2px"><th>Source</th><th>Status</th></tr>')
        || !str_contains($blocks, '<tr style="border-right: thick double green"><td>Posts</td><td>Ready</td></tr>')
        || !str_contains($blocks, '<tr style="border-bottom-width: 3px; border-bottom-style: dotted; border-bottom-color: #123"><td>Media</td><td>Review</td></tr>')
    ) {
        throw new RuntimeException('Table geometry self-test missing WordPress row border presentation output');
    }
    json_encode($rowBorderPresentationPacket, JSON_THROW_ON_ERROR);
    json_encode($rowBorderPresentationDowngrades, JSON_THROW_ON_ERROR);

    $sectionPresentationTable = null;
    foreach ($document->children as $node) {
        if ($node->type === 'table' && $node->attr('id') === 'section-presentation-grid') {
            $sectionPresentationTable = $node;
            break;
        }
    }
    $sectionPresentationPacket = $sectionPresentationTable instanceof AstNode ? $sectionPresentationTable->attr('tableGeometry') : null;
    $sectionPresentationDowngrades = $sectionPresentationTable instanceof AstNode ? TableGeometry::reviewPacket($sectionPresentationTable, [
        'accessibility' => false,
        'writers' => ['markdown', 'asciidoc', 'latex'],
    ]) : [];
    $sectionBackgroundDiagnostics = [];
    $sectionBorderDiagnostics = [];
    foreach (['markdown', 'asciidoc', 'latex'] as $writer) {
        $backgroundMatches = array_values(array_filter(
            $sectionPresentationDowngrades['writerDowngrades'][$writer] ?? [],
            static fn (array $diagnostic): bool => ($diagnostic['reason'] ?? null) === 'section-background'
        ));
        $borderMatches = array_values(array_filter(
            $sectionPresentationDowngrades['writerDowngrades'][$writer] ?? [],
            static fn (array $diagnostic): bool => ($diagnostic['reason'] ?? null) === 'section-border-presentation'
        ));
        $sectionBackgroundDiagnostics[$writer] = $backgroundMatches[0] ?? [];
        $sectionBorderDiagnostics[$writer] = $borderMatches[0] ?? [];
    }
    if (
        !$sectionPresentationTable instanceof AstNode
        || !is_array($sectionPresentationPacket)
        || ($sectionPresentationPacket['summary']['hasSectionBackgrounds'] ?? null) !== true
        || ($sectionPresentationPacket['summary']['sectionBackgroundCount'] ?? null) !== 3
        || ($sectionPresentationPacket['summary']['sectionBackgroundSections'] ?? null) !== ['head', 'body', 'foot']
        || ($sectionPresentationPacket['summary']['sectionBackgroundColors'] ?? null) !== ['#fff4cc', 'yellow', 'rgb(230, 255, 237)']
        || ($sectionPresentationPacket['summary']['hasSectionBorderPresentations'] ?? null) !== true
        || ($sectionPresentationPacket['summary']['sectionBorderPresentationCount'] ?? null) !== 3
        || ($sectionPresentationPacket['summary']['sectionBorderPresentationSections'] ?? null) !== ['head', 'body', 'foot']
        || ($sectionPresentationPacket['summary']['sectionBorderPresentationEdgeCount'] ?? null) !== 2
        || ($sectionPresentationPacket['summary']['sectionBorderPresentationEdges'] ?? null) !== ['bottom', 'top']
        || ($sectionPresentationPacket['sectionBackgrounds'][0]['sourceAttributes']['htmlAttributes']['id'] ?? null) !== 'section-head'
        || ($sectionPresentationPacket['sectionBackgrounds'][1]['backgroundColorSource'] ?? null) !== 'bgcolor'
        || ($sectionPresentationPacket['sectionBorderPresentations'][0]['borderEdges'][0]['value'] ?? null) !== '2px solid #336699'
        || ($sectionPresentationPacket['sectionBorderPresentations'][2]['borderEdges'][0]['borderColor'] ?? null) !== '#112233'
        || ($sectionBackgroundDiagnostics['markdown']['code'] ?? null) !== 'markdown-section-background-require-raw-html'
        || ($sectionBorderDiagnostics['markdown']['code'] ?? null) !== 'markdown-section-border-presentation-require-raw-html'
        || ($sectionBackgroundDiagnostics['asciidoc']['code'] ?? null) !== 'asciidoc-section-background-review-required'
        || ($sectionBorderDiagnostics['latex']['code'] ?? null) !== 'latex-section-border-presentation-review-required'
    ) {
        throw new RuntimeException('Table geometry self-test missing HTML section presentation metadata');
    }
    if (
        !str_contains($blocks, '<thead id="section-head" style="background-color: #FFF4CC; border-bottom: 2px solid #336699">')
        || !str_contains($blocks, '<tbody id="section-body" bgcolor="yellow" style="border-color: #336699; border-style: dashed; border-width: 2px">')
        || !str_contains($blocks, '<tfoot id="section-foot" style="background-color: rgb(230, 255, 237); border-top-width: 3px; border-top-style: dotted; border-top-color: #123">')
    ) {
        throw new RuntimeException('Table geometry self-test missing WordPress section presentation output');
    }
    json_encode($sectionPresentationPacket, JSON_THROW_ON_ERROR);
    json_encode($sectionPresentationDowngrades, JSON_THROW_ON_ERROR);

    $cellBackgroundTable = null;
    foreach ($document->children as $node) {
        if ($node->type === 'table' && $node->attr('id') === 'cell-background-grid') {
            $cellBackgroundTable = $node;
            break;
        }
    }
    $cellBackgroundPacket = $cellBackgroundTable instanceof AstNode ? $cellBackgroundTable->attr('tableGeometry') : null;
    $cellBackgroundDowngrades = $cellBackgroundTable instanceof AstNode ? TableGeometry::reviewPacket($cellBackgroundTable, [
        'accessibility' => false,
        'writers' => ['markdown', 'asciidoc', 'latex'],
    ]) : [];
    $cellBackgroundDiagnostics = [];
    foreach (['markdown', 'asciidoc', 'latex'] as $writer) {
        $matches = array_values(array_filter(
            $cellBackgroundDowngrades['writerDowngrades'][$writer] ?? [],
            static fn (array $diagnostic): bool => ($diagnostic['reason'] ?? null) === 'cell-background'
        ));
        $cellBackgroundDiagnostics[$writer] = $matches[0] ?? [];
    }
    if (
        !$cellBackgroundTable instanceof AstNode
        || !is_array($cellBackgroundPacket)
        || ($cellBackgroundPacket['summary']['hasCellBackgrounds'] ?? null) !== true
        || ($cellBackgroundPacket['summary']['cellBackgroundCount'] ?? null) !== 3
        || ($cellBackgroundPacket['summary']['cellBackgroundColumns'] ?? null) !== [0, 1]
        || ($cellBackgroundPacket['summary']['cellBackgroundSections'] ?? null) !== ['head', 'body']
        || ($cellBackgroundPacket['summary']['cellBackgroundColors'] ?? null) !== ['#fff4cc', '#e6ffed', 'rgb(230, 255, 237)']
        || ($cellBackgroundPacket['cellBackgrounds'][0]['attributes'] ?? null) !== ['bgcolor' => '#fff4cc']
        || ($cellBackgroundPacket['cellBackgrounds'][2]['legacyBackgroundColor'] ?? null) !== 'yellow'
        || ($cellBackgroundDiagnostics['markdown']['code'] ?? null) !== 'markdown-cell-background-require-raw-html'
        || ($cellBackgroundDiagnostics['asciidoc']['code'] ?? null) !== 'asciidoc-cell-background-review-required'
        || ($cellBackgroundDiagnostics['latex']['code'] ?? null) !== 'latex-cell-background-review-required'
    ) {
        throw new RuntimeException('Table geometry self-test missing HTML cell background metadata');
    }
    if (
        !str_contains($blocks, '<th bgcolor="#FFF4CC">Source</th><th>Status</th>')
        || !str_contains($blocks, '<td style="background-color: #e6ffed">Posts</td><td bgcolor="yellow" style="background-color: rgb(230, 255, 237)">Ready</td>')
        || !str_contains($blocks, '<td>Media</td><td>Review</td>')
    ) {
        throw new RuntimeException('Table geometry self-test missing WordPress cell background output');
    }
    json_encode($cellBackgroundPacket, JSON_THROW_ON_ERROR);
    json_encode($cellBackgroundDowngrades, JSON_THROW_ON_ERROR);

    $cellBorderPresentationTable = null;
    foreach ($document->children as $node) {
        if ($node->type === 'table' && $node->attr('id') === 'cell-border-presentation-grid') {
            $cellBorderPresentationTable = $node;
            break;
        }
    }
    $cellBorderPresentationPacket = $cellBorderPresentationTable instanceof AstNode ? $cellBorderPresentationTable->attr('tableGeometry') : null;
    $cellBorderPresentationDowngrades = $cellBorderPresentationTable instanceof AstNode ? TableGeometry::reviewPacket($cellBorderPresentationTable, [
        'accessibility' => false,
        'writers' => ['markdown', 'asciidoc', 'latex'],
    ]) : [];
    $cellBorderPresentationDiagnostics = [];
    foreach (['markdown', 'asciidoc', 'latex'] as $writer) {
        $matches = array_values(array_filter(
            $cellBorderPresentationDowngrades['writerDowngrades'][$writer] ?? [],
            static fn (array $diagnostic): bool => ($diagnostic['reason'] ?? null) === 'cell-border-presentation'
        ));
        $cellBorderPresentationDiagnostics[$writer] = $matches[0] ?? [];
    }
    if (
        !$cellBorderPresentationTable instanceof AstNode
        || !is_array($cellBorderPresentationPacket)
        || ($cellBorderPresentationPacket['summary']['hasCellBorderPresentations'] ?? null) !== true
        || ($cellBorderPresentationPacket['summary']['cellBorderPresentationCount'] ?? null) !== 3
        || ($cellBorderPresentationPacket['summary']['cellBorderPresentationColumns'] ?? null) !== [0, 1]
        || ($cellBorderPresentationPacket['summary']['cellBorderPresentationSections'] ?? null) !== ['head', 'body']
        || ($cellBorderPresentationPacket['summary']['cellBorderPresentationColors'] ?? null) !== ['#336699', 'rgb(51, 102, 153)']
        || ($cellBorderPresentationPacket['summary']['cellBorderPresentationStyles'] ?? null) !== ['dashed', 'solid']
        || ($cellBorderPresentationPacket['summary']['cellBorderPresentationWidths'] ?? null) !== ['2px', 'thin medium thick 2px']
        || ($cellBorderPresentationPacket['cellBorderPresentations'][0]['attributes'] ?? null) !== ['border-color' => '#336699', 'border-style' => 'dashed', 'border-width' => '2px']
        || ($cellBorderPresentationPacket['cellBorderPresentations'][2]['borderWidth'] ?? null) !== 'thin medium thick 2px'
        || ($cellBorderPresentationDiagnostics['markdown']['code'] ?? null) !== 'markdown-cell-border-presentation-require-raw-html'
        || ($cellBorderPresentationDiagnostics['asciidoc']['code'] ?? null) !== 'asciidoc-cell-border-presentation-review-required'
        || ($cellBorderPresentationDiagnostics['latex']['code'] ?? null) !== 'latex-cell-border-presentation-review-required'
    ) {
        throw new RuntimeException('Table geometry self-test missing HTML cell border presentation metadata');
    }
    if (
        !str_contains($blocks, '<th style="border-color: #336699; border-style: dashed; border-width: 2px">Source</th><th>Status</th>')
        || !str_contains($blocks, '<td style="border-color: rgb(51, 102, 153); border-style: solid">Posts</td><td style="border-width: thin medium thick 2px">Ready</td>')
        || !str_contains($blocks, '<td>Media</td><td>Review</td>')
    ) {
        throw new RuntimeException('Table geometry self-test missing WordPress cell border presentation output');
    }
    json_encode($cellBorderPresentationPacket, JSON_THROW_ON_ERROR);
    json_encode($cellBorderPresentationDowngrades, JSON_THROW_ON_ERROR);

    $cellSideBorderPresentationTable = null;
    foreach ($document->children as $node) {
        if ($node->type === 'table' && $node->attr('id') === 'cell-side-border-grid') {
            $cellSideBorderPresentationTable = $node;
            break;
        }
    }
    $cellSideBorderPresentationPacket = $cellSideBorderPresentationTable instanceof AstNode ? $cellSideBorderPresentationTable->attr('tableGeometry') : null;
    $cellSideBorderPresentationDowngrades = $cellSideBorderPresentationTable instanceof AstNode ? TableGeometry::reviewPacket($cellSideBorderPresentationTable, [
        'accessibility' => false,
        'writers' => ['markdown', 'asciidoc', 'latex'],
    ]) : [];
    $cellSideBorderPresentationDiagnostics = [];
    foreach (['markdown', 'asciidoc', 'latex'] as $writer) {
        $matches = array_values(array_filter(
            $cellSideBorderPresentationDowngrades['writerDowngrades'][$writer] ?? [],
            static fn (array $diagnostic): bool => ($diagnostic['reason'] ?? null) === 'cell-border-presentation'
        ));
        $cellSideBorderPresentationDiagnostics[$writer] = $matches[0] ?? [];
    }
    if (
        !$cellSideBorderPresentationTable instanceof AstNode
        || !is_array($cellSideBorderPresentationPacket)
        || ($cellSideBorderPresentationPacket['summary']['hasCellBorderPresentations'] ?? null) !== true
        || ($cellSideBorderPresentationPacket['summary']['cellBorderPresentationCount'] ?? null) !== 3
        || ($cellSideBorderPresentationPacket['summary']['cellBorderPresentationEdgeCount'] ?? null) !== 4
        || ($cellSideBorderPresentationPacket['summary']['cellBorderPresentationEdges'] ?? null) !== ['top', 'left', 'right', 'bottom']
        || ($cellSideBorderPresentationPacket['summary']['cellBorderPresentationEdgeWidths'] ?? null) !== ['2px', '1pt', 'thick', '3px']
        || ($cellSideBorderPresentationPacket['summary']['cellBorderPresentationEdgeStyles'] ?? null) !== ['dashed', 'solid', 'double', 'dotted']
        || ($cellSideBorderPresentationPacket['summary']['cellBorderPresentationEdgeColors'] ?? null) !== ['#336699', 'red', 'green', '#112233']
        || ($cellSideBorderPresentationPacket['cellBorderPresentations'][0]['attributes'] ?? null) !== ['border-left' => '1pt solid red', 'border-top' => '2px dashed #336699']
        || ($cellSideBorderPresentationPacket['cellBorderPresentations'][2]['borderEdges'][0]['attributes'] ?? null) !== ['border-bottom-color' => '#112233', 'border-bottom-style' => 'dotted', 'border-bottom-width' => '3px']
        || ($cellSideBorderPresentationDiagnostics['markdown']['edgeCount'] ?? null) !== 4
        || ($cellSideBorderPresentationDiagnostics['markdown']['edges'] ?? null) !== ['top', 'left', 'right', 'bottom']
        || ($cellSideBorderPresentationDiagnostics['markdown']['requiredFeature'] ?? null) !== 'raw-html-cell-border-presentation'
    ) {
        throw new RuntimeException('Table geometry self-test missing HTML cell side-border metadata');
    }
    if (
        !str_contains($blocks, '<th style="border-top: 2px dashed #336699; border-left: 1pt solid red">Source</th><th>Status</th>')
        || !str_contains($blocks, '<td style="border-right: thick double green">Posts</td><td style="border-bottom-width: 3px; border-bottom-style: dotted; border-bottom-color: #123">Ready</td>')
        || !str_contains($blocks, '<td>Media</td><td>Review</td>')
    ) {
        throw new RuntimeException('Table geometry self-test missing WordPress cell side-border output');
    }
    json_encode($cellSideBorderPresentationPacket, JSON_THROW_ON_ERROR);
    json_encode($cellSideBorderPresentationDowngrades, JSON_THROW_ON_ERROR);

    $backgroundColorTable = null;
    foreach ($document->children as $node) {
        if ($node->type === 'table' && $node->attr('id') === 'background-color-grid') {
            $backgroundColorTable = $node;
            break;
        }
    }
    $backgroundColorPacket = $backgroundColorTable instanceof AstNode ? $backgroundColorTable->attr('tableGeometry') : null;
    $backgroundColorDowngrades = $backgroundColorTable instanceof AstNode ? TableGeometry::reviewPacket($backgroundColorTable, [
        'accessibility' => false,
        'writers' => ['markdown', 'asciidoc', 'latex'],
    ]) : [];
    $backgroundColorDiagnostics = [];
    foreach (['markdown', 'asciidoc', 'latex'] as $writer) {
        $matches = array_values(array_filter(
            $backgroundColorDowngrades['writerDowngrades'][$writer] ?? [],
            static fn (array $diagnostic): bool => ($diagnostic['reason'] ?? null) === 'table-background'
        ));
        $backgroundColorDiagnostics[$writer] = $matches[0] ?? [];
    }
    if (
        !$backgroundColorTable instanceof AstNode
        || !is_array($backgroundColorPacket)
        || ($backgroundColorPacket['tableBackground']['attributes'] ?? null) !== [
            'background-color' => '#e6ffed',
            'bgcolor' => '#fff4cc',
        ]
        || ($backgroundColorPacket['tableBackground']['backgroundColor'] ?? null) !== '#e6ffed'
        || ($backgroundColorPacket['tableBackground']['backgroundColorSource'] ?? null) !== 'style'
        || ($backgroundColorPacket['summary']['hasTableBackground'] ?? null) !== true
        || ($backgroundColorPacket['summary']['tableBackgroundColor'] ?? null) !== '#e6ffed'
        || ($backgroundColorDiagnostics['markdown']['code'] ?? null) !== 'markdown-table-background-requires-raw-html'
        || ($backgroundColorDiagnostics['asciidoc']['code'] ?? null) !== 'asciidoc-table-background-review-required'
        || ($backgroundColorDiagnostics['latex']['code'] ?? null) !== 'latex-table-background-review-required'
    ) {
        throw new RuntimeException('Table geometry self-test missing HTML table background metadata');
    }
    if (
        !str_contains($blocks, '<table id="background-color-grid" data-source="html-reader" bgcolor="#fff4cc" style="background-color:#e6ffed">')
        || str_contains($blocks, 'background-image:url')
        || str_contains($blocks, 'javascript:alert')
    ) {
        throw new RuntimeException('Table geometry self-test missing sanitized WordPress table background output');
    }
    json_encode($backgroundColorPacket, JSON_THROW_ON_ERROR);
    json_encode($backgroundColorDowngrades, JSON_THROW_ON_ERROR);

    $layoutWidthTable = null;
    foreach ($document->children as $node) {
        if ($node->type === 'table' && $node->attr('id') === 'layout-width-grid') {
            $layoutWidthTable = $node;
            break;
        }
    }
    $layoutWidthPacket = $layoutWidthTable instanceof AstNode ? $layoutWidthTable->attr('tableGeometry') : null;
    $layoutWidthDowngrades = $layoutWidthTable instanceof AstNode ? TableGeometry::reviewPacket($layoutWidthTable, [
        'accessibility' => false,
        'writers' => ['markdown', 'asciidoc', 'latex'],
    ]) : [];
    $layoutWidthDiagnostics = [];
    foreach (['markdown', 'asciidoc', 'latex'] as $writer) {
        $matches = array_values(array_filter(
            $layoutWidthDowngrades['writerDowngrades'][$writer] ?? [],
            static fn (array $diagnostic): bool => ($diagnostic['reason'] ?? null) === 'table-layout-width'
        ));
        $layoutWidthDiagnostics[$writer] = $matches[0] ?? [];
    }
    if (
        !$layoutWidthTable instanceof AstNode
        || !is_array($layoutWidthPacket)
        || ($layoutWidthPacket['tableLayout']['attributes'] ?? null) !== [
            'width' => '80%',
        ]
        || ($layoutWidthPacket['tableLayout']['widthType'] ?? null) !== 'percent'
        || ($layoutWidthPacket['summary']['hasTableLayout'] ?? null) !== true
        || ($layoutWidthPacket['summary']['tableWidth'] ?? null) !== '80%'
        || ($layoutWidthPacket['summary']['tableWidthType'] ?? null) !== 'percent'
        || ($layoutWidthDiagnostics['markdown']['code'] ?? null) !== 'markdown-table-width-requires-raw-html'
        || ($layoutWidthDiagnostics['asciidoc']['code'] ?? null) !== 'asciidoc-table-width-review-required'
        || ($layoutWidthDiagnostics['latex']['code'] ?? null) !== 'latex-table-width-review-required'
    ) {
        throw new RuntimeException('Table geometry self-test missing HTML table width layout metadata');
    }
    if (!str_contains($blocks, '<table id="layout-width-grid" data-source="html-reader" width="80%">')) {
        throw new RuntimeException('Table geometry self-test missing WordPress table width output');
    }
    json_encode($layoutWidthPacket, JSON_THROW_ON_ERROR);
    json_encode($layoutWidthDowngrades, JSON_THROW_ON_ERROR);

    $layoutHeightTable = null;
    foreach ($document->children as $node) {
        if ($node->type === 'table' && $node->attr('id') === 'layout-height-grid') {
            $layoutHeightTable = $node;
            break;
        }
    }
    $layoutHeightPacket = $layoutHeightTable instanceof AstNode ? $layoutHeightTable->attr('tableGeometry') : null;
    $layoutHeightDowngrades = $layoutHeightTable instanceof AstNode ? TableGeometry::reviewPacket($layoutHeightTable, [
        'accessibility' => false,
        'writers' => ['markdown', 'asciidoc', 'latex'],
    ]) : [];
    $layoutHeightDiagnostics = [];
    foreach (['markdown', 'asciidoc', 'latex'] as $writer) {
        $matches = array_values(array_filter(
            $layoutHeightDowngrades['writerDowngrades'][$writer] ?? [],
            static fn (array $diagnostic): bool => ($diagnostic['reason'] ?? null) === 'table-layout-height'
        ));
        $layoutHeightDiagnostics[$writer] = $matches[0] ?? [];
    }
    if (
        !$layoutHeightTable instanceof AstNode
        || !is_array($layoutHeightPacket)
        || ($layoutHeightPacket['tableLayout']['attributes'] ?? null) !== [
            'height' => '320',
        ]
        || ($layoutHeightPacket['tableLayout']['heightType'] ?? null) !== 'pixels'
        || ($layoutHeightPacket['summary']['hasTableLayout'] ?? null) !== true
        || ($layoutHeightPacket['summary']['tableHeight'] ?? null) !== '320'
        || ($layoutHeightPacket['summary']['tableHeightType'] ?? null) !== 'pixels'
        || ($layoutHeightDiagnostics['markdown']['code'] ?? null) !== 'markdown-table-height-requires-raw-html'
        || ($layoutHeightDiagnostics['asciidoc']['code'] ?? null) !== 'asciidoc-table-height-review-required'
        || ($layoutHeightDiagnostics['latex']['code'] ?? null) !== 'latex-table-height-review-required'
    ) {
        throw new RuntimeException('Table geometry self-test missing HTML table height layout metadata');
    }
    if (!str_contains($blocks, '<table id="layout-height-grid" data-source="html-reader" height="320">')) {
        throw new RuntimeException('Table geometry self-test missing WordPress table height output');
    }
    json_encode($layoutHeightPacket, JSON_THROW_ON_ERROR);
    json_encode($layoutHeightDowngrades, JSON_THROW_ON_ERROR);

    $layoutModeTable = null;
    foreach ($document->children as $node) {
        if ($node->type === 'table' && $node->attr('id') === 'layout-mode-grid') {
            $layoutModeTable = $node;
            break;
        }
    }
    $layoutModePacket = $layoutModeTable instanceof AstNode ? $layoutModeTable->attr('tableGeometry') : null;
    $layoutModeDowngrades = $layoutModeTable instanceof AstNode ? TableGeometry::reviewPacket($layoutModeTable, [
        'accessibility' => false,
        'writers' => ['markdown', 'asciidoc', 'latex'],
    ]) : [];
    $layoutModeDiagnostics = [];
    foreach (['markdown', 'asciidoc', 'latex'] as $writer) {
        $matches = array_values(array_filter(
            $layoutModeDowngrades['writerDowngrades'][$writer] ?? [],
            static fn (array $diagnostic): bool => ($diagnostic['reason'] ?? null) === 'table-layout-mode'
        ));
        $layoutModeDiagnostics[$writer] = $matches[0] ?? [];
    }
    if (
        !$layoutModeTable instanceof AstNode
        || !is_array($layoutModePacket)
        || ($layoutModePacket['tableLayout']['attributes'] ?? null) !== [
            'table-layout' => 'fixed',
        ]
        || ($layoutModePacket['tableLayout']['layoutMode'] ?? null) !== 'fixed'
        || ($layoutModePacket['tableLayout']['layoutModeSource'] ?? null) !== 'style'
        || ($layoutModePacket['summary']['hasTableLayout'] ?? null) !== true
        || ($layoutModePacket['summary']['tableLayoutMode'] ?? null) !== 'fixed'
        || ($layoutModePacket['summary']['tableLayoutModeSource'] ?? null) !== 'style'
        || ($layoutModeDiagnostics['markdown']['code'] ?? null) !== 'markdown-table-layout-mode-requires-raw-html'
        || ($layoutModeDiagnostics['asciidoc']['code'] ?? null) !== 'asciidoc-table-layout-mode-review-required'
        || ($layoutModeDiagnostics['latex']['code'] ?? null) !== 'latex-table-layout-mode-review-required'
    ) {
        throw new RuntimeException('Table geometry self-test missing HTML table CSS table-layout metadata');
    }
    if (!str_contains($blocks, '<table id="layout-mode-grid" data-source="html-reader" style="table-layout:fixed">')) {
        throw new RuntimeException('Table geometry self-test missing WordPress table-layout style output');
    }
    if (str_contains($blocks, 'background-image')) {
        throw new RuntimeException('Table geometry self-test leaked unsafe table-layout source style');
    }
    json_encode($layoutModePacket, JSON_THROW_ON_ERROR);
    json_encode($layoutModeDowngrades, JSON_THROW_ON_ERROR);

    $borderCollapseTable = null;
    foreach ($document->children as $node) {
        if ($node->type === 'table' && $node->attr('id') === 'border-collapse-grid') {
            $borderCollapseTable = $node;
            break;
        }
    }
    $borderCollapsePacket = $borderCollapseTable instanceof AstNode ? $borderCollapseTable->attr('tableGeometry') : null;
    $borderCollapseDowngrades = $borderCollapseTable instanceof AstNode ? TableGeometry::reviewPacket($borderCollapseTable, [
        'accessibility' => false,
        'writers' => ['markdown', 'asciidoc', 'latex'],
    ]) : [];
    $borderCollapseDiagnostics = [];
    foreach (['markdown', 'asciidoc', 'latex'] as $writer) {
        $matches = array_values(array_filter(
            $borderCollapseDowngrades['writerDowngrades'][$writer] ?? [],
            static fn (array $diagnostic): bool => ($diagnostic['reason'] ?? null) === 'table-border-collapse'
        ));
        $borderCollapseDiagnostics[$writer] = $matches[0] ?? [];
    }
    if (
        !$borderCollapseTable instanceof AstNode
        || !is_array($borderCollapsePacket)
        || ($borderCollapsePacket['tableBorderCollapse']['attributes'] ?? null) !== [
            'border-collapse' => 'collapse',
        ]
        || ($borderCollapsePacket['tableBorderCollapse']['borderCollapse'] ?? null) !== 'collapse'
        || ($borderCollapsePacket['tableBorderCollapse']['borderCollapseSource'] ?? null) !== 'style'
        || ($borderCollapsePacket['summary']['hasTableBorderCollapse'] ?? null) !== true
        || ($borderCollapsePacket['summary']['tableBorderCollapse'] ?? null) !== 'collapse'
        || ($borderCollapsePacket['summary']['tableBorderCollapseSource'] ?? null) !== 'style'
        || ($borderCollapseDiagnostics['markdown']['code'] ?? null) !== 'markdown-table-border-collapse-requires-raw-html'
        || ($borderCollapseDiagnostics['asciidoc']['code'] ?? null) !== 'asciidoc-table-border-collapse-review-required'
        || ($borderCollapseDiagnostics['latex']['code'] ?? null) !== 'latex-table-border-collapse-review-required'
    ) {
        throw new RuntimeException('Table geometry self-test missing HTML table border-collapse metadata');
    }
    if (
        !str_contains($blocks, '<table id="border-collapse-grid" data-source="html-reader" style="border-collapse:collapse">')
        || str_contains($blocks, 'background-image:url')
        || str_contains($blocks, 'javascript:alert')
    ) {
        throw new RuntimeException('Table geometry self-test missing sanitized WordPress table border-collapse output');
    }
    json_encode($borderCollapsePacket, JSON_THROW_ON_ERROR);
    json_encode($borderCollapseDowngrades, JSON_THROW_ON_ERROR);

    $borderPresentationTable = null;
    foreach ($document->children as $node) {
        if ($node->type === 'table' && $node->attr('id') === 'border-presentation-grid') {
            $borderPresentationTable = $node;
            break;
        }
    }
    $borderPresentationPacket = $borderPresentationTable instanceof AstNode ? $borderPresentationTable->attr('tableGeometry') : null;
    $borderPresentationDowngrades = $borderPresentationTable instanceof AstNode ? TableGeometry::reviewPacket($borderPresentationTable, [
        'accessibility' => false,
        'writers' => ['markdown', 'asciidoc', 'latex'],
    ]) : [];
    $borderPresentationDiagnostics = [];
    foreach (['markdown', 'asciidoc', 'latex'] as $writer) {
        $matches = array_values(array_filter(
            $borderPresentationDowngrades['writerDowngrades'][$writer] ?? [],
            static fn (array $diagnostic): bool => ($diagnostic['reason'] ?? null) === 'table-border-presentation'
        ));
        $borderPresentationDiagnostics[$writer] = $matches[0] ?? [];
    }
    if (
        !$borderPresentationTable instanceof AstNode
        || !is_array($borderPresentationPacket)
        || ($borderPresentationPacket['tableBorderPresentation']['attributes'] ?? null) !== [
            'border-color' => '#336699',
            'border-style' => 'dashed',
            'border-width' => '2px',
        ]
        || ($borderPresentationPacket['tableBorderPresentation']['borderColor'] ?? null) !== '#336699'
        || ($borderPresentationPacket['tableBorderPresentation']['borderStyle'] ?? null) !== 'dashed'
        || ($borderPresentationPacket['tableBorderPresentation']['borderWidth'] ?? null) !== '2px'
        || ($borderPresentationPacket['summary']['hasTableBorderPresentation'] ?? null) !== true
        || ($borderPresentationPacket['summary']['tableBorderColor'] ?? null) !== '#336699'
        || ($borderPresentationPacket['summary']['tableBorderStyle'] ?? null) !== 'dashed'
        || ($borderPresentationPacket['summary']['tableBorderWidth'] ?? null) !== '2px'
        || ($borderPresentationPacket['summary']['tableBorderPresentationAttributeCount'] ?? null) !== 3
        || ($borderPresentationDiagnostics['markdown']['code'] ?? null) !== 'markdown-table-border-presentation-requires-raw-html'
        || ($borderPresentationDiagnostics['asciidoc']['code'] ?? null) !== 'asciidoc-table-border-presentation-review-required'
        || ($borderPresentationDiagnostics['latex']['code'] ?? null) !== 'latex-table-border-presentation-review-required'
    ) {
        throw new RuntimeException('Table geometry self-test missing HTML table border presentation metadata');
    }
    if (
        !str_contains($blocks, '<table id="border-presentation-grid" data-source="html-reader" style="border-color:#336699; border-style:dashed; border-width:2px">')
        || str_contains($blocks, 'border-image')
    ) {
        throw new RuntimeException('Table geometry self-test missing sanitized WordPress table border presentation output');
    }
    json_encode($borderPresentationPacket, JSON_THROW_ON_ERROR);
    json_encode($borderPresentationDowngrades, JSON_THROW_ON_ERROR);

    $placementAlignmentTable = null;
    $invalidPlacementAlignmentTable = null;
    foreach ($document->children as $node) {
        if ($node->type !== 'table') {
            continue;
        }

        if ($node->attr('id') === 'placement-align-grid') {
            $placementAlignmentTable = $node;
        } elseif ($node->attr('id') === 'placement-align-invalid') {
            $invalidPlacementAlignmentTable = $node;
        }
    }
    $placementAlignmentPacket = $placementAlignmentTable instanceof AstNode ? $placementAlignmentTable->attr('tableGeometry') : null;
    $invalidPlacementAlignmentPacket = $invalidPlacementAlignmentTable instanceof AstNode ? $invalidPlacementAlignmentTable->attr('tableGeometry') : null;
    $placementAlignmentDowngrades = $placementAlignmentTable instanceof AstNode ? TableGeometry::reviewPacket($placementAlignmentTable, [
        'accessibility' => false,
        'writers' => ['markdown', 'asciidoc', 'latex'],
    ]) : [];
    $placementAlignmentDiagnostics = [];
    foreach (['markdown', 'asciidoc', 'latex'] as $writer) {
        $matches = array_values(array_filter(
            $placementAlignmentDowngrades['writerDowngrades'][$writer] ?? [],
            static fn (array $diagnostic): bool => ($diagnostic['reason'] ?? null) === 'table-alignment'
        ));
        $placementAlignmentDiagnostics[$writer] = $matches[0] ?? [];
    }
    if (
        !$placementAlignmentTable instanceof AstNode
        || !is_array($placementAlignmentPacket)
        || ($placementAlignmentPacket['tableAlignment']['attributes'] ?? null) !== [
            'align' => 'center',
        ]
        || ($placementAlignmentPacket['summary']['hasTableAlignment'] ?? null) !== true
        || ($placementAlignmentPacket['summary']['tableAlignment'] ?? null) !== 'center'
        || ($placementAlignmentDiagnostics['markdown']['code'] ?? null) !== 'markdown-table-alignment-requires-raw-html'
        || ($placementAlignmentDiagnostics['asciidoc']['code'] ?? null) !== 'asciidoc-table-alignment-review-required'
        || ($placementAlignmentDiagnostics['latex']['code'] ?? null) !== 'latex-table-alignment-review-required'
        || !is_array($invalidPlacementAlignmentPacket)
        || array_key_exists('tableAlignment', $invalidPlacementAlignmentPacket)
    ) {
        throw new RuntimeException('Table geometry self-test missing legacy table placement alignment metadata');
    }
    if (
        !str_contains($blocks, '<table id="placement-align-grid" data-source="html-reader" align="center">')
        || str_contains($blocks, '<table id="placement-align-invalid" align="middle"')
    ) {
        throw new RuntimeException('Table geometry self-test missing WordPress table placement alignment output');
    }
    json_encode($placementAlignmentPacket, JSON_THROW_ON_ERROR);
    json_encode($invalidPlacementAlignmentPacket, JSON_THROW_ON_ERROR);
    json_encode($placementAlignmentDowngrades, JSON_THROW_ON_ERROR);

    $directionalityTable = null;
    foreach ($document->children as $node) {
        if ($node->type === 'table' && $node->attr('id') === 'directionality-grid') {
            $directionalityTable = $node;
            break;
        }
    }
    $directionalityPacket = $directionalityTable instanceof AstNode ? $directionalityTable->attr('tableGeometry') : null;
    $directionalityDowngrades = $directionalityTable instanceof AstNode ? TableGeometry::reviewPacket($directionalityTable, [
        'accessibility' => false,
        'writers' => ['markdown', 'asciidoc', 'latex'],
    ]) : [];
    $directionalityDiagnostics = [];
    foreach (['markdown', 'asciidoc', 'latex'] as $writer) {
        $matches = array_values(array_filter(
            $directionalityDowngrades['writerDowngrades'][$writer] ?? [],
            static fn (array $diagnostic): bool => ($diagnostic['reason'] ?? null) === 'table-direction'
        ));
        $directionalityDiagnostics[$writer] = $matches[0] ?? [];
    }
    if (
        !$directionalityTable instanceof AstNode
        || !is_array($directionalityPacket)
        || ($directionalityPacket['directionality']['table']['direction'] ?? null) !== 'rtl'
        || ($directionalityPacket['directionality']['sections'][0]['direction'] ?? null) !== 'ltr'
        || ($directionalityPacket['directionality']['rows'][1]['direction'] ?? null) !== 'ltr'
        || ($directionalityPacket['directionality']['cells'][1]['direction'] ?? null) !== 'auto'
        || ($directionalityPacket['directionality']['cells'][3]['source'] ?? null) !== 'section'
        || ($directionalityPacket['summary']['hasTableDirectionality'] ?? null) !== true
        || ($directionalityPacket['summary']['directionalCellCount'] ?? null) !== 6
        || ($directionalityPacket['summary']['tableDirections'] ?? null) !== ['auto', 'ltr', 'rtl']
        || ($directionalityDiagnostics['markdown']['code'] ?? null) !== 'markdown-table-direction-requires-raw-html'
        || ($directionalityDiagnostics['asciidoc']['code'] ?? null) !== 'asciidoc-table-direction-review-required'
        || ($directionalityDiagnostics['latex']['code'] ?? null) !== 'latex-table-direction-review-required'
    ) {
        throw new RuntimeException('Table geometry self-test missing HTML table directionality metadata');
    }
    if (
        !str_contains($blocks, '<table id="directionality-grid" data-source="html-reader" dir="rtl">')
        || !str_contains($blocks, '<thead dir="ltr"><tr dir="rtl"><th>Scope</th><th dir="auto">State</th></tr></thead>')
        || !str_contains($blocks, '<tbody dir="rtl" data-section="body"><tr><th>Posts</th><td>جاهز</td></tr><tr dir="ltr"><th>Media</th><td dir="auto">Review</td></tr></tbody>')
    ) {
        throw new RuntimeException('Table geometry self-test missing WordPress output for HTML directionality handoff');
    }
    json_encode($directionalityPacket, JSON_THROW_ON_ERROR);
    json_encode($directionalityDowngrades, JSON_THROW_ON_ERROR);

    $localizationTable = null;
    foreach ($document->children as $node) {
        if ($node->type === 'table' && $node->attr('id') === 'localized-source-grid') {
            $localizationTable = $node;
            break;
        }
    }
    $localizationPacket = $localizationTable instanceof AstNode ? $localizationTable->attr('tableGeometry') : null;
    $localizationDowngrades = $localizationTable instanceof AstNode ? TableGeometry::reviewPacket($localizationTable, [
        'accessibility' => false,
        'writers' => ['markdown', 'asciidoc', 'latex'],
    ]) : [];
    $localizationDiagnostics = [];
    foreach (['markdown', 'asciidoc', 'latex'] as $writer) {
        $matches = array_values(array_filter(
            $localizationDowngrades['writerDowngrades'][$writer] ?? [],
            static fn (array $diagnostic): bool => ($diagnostic['reason'] ?? null) === 'table-localization'
        ));
        $localizationDiagnostics[$writer] = $matches[0] ?? [];
    }
    if (
        !$localizationTable instanceof AstNode
        || !is_array($localizationPacket)
        || ($localizationPacket['localization']['table']['language'] ?? null) !== 'ar-EG'
        || ($localizationPacket['localization']['table']['translate'] ?? null) !== 'no'
        || ($localizationPacket['localization']['sections'][1]['language'] ?? null) !== 'ar'
        || ($localizationPacket['localization']['cells'][5]['language'] ?? null) !== 'en-US'
        || ($localizationPacket['localization']['cells'][5]['translate'] ?? null) !== 'no'
        || ($localizationPacket['summary']['hasTableLocalization'] ?? null) !== true
        || ($localizationPacket['summary']['localizedCellCount'] ?? null) !== 6
        || ($localizationPacket['summary']['tableLanguages'] ?? null) !== ['ar', 'ar-EG', 'en', 'en-US', 'fr']
        || ($localizationDiagnostics['markdown']['code'] ?? null) !== 'markdown-table-localization-requires-raw-html'
        || ($localizationDiagnostics['asciidoc']['code'] ?? null) !== 'asciidoc-table-localization-review-required'
        || ($localizationDiagnostics['latex']['code'] ?? null) !== 'latex-table-localization-review-required'
    ) {
        throw new RuntimeException('Table geometry self-test missing HTML table localization metadata');
    }
    if (
        !str_contains($blocks, '<table id="localized-source-grid" data-source="html-reader" lang="ar-EG" xml:lang="ar-EG" translate="no">')
        || !str_contains($blocks, '<figcaption class="wp-element-caption" lang="en">Localized source review</figcaption>')
        || !str_contains($blocks, '<tbody lang="ar" translate="no" data-section="body"><tr><th>منشورات</th><td>جاهز</td></tr><tr lang="en" translate="yes"><th>Media</th><td lang="en-US" translate="no">Review</td></tr></tbody>')
    ) {
        throw new RuntimeException('Table geometry self-test missing WordPress output for HTML localization handoff');
    }
    json_encode($localizationPacket, JSON_THROW_ON_ERROR);
    json_encode($localizationDowngrades, JSON_THROW_ON_ERROR);

    $readerTable = null;
    foreach ($document->children as $node) {
        if ($node->type === 'table' && $node->attr('caption') === 'Reader packet import metrics') {
            $readerTable = $node;
            break;
        }
    }
    $readerPacket = $readerTable instanceof AstNode ? $readerTable->attr('tableGeometry') : null;
    if (!is_array($readerPacket) || ($readerPacket['summary']['cellCount'] ?? null) !== 9 || ($readerPacket['coverage'][6]['text'] ?? null) !== 'Media') {
        throw new RuntimeException('Table geometry self-test missing Markdown reader attached review packet');
    }
    json_encode($readerPacket, JSON_THROW_ON_ERROR);
    if (!str_contains($blocks, '<figcaption class="wp-element-caption">Reader packet import metrics</figcaption>')) {
        throw new RuntimeException('Table geometry self-test missing Markdown reader table WordPress output');
    }

    $visualRowHeadColspanTable = null;
    foreach ($document->children as $node) {
        if ($node->type === 'table' && $node->attr('caption') === 'Visual row head review') {
            $visualRowHeadColspanTable = $node;
            break;
        }
    }
    $visualRowHeadColspanPacket = $visualRowHeadColspanTable instanceof AstNode ? $visualRowHeadColspanTable->attr('tableGeometry') : null;
    if (
        !is_array($visualRowHeadColspanPacket)
        || ($visualRowHeadColspanPacket['rowGroups'][1]['rowHeadColumns'] ?? null) !== 2
        || ($visualRowHeadColspanPacket['summary']['maxRowHeadColumns'] ?? null) !== 2
        || ($visualRowHeadColspanPacket['summary']['rowHeadGroupCount'] ?? null) !== 1
        || ($visualRowHeadColspanPacket['coverage'][2]['columns'] ?? null) !== [0, 1]
        || ($visualRowHeadColspanPacket['coverage'][3]['columns'] ?? null) !== [2]
    ) {
        throw new RuntimeException('Table geometry self-test missing visual row-head colspan metadata');
    }
    if (!str_contains($blocks, '<tbody data-section="body"><tr><th colspan="2">Posts and pages</th><td>Ready</td></tr><tr><th colspan="2">Media assets</th><td>Review</td></tr></tbody>')) {
        throw new RuntimeException('Table geometry self-test missing visual row-head colspan WordPress output');
    }
    json_encode($visualRowHeadColspanPacket, JSON_THROW_ON_ERROR);

    $multiBodyRowHeadTable = null;
    foreach ($document->children as $node) {
        if ($node->type === 'table' && $node->attr('caption') === 'Multiple body row-head review') {
            $multiBodyRowHeadTable = $node;
            break;
        }
    }
    $multiBodyRowHeadPacket = $multiBodyRowHeadTable instanceof AstNode ? $multiBodyRowHeadTable->attr('tableGeometry') : null;
    $multiBodyRowHeadMarkdownDiagnostics = is_array($multiBodyRowHeadPacket)
        ? array_values(array_filter(
            $multiBodyRowHeadPacket['writerDowngrades']['markdown'] ?? [],
            static fn (array $diagnostic): bool => ($diagnostic['code'] ?? null) === 'markdown-table-bodies-flattened'
        ))
        : [];
    if (
        !is_array($multiBodyRowHeadPacket)
        || ($multiBodyRowHeadPacket['summary']['rowHeadSections'] ?? null) !== ['body', 'body1']
        || ($multiBodyRowHeadPacket['summary']['rowHeadColumnCounts'] ?? null) !== [2, 1]
        || ($multiBodyRowHeadPacket['summary']['hasDifferingRowHeadColumns'] ?? null) !== true
        || ($multiBodyRowHeadPacket['summary']['rowHeadGroupRanges'][0]['rowHeadColumns'] ?? null) !== 2
        || ($multiBodyRowHeadPacket['summary']['rowHeadGroupRanges'][1]['bodyOrdinal'] ?? null) !== 1
        || ($multiBodyRowHeadMarkdownDiagnostics[0]['bodySectionRowHeadColumns'] ?? null) !== [2, 1]
        || ($multiBodyRowHeadMarkdownDiagnostics[0]['rowHeadBodySections'] ?? null) !== ['body', 'body1']
        || ($multiBodyRowHeadMarkdownDiagnostics[0]['hasDifferingRowHeadColumns'] ?? null) !== true
    ) {
        throw new RuntimeException('Table geometry self-test missing multiple body row-head handoff metadata');
    }
    if (
        !str_contains($blocks, '<tbody id="posts-body"><tr><th colspan="2">Posts</th><td>Ready</td></tr><tr><th colspan="2">Pages</th><td>Review</td></tr></tbody>')
        || !str_contains($blocks, '<tbody id="media-body"><tr><th>Images</th><td>7</td><td>Review</td></tr><tr><th>Video</th><td>2</td><td>Ready</td></tr></tbody>')
    ) {
        throw new RuntimeException('Table geometry self-test missing multiple body row-head WordPress output');
    }
    json_encode($multiBodyRowHeadPacket, JSON_THROW_ON_ERROR);

    $captionSourceTable = null;
    foreach ($document->children as $node) {
        if ($node->type === 'table' && $node->attr('caption') === 'Caption source handoff') {
            $captionSourceTable = $node;
            break;
        }
    }
    $captionSourcePacket = $captionSourceTable instanceof AstNode ? $captionSourceTable->attr('tableGeometry') : null;
    if (
        !is_array($captionSourcePacket)
        || ($captionSourcePacket['captions']['long']['sourceAttributes']['id'] ?? null) !== 'caption-source-handoff'
        || ($captionSourcePacket['summary']['hasCaptionSourceAttributes'] ?? null) !== true
        || ($captionSourcePacket['summary']['captionSide'] ?? null) !== 'bottom'
        || ($captionSourcePacket['writerDowngrades']['markdown'][0]['code'] ?? null) !== 'markdown-caption-source-attributes-require-raw-html'
    ) {
        throw new RuntimeException('Table geometry self-test missing caption source attribute handoff metadata');
    }
    if (!str_contains($blocks, '<figcaption id="caption-source-handoff" class="wp-element-caption source-caption" data-origin="html-reader" aria-label="Caption source" style="caption-side: bottom">Caption source handoff</figcaption>')) {
        throw new RuntimeException('Table geometry self-test missing WordPress output for caption source attributes');
    }
    if (str_contains($blocks, 'onclick=')) {
        throw new RuntimeException('Table geometry self-test rendered unsafe caption source event attributes');
    }
    json_encode($captionSourcePacket, JSON_THROW_ON_ERROR);

    $sideCaptionTable = null;
    foreach ($document->children as $node) {
        if ($node->type === 'table' && $node->attr('caption') === 'Side caption') {
            $sideCaptionTable = $node;
            break;
        }
    }
    $sideCaptionPacket = $sideCaptionTable instanceof AstNode ? $sideCaptionTable->attr('tableGeometry') : null;
    $sideCaptionMarkdown = is_array($sideCaptionPacket)
        ? array_values(array_filter(
            $sideCaptionPacket['writerDowngrades']['markdown'] ?? [],
            static fn (array $diagnostic): bool => ($diagnostic['code'] ?? null) === 'markdown-caption-side-review-required'
        ))
        : [];
    if (
        !is_array($sideCaptionPacket)
        || ($sideCaptionPacket['summary']['captionSide'] ?? null) !== 'left'
        || ($sideCaptionPacket['summary']['captionSideSupported'] ?? null) !== false
        || ($sideCaptionPacket['summary']['captionSideReviewRequired'] ?? null) !== true
        || ($sideCaptionPacket['summary']['captionPlacementFallback'] ?? null) !== 'after-table'
        || count($sideCaptionMarkdown) !== 1
        || ($sideCaptionMarkdown[0]['requiredFeature'] ?? null) !== 'raw-html-caption-side'
    ) {
        throw new RuntimeException('Table geometry self-test missing side caption review metadata');
    }
    if (
        !str_contains($blocks, '<table id="side-caption-grid" data-source="html-reader">')
        || !str_contains($blocks, '</table><figcaption id="side-caption" class="wp-element-caption caption-title" data-origin="html-reader" style="caption-side: left; color: green">Side <em>caption</em></figcaption>')
        || str_contains($blocks, 'onclick=')
    ) {
        throw new RuntimeException('Table geometry self-test missing sanitized side caption WordPress fallback output');
    }
    json_encode($sideCaptionPacket, JSON_THROW_ON_ERROR);

    $legacyCaptionAlignTopTable = null;
    $legacyCaptionAlignSideTable = null;
    foreach ($document->children as $node) {
        if ($node->type !== 'table') {
            continue;
        }

        if ($node->attr('caption') === 'Legacy top align caption') {
            $legacyCaptionAlignTopTable = $node;
        } elseif ($node->attr('caption') === 'Legacy side align caption') {
            $legacyCaptionAlignSideTable = $node;
        }
    }
    $legacyCaptionAlignTopPacket = $legacyCaptionAlignTopTable instanceof AstNode ? $legacyCaptionAlignTopTable->attr('tableGeometry') : null;
    $legacyCaptionAlignSidePacket = $legacyCaptionAlignSideTable instanceof AstNode ? $legacyCaptionAlignSideTable->attr('tableGeometry') : null;
    $legacyCaptionAlignSideMarkdown = is_array($legacyCaptionAlignSidePacket)
        ? array_values(array_filter(
            $legacyCaptionAlignSidePacket['writerDowngrades']['markdown'] ?? [],
            static fn (array $diagnostic): bool => ($diagnostic['code'] ?? null) === 'markdown-caption-side-review-required'
        ))
        : [];
    if (
        !is_array($legacyCaptionAlignTopPacket)
        || ($legacyCaptionAlignTopPacket['summary']['captionSide'] ?? null) !== 'top'
        || ($legacyCaptionAlignTopPacket['summary']['captionSideSource'] ?? null) !== 'align'
        || ($legacyCaptionAlignTopPacket['summary']['captionBeforeTable'] ?? null) !== true
        || ($legacyCaptionAlignTopPacket['captions']['long']['sourceAttributes']['htmlAttributes']['align'] ?? null) !== 'top'
    ) {
        throw new RuntimeException('Table geometry self-test missing legacy caption align top placement metadata');
    }
    if (
        !is_array($legacyCaptionAlignSidePacket)
        || ($legacyCaptionAlignSidePacket['summary']['captionSide'] ?? null) !== 'right'
        || ($legacyCaptionAlignSidePacket['summary']['captionSideSource'] ?? null) !== 'align'
        || ($legacyCaptionAlignSidePacket['summary']['captionSideReviewRequired'] ?? null) !== true
        || ($legacyCaptionAlignSidePacket['summary']['captionPlacementFallback'] ?? null) !== 'after-table'
        || ($legacyCaptionAlignSideMarkdown[0]['captionSideSource'] ?? null) !== 'align'
    ) {
        throw new RuntimeException('Table geometry self-test missing legacy caption align side review metadata');
    }
    if (
        !str_contains($blocks, '<figcaption id="legacy-caption-align-top" class="wp-element-caption caption-title" data-origin="legacy-doc">Legacy <em>top</em> align caption</figcaption><table id="legacy-caption-align-top-grid" data-source="html-reader">')
        || !str_contains($blocks, '</table><figcaption id="legacy-caption-align-side" class="wp-element-caption caption-title" data-origin="legacy-doc">Legacy <em>side</em> align caption</figcaption>')
    ) {
        throw new RuntimeException('Table geometry self-test missing WordPress output for legacy caption align placement');
    }
    json_encode($legacyCaptionAlignTopPacket, JSON_THROW_ON_ERROR);
    json_encode($legacyCaptionAlignSidePacket, JSON_THROW_ON_ERROR);

    $summarySourceTable = $summarySourceTables[0] ?? null;
    $summarySourcePacket = $summarySourceTable instanceof AstNode ? $summarySourceTable->attr('tableGeometry') : null;
    $summarySourceBlocks = (new WordPressBlockWriter())->write($summarySourceDocument);
    $summaryWriterDowngrades = is_array($summarySourcePacket) && is_array($summarySourcePacket['writerDowngrades']['markdown'] ?? null)
        ? $summarySourcePacket['writerDowngrades']['markdown']
        : [];
    $summaryDiagnostics = array_values(array_filter(
        $summaryWriterDowngrades,
        static fn (array $diagnostic): bool => ($diagnostic['code'] ?? '') === 'markdown-table-summary-require-raw-html'
    ));
    if (
        !is_array($summarySourcePacket)
        || ($summarySourcePacket['sourceSummary']['text'] ?? null) !== 'Legacy source table describes post counts by import state.'
        || ($summarySourcePacket['summary']['hasSourceSummary'] ?? null) !== true
        || ($summaryDiagnostics[0]['summaryText'] ?? null) !== 'Legacy source table describes post counts by import state.'
    ) {
        throw new RuntimeException('Table geometry self-test missing source table summary handoff metadata');
    }
    if (!str_contains($summarySourceBlocks, '<table id="summary-source-grid" summary="Legacy source table describes post counts by import state." data-source="html-reader">')) {
        throw new RuntimeException('Table geometry self-test missing source summary attribute in WordPress output');
    }
    json_encode($summarySourcePacket, JSON_THROW_ON_ERROR);

    $axisSourceTable = $axisSourceTables[0] ?? null;
    $axisSourcePacket = $axisSourceTable instanceof AstNode ? $axisSourceTable->attr('tableGeometry') : null;
    $axisSourceBlocks = (new WordPressBlockWriter())->write($axisSourceDocument);
    $axisWriterDowngrades = is_array($axisSourcePacket) && is_array($axisSourcePacket['writerDowngrades']['markdown'] ?? null)
        ? $axisSourcePacket['writerDowngrades']['markdown']
        : [];
    $axisDiagnostics = array_values(array_filter(
        $axisWriterDowngrades,
        static fn (array $diagnostic): bool => ($diagnostic['code'] ?? '') === 'markdown-header-axis-require-raw-html'
    ));
    if (
        !is_array($axisSourcePacket)
        || ($axisSourcePacket['summary']['headerAxisCount'] ?? null) !== 3
        || ($axisSourcePacket['summary']['headerAxes'] ?? null) !== ['document', 'import', 'state', 'review', 'content-type']
        || ($axisSourcePacket['headerAssociations']['dataCells'][0]['sourceHeaderReferences'][0]['targetAxis'] ?? null) !== ['document', 'import']
        || ($axisDiagnostics[0]['requiredFeature'] ?? null) !== 'raw-html-table-header-axis'
        || ($axisDiagnostics[0]['axes'] ?? null) !== ['document', 'import', 'state', 'review', 'content-type']
    ) {
        throw new RuntimeException('Table geometry self-test missing source header axis handoff metadata');
    }
    if (!str_contains($axisSourceBlocks, '<th id="axis-document" axis="document, import" scope="col">Document</th>')) {
        throw new RuntimeException('Table geometry self-test missing source header axis in WordPress output');
    }
    if (!str_contains($axisSourceBlocks, '<th id="axis-posts" axis="content-type" scope="row">Posts</th><td headers="axis-document axis-state axis-posts">Ready</td>')) {
        throw new RuntimeException('Table geometry self-test missing row header axis and headers handoff in WordPress output');
    }
    json_encode($axisSourcePacket, JSON_THROW_ON_ERROR);

    $autoScopeTable = $autoScopeTables[0] ?? null;
    $autoScopePacket = $autoScopeTable instanceof AstNode ? $autoScopeTable->attr('tableGeometry') : null;
    $autoScopeBlocks = (new WordPressBlockWriter())->write($autoScopeDocument);
    if (
        !is_array($autoScopePacket)
        || ($autoScopePacket['summary']['diagnosticCodes'] ?? null) !== []
        || ($autoScopePacket['summary']['hasInvalidSourceScopes'] ?? null) !== false
        || ($autoScopePacket['headerAssociations']['summary']['headerScopes'] ?? null) !== ['col', 'row']
        || isset($autoScopePacket['headerAssociations']['headerCells'][0]['sourceScope'])
        || isset($autoScopePacket['headerAssociations']['headerCells'][2]['sourceScope'])
        || ($autoScopePacket['headerAssociations']['dataCells'][0]['headers'] ?? null) !== ['auto-state', 'auto-posts']
    ) {
        throw new RuntimeException('Table geometry self-test missing HTML scope=auto computed header handoff metadata');
    }
    if (
        str_contains($autoScopeBlocks, 'scope="auto"')
        || !str_contains($autoScopeBlocks, '<th scope="col" id="auto-document">Document</th><th scope="col" id="auto-state">State</th>')
        || !str_contains($autoScopeBlocks, '<th scope="row" id="auto-posts">Posts</th><td headers="auto-state auto-posts">Ready</td>')
    ) {
        throw new RuntimeException('Table geometry self-test missing computed WordPress output for HTML scope=auto headers');
    }
    json_encode($autoScopePacket, JSON_THROW_ON_ERROR);

    $captionMetadataTable = null;
    foreach ($document->children as $node) {
        if ($node->type === 'table' && $node->attr('shortCaption') === 'Queue short') {
            $captionMetadataTable = $node;
            break;
        }
    }
    $captionPacket = $captionMetadataTable instanceof AstNode ? TableGeometry::reviewPacket($captionMetadataTable, ['accessibility' => false]) : null;
    if (
        !is_array($captionPacket)
        || ($captionPacket['captions']['long']['inlineTypes'] ?? null) !== ['text', 'emph', 'link']
        || ($captionPacket['captions']['short']['inlineTypes'] ?? null) !== ['text', 'strong']
        || ($captionPacket['summary']['hasShortCaption'] ?? null) !== true
    ) {
        throw new RuntimeException('Table geometry self-test missing long/short caption review-packet metadata');
    }
    if (($captionPacket['captions']['long']['inlines'][3]['url'] ?? null) !== 'https://example.test/review') {
        throw new RuntimeException('Table geometry self-test missing caption inline link metadata');
    }
    if (
        ($captionPacket['summary']['writerDowngradeCodes'] ?? null) !== ['markdown-short-caption-prefix-required']
        || ($captionPacket['writerDowngrades']['markdown'][0]['requiredFeature'] ?? null) !== 'pandoc-short-caption-prefix'
        || ($captionPacket['writerDowngrades']['markdown'][0]['shortCaptionInlineTypes'] ?? null) !== ['text', 'strong']
    ) {
        throw new RuntimeException('Table geometry self-test missing short caption writer handoff diagnostics');
    }
    if (!str_contains($blocks, '<figure class="wp-block-table" data-pandoc-short-caption="Queue short"><table><thead><tr><th style="text-align:left">Scope</th><th style="text-align:right">State</th></tr></thead><tbody><tr><td style="text-align:left">Posts</td><td style="text-align:right">Ready</td></tr></tbody></table><figcaption class="wp-element-caption">Long <em>caption</em> for <a href="https://example.test/review" title="Review">reviewer</a></figcaption></figure>')) {
        throw new RuntimeException('Table geometry self-test missing WordPress output for caption metadata handoff');
    }
    json_encode($captionPacket, JSON_THROW_ON_ERROR);

    $blockCaptionPacket = TableGeometry::reviewPacket($blockCaptionTable, ['accessibility' => false]);
    if (
        ($blockCaptionPacket['captions']['long']['source'] ?? null) !== 'captionBlocks'
        || ($blockCaptionPacket['captions']['long']['blockTypes'] ?? null) !== ['paragraph', 'bullet_list']
        || ($blockCaptionPacket['summary']['hasCaptionBlocks'] ?? null) !== true
        || ($blockCaptionPacket['summary']['captionBlockCount'] ?? null) !== 2
    ) {
        throw new RuntimeException('Table geometry self-test missing block-level caption review-packet metadata');
    }
    if (
        ($blockCaptionPacket['summary']['writerDowngradeCodes'] ?? null) !== ['markdown-caption-blocks-flattened']
        || ($blockCaptionPacket['writerDowngrades']['markdown'][0]['requiredFeature'] ?? null) !== 'inline-caption-markdown'
        || ($blockCaptionPacket['writerDowngrades']['markdown'][0]['blockTypes'] ?? null) !== ['paragraph', 'bullet_list']
    ) {
        throw new RuntimeException('Table geometry self-test missing block-level caption writer handoff diagnostics');
    }
    if (!str_contains($blocks, '<figcaption class="wp-element-caption"><p>Block <strong>caption</strong> for reviewer</p><ul><li>Queue note</li></ul></figcaption>')) {
        throw new RuntimeException('Table geometry self-test missing WordPress output for block-level table caption');
    }
    json_encode($blockCaptionPacket, JSON_THROW_ON_ERROR);

    $malformedSpanPacket = TableGeometry::reviewPacket($malformedSpanTable, ['accessibility' => false]);
    if (
        ($malformedSpanPacket['summary']['diagnosticCodes'] ?? null) !== ['cell-span-normalized']
        || ($malformedSpanPacket['summary']['hasNormalizedSpans'] ?? null) !== true
        || ($malformedSpanPacket['summary']['normalizedSpanCount'] ?? null) !== 3
    ) {
        throw new RuntimeException('Table geometry self-test missing malformed source span normalization diagnostics');
    }
    if (
        array_map(static fn (array $diagnostic): string => (string) $diagnostic['attribute'], $malformedSpanPacket['diagnostics'] ?? []) !== ['colspan', 'rowspan', 'rowspan']
        || array_map(static fn (array $diagnostic): mixed => $diagnostic['rawValue'] ?? null, $malformedSpanPacket['diagnostics'] ?? []) !== ['0', 'many', -3]
    ) {
        throw new RuntimeException('Table geometry self-test missing raw malformed source span values');
    }
    if (($malformedSpanPacket['coverage'][0]['colspan'] ?? null) !== 1 || ($malformedSpanPacket['coverage'][0]['rowspan'] ?? null) !== 1) {
        throw new RuntimeException('Table geometry self-test missing normalized malformed source span coverage');
    }
    if (!str_contains($blocks, '<table id="malformed-source-span-grid"><tbody><tr><td style="text-align:left">Posts</td><td style="text-align:right">42</td><td style="text-align:center">Ready</td></tr><tr><td style="text-align:left">Media</td><td style="text-align:right">7</td><td style="text-align:center">Review</td></tr></tbody></table>')) {
        throw new RuntimeException('Table geometry self-test missing normalized WordPress output for malformed source spans');
    }
    if (str_contains($blocks, 'colspan="0"') || str_contains($blocks, 'rowspan="-3"')) {
        throw new RuntimeException('Table geometry self-test leaked malformed span attributes into WordPress output');
    }
    json_encode($malformedSpanPacket, JSON_THROW_ON_ERROR);

    $blockContentPacket = TableGeometry::reviewPacket($blockContentTable, [
        'accessibility' => false,
        'writers' => ['markdown', 'asciidoc'],
    ]);
    if (
        ($blockContentPacket['summary']['hasBlockContentCells'] ?? null) !== true
        || ($blockContentPacket['summary']['blockContentCellCount'] ?? null) !== 1
        || ($blockContentPacket['summary']['multiBlockCellCount'] ?? null) !== 1
        || ($blockContentPacket['summary']['cellBlockTypes'] ?? null) !== ['paragraph', 'bullet_list']
    ) {
        throw new RuntimeException('Table geometry self-test missing block-level table cell review metadata');
    }
    if (
        ($blockContentPacket['coverage'][2]['content']['blockTypes'] ?? null) !== ['paragraph', 'bullet_list']
        || ($blockContentPacket['sections'][1]['rows'][0]['slots'][0]['content']['blockCount'] ?? null) !== 2
    ) {
        throw new RuntimeException('Table geometry self-test missing block-level table cell coverage metadata');
    }
    if (
        ($blockContentPacket['summary']['writerDowngradeCodes'] ?? null) !== ['markdown-cell-blocks-flattened', 'asciidoc-block-cell-required']
        || ($blockContentPacket['writerDowngrades']['markdown'][0]['requiredFeature'] ?? null) !== 'multiline-or-grid-table-cell'
        || ($blockContentPacket['writerDowngrades']['asciidoc'][0]['requiredFeature'] ?? null) !== 'asciidoc-block-cell'
    ) {
        throw new RuntimeException('Table geometry self-test missing block-level cell writer handoff diagnostics');
    }
    if (!str_contains($blocks, '<td style="text-align:left"><p>Review <em>source</em></p><ul><li>Image alt text</li><li><strong>Resolve captions</strong></li></ul></td><td style="text-align:right">Ready</td>')) {
        throw new RuntimeException('Table geometry self-test missing WordPress output for block-level table cell content');
    }
    json_encode($blockContentPacket, JSON_THROW_ON_ERROR);

    $latexRequirementPacket = TableGeometry::reviewPacket($latexRequirementTable, [
        'accessibility' => false,
        'writers' => ['latex'],
    ]);
    if (
        ($latexRequirementPacket['summary']['writerDowngradeCodes'] ?? null) !== [
            'latex-multicolumn-required',
            'latex-multirow-required',
            'latex-cell-block-required',
            'latex-nested-table-required',
        ]
        || ($latexRequirementPacket['writerDowngrades']['latex'][3]['requiredFeature'] ?? null) !== 'parbox-or-minipage-cell'
        || ($latexRequirementPacket['writerDowngrades']['latex'][4]['requiredFeature'] ?? null) !== 'nested-tabular-minipage'
        || ($latexRequirementPacket['writerDowngrades']['latex'][4]['nestedTableCaptions'] ?? null) !== ['Nested LaTeX audit']
    ) {
        throw new RuntimeException('Table geometry self-test missing LaTeX table writer requirement diagnostics');
    }
    if (!str_contains($blocks, '<figcaption class="wp-element-caption">LaTeX table requirement audit</figcaption>')) {
        throw new RuntimeException('Table geometry self-test missing LaTeX requirement review table output');
    }
    json_encode($latexRequirementPacket, JSON_THROW_ON_ERROR);

    $latexFooterPacket = TableGeometry::reviewPacket($latexFooterTable, [
        'accessibility' => false,
        'writers' => ['latex'],
    ]);
    if (
        ($latexFooterPacket['summary']['writerDowngradeCodes'] ?? null) !== []
        || ($latexFooterPacket['writerDowngrades']['latex'] ?? null) !== []
        || ($latexFooterPacket['summary']['hasTableFoot'] ?? null) !== true
        || ($latexFooterPacket['summary']['tableFootRowCount'] ?? null) !== 1
    ) {
        throw new RuntimeException('Table geometry self-test did not treat LaTeX longtable footer handoff as supported');
    }
    if (!str_contains($blocks, '<tfoot><tr><td style="text-align:left">Total</td><td style="text-align:right">Ready</td></tr></tfoot></table><figcaption class="wp-element-caption">LaTeX footer audit</figcaption>')) {
        throw new RuntimeException('Table geometry self-test missing LaTeX footer review table output');
    }
    json_encode($latexFooterPacket, JSON_THROW_ON_ERROR);

    $footerSectionPacket = TableGeometry::reviewPacket($latexFooterTable, [
        'accessibility' => false,
        'writers' => ['markdown', 'asciidoc'],
    ]);
    if (
        ($footerSectionPacket['summary']['writerDowngradeCodes'] ?? null) !== [
            'markdown-table-foot-flattened',
            'asciidoc-table-foot-required',
        ]
        || ($footerSectionPacket['writerDowngrades']['markdown'][0]['requiredFeature'] ?? null) !== 'body-row-flattening'
        || ($footerSectionPacket['writerDowngrades']['asciidoc'][0]['requiredFeature'] ?? null) !== 'table-footer'
        || ($footerSectionPacket['writerDowngrades']['markdown'][0]['footRowCount'] ?? null) !== 1
    ) {
        throw new RuntimeException('Table geometry self-test missing Markdown/AsciiDoc footer-section writer diagnostics');
    }
    json_encode($footerSectionPacket, JSON_THROW_ON_ERROR);

    $emptyReviewPacket = TableGeometry::reviewPacket($emptyReviewTable, [
        'accessibility' => false,
        'writers' => ['markdown', 'asciidoc', 'latex', 'wordpress'],
    ]);
    if (
        ($emptyReviewPacket['summary']['diagnosticCodes'] ?? null) !== ['table-has-no-cells']
        || ($emptyReviewPacket['summary']['hasEmptyTable'] ?? null) !== true
        || ($emptyReviewPacket['summary']['emptyTableSectionCount'] ?? null) !== 2
        || ($emptyReviewPacket['summary']['writerDowngradeCodes'] ?? null) !== [
            'markdown-empty-table-omitted',
            'asciidoc-empty-table-review-required',
            'latex-empty-table-review-required',
        ]
        || ($emptyReviewPacket['writerDowngrades']['wordpress'] ?? null) !== []
    ) {
        throw new RuntimeException('Table geometry self-test missing empty-table review diagnostics');
    }
    if (!str_contains($blocks, '<table><tbody id="empty-body"></tbody></table><figcaption class="wp-element-caption">Empty import table audit</figcaption>')) {
        throw new RuntimeException('Table geometry self-test missing empty-table WordPress output');
    }
    json_encode($emptyReviewPacket, JSON_THROW_ON_ERROR);

    echo "table geometry handoff self-test ok\n";
    return;
}

echo $blocks . "\n";
