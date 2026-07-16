<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\OdfReader;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\ZipPackage;

$manifestXml = <<<'XML'
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:version="1.3" manifest:media-type="application/vnd.oasis.opendocument.text"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>
</manifest:manifest>
XML;

$stylesXml = <<<'XML'
<office:document-styles
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"/>
XML;

$contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:table="urn:oasis:names:tc:opendocument:xmlns:table:1.0">
  <office:body>
    <office:text>
      <table:database-ranges>
        <table:database-range table:name="ReadyPosts" table:target-range-address="Review.A1:Review.D12" table:contains-header="true">
          <table:database-source-sql table:database-name="ImportDS" table:sql-statement="SELECT post_title,status,imported,total FROM wp_posts" table:parse-sql-statement="true"/>
          <table:subtotal-rules table:bind-styles-to-content="true" table:case-sensitive="false" table:page-breaks-on-group-change="true">
            <table:sort-groups table:case-sensitive="true">
              <table:sort-by table:field-number="2" table:data-type="text" table:order="ascending"/>
            </table:sort-groups>
            <table:subtotal-rule table:group-by-field-number="2">
              <table:subtotal-field table:field-number="3" table:function="count"/>
              <table:subtotal-field table:field-number="4" table:function="sum"/>
            </table:subtotal-rule>
          </table:subtotal-rules>
        </table:database-range>
      </table:database-ranges>
      <table:named-expressions>
        <table:named-range table:name="ReadyPostRows" table:cell-range-address="Review.A2:Review.D12" table:base-cell-address="Review.A1" table:range-usable-as="filter"/>
        <table:named-expression table:name="ReadyPostCount" table:expression="of:=COUNTIF([.B2:.B12];&quot;ready&quot;)" table:base-cell-address="Review.A1"/>
        <table:named-range table:name="ReadyPostRows" table:cell-range-address="Archive.A2:Archive.D12" table:base-cell-address="Archive.A1"/>
      </table:named-expressions>
      <table:label-ranges>
        <table:label-range table:label-cell-range-address="Review.A1:Review.D1" table:data-cell-range-address="Review.A2:Review.D12" table:orientation="column"/>
        <table:label-range table:label-cell-range-address="Review.A2:Review.A12" table:data-cell-range-address="Review.B2:Review.D12" table:orientation="row"/>
      </table:label-ranges>
      <table:calculation-settings table:case-sensitive="true" table:precision-as-shown="false" table:search-criteria-must-apply-to-whole-cell="true" table:automatic-find-labels="true" table:use-regular-expressions="false" table:use-wildcards="true" table:null-year="1930" table:iteration="true" table:iteration-count="75" table:iteration-tolerance="0.0001"/>
      <table:consolidation table:function="sum" table:source-cell-range-addresses="Review.A2:Review.D12 Archive.A2:Archive.D12" table:target-cell-address="Summary.A2" table:use-labels="column row" table:link-to-source-data="true"/>
      <table:data-pilot-tables>
        <table:data-pilot-table table:name="ReadyPostPivot" table:application-data="wp-import-review" table:target-range-address="Pivot.A1:Pivot.D8" table:buttons="true" table:show-filter-button="true" table:grand-total="both" table:ignore-empty-rows="true" table:identify-categories="false">
          <table:source-cell-range table:cell-range-address="Review.A1:Review.D12"/>
          <table:data-pilot-field table:source-field-name="status" table:orientation="row" table:used-hierarchy="1">
            <table:data-pilot-display-info table:enabled="true" table:display-member-mode="from-top" table:member-count="5" table:data-field="total"/>
            <table:data-pilot-sort-info table:order="descending" table:sort-mode="data" table:data-field="total"/>
            <table:data-pilot-layout-info table:layout-mode="outline-subtotals-top" table:add-empty-lines="true"/>
            <table:data-pilot-field-reference table:type="item-difference" table:field-name="previous_status" table:member-type="named" table:member-name="draft"/>
            <table:data-pilot-level table:show-empty="false" table:repeat-item-labels="true">
              <table:data-pilot-subtotals>
                <table:data-pilot-subtotal table:function="count"/>
                <table:data-pilot-subtotal table:function="sum"/>
              </table:data-pilot-subtotals>
              <table:data-pilot-members>
                <table:data-pilot-member table:name="ready" table:display="true" table:show-details="true"/>
                <table:data-pilot-member table:name="draft" table:display="false"/>
              </table:data-pilot-members>
            </table:data-pilot-level>
          </table:data-pilot-field>
          <table:data-pilot-field table:source-field-name="total" table:orientation="data" table:function="sum"/>
        </table:data-pilot-table>
      </table:data-pilot-tables>
      <table:content-validations>
        <table:content-validation table:name="ReviewStatusValidation" table:condition="cell-content-is-in-list(&quot;draft&quot;;&quot;ready&quot;;&quot;legal&quot;)" table:base-cell-address="Review.B2" table:allow-empty-cell="false" table:display-list="sort-ascending">
          <table:help-message table:title="Review status" table:display="true">
            <text:p>Choose a migration review status.</text:p>
          </table:help-message>
          <table:error-message table:title="Invalid status" table:display="true" table:message-type="warning">
            <text:p>Use draft, ready, or legal.</text:p>
          </table:error-message>
          <table:error-macro table:name="ReviewStatusMacro" table:execute="false"/>
        </table:content-validation>
      </table:content-validations>
      <table:table table:name="Validation Review">
        <table:table-row>
          <table:table-cell><text:p>Status</text:p></table:table-cell>
          <table:table-cell table:content-validation-name="ReviewStatusValidation" office:value-type="string" office:string-value="ready">
            <table:detective>
              <table:highlighted-range table:cell-range-address="Review.A2:Review.D12" table:direction="from-dependents" table:contains-error="false"/>
              <table:operation table:name="trace-dependents" table:index="1"/>
            </table:detective>
            <text:p>ready</text:p>
          </table:table-cell>
        </table:table-row>
      </table:table>
      <text:p>Import source <text:database-display text:database-name="ImportDS" text:table-name="wp_posts" text:table-type="table" text:column-name="post_title">Imported post title</text:database-display> moved to row <text:database-row-number text:database-name="ImportDS" text:table-name="wp_posts" text:row-number="12"/>.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

$metaXml = <<<'XML'
<office:document-meta
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0">
  <office:meta/>
</office:document-meta>
XML;

$package = ZipPackage::fromParts([
    ['name' => 'mimetype', 'data' => OdfReader::MIMETYPE, 'compressionMethod' => 0],
    ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml],
    ['name' => 'content.xml', 'data' => $contentXml],
    ['name' => 'styles.xml', 'data' => $stylesXml],
    ['name' => 'meta.xml', 'data' => $metaXml],
]);

$result = (new OdfReader())->readPackage($package);
$blocks = (new WordPressBlockWriter())->write($result['document']);

if (in_array('--self-test', $argv, true)) {
    $declarations = $result['importReport']['contentDeclarations'] ?? [];
    $readyPosts = $result['document']->attr('contentDeclarations')['databaseRangesByName']['ReadyPosts'] ?? null;
    if (!is_array($readyPosts)) {
        throw new RuntimeException('Expected ODT database-range metadata to be preserved for ReadyPosts');
    }
    $subtotalRules = $readyPosts['subtotalRules'] ?? [];
    if (!is_array($subtotalRules)) {
        throw new RuntimeException('Expected ODT subtotal-rules metadata to be preserved for ReadyPosts');
    }
    $rules = $subtotalRules['rules'] ?? [];
    if (!is_array($rules)) {
        throw new RuntimeException('Expected ODT subtotal-rule entries to be preserved for ReadyPosts');
    }
    $fields = $rules[0]['fields'] ?? [];
    if (!is_array($fields)) {
        throw new RuntimeException('Expected ODT subtotal-field entries to be preserved for ReadyPosts');
    }
    if (($declarations['databaseRangeCount'] ?? 0) !== 1) {
        throw new RuntimeException('Expected ODT database range to be counted in the import report');
    }
    if (($declarations['databaseSubtotalRuleCount'] ?? 0) !== 1) {
        throw new RuntimeException('Expected ODT subtotal rule to be counted in the import report');
    }
    if (($declarations['databaseSubtotalFieldCount'] ?? 0) !== 2) {
        throw new RuntimeException('Expected ODT subtotal fields to be counted in the import report');
    }
    if (($declarations['namedExpressionCount'] ?? 0) !== 3) {
        throw new RuntimeException('Expected ODT named ranges and expressions to be counted in the import report');
    }
    if (($declarations['namedRangeCount'] ?? 0) !== 2) {
        throw new RuntimeException('Expected ODT named range count to be preserved');
    }
    if (($declarations['namedFormulaExpressionCount'] ?? 0) !== 1) {
        throw new RuntimeException('Expected ODT named formula expression count to be preserved');
    }
    if (($declarations['namedExpressionDuplicateNameCount'] ?? 0) !== 1) {
        throw new RuntimeException('Expected ODT duplicate named-expression names to be counted');
    }
    if (($declarations['namedExpressionDuplicateEntryCount'] ?? 0) !== 1) {
        throw new RuntimeException('Expected ODT duplicate named-expression entries to be counted');
    }
    if (($declarations['namedExpressionDuplicateNames'] ?? []) !== ['ReadyPostRows']) {
        throw new RuntimeException('Expected ODT duplicate named-expression name list to be preserved');
    }
    if (($declarations['labelRangeCount'] ?? 0) !== 2) {
        throw new RuntimeException('Expected ODT label ranges to be counted in the import report');
    }
    $labelRanges = $result['document']->attr('contentDeclarations')['labelRanges'] ?? [];
    if (!is_array($labelRanges)
        || ($labelRanges[0]['labelCellRangeAddress'] ?? '') !== 'Review.A1:Review.D1'
        || ($labelRanges[0]['dataCellRangeAddress'] ?? '') !== 'Review.A2:Review.D12'
        || ($labelRanges[1]['orientation'] ?? '') !== 'row') {
        throw new RuntimeException('Expected ODT label-range addresses and orientations to be preserved');
    }
    $labelOrientationCounts = $result['document']->attr('contentDeclarations')['labelRangeOrientationCounts'] ?? [];
    if (!is_array($labelOrientationCounts)
        || ($labelOrientationCounts['column'] ?? 0) !== 1
        || ($labelOrientationCounts['row'] ?? 0) !== 1) {
        throw new RuntimeException('Expected ODT label-range orientation counts to be preserved');
    }
    if (($declarations['calculationSettingCount'] ?? 0) !== 1) {
        throw new RuntimeException('Expected ODT calculation settings to be counted in the import report');
    }
    $calculationSettings = $result['document']->attr('contentDeclarations')['calculationSettings'] ?? [];
    if (!is_array($calculationSettings)
        || ($calculationSettings['caseSensitive'] ?? null) !== true
        || ($calculationSettings['precisionAsShown'] ?? null) !== false
        || ($calculationSettings['searchCriteriaMustApplyToWholeCell'] ?? null) !== true
        || ($calculationSettings['useWildcards'] ?? null) !== true
        || ($calculationSettings['iterationCount'] ?? null) !== 75
        || ($calculationSettings['iterationTolerance'] ?? '') !== '0.0001') {
        throw new RuntimeException('Expected ODT calculation settings policy metadata to be preserved');
    }
    if (($declarations['consolidationCount'] ?? 0) !== 1) {
        throw new RuntimeException('Expected ODT consolidation metadata to be counted in the import report');
    }
    if (($declarations['consolidationSourceRangeCount'] ?? 0) !== 2) {
        throw new RuntimeException('Expected ODT consolidation source ranges to be counted in the import report');
    }
    $consolidations = $result['document']->attr('contentDeclarations')['consolidations'] ?? [];
    $consolidation = is_array($consolidations[0] ?? null) ? $consolidations[0] : null;
    if (!is_array($consolidation)
        || ($consolidation['function'] ?? '') !== 'sum'
        || ($consolidation['targetCellAddress'] ?? '') !== 'Summary.A2'
        || ($consolidation['sourceCellRangeAddresses'] ?? []) !== ['Review.A2:Review.D12', 'Archive.A2:Archive.D12']
        || ($consolidation['linkToSourceData'] ?? null) !== true) {
        throw new RuntimeException('Expected ODT consolidation source and target metadata to be preserved');
    }
    $namedExpressions = $result['document']->attr('contentDeclarations')['namedExpressionsByName'] ?? [];
    $readyRows = is_array($namedExpressions) ? ($namedExpressions['ReadyPostRows'] ?? null) : null;
    $readyCount = is_array($namedExpressions) ? ($namedExpressions['ReadyPostCount'] ?? null) : null;
    if (!is_array($readyRows) || ($readyRows['cellRangeAddress'] ?? '') !== 'Archive.A2:Archive.D12') {
        throw new RuntimeException('Expected ODT named range cell address metadata to be preserved');
    }
    if (!is_array($readyCount) || ($readyCount['expression'] ?? '') !== 'of:=COUNTIF([.B2:.B12];"ready")') {
        throw new RuntimeException('Expected ODT named expression formula metadata to be preserved');
    }
    $dataPilotTables = $result['document']->attr('contentDeclarations')['dataPilotTablesByName'] ?? [];
    $readyPivot = is_array($dataPilotTables) ? ($dataPilotTables['ReadyPostPivot'] ?? null) : null;
    if (!is_array($readyPivot) || ($readyPivot['source']['cellRangeAddress'] ?? '') !== 'Review.A1:Review.D12') {
        throw new RuntimeException('Expected ODT data-pilot source cell range metadata to be preserved');
    }
    if (($declarations['dataPilotTableCount'] ?? 0) !== 1) {
        throw new RuntimeException('Expected ODT data-pilot table to be counted in the import report');
    }
    if (($declarations['dataPilotFieldCount'] ?? 0) !== 2) {
        throw new RuntimeException('Expected ODT data-pilot fields to be counted in the import report');
    }
    if (($declarations['dataPilotSubtotalCount'] ?? 0) !== 2) {
        throw new RuntimeException('Expected ODT data-pilot subtotals to be counted in the import report');
    }
    if (($declarations['dataPilotMemberCount'] ?? 0) !== 2) {
        throw new RuntimeException('Expected ODT data-pilot members to be counted in the import report');
    }
    if (($declarations['dataPilotDisplayInfoCount'] ?? 0) !== 1
        || ($declarations['dataPilotSortInfoCount'] ?? 0) !== 1
        || ($declarations['dataPilotLayoutInfoCount'] ?? 0) !== 1
        || ($declarations['dataPilotFieldReferenceCount'] ?? 0) !== 1) {
        throw new RuntimeException('Expected ODT data-pilot field policy metadata to be counted in the import report');
    }
    if (($declarations['contentValidationCount'] ?? 0) !== 1) {
        throw new RuntimeException('Expected ODT content validation to be counted in the import report');
    }
    if (($declarations['contentValidationConditionCount'] ?? 0) !== 1) {
        throw new RuntimeException('Expected ODT content validation condition to be counted in the import report');
    }
    if (($declarations['contentValidationMessageCount'] ?? 0) !== 2) {
        throw new RuntimeException('Expected ODT content validation messages to be counted in the import report');
    }
    $contentValidations = $result['document']->attr('contentDeclarations')['contentValidationsByName'] ?? [];
    $statusValidation = is_array($contentValidations) ? ($contentValidations['ReviewStatusValidation'] ?? null) : null;
    if (!is_array($statusValidation) || ($statusValidation['condition'] ?? '') !== 'cell-content-is-in-list("draft";"ready";"legal")') {
        throw new RuntimeException('Expected ODT content validation condition metadata to be preserved');
    }
    if (($statusValidation['helpMessage']['title'] ?? '') !== 'Review status' || ($statusValidation['errorMessage']['messageType'] ?? '') !== 'warning') {
        throw new RuntimeException('Expected ODT content validation help and error messages to be preserved');
    }
    $pivotFields = $readyPivot['fields'] ?? [];
    if (!is_array($pivotFields)) {
        throw new RuntimeException('Expected ODT data-pilot field metadata to be preserved');
    }
    $statusLevel = is_array($pivotFields[0]['levels'][0] ?? null) ? $pivotFields[0]['levels'][0] : [];
    if (($pivotFields[0]['orientation'] ?? '') !== 'row' || ($pivotFields[1]['function'] ?? '') !== 'sum') {
        throw new RuntimeException('Expected ODT data-pilot field orientation and aggregation metadata to be preserved');
    }
    if (($pivotFields[0]['displayInfo']['displayMemberMode'] ?? '') !== 'from-top'
        || ($pivotFields[0]['displayInfo']['memberCount'] ?? null) !== 5
        || ($pivotFields[0]['sortInfo']['order'] ?? '') !== 'descending'
        || ($pivotFields[0]['sortInfo']['sortMode'] ?? '') !== 'data'
        || ($pivotFields[0]['layoutInfo']['layoutMode'] ?? '') !== 'outline-subtotals-top'
        || ($pivotFields[0]['layoutInfo']['addEmptyLines'] ?? null) !== true
        || ($pivotFields[0]['fieldReference']['memberName'] ?? '') !== 'draft') {
        throw new RuntimeException('Expected ODT data-pilot field display, sort, layout, and reference metadata to be preserved');
    }
    if (($statusLevel['subtotals'][0]['function'] ?? '') !== 'count' || ($statusLevel['members'][0]['name'] ?? '') !== 'ready') {
        throw new RuntimeException('Expected ODT data-pilot level subtotals and members to be preserved');
    }
    if (($subtotalRules['sortGroups']['sortBy'][0]['fieldNumber'] ?? null) !== 2) {
        throw new RuntimeException('Expected ODT subtotal sort group field metadata to be preserved');
    }
    if (($fields[0]['function'] ?? null) !== 'count' || ($fields[1]['function'] ?? null) !== 'sum') {
        throw new RuntimeException('Expected ODT subtotal field functions to be preserved');
    }
    if (($result['importReport']['content']['fieldCount'] ?? 0) !== 2) {
        throw new RuntimeException('Expected ODT database fields to be counted in the import report');
    }
    if (($result['importReport']['content']['tableCellDetectiveCount'] ?? 0) !== 1) {
        throw new RuntimeException('Expected ODT table-cell detective metadata to be counted in the import report');
    }
    if (($result['importReport']['content']['tableCellDetectiveHighlightCount'] ?? 0) !== 1) {
        throw new RuntimeException('Expected ODT table-cell detective highlighted ranges to be counted in the import report');
    }
    if (($result['importReport']['content']['tableCellDetectiveOperationCount'] ?? 0) !== 1) {
        throw new RuntimeException('Expected ODT table-cell detective operations to be counted in the import report');
    }
    if (!str_contains($blocks, '<span class="odf-field odf-field-database-display" data-odf-field-type="database-display" data-odf-field-database-name="ImportDS" data-odf-field-table-name="wp_posts" data-odf-field-table-type="table" data-odf-field-column-name="post_title">Imported post title</span>')) {
        throw new RuntimeException('Expected ODT database-display field metadata to render in WordPress blocks');
    }
    if (!str_contains($blocks, '<span class="odf-field odf-field-database-row-number" data-odf-field-type="database-row-number" data-odf-field-database-name="ImportDS" data-odf-field-table-name="wp_posts" data-odf-field-row-number="12">12</span>')) {
        throw new RuntimeException('Expected ODT database-row-number fallback value to render in WordPress blocks');
    }
    if (!str_contains($blocks, '<td class="odf-table-cell-value odf-table-cell-validation odf-table-cell-detective" data-odf-cell-value-type="string" data-odf-cell-string-value="ready" data-odf-cell-content-validation-name="ReviewStatusValidation" data-odf-cell-content-validation-exists="true" data-odf-cell-content-validation-condition="cell-content-is-in-list(&quot;draft&quot;;&quot;ready&quot;;&quot;legal&quot;)" data-odf-cell-content-validation-allow-empty-cell="false" data-odf-cell-detective-highlight-count="1" data-odf-cell-detective-ranges="Review.A2:Review.D12" data-odf-cell-detective-directions="from-dependents" data-odf-cell-detective-operation-count="1" data-odf-cell-detective-operation-names="trace-dependents"><p>ready</p></td>')) {
        throw new RuntimeException('Expected ODT content validation cell metadata to render in WordPress blocks');
    }

    echo "odf database field handoff self-test ok\n";
    return;
}

echo $blocks;
