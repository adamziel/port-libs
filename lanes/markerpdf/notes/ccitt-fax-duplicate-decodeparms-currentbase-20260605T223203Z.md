# markerPDF CCITT Fax Duplicate DecodeParms Boundary

Session: `port-dev-markerpdf-ccitt-fax-filter-20260605T223203Z`
Micro-slice: `markerpdf-ccitt-fax-filter-boundary-current-base-20260605T223203Z`
Base accepted HEAD: `18c727ab4ba0278a130b5c84e0abdae51d11d874`

## Source Truth

Pinned upstream markerPDF keeps searchable PDF text extraction separate from image rendering: image payloads are handed to the `marker/pdf/images.py` rendering path while searchable text remains on the PDF text path. Under this no-GPU lane, native PHP records CCITT Fax image review metadata without rasterizing, invoking OCR/model code, using PDFium/PIL, or shelling out to external PDF tools.

For this slice, duplicate top-level CCITT Fax `/DecodeParms` parameters are treated as ambiguous. A dictionary such as `<< /K -1 /K 0 /Rows 1 /Rows 2 >>` must not silently choose one parameter value before WordPress image review.

## Behavior

`PdfTextExtractor` and `PdfImageRenderer` now fail closed when a CCITT Fax DecodeParms dictionary repeats any top-level CCITT parameter:

- `/K`
- `/Columns`
- `/Rows`
- `/BlackIs1`
- `/EncodedByteAlign`
- `/EndOfLine`
- `/EndOfBlock`
- `/DamagedRowsBeforeError`

The review metadata sets `valid_decode_parms=false`, records `duplicate_decode_parms_fields`, uses `decode_parms_review=duplicate_ccitt_decodeparms_parameter_fail_closed`, and keeps `native_raster_decode=false`. Effective geometry/coding fields fall back to the conservative fail-closed defaults, and image bytes remain excluded from visible WordPress text and review JSON.

## Red-First Probe

After adding the duplicate-parameter regression test and before the source change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php
1 test files, 669 assertions, 1 failures
```

The failure showed duplicate `/K` and `/BlackIs1` parameters were still treated as valid first values instead of ambiguous fail-closed DecodeParms metadata.

## Verification

Focused CCITT boundary gate after the fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php
1 test files, 708 assertions, 0 failures
```

Adjacent renderer/filter gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageRendererTest.php
3 test files, 1504 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-ccitt-fax-duplicate-decodeparms-currentbase.php
```

The smoke emitted `duplicate_ccitt_decodeparms_parameter_fail_closed`, `inline_duplicate_fields=["k","black_is_1"]`, `xobject_duplicate_fields=["k","rows"]`, `payload_in_visible_text=false`, `native_raster_decode=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Syntax and diff hygiene:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/src/PdfImageRenderer.php
php -l lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-ccitt-fax-duplicate-decodeparms-currentbase.php
git diff --check -- lanes/markerpdf
```

All syntax checks passed and `git diff --check -- lanes/markerpdf` produced no output.

Root harness: not run - isolated micro-slice.

## Status Delta

- Focused PASS cases: `2248 -> 2249`.
- WordPress scenarios: `1937 -> 1938`.
- Focused CCITT assertions after this slice: `708`.

## Non-Overlap

This does not repeat accepted CCITT image-only stream exclusion, invalid/unresolved DecodeParms fail-closed metadata, null filter slot alignment, compact DecodeParms alignment, escaped CCITT names, PDF comment handling in filter arrays or DecodeParms dictionaries, Flate/LZW/RunLength/Crypt prefix ownership, direct EOFB/RTC ownership, row EOL/Rows/Height ownership, inline markers, CCF aliases, ImageMask polarity, nested masks/alternates, invalid dimensions, post-CCITT filters, generic duplicate stream-owned `/Filter` or `/DecodeParms` keys, or generic duplicate predictor/Crypt `/Name` DecodeParms handling.

This bounded slice only covers duplicate CCITT-specific DecodeParms fields in the renderer and text-extractor image review paths.

## Dependency Closure

No new support component is needed. The patch reuses the native PHP PDF dictionary scanners, CCITT DecodeParms review builders, image renderer review path, text extractor image boundary review, and WordPress smoke renderer. Full CCITT raster decoding remains intentionally out of scope for this no-GPU worker.
