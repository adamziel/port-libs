# XMP Undefined Entity Boundary Current Base - 2026-06-08

## Behavior

Catalog `/Metadata` XMP streams with undefined XML entity references now fail closed as malformed XMP XML before document metadata promotion. The review path records `rejected_malformed_document_xmp_xml` with an `xmp_summary` status of `rejected_malformed_xmp_xml`, keeps payload bytes and text values redacted, preserves Info metadata fallback, and does not promote a later valid trailing XMP packet.

Rejected XML-like non-metadata streams reuse the same `xmp_summary` so WordPress import review can explain the malformed XMP boundary without accepting the stream as document metadata.

## Source Truth

The native PHP extractor treats XMP packet/root selection as the source boundary and then uses strict `DOMDocument::loadXML()` with `LIBXML_NONET` to verify well-formed XML before any `textContent`-based value extraction. DTD/entity declarations continue to be rejected by the existing unsafe-markup boundary first; this slice covers undefined entity references without declarations.

## Evidence

Red-first before extractor change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpUndefinedEntityBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 14 assertions, 2 failures
```

Focused after fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpUndefinedEntityBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS fails closed on undefined XMP entity references before trailing packet promotion
PASS summarizes undefined entities in rejected XML streams without metadata promotion
1 test files, 62 assertions, 0 failures
```

Adjacent XMP boundary family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpUndefinedEntityBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataXmpEntityBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataXmpUnsafePacketBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataXmpMalformedPacketBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataXmpMalformedEncodingBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataXmpPacketBoundaryCurrentBaseTest.php
6 test files, 298 assertions, 0 failures
```

Syntax and smoke:

```text
php -l lanes/markerpdf/src/PdfMetadataExtractor.php
php -l lanes/markerpdf/tests/PdfMetadataXmpUndefinedEntityBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-xmp-undefined-entity-boundary-currentbase.php
php lanes/markerpdf/examples/wordpress-pdf-xmp-undefined-entity-boundary-currentbase.php
```

The example emits `metadata_review_status=rejected_malformed_document_xmp_xml`, `xmp_summary_status=rejected_malformed_xmp_xml`, `packet_boundary_applied=true`, `undefined_entity_values_excluded=true`, `trailing_packet_not_promoted=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Status Delta

- `phpPass`: `3576 -> 3578` for the two new focused PASS cases.
- `wordpressScenarios`: `2888 -> 2889` for the new undefined-entity WordPress smoke.
- Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. The slice reuses native PHP stream decoding, XMP packet/root boundary helpers, `DOMDocument`, and libxml internal error capture. It does not execute Python, OCR, GPU/model code, or external PDF tools.

## Non-Overlap

This does not repeat accepted XMP entity-declaration, unsafe packet, malformed packet root, malformed UTF-16 encoding, packet padding, or appended decoy coverage. It covers undefined XML entity references inside an otherwise bounded active packet where strict DOM parsing fails.

## Next Task

Continue native no-GPU markerPDF metadata coverage around non-overlapping XMP/catalog boundaries, PDF/A extension schema review, or object/filter metadata that affects WordPress import fidelity.
