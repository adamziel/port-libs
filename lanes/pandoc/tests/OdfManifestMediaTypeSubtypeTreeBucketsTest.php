<?php

declare(strict_types=1);

use PortLibs\Pandoc\OdfReader;
use PortLibs\Pandoc\OpenDocumentPackage;
use PortLibs\Pandoc\ZipPackage;

return [
    'summarizes ODF manifest media types by subtype tree' => static function (TestRunner $t): void {
        $package = odf_manifest_media_type_subtype_tree_fixture_package();
        $compact = OpenDocumentPackage::fromPackage($package)->summarize();
        $rich = (new OdfReader())->readPackage($package);
        $compactSummary = $compact['manifestMediaTypeSummary'];
        $richSummary = $rich['importReport']['manifest']['mediaTypeSummary'];
        $documentSummary = $rich['document']->attr('manifest')['mediaTypeSummary'];
        $compactIdentity = $compact['packageIdentity'];
        $richIdentity = $rich['importReport']['manifest']['packageProvenance']['packageIdentity'];
        $documentIdentity = $rich['document']->attr('manifest')['packageProvenance']['packageIdentity'];
        $trees = odf_manifest_media_type_subtype_tree_index_by(
            $compactSummary['mediaTypeSubtypeTreeSummaries'],
            'mediaTypeSubtypeTreeKey'
        );

        $t->same('Manifest subtype tree buckets.', $rich['document']->children[0]->attr('text'));
        $t->same($compactSummary['mediaTypeSubtypeTreeCount'], $richSummary['mediaTypeSubtypeTreeCount']);
        $t->same($compactSummary['mediaTypeSubtypeTreeCounts'], $richSummary['mediaTypeSubtypeTreeCounts']);
        $t->same($compactSummary['mediaTypeSubtypeTreeSummaries'], $richSummary['mediaTypeSubtypeTreeSummaries']);
        $t->same($richSummary['mediaTypeSubtypeTreeSummaries'], $documentSummary['mediaTypeSubtypeTreeSummaries']);
        $t->same(6, $compactSummary['mediaTypeSubtypeTreeCount']);
        $t->same([
            '(empty)' => 1,
            '(invalid)' => 1,
            'experimental' => 1,
            'personal' => 1,
            'standard' => 4,
            'vendor' => 2,
        ], $compactSummary['mediaTypeSubtypeTreeCounts']);
        $t->same([
            '(empty)',
            '(invalid)',
            'experimental',
            'personal',
            'standard',
            'vendor',
        ], array_column($compactSummary['mediaTypeSubtypeTreeSummaries'], 'mediaTypeSubtypeTreeKey'));

        $vendor = $trees['vendor'];
        $t->same('vendor', $vendor['mediaTypeSubtypeTree']);
        $t->same(2, $vendor['count']);
        $t->same(['/', 'Object 1/'], $vendor['parts']);
        $t->same([
            'application/vnd.oasis.opendocument.formula' => 1,
            'application/vnd.oasis.opendocument.text' => 1,
        ], $vendor['mediaTypeBaseCounts']);
        $t->same([
            'application/vnd.oasis.opendocument.formula',
            'application/vnd.oasis.opendocument.text',
        ], $vendor['rawMediaTypes']);
        $t->same(1, $vendor['directoryCount']);
        $t->same(1, $vendor['nonDirectoryCount']);

        $standard = $trees['standard'];
        $t->same('standard', $standard['mediaTypeSubtypeTree']);
        $t->same(4, $standard['count']);
        $t->same(['Pictures/hero.png', 'content.xml', 'meta.xml', 'styles.xml'], $standard['parts']);
        $t->same(['image/png' => 1, 'text/xml' => 3], $standard['mediaTypeBaseCounts']);
        $t->same(['image/png; role="preview"', 'text/xml'], $standard['rawMediaTypes']);
        $t->same(1, $standard['parameterizedItemCount']);
        $t->same(0, $standard['directoryCount']);
        $t->same(4, $standard['nonDirectoryCount']);

        $personal = $trees['personal'];
        $t->same('personal', $personal['mediaTypeSubtypeTree']);
        $t->same(1, $personal['count']);
        $t->same(['Custom/personal.xml'], $personal['parts']);
        $t->same(['application/prs.review+xml' => 1], $personal['mediaTypeBaseCounts']);

        $experimental = $trees['experimental'];
        $t->same('experimental', $experimental['mediaTypeSubtypeTree']);
        $t->same(1, $experimental['count']);
        $t->same(['Custom/experiment.bin'], $experimental['parts']);
        $t->same(['application/x-review' => 1], $experimental['mediaTypeBaseCounts']);

        $invalid = $trees['(invalid)'];
        $t->same(null, $invalid['mediaTypeSubtypeTree']);
        $t->same(1, $invalid['count']);
        $t->same(['Custom/invalid.dat'], $invalid['parts']);
        $t->same(['review-type' => 1], $invalid['mediaTypeBaseCounts']);
        $t->same(['review-type'], $invalid['rawMediaTypes']);

        $empty = $trees['(empty)'];
        $t->same(null, $empty['mediaTypeSubtypeTree']);
        $t->same(1, $empty['count']);
        $t->same(['Configurations2/'], $empty['parts']);
        $t->same(['(empty)' => 1], $empty['mediaTypeBaseCounts']);
        $t->same(1, $empty['directoryCount']);
        $t->same(0, $empty['nonDirectoryCount']);

        $t->same($compactSummary, $compactIdentity['manifestMediaTypeSummary']);
        $t->same($richSummary, $richIdentity['manifestMediaTypeSummary']);
        $t->same($richIdentity['manifestMediaTypeSummary'], $documentIdentity['manifestMediaTypeSummary']);
        $t->same(6, $compactIdentity['manifestMediaTypeSubtypeTreeCount']);
        $t->same($compactSummary['mediaTypeSubtypeTreeCounts'], $compactIdentity['manifestMediaTypeSubtypeTreeCounts']);
        $t->same($compactSummary['mediaTypeSubtypeTreeSummaries'], $compactIdentity['manifestMediaTypeSubtypeTreeSummaries']);
        $t->same($richIdentity['manifestMediaTypeSubtypeTreeCounts'], $documentIdentity['manifestMediaTypeSubtypeTreeCounts']);
        $t->same($richIdentity['manifestMediaTypeSubtypeTreeSummaries'], $documentIdentity['manifestMediaTypeSubtypeTreeSummaries']);
    },
];

function odf_manifest_media_type_subtype_tree_fixture_package(): ZipPackage
{
    $contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:body>
    <office:text>
      <text:p>Manifest subtype tree buckets.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;
    $stylesXml = <<<'XML'
<office:document-styles
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0">
  <office:styles>
    <style:style style:name="BodyText" style:family="paragraph"/>
  </office:styles>
</office:document-styles>
XML;
    $metaXml = <<<'XML'
<office:document-meta
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:dc="http://purl.org/dc/elements/1.1/">
  <office:meta>
    <dc:title>Manifest Subtype Tree Buckets</dc:title>
  </office:meta>
</office:document-meta>
XML;
    $manifestXml = <<<'XML'
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:version="1.3" manifest:media-type="application/vnd.oasis.opendocument.text"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Pictures/hero.png" manifest:media-type="image/png; role=&quot;preview&quot;"/>
  <manifest:file-entry manifest:full-path="Object 1/" manifest:media-type="application/vnd.oasis.opendocument.formula"/>
  <manifest:file-entry manifest:full-path="Custom/personal.xml" manifest:media-type="application/prs.review+xml"/>
  <manifest:file-entry manifest:full-path="Custom/experiment.bin" manifest:media-type="application/x-review"/>
  <manifest:file-entry manifest:full-path="Custom/invalid.dat" manifest:media-type="review-type"/>
  <manifest:file-entry manifest:full-path="Configurations2/"/>
</manifest:manifest>
XML;

    return ZipPackage::fromParts([
        ['name' => 'mimetype', 'data' => OpenDocumentPackage::TEXT_MIMETYPE, 'compressionMethod' => 0],
        ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml, 'compressionMethod' => 0],
        ['name' => 'content.xml', 'data' => $contentXml, 'compressionMethod' => 0],
        ['name' => 'styles.xml', 'data' => $stylesXml, 'compressionMethod' => 0],
        ['name' => 'meta.xml', 'data' => $metaXml, 'compressionMethod' => 0],
        ['name' => 'Pictures/hero.png', 'data' => 'PNGDATA', 'compressionMethod' => 0],
        ['name' => 'Object 1/', 'data' => '', 'compressionMethod' => 0],
        ['name' => 'Custom/personal.xml', 'data' => '<personal/>', 'compressionMethod' => 0],
        ['name' => 'Custom/experiment.bin', 'data' => 'experiment', 'compressionMethod' => 0],
        ['name' => 'Custom/invalid.dat', 'data' => 'invalid', 'compressionMethod' => 0],
        ['name' => 'Configurations2/', 'data' => '', 'compressionMethod' => 0],
    ], 'odf manifest media type subtype tree buckets');
}

/**
 * @param list<array<string, mixed>> $items
 * @return array<string, array<string, mixed>>
 */
function odf_manifest_media_type_subtype_tree_index_by(array $items, string $key): array
{
    $indexed = [];
    foreach ($items as $item) {
        $indexed[(string) $item[$key]] = $item;
    }

    return $indexed;
}
