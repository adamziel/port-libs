# markerPDF metadata XMP OutputIntent associated files current-base

Micro-slice: `metadata-xmp-outputintent-associated-files-currentbase-20260602T174411Z`

Session: `port-dev-markerpdf-meta35pdf-20260602T174411Z`

Accepted base: `252c505983bfd6b8ea68d7f5271483812ad199ee`

## Source Truth

- `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json` pins upstream `sddai/markerPDF` at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`. The upstream cache path named by the manifest is not present in this isolated worktree, so this slice uses the current lane manifest, accepted metadata/xref notes, and focused PHP fixtures as source-truth evidence.
- Upstream markerPDF routes document conversion through Python/PDF parser dependencies before metadata is emitted beside converted output. The native PHP lane must therefore preserve PDF parser object ownership before WordPress metadata and visible text are produced.
- PDF parser behavior for this slice: the latest `startxref` cross-reference table or stream rows select the live direct object body by byte offset and generation. Stale duplicate direct objects appended after the current EOF must not override current catalog `/Metadata`, `/Info`, root `/OutputIntents`, catalog `/AF` FileSpec rows, associated embedded streams, or page content.

## Implemented Behavior

- `PdfMetadataExtractor::pdfObjects()` now builds direct object definitions with object-generation-offset metadata, then prefers the latest `startxref` xref table/stream chain for live direct object selection.
- The new bounded xref reader handles table rows, xref streams with `/W`, `/Index`, `/Size`, `/Prev`, and hybrid table `/XRefStm` references for direct objects.
- Metadata extraction now uses current xref-selected object bodies for catalog XMP, trailer `/Info`, root PDF/A OutputIntents, catalog associated FileSpec review metadata, associated embedded payload hashes/checksums, FileSpec-local XMP review, nested OutputIntents, and visible page text fixture boundaries.
- Object-stream expansion remains out of scope for this slice; compressed metadata members continue to rely on existing parser behavior.

## Red Baseline

Before the source repair, the new focused fixture failed because stale duplicate objects appended after `%%EOF` overrode the current xref-selected catalog metadata:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php
FAIL uses current xref-selected catalog metadata OutputIntents and associated files
Expected: 'Current XRef Catalog Title'
Actual: 'Stale Catalog XMP Title'
1 test files, 600 assertions, 1 failures
```

## Focused Evidence

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php
1 test files, 633 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-metadata-current-xref-outputintent-associatedfile-review-currentbase.php
source:["xmp","info","catalog","output_intents"]
title:"Current XRef WordPress Packet"
language:"en-US"
pdfa_identifiers:["Current XRef sRGB"]
associated_file_count:1
associated_filename:"current-xref.xml"
associated_relationship:"Source"
associated_outputintent_identifier:"Current Attachment sRGB"
stale_duplicates_excluded:true
associated_payload_content_omitted:true
visible_text:"Current XRef Metadata Import Body"
```

Required local checks passed after the implementation:

```text
php -l lanes/markerpdf/src/PdfMetadataExtractor.php
php -l lanes/markerpdf/tests/PdfMetadataExtractorTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-metadata-current-xref-outputintent-associatedfile-review-currentbase.php
php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/markerpdf
```

Root harness status: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `609 -> 610`.
- `wordpressScenarios`: `609 -> 610`.
- Focused metadata gate now passes `1 test files, 633 assertions, 0 failures`.

## Non-Overlap

This does not repeat accepted catalog PDF/A OutputIntent extraction, OutputIntent-associated FileSpec review, catalog `/AF` without collection, Portfolio `/Collection`, PieceInfo private Metadata/OutputIntents, encrypted metadata priority, xref-stream trailer `/Root`/`Info`/`ID` precedence, page `/AF`, EmbeddedFiles name-tree extraction, or parser stream-owner object-boundary repairs.

The new behavior is specifically current xref-selected direct object ownership for metadata: stale duplicate direct objects after the current EOF cannot override current catalog XMP, `/Info`, root OutputIntents, catalog associated FileSpec review rows, or the current visible page stream used by WordPress import.

## Dependency Closure

No new support component is needed. This reuses the native PHP direct object scanner, trailer/xref stream helpers, top-level PDF dictionary/value parsing, stream decoder, XMP parser, OutputIntent review helpers, embedded-file Params checksum review, and WordPress smoke path.

Full upstream markerPDF parity remains dependency-gated by `pdftext`, `pypdfium2`/PDFium, Surya/Torch models, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, OCR/raster helpers, and external rendering/validation tooling.

## Next Task

Continue with non-overlapping metadata/parser gaps such as compressed object-stream metadata member selection or malformed xref-stream recovery boundaries; do not repeat current direct-object xref selection for catalog XMP, root OutputIntents, or catalog associated FileSpec review.
