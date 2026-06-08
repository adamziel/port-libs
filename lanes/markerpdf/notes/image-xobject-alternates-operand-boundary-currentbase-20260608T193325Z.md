# markerPDF Image XObject Alternates Operand Boundary Current Base

Session: `port-dev-markerpdf-image-xobject-20260608T193325Z`  
Micro-slice: `markerpdf-image-xobject-boundary-current-base-20260608T193325Z`  
Base accepted HEAD: `13ef792b9726ca74a5372ce5b45a701d4366670c`

## Source Truth

Pinned upstream markerPDF separates searchable text extraction from image rendering: `marker/pdf/extract_text.py` obtains text through pdftext/PDFium page text, while `marker/pdf/images.py` renders page imagery through PDFium and converts images to RGB.

Under the current no-GPU markerPDF scope, this PHP lane owns the native parser boundary before any future raster backend. PDF Image XObject `/Alternates` entries are alternate raster streams for media/rendering review; malformed `/Alternates` operands must not silently select hidden streams or leak payload bytes into WordPress text.

Upstream references:

- `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py`
- `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/images.py`

## Behavior

`PdfTextExtractor::imageXObjectAlternateImageReviews()` already handled direct, indirect, and wrapper alternate images. The remaining boundary was the `/Alternates` operand itself: the generic array resolver accepted the first array and ignored extra top-level operands.

This patch adds an Image XObject alternates operand review:

- direct `/Alternates [ ... ] 99 0 R` is rejected with `reason=trailing_top_level_operand`;
- indirect `/Alternates 20 0 R` where object `20 0` is `[ ... ] 99 0 R` is rejected with `reason=trailing_indirect_array_operand`;
- primary image XObject review remains present;
- valid sibling `/Alternates [<< /Image 10 0 R /DefaultForPrinting false >>]` remains preserved;
- rejected alternate payload hashes and bytes stay out of review metadata and visible WordPress paragraphs.

## Red First

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectAlternatesOperandBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL rejects tailed Image XObject Alternates operands before alternate stream review
Expected: 0
Actual: 1
1 test files, 6 assertions, 1 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectAlternatesOperandBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects tailed Image XObject Alternates operands before alternate stream review
1 test files, 45 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectAlternatesOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectIndirectAlternatesBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectAlternateWrapperBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfDctDecodeAlternateImageBoundaryCurrentBaseTest.php
Focused test run: 4 selected test files (root lock skipped)
4 test files, 112 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-image-xobject-alternates-operand-boundary-currentbase.php
```

The smoke exits `0` and emits `direct_tailed_alternates_rejected=true`, `indirect_tailed_alternates_rejected=true`, `valid_alternate_preserved=true`, `payload_excluded_from_text_and_review=true`, `executes_python_or_models=false`, `executes_external_pdf_tools=false`, and `executes_pypdfium_or_pil=false`.

Root harness status: not run - isolated micro-slice.

## Status Delta

- Focused behavior tests: `3433 -> 3434` pass / `0` fail.
- Focused assertion count for the new test: red-first `6` assertions / `1` failure, then `45` assertions / `0` failures.
- WordPress scenario count: `2789 -> 2790`.
- Mapped upstream denominator: unchanged; this refines the existing image XObject boundary behavior row.

## Non-Overlap

This does not repeat accepted direct `/Alternates` review, indirect `/Alternates` array resolution, alternate wrapper operands, DCT alternate-image EOI clipping, SMask/Mask/Metadata reference-tail rejection, OPI array operand boundaries, image numeric/top-level subtype boundaries, image XObject placement/CTM/clipping, optional-content image visibility, Form/pattern image traversal, Type3 CharProc image review, inline image tokenizer behavior, or raster/OCR/model execution.

The bounded behavior is only malformed direct or indirect Image XObject `/Alternates` operands before alternate stream traversal.

## Dependency Closure

No new support component is needed. The patch reuses the native PHP PDF dictionary scanner, single-array operand validator, exact-reference resolver, image XObject review rows, stream filter decoder, focused PHP tests, and WordPress smoke renderer. Full raster parity remains gated on a future native raster backend or PDFium/PIL handoff; OCR, Surya, Texify, Torch, model workers, Poppler, Ghostscript, live services, and external PDF tools were not run.
