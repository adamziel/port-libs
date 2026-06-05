# markerpdf XMP resource-reference boundary current base

Slice: `markerpdf-xmp-metadata-boundary-current-base-20260605T183045Z`

Base: `8a209745d849ff74146dd38c58413945e1e6a43c`

## Source truth

Upstream markerPDF relies on PDF metadata/XMP extraction from searchable PDFs before model/OCR stages. Under the current no-GPU markerPDF scope, this slice keeps the boundary native: catalog `/Metadata` XML streams are parsed in PHP, promoted into document metadata only when they are document XMP, and never copied into visible WordPress paragraph text.

The PDF/XMP behavior covered here is same-packet RDF fragment indirection: metadata properties may carry `rdf:resource="#id"` and the target document-level `rdf:Description` may hold `rdf:value`, `rdf:Alt`, `rdf:Seq`, or `rdf:Bag` values. External, missing, and cyclic references are ignored.

## Implementation

- `PdfMetadataExtractor` resolves same-packet XMP `rdf:resource` fragment references for scalar document fields and RDF list containers.
- Fragment target descriptions are skipped as independent top-level document descriptions so qualifier-only target text cannot become promoted metadata.
- Missing, external, malformed, and cyclic resource references fail closed.

## Verification

Red-first:

`php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpResourceReferenceBoundaryCurrentBaseTest.php`

Result: `1 test files, 14 assertions, 2 failures`.

After fix:

`php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpResourceReferenceBoundaryCurrentBaseTest.php`

Result: `1 test files, 48 assertions, 0 failures`.

Adjacent XMP family:

`php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmp*Test.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php`

Result: `32 test files, 2206 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-xmp-resource-reference-boundary-currentbase.php`

Result: emits `title_from_fragment_resource=true`, `authors_from_fragment_seq=true`, `keywords_from_fragment_bag=true`, `external_resource_ignored=true`, `fragment_target_qualifiers_excluded=true`, `decoy_xmp_excluded=true`, `visible_text_excludes_xmp=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Dependency closure

No new support component is needed. This reuses the existing native PDF object, stream-filter, DOM XML, and metadata merge helpers. GPU/model OCR, Python markerPDF workers, PDFium, and external PDF tools remain intentionally out of scope.

## Non-overlap

This avoids the accepted XMP packet-boundary, declared-encoding, namespace, typed-node, qualified-value, sparse-list, split-description, text-subject, and resource-wrapped-list slices. The new behavior is specifically same-packet RDF `rdf:resource` fragment resolution plus qualifier exclusion for fragment target descriptions.
