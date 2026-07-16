# markerPDF CCITT Fax DecodeParms trailing-operand boundary current-base

Session: `port-dev-markerpdf-ccitt-fax-filter-20260608T053725Z`
Micro-slice: `markerpdf-ccitt-fax-filter-boundary-current-base-20260608T053725Z`
Base accepted HEAD: `4cdbc422e45adc25f1ad62ce24e13ad1c7bd277e`

## Source Truth

Upstream markerPDF keeps PDF image streams on the image-rendering path instead of promoting raster bytes to searchable text. In this no-GPU PHP lane, CCITTFaxDecode/CCF streams remain review-only until a native raster backend exists.

PDF stream dictionaries must still reject malformed top-level operands. A CCITT `/DecodeParms << ... >> << ... >>` value is not a valid single DecodeParms dictionary. WordPress import should expose that as fail-closed DecodeParms review metadata rather than accepting the first dictionary or misclassifying the second dictionary as a malformed `/Filter` operand.

## Implementation

- `PdfImageRenderer` now preserves direct and inline-image `/DecodeParms` dictionary tails long enough to classify them as malformed CCITT DecodeParms operands.
- `PdfTextExtractor` now applies the same boundary in page-resource image XObject review and lets `/Filter` scanning skip over a malformed `/DecodeParms` tail so ownership is attributed correctly.
- XObject, direct renderer, and inline-image paths all report `dictionary_with_trailing_operands` with `reject_top_level_decodeparms_dictionary_tail`.
- CCITT fax payload bytes stay out of visible text and review JSON.

## Evidence

Red-first focused run after adding the new test, before implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCcittFaxDecodeParmsTrailingOperandCurrentBaseTest.php
1 test files, 7 assertions, 3 failures
```

After implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCcittFaxDecodeParmsTrailingOperandCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 32 assertions, 0 failures
```

Adjacent CCITT gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCcittFaxDecodeParmsTrailingOperandCurrentBaseTest.php lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php
Focused test run: 2 selected test files (root lock skipped)
2 test files, 1208 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-ccitt-fax-decodeparms-trailing-operand-currentbase.php
```

The smoke exits 0 and emits `xobject_decode_parms_operand_detail=dictionary_with_trailing_operands`, `xobject_decode_parms_policy=reject_top_level_decodeparms_dictionary_tail`, `inline_decode_parms_operand_detail=dictionary_with_trailing_operands`, `xobject_payload_in_visible_text=false`, `inline_payload_excluded_from_text=true`, `executes_python_or_models=false`, `executes_external_pdf_tools=false`, and `executes_pypdfium_or_pil=false`.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted CCITT image-only stream exclusion, null-filter DecodeParms alignment, invalid field validation, unresolved/malformed scalar operands, duplicate DecodeParms declarations or parameters, indirect array tails, escaped DecodeParms keys, ImageMask polarity, nested mask/alternate review, Flate/LZW/ASCII85/Crypt prefix ownership, direct EOFB/RTC/EOL ownership, or post-CCITT filter-stack reachability. The new behavior is only top-level trailing operands after a dictionary-valued CCITT DecodeParms operand.

## Dependency Closure

No new support component is needed. This reuses native PDF dictionary/value parsing, inline-image abbreviation expansion, image XObject review, and CCITT DecodeParms metadata builders. Full CCITT raster decoding remains intentionally out of scope without an explicitly approved native raster backend or external PDFium/PIL-style dependency; no Python, OCR, Surya/Texify/Torch, pypdfium/PIL, model worker, live service, or external PDF tool was run.
