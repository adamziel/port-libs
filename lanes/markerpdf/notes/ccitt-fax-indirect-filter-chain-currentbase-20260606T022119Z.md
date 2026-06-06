# markerPDF CCITT Fax Indirect Filter Chain Boundary

Session: `port-dev-markerpdf-ccitt-fax-filter-20260606T022119Z`
Micro-slice: `markerpdf-ccitt-fax-filter-boundary-current-base-20260606T022119Z`
Base accepted HEAD: `2ae93de9974bfc7ff1d89adfc4f6b9b0a20b5b16`

## Source-Truth Boundary

Upstream `sddai/markerPDF` keeps PDF image rendering separate from searchable text extraction and delegates raster image work to image/PDF backends. Under this no-GPU PHP lane, CCITT Fax image streams stay review-only parser metadata: filter aliases, DecodeParms, geometry, coding, and ImageMask polarity are recorded, while CCITT payload bytes are not promoted into WordPress paragraph text.

PDF `/Filter` and `/DecodeParms` operands may be indirect objects. This slice covers the current-base boundary where both operands are chained through more than one indirect object before reaching `/CCF` and the CCITT DecodeParms dictionary. Cyclic chains remain unresolved and fail closed.

## Native Behavior Added

`PdfImageRenderer` now recursively resolves pure indirect-reference object bodies while preserving the existing seen-object cycle guard. Chained `/Filter 20 0 R -> 21 0 R -> /CCF` and `/DecodeParms 30 0 R -> 31 0 R -> << ... >>` operands now produce the same CCITT review-only metadata as direct operands.

The behavior is bounded to native PDF value resolution already used by image filter metadata. It does not rasterize CCITT bytes, run OCR, call Python, or use external PDF/image tools.

## Evidence

Red-first focused run after adding the regression and before the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL resolves chained indirect renderer CCITT Fax Filter and DecodeParms operands before RGB preview
Expected: array (
  0 => 'CCF',
)
Actual: array (
  0 => 'UnresolvedFilterOperand',
)
1 test files, 778 assertions, 1 failures
```

Focused run after the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS resolves chained indirect renderer CCITT Fax Filter and DecodeParms operands before RGB preview
1 test files, 794 assertions, 0 failures
```

Adjacent image/filter family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageRendererTest.php lanes/markerpdf/tests/PdfImageInlineFilterPaletteAlphaCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php
Focused test run: 5 selected test files (root lock skipped)
5 test files, 2400 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-ccitt-fax-indirect-filter-chain-currentbase.php
```

The smoke emits `chained_indirect_filter_resolved=true`, `chained_indirect_decodeparms_resolved=true`, `chained_indirect_ccitt_review_only=true`, `cyclic_filter_operand_fail_closed=true`, `payload_excluded_from_review=true`, `executes_python_or_models=false`, `executes_external_pdf_tools=false`, and `executes_pypdfium_or_pil=false`.

PHP lint:

```text
php -l lanes/markerpdf/src/PdfImageRenderer.php
php -l lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-ccitt-fax-indirect-filter-chain-currentbase.php
```

All three changed PHP files reported no syntax errors.

Root harness: not run - isolated micro-slice.

## Status Delta

- Focused PASS cases: `2336 -> 2337`.
- Focused tests tracked: `2053 -> 2054`.
- Focused assertions in `PdfCcittFaxFilterBoundaryCurrentBaseTest.php`: `777 -> 794`.
- WordPress scenarios: `2002 -> 2003`.

## Non-Overlap

This does not repeat accepted direct CCITT Filter/DecodeParms metadata, resolved malformed indirect DecodeParms classification, unresolved DecodeParms fail-closed handling, null filter DecodeParms alignment, escaped filter keys, duplicate DecodeParms fail-closed handling, invalid dimension fallback, direct EOFB/RTC ownership, row/EOL ownership, native prefix handling, DCT/JBIG2/JPX review-only image filters, inline image tokenizer boundaries, attachment stream-filter stacks, or generic stream filter decoding.

The bounded behavior here is specifically chained indirect image-renderer `/Filter` and `/DecodeParms` operand resolution for CCITT Fax review metadata, with cyclic operand chains still fail-closed.

## Dependency Closure

No new support component is needed. This reuses the native PDF object map, indirect-reference parser, image filter boundary planner, CCITT DecodeParms metadata, ImageMask polarity review, image XObject review, and WordPress smoke renderer. Full CCITT raster parity remains out of scope for this no-GPU lane and would require a future native raster backend or external image/PDF backend activation gate with separate evidence.
