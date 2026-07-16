# markerpdf-xmp-nested-resource-reference-boundary-current-base-20260608T081513Z

## Scope

- Lane: `markerpdf`
- Base accepted HEAD: `3731382a4ed6eeadc3435312fda3e3239821ee32`
- Micro-slice: `markerpdf-xmp-metadata-boundary-current-base-20260608T081513Z`
- Behavior cluster: root document XMP metadata streams now resolve same-packet nested `rdf:resource` and `rdf:nodeID` targets inside the document-level RDF graph, while nested `rdf:RDF` decoys and trailing packets remain excluded.

## Source Truth

- Local source truth: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json` native metadata/XMP rows plus the existing accepted XMP resource-reference, nodeID, typed-node, external-about, parseType, and unsafe-packet current-base tests.
- PDF/RDF boundary applied: only `/Catalog /Metadata` indirect streams with `/Type /Metadata` and `/Subtype /XML` become document XMP. Fragment-local RDF references can supply document metadata only when reached from a document-level XMP property; non-document XML streams stay review-only summaries with text redaction.

## Evidence

- Red-first:
  - `php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpNestedResourceReferenceBoundaryCurrentBaseTest.php`
  - Result before parser patch: `1 test files, 14 assertions, 2 failures`
  - Failure: accepted stream fell back to Info-only metadata and rejected-stream summary omitted nested resource target fields.
- Fixed focused run:
  - `php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpNestedResourceReferenceBoundaryCurrentBaseTest.php`
  - Result: `1 test files, 50 assertions, 0 failures`
- Adjacent XMP reference run:
  - `php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpResourceReferenceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataXmpNodeIdBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataXmpNestedResourceReferenceBoundaryCurrentBaseTest.php`
  - Result: `3 test files, 145 assertions, 0 failures`
- Broader XMP resource/nodeID run:
  - `php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmp*Resource*CurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataXmp*NodeId*CurrentBaseTest.php`
  - Result: `6 test files, 276 assertions, 0 failures`
- WordPress smoke:
  - `php lanes/markerpdf/examples/wordpress-pdf-xmp-nested-resource-reference-boundary-currentbase.php`
  - Result: exits `0`; validates nested target promotion, nested RDF decoy exclusion, external reference exclusion, and visible-text XMP isolation.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PDF object parser, Flate stream decoder, DOM-based XMP parser, and no-GPU metadata conversion path. It does not invoke Python, OCR, Surya, Texify, Torch, raster rendering, external PDF tools, or live services.

## Non-Overlap

This does not repeat the accepted top-level XMP `rdf:resource` or top-level `rdf:nodeID` target behavior. The new boundary is nested target lookup within the same document-level RDF graph, with explicit protection against nested `rdf:RDF` decoys and non-metadata stream payload exposure.

## Next

Continue with non-overlapping native searchable-PDF metadata/parser work: XMP malformed packet boundaries not covered here, stream filter metadata, font/CMap edges, annotations/forms, xref repair, page geometry, or supplied-boundary table/equation handoffs.
