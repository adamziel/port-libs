# markerPDF CCITT Fax Unresolved DecodeParms Boundary

Session: `port-dev-markerpdf-ccitt-fax-filter-20260605T055622Z`
Micro-slice: `markerpdf-ccitt-fax-filter-boundary-current-base-20260605T055622Z`
Base accepted HEAD: `faa78576a7e937b1a3569a086f4da2a3cae63756`

## Source-Truth Boundary

Upstream `sddai/markerPDF` keeps searchable PDF text extraction separate from
raster image rendering. Under the no-GPU native PHP lane scope, CCITT Fax image
payloads remain review-only and must not be emitted as WordPress paragraph text,
but the parser should still report declared image-filter boundaries honestly.

An explicit CCITT `/DecodeParms` operand that cannot be resolved to a
dictionary is not equivalent to an omitted `/DecodeParms`. For
`/Filter /CCITTFaxDecode /DecodeParms 99 0 R`, the native review path now marks
the parameter operand fail-closed instead of silently applying default fax
parameters as if no operand existed.

## Native Behavior Added

`PdfTextExtractor::extractImageXObjectBoundaryReview()` now preserves the fact
that an Image XObject dictionary contained `/DecodeParms` even when the current
object scanner cannot resolve the operand to a dictionary. CCITT/CCF filter rows
now report:

- `valid_decode_parms=false`
- `invalid_decode_parms_fields=["decode_parms_operand"]`
- `decode_parms_review=unresolved_ccitt_decodeparms_fail_closed`
- `decode_parms_operand=unresolved_reference`

`PdfImageRenderer::inlineImageReviewPlan()` now uses the same fail-closed shape
for inline CCITT `/DP 99 0 R` operands. Ordinary omitted or explicit-null
DecodeParms still keep the existing default effective CCITT geometry, and
non-CCITT filters keep their prior non-dictionary behavior.

CCITT raster decode remains disabled in this lane:
`native_raster_decode=false`, `decoded_with_current_filters=false`, and
`payload_in_visible_text=false`.

## Evidence

Current-base probe before the source edit:

```text
XObject /CCITTFaxDecode /DecodeParms 99 0 R reported decode_parms=null,
ccitt_fax_decode_boundary.decode_parms_present=false, and default effective
CCITT parameters.

Inline /F /CCF /DP 99 0 R reported decode_parms=null and default effective
CCITT parameters.
```

Focused gate after the fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 236 assertions, 0 failures
```

Adjacent image/filter gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageRendererTest.php lanes/markerpdf/tests/PdfImageInlineFilterPaletteAlphaCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
Focused test run: 7 selected test files (root lock skipped)
7 test files, 1983 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-ccitt-fax-filter-import.php
```

The smoke emits `inline_unresolved_decode_parms_valid=false`,
`inline_unresolved_decode_parms_review=unresolved_ccitt_decodeparms_fail_closed`,
`inline_unresolved_decode_parms_operand=unresolved_reference`,
`xobject_unresolved_decode_parms_valid=false`,
`xobject_unresolved_decode_parms_review=unresolved_ccitt_decodeparms_fail_closed`,
`xobject_unresolved_decode_parms_operand=unresolved_reference`,
`xobject_unresolved_payload_excluded_from_text=true`,
`executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Status Delta

- Focused PASS cases: `1494 -> 1495`.
- Focused assertions in `PdfCcittFaxFilterBoundaryCurrentBaseTest.php`: `207 -> 236`.
- WordPress scenarios: `1400 -> 1401`.
- Manifest current-base CCITT boundary behaviors: `1 -> 2`.

## Non-Overlap

This does not repeat accepted CCITT image-only stream exclusion, direct or
indirect valid DecodeParms extraction, malformed dictionary fail-closed field
validation, null-filter/compact DecodeParms alignment, escaped-key lookup,
nested image CCITT review, Identity/Crypt boundaries, DCT/JPX/JBIG2
preview-only image filters, or generic inline image payload exclusion. The new
bounded behavior is specifically explicit unresolved CCITT `/DecodeParms`
operand handling for Image XObject review and inline image preview planning.

## Dependency Closure

No new support component is needed. This reuses the native PDF dictionary
scanner, filter resolver, DecodeParms review builders, Image XObject boundary
review path, inline image review planner, and WordPress smoke renderer. Full
CCITT raster parity remains gated on PDFium/PIL or a future native raster
backend; no Python, OCR, model, pypdfium, PIL, external PDF tool, live-service
provider, or GPU execution was run.
