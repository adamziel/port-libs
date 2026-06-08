# markerPDF Image XObject ExtGState Transfer Function Boundary Current Base

Session: `port-dev-markerpdf-image-xobject-20260608T151548Z`
Micro-slice: `markerpdf-image-xobject-boundary-current-base-20260608T151548Z`
Base accepted HEAD: `9b7dedf8f156ee7a192d9054f47ee79347ca34c8`

## Source Truth

Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` keeps searchable PDF text extraction separate from image rendering. Text is extracted through the PDF text path while image rendering state belongs to the `marker.pdf.images.render_image` handoff.

For this no-GPU native PHP lane, ExtGState `/TR` and `/TR2` transfer functions are review-only rendering metadata. They should follow `gs` graphics-state application into Image XObject invocation review, while raster payload bytes stay out of WordPress paragraphs and no external renderer or model runs.

## Behavior

`PdfTextExtractor` now carries top-level ExtGState transfer-function metadata through image invocation graphics state:

- `/TR2 /Identity` is exposed as a named transfer-function review row;
- `/TR 22 0 R` records the exact referenced function object and generation;
- referenced function dictionaries expose bounded `/FunctionType`, `/Domain`, and `/Range` metadata;
- transfer metadata composes with existing alpha, blend-mode, overprint, soft-mask, CTM, and bbox review metadata;
- payload bytes remain excluded from visible text and review JSON.

## Red First

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectExtGStateTransferFunctionCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL records ExtGState transfer functions before Image XObject review (lanes/markerpdf/tests/PdfImageXObjectExtGStateTransferFunctionCurrentBaseTest.php)
Values are not identical
Expected: 'TR2'
Actual: NULL

1 test files, 14 assertions, 1 failures
```

## Verification

Focused after the fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectExtGStateTransferFunctionCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS records ExtGState transfer functions before Image XObject review

1 test files, 51 assertions, 0 failures
```

Adjacent image-XObject ExtGState gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectExtGStateTransferFunctionCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectExtGStateOverprintCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectExtGStateSoftMaskNoneCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php
Focused test run: 4 selected test files (root lock skipped)
...
4 test files, 1374 assertions, 0 failures
```

Full Image XObject current-base family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObject*CurrentBaseTest.php
Focused test run: 41 selected test files (root lock skipped)
...
41 test files, 3026 assertions, 0 failures
```

Syntax checks:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfTextExtractor.php

php -l lanes/markerpdf/tests/PdfImageXObjectExtGStateTransferFunctionCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfImageXObjectExtGStateTransferFunctionCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-pdf-image-xobject-transfer-function-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-image-xobject-transfer-function-currentbase.php
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-image-xobject-transfer-function-currentbase.php
```

The smoke exits 0 and emits `identity_transfer_function="Identity"`, `function_transfer_object=22`, `function_type=2`, `function_domain=[0,1]`, `function_range=[0,1]`, `payload_in_visible_text=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Whitespace check:

```text
git diff --check -- lanes/markerpdf
```

The check exits 0 with no output.

Root harness status: not run - isolated micro-slice.

## Status Delta

- Adds 1 focused PHP PASS case and 51 focused assertions.
- Adds 1 WordPress smoke/example.
- Expected lane-status movement: `phpPass` 3215 -> 3216 and `wordpressScenarios` 2633 -> 2634.
- Upstream denominator unchanged; this refines the already mapped Image XObject render/text boundary.

## Non-Overlap

This does not repeat accepted Image XObject payload exclusion, CTM placement, malformed `cm`/`Do` operands, optional content, artifacts, clipping/page geometry, alpha paint suppression, ExtGState overprint, ExtGState `/SMask /None`, soft-mask dictionary `/TR`, image dictionary SMask/Mask/Decode/filter metadata, ImageMask paint-color review, pattern image paints, Type3 CharProc image review, OPI metadata, encrypted fail-closed review, inline-image tokenization, OCR, model execution, or raster rendering.

The bounded behavior is only top-level ExtGState `/TR` and `/TR2` transfer-function metadata at Image XObject invocation review.

## Dependency Closure

No new support component is needed. This reuses the native PDF object scanner, page resource parser, ExtGState review path, content-stream tokenizer, graphics-state q/Q and `gs` handling, Image XObject review rows, stream decoder, and WordPress smoke path. Full live OCR/model/raster parity remains intentionally out of scope under the no-GPU markerPDF directive and remains gated on pdftext/PDFium/pypdfium2/PIL, Surya/Torch, tabled-pdf, Texify, runtime app/server workers, benchmark/model downloads, and external OCR/rendering helpers.
