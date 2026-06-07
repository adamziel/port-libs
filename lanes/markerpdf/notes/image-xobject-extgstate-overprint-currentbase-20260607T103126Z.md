# markerPDF Image XObject ExtGState Overprint Boundary Current Base

Session: `port-dev-markerpdf-image-xobject-20260607T103126Z`
Micro-slice: `markerpdf-image-xobject-boundary-current-base-20260607T103126Z`
Base accepted HEAD: `f621e81917015d64a089d0c0844fa389408ad093`

## Source Truth

Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` keeps searchable text extraction separate from image rendering. Text is extracted through the PDF text path while image rendering state belongs to the `marker.pdf.images.render_image` handoff.

For this no-GPU native PHP lane, ExtGState overprint controls are review-only rendering metadata. `/OP`, `/op`, and `/OPM` should follow the graphics state into Image XObject invocation review, while raster payload bytes stay out of WordPress paragraphs and no external renderer or model runs.

## Behavior

`PdfTextExtractor` now carries ExtGState overprint metadata through image invocation graphics state:

- `/OP` is exposed as `stroking_overprint`;
- `/op` is exposed as `nonstroking_overprint`;
- `/OPM` is exposed as `overprint_mode`;
- the fields compose with existing alpha, blend-mode, soft-mask, CTM, and bbox review metadata;
- payload bytes remain excluded from visible text and review JSON.

## Red First

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectExtGStateOverprintCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL records ExtGState overprint controls before Image XObject review (lanes/markerpdf/tests/PdfImageXObjectExtGStateOverprintCurrentBaseTest.php)
Values are not identical
Expected: true
Actual: NULL

1 test files, 12 assertions, 1 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectExtGStateOverprintCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS records ExtGState overprint controls before Image XObject review

1 test files, 37 assertions, 0 failures
```

Adjacent image-XObject gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectExtGStateOverprintCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectExtGStateSoftMaskNoneCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php
Focused test run: 3 selected test files (root lock skipped)
...
3 test files, 1323 assertions, 0 failures
```

Full Image XObject focused family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObject*Test.php
Focused test run: 24 selected test files (root lock skipped)
...
24 test files, 2179 assertions, 0 failures
```

Syntax checks:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfTextExtractor.php

php -l lanes/markerpdf/tests/PdfImageXObjectExtGStateOverprintCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfImageXObjectExtGStateOverprintCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-pdf-image-xobject-extgstate-overprint-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-image-xobject-extgstate-overprint-currentbase.php
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-image-xobject-extgstate-overprint-currentbase.php
```

The smoke exits 0 and emits `spot_stroking_overprint=true`, `spot_nonstroking_overprint=true`, `spot_overprint_mode=1`, `process_stroking_overprint=false`, `process_nonstroking_overprint=false`, `process_overprint_mode=0`, `payload_in_visible_text=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Whitespace check:

```text
git diff --check -- lanes/markerpdf
```

The check exits 0 with no output.

Root harness status: not run - isolated micro-slice.

## Status Delta

- Adds 1 focused PHP PASS case and 37 focused assertions.
- Adds 1 WordPress smoke/example.
- Expected lane-status movement: `phpPass` 2836 -> 2837 and `wordpressScenarios` 2380 -> 2381.
- Upstream denominator unchanged; this refines the already mapped Image XObject render/text boundary.

## Non-Overlap

This does not repeat accepted Image XObject payload exclusion, CTM placement, malformed `cm`/`Do` operands, optional content, artifacts, clipping/page geometry, alpha paint suppression, ExtGState `/SMask /None`, image dictionary SMask/Mask/Decode/filter metadata, ImageMask paint-color review, pattern image paints, Type3 CharProc image review, OPI metadata, encrypted fail-closed review, inline-image tokenization, OCR, model execution, or raster rendering.

The bounded behavior is only ExtGState overprint controls at Image XObject invocation review.

## Dependency Closure

No new support component is needed. This reuses the native PDF object scanner, page resource parser, ExtGState review path, content-stream tokenizer, graphics-state q/Q handling, Image XObject review rows, stream decoder, and WordPress smoke path. Full live OCR/model/raster parity remains intentionally out of scope under the no-GPU markerPDF directive and remains gated on pdftext/PDFium/pypdfium2/PIL, Surya/Torch, tabled-pdf, Texify, runtime app/server workers, benchmark/model downloads, and external OCR/rendering helpers.
