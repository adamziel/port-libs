# markerPDF XObject CCITT Fax Compact DecodeParms Boundary

Session: `port-dev-markerpdf-ccitt-fax-filter-20260605T033902Z`
Micro-slice: `markerpdf-ccitt-fax-filter-boundary-current-base-20260605T033902Z`
Base accepted HEAD: `41ce8a579c8dc3a90d848469e642c8f81e2ca69b`

## Source-Truth Boundary

Upstream `sddai/markerPDF` at the manifest-pinned commit keeps searchable text extraction on the pdftext/PDFium text-page path and routes image pixels through `marker/pdf/images.py::render_image()`. Under the current no-GPU native PHP lane scope, CCITT Fax raster bytes stay review-only, but image-filter metadata must remain accurate before WordPress import review.

PDF stream filter arrays can contain `null` entries, and some producers compact `/DecodeParms` arrays to align with only non-null filters. For a filter stack such as `/Filter [null /ASCIIHexDecode /CCF] /DecodeParms [null << /K -1 /Columns 24 ... >>]`, the CCITT dictionary belongs to the non-null `/CCF` filter, not to the preceding ASCIIHex filter.

## Native Behavior Added

`PdfTextExtractor::imageXObjectFilterDetails()` now uses the existing `decodeParmsForFilterIndex()` helper for XObject review rows. This preserves compact/null-slot DecodeParms alignment for image XObjects the same way the stream decoder and image renderer already do.

This maps WordPress import boundaries where:

- the non-null filter stack remains `["ASCIIHexDecode", "CCF"]`;
- ASCIIHexDecode has no DecodeParms metadata;
- CCF receives the CCITT `/K`, `/Columns`, `/Rows`, `/BlackIs1`, `/EncodedByteAlign`, `/EndOfLine`, `/EndOfBlock`, and `/DamagedRowsBeforeError` values;
- effective CCITT geometry remains review metadata only;
- fax payload bytes stay out of visible text and review JSON;
- `native_raster_decode=false` remains unchanged.

## Evidence

Focused baseline after adding the new assertion, before the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL aligns XObject CCITT Fax DecodeParms arrays after null filter entries before WordPress review
Actual: ASCIIHexDecode decode_parms={"type":"ASCIIHexDecode"} and CCF decode_parms=NULL
1 test files, 154 assertions, 1 failures
```

Focused gate after the fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS records CCITT Fax image DecodeParms without rasterizing or leaking payload text
PASS marks malformed CCITT Fax DecodeParms fail closed without treating them as defaults
PASS marks inline CCITT Fax image filters review-only before WordPress image preview
PASS marks malformed inline CCITT Fax DecodeParms fail closed before RGB preview
PASS resolves escaped CCITT Fax DecodeParms keys while ignoring nested decoys before RGB preview
PASS aligns CCITT Fax DecodeParms arrays after null filter entries before RGB preview
PASS aligns XObject CCITT Fax DecodeParms arrays after null filter entries before WordPress review
PASS keeps Flate-wrapped CCITT Fax endstream decoys inside image payload boundaries
PASS uses direct CCITT EOFB and RTC markers before accepting fake endstream owners
PASS records effective CCITT Fax DecodeParms defaults and geometry boundaries before RGB preview

1 test files, 157 assertions, 0 failures
```

Adjacent image/filter gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageRendererTest.php lanes/markerpdf/tests/PdfImageInlineFilterPaletteAlphaCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php
Focused test run: 5 selected test files (root lock skipped)
5 test files, 986 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-ccitt-fax-filter-import.php
```

The smoke emits `xobject_compact_decode_parms_aligned=true`, `xobject_compact_payload_excluded_from_review=true`, `xobject_compact_payload_excluded_from_text=true`, visible paragraphs `CCITT Boundary` and `Native Import`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Finish checks:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfTextExtractor.php

php -l lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-pdf-ccitt-fax-filter-import.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-ccitt-fax-filter-import.php

php -r 'foreach (["lanes/markerpdf/lane-status.json", "lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true); if (json_last_error() !== JSON_ERROR_NONE) { fwrite(STDERR, $file . ": " . json_last_error_msg() . PHP_EOL); exit(1); } echo $file . " OK\n"; }'
lanes/markerpdf/lane-status.json OK
lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json OK

git diff --check -- lanes/markerpdf
passed with no output
```

Root harness: not run - isolated micro-slice.

## Status Delta

- Focused PASS cases: `1364 -> 1365`.
- Focused assertions in `PdfCcittFaxFilterBoundaryCurrentBaseTest.php`: `139 -> 157`.
- WordPress scenarios: `1306 -> 1307`.
- Manifest current-base CCITT boundary behaviors: `1 -> 2`.

## Non-Overlap

This does not repeat accepted CCITT image-only stream exclusion, raw DecodeParms extraction, malformed DecodeParms fail-closed metadata, effective DecodeParms geometry metadata, inline CCITT review-only notes, inline invalid DecodeParms review, inline null-filter DecodeParms alignment, escaped-name/nested-decoy DecodeParms lookup, Flate-prefix CCITT boundary recovery, direct EOFB/RTC ownership, DCT/JPX/JBIG2 preview-only image filters, or generic inline image payload exclusion. The new bounded behavior is specifically image XObject compact DecodeParms alignment after null Filter placeholders.

## Dependency Closure

No new support component is needed. This reuses the native PDF object parser, stream filter resolver, DecodeParms index helper, image XObject review path, and WordPress smoke renderer. Full CCITT raster parity remains gated on PDFium/PIL or a future native raster backend; no Python, OCR, model, pypdfium, PIL, external PDF tool, or live-service provider execution was run.
