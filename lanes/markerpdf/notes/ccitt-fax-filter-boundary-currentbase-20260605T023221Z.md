# markerPDF CCITT Fax Escaped DecodeParms Boundary

Session: `port-dev-markerpdf-ccitt-fax-filter-20260605T023221Z`
Micro-slice: `markerpdf-ccitt-fax-filter-boundary-current-base-20260605T023221Z`
Base accepted HEAD: `c4c15d3f3174e9c5bf26a4d74cccf2a2951fff52`

## Source-Truth Boundary

Upstream `sddai/markerPDF` at the manifest-pinned commit keeps searchable PDF text on the pdftext/PDFium text-page path and routes image pixels through `marker/pdf/images.py::render_image()` before RGB handoff. Under the current no-GPU lane scope, native PHP still does not decode CCITT Fax raster bytes, but it must preserve parser-side image filter metadata accurately before WordPress import review.

PDF names can be escaped with `#hh` bytes, including dictionary keys. CCITT `/DecodeParms` such as `/K`, `/Columns`, `/Rows`, `/BlackIs1`, `/EncodedByteAlign`, `/EndOfLine`, `/EndOfBlock`, and `/DamagedRowsBeforeError` must therefore be read as decoded top-level dictionary names. Nested decoy dictionaries and PDF-looking text inside inline image payloads are not parameter sources.

## Native Behavior Added

`PdfImageRenderer` now reads image DecodeParms integers/booleans through its tokenized dictionary reader instead of regex matching raw key bytes.

This maps inline `/CCITTFaxDecode` and `/CCF` renderer metadata where:

- escaped keys such as `/#4B`, `/Colu#6Dns`, `/Ro#77s`, `/Black#49s1`, and `/EndOf#42lock` are decoded before lookup;
- nested dictionaries like `/Decoy << /Columns 4 /Rows 1 /EndOfBlock true >>` are ignored for top-level CCITT parameters;
- effective CCITT geometry and mismatch metadata stay review-only;
- fax payload bytes remain excluded from visible WordPress paragraphs and review JSON;
- `native_raster_decode=false` remains unchanged.

## Evidence

Existing focused baseline before the new test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 131 assertions, 0 failures
```

Red-first focused run after adding the escaped DecodeParms case, before the renderer fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL resolves escaped CCITT Fax DecodeParms keys while ignoring nested decoys before RGB preview
Expected k=-1, columns=32, rows=3, black_is_1=true, end_of_block=false.
Actual k=NULL, columns=4, rows=1, black_is_1=false, end_of_block=true.
1 test files, 133 assertions, 1 failures
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
PASS keeps Flate-wrapped CCITT Fax endstream decoys inside image payload boundaries
PASS uses direct CCITT EOFB and RTC markers before accepting fake endstream owners
PASS records effective CCITT Fax DecodeParms defaults and geometry boundaries before RGB preview

1 test files, 139 assertions, 0 failures
```

Adjacent image/filter gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageRendererTest.php lanes/markerpdf/tests/PdfImageInlineFilterPaletteAlphaCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php
Focused test run: 5 selected test files (root lock skipped)
5 test files, 902 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-ccitt-fax-filter-import.php
```

The smoke emits `inline_escaped_decode_parms={"k":-1,"columns":32,"rows":3,"black_is_1":true,"encoded_byte_align":true,"end_of_line":true,"end_of_block":false,"damaged_rows_before_error":5}`, `inline_escaped_decode_parms_nested_decoys_ignored=true`, `inline_escaped_payload_excluded_from_review=true`, visible paragraphs `CCITT Boundary` and `Native Import`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Status Delta

- Focused PASS cases: `1302 -> 1303`.
- Focused assertions in `PdfCcittFaxFilterBoundaryCurrentBaseTest.php`: `131 -> 139`.
- WordPress scenarios: `1259 -> 1260`.

## Non-Overlap

This does not repeat accepted CCITT image-only stream exclusion, raw DecodeParms extraction, malformed DecodeParms fail-closed metadata, effective DecodeParms geometry metadata, inline CCITT review-only notes, inline invalid DecodeParms review, Flate-prefix CCITT boundary recovery, direct EOFB/RTC ownership, DCT/JPX/JBIG2 preview-only image filters, or generic inline image payload exclusion. The new bounded behavior is specifically escaped-name and nested-decoy-safe CCITT DecodeParms lookup in the native image renderer.

## Dependency Closure

No new support component is needed. This reuses the native PDF inline-image dictionary expander, tokenized dictionary value reader, image filter metadata planner, CCITT DecodeParms review builder, and WordPress smoke renderer. Full CCITT raster parity remains gated on PDFium/PIL or a future native raster backend; no Python, OCR, model, pypdfium, PIL, external PDF tool, or live-service provider execution was run.
