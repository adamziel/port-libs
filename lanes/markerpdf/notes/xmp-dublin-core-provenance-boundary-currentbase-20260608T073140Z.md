# XMP Dublin Core Provenance Boundary Current Base

Slice: `markerpdf-xmp-metadata-boundary-current-base-20260608T073140Z`

Base accepted HEAD: `fc2ba34a75a980b11ada0190096c265328c8d9ce`

## Source Truth

Upstream `sddai/markerPDF` routes document import through searchable PDF metadata/text extraction before WordPress-ready output. Under the current no-GPU markerPDF scope, the native PHP boundary owns catalog `/Metadata` XMP streams without invoking PDFium, OCR, Surya/Texify/Torch, raster renderers, or external PDF tools.

PDF XMP metadata is RDF/XML. Accepted catalog `/Metadata` packets may carry Dublin Core provenance properties that are useful for WordPress import review, deduplication, source attribution, and migration audit trails: `dc:identifier`, `dc:publisher`, `dc:contributor`, `dc:relation`, `dc:source`, `dc:type`, and `dc:coverage`. Those values are metadata, not visible page text.

## Implementation

- `PdfMetadataExtractor` now parses those Dublin Core provenance fields from the accepted document-level XMP packet.
- Values are preserved in the root `xmp` array and in review-only `xmp_dublin_core` metadata with counts.
- The parser uses a text-preserving list helper for identifiers/source/relation fields so DOI/URN/source strings are not split like keyword text.
- Rejected XML stream summaries now mark `dublin_core` as a redacted field when provenance fields exist, proving values were detected without exposing payload text.
- Private RDF resource descriptions and trailing XMP packets remain excluded.

## Red/Green Evidence

Pre-fix focused command:

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpDublinCoreProvenanceBoundaryCurrentBaseTest.php
```

Result before source fix:

```text
1 test files, 18 assertions, 2 failures
```

Failure summary:

- accepted XMP `identifiers` were `NULL`;
- rejected XML-stream summary did not report/redact `dublin_core`.

Post-fix focused command:

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpDublinCoreProvenanceBoundaryCurrentBaseTest.php
```

Result:

```text
1 test files, 57 assertions, 0 failures
```

Adjacent XMP family command:

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmp*CurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php
```

Result:

```text
62 test files, 3746 assertions, 0 failures
```

WordPress smoke:

```sh
php lanes/markerpdf/examples/wordpress-pdf-xmp-dublin-core-provenance-currentbase.php
```

Result: exits 0 and emits `identifier_count=2`, `publisher_count=1`, `contributor_count=2`, `review_only=true`, `private_decoy_excluded=true`, `trailing_decoy_excluded=true`, `visible_text_excludes_xmp_provenance=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted XMP catalog `/Metadata` trust-boundary work around null/duplicate/direct/unresolved metadata operands, stream role checks, XMP packet begin/end selection, UTF-16/declared encoding, entity/DTD rejection, empty/self-closing roots, attributes, list membership, resource references, nodeID references, parseType collections, media-management identifiers, PDF/A schemas, encrypted metadata priority, xref metadata selection, page-resource inheritance, fonts/CMaps, stream filters, annotations, forms, images, outlines, OCR, or model execution.

The bounded new behavior is specifically standard Dublin Core provenance properties inside an already accepted document XMP packet.

## Dependency Closure

No new support component is needed. This reuses native PHP XML/XMP parsing, PDF stream decoding, metadata review summaries, and the WordPress smoke path. GPU/model execution, OCR, PDFium/PIL rendering, external PDF tools, JavaScript/action execution, and live service/provider tests remain intentionally out of scope.

Root harness: not run - isolated micro-slice.

## Next Task

Continue native no-GPU markerPDF work on non-overlapping searchable-PDF parser behavior around fonts, CMaps, stream filters, xref repair, metadata, outlines, annotations, forms, page geometry, image/filter metadata, or supplied-boundary table/equation handoffs.
