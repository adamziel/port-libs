# markerPDF XMP media-management metadata boundary

Micro-slice: `markerpdf-xmp-metadata-boundary-current-base-20260606T055726Z`

Accepted base: `cf7ad8dedfdead64d21e5ec92010b21088cacf79`

## Source Truth

- Adobe XMP Media Management uses the `http://ns.adobe.com/xap/1.0/mm/` namespace for document identity properties such as `xmpMM:DocumentID`, `xmpMM:InstanceID`, `xmpMM:OriginalDocumentID`, and resource references such as `xmpMM:DerivedFrom`.
- In this native no-GPU markerPDF lane, catalog `/Metadata` XMP streams are document metadata/review artifacts. XMP packet payload and private RDF resource decoys must not become visible WordPress paragraph text.

## Implementation

- `PdfMetadataExtractor` now extracts document-level XMP media-management identifiers into `xmp.media_management` and mirrors them at top-level `xmp_media_management` for import review.
- `xmpMM:DerivedFrom` resource references are resolved only within the active XMP packet and summarized as review-only provenance using `stRef:documentID`, `stRef:instanceID`, and `stRef:originalDocumentID`.
- Private/non-document RDF resources remain excluded from document metadata selection.
- Rejected non-document XML metadata streams report only media-management field names and derived-reference presence in `xmp_summary`; identifier values remain redacted.

## Red-First Evidence

Initial focused run before source changes:

`php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpMediaManagementBoundaryCurrentBaseTest.php`

Result: failed as expected because `xmp_media_management` was absent and rejected-stream `xmp_summary.field_names` omitted `media_management`.

## Verification

Focused new test:

`php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpMediaManagementBoundaryCurrentBaseTest.php`

Result: `1 test files, 44 assertions, 0 failures`.

Focused XMP current-base family:

`php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmp*CurrentBaseTest.php`

Result: `38 test files, 1694 assertions, 0 failures`.

Adjacent metadata extractor check:

`php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php lanes/markerpdf/tests/PdfMetadataXmpMediaManagementBoundaryCurrentBaseTest.php`

Result: `2 test files, 906 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-xmp-media-management-boundary-currentbase.php`

Emits `document_id_preserved=true`, `instance_id_preserved=true`, `derived_from_preserved=true`, `packet_boundary_applied=true`, `private_resource_decoy_excluded=true`, `trailing_packet_decoy_excluded=true`, `visible_text_excludes_xmp_ids=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Syntax:

- `php -l lanes/markerpdf/src/PdfMetadataExtractor.php` passed.
- `php -l lanes/markerpdf/tests/PdfMetadataXmpMediaManagementBoundaryCurrentBaseTest.php` passed.
- `php -l lanes/markerpdf/examples/wordpress-pdf-xmp-media-management-boundary-currentbase.php` passed.

## Non-Overlap

This does not repeat accepted XMP packet begin/end priority, trailing packet padding, UTF-16/declared encoding, DTD/entity fail-closed, namespace wrapper selection, typed RDF nodes, RDF list membership, resource-reference scalar values, PDF/A extension schemas, FileSpec/PieceInfo associated XMP provenance, encrypted metadata priority, or catalog `/Metadata` operand/filter boundaries. The bounded behavior is document-level XMP Media Management identity/provenance metadata and rejected-stream redaction.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object parser, stream decoder, XMP packet/root selector, DOM-based XMP value extraction helpers, text extractor, and WordPress smoke pattern. Live OCR, Surya/Texify/Torch model execution, PDFium rendering, signature validation, and external PDF tools remain intentionally out of scope for this no-GPU markerPDF slice.

## Next

Continue native no-GPU markerPDF work on non-overlapping searchable-PDF metadata, font/CMap, stream-filter, xref repair, annotation/form/security, page geometry, image/filter metadata, and supplied-boundary table/equation handoff gaps.
