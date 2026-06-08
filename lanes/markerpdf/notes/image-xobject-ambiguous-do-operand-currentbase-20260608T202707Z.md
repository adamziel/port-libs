# markerPDF Image XObject Ambiguous Do Operand Boundary Current Base

Session: `port-dev-markerpdf-image-xobject-20260608T202707Z`
Micro-slice: `markerpdf-image-xobject-boundary-current-base-20260608T202707Z`
Base accepted HEAD: `f5131e36ddeeb4eb873da16b8f77aa4b6e597ea6`

## Source Truth

Pinned upstream markerPDF separates searchable text extraction from image rendering:

- `marker/pdf/extract_text.py` extracts searchable page text through pdftext/PDFium page text.
- `marker/pdf/images.py` renders page or bbox imagery through PDFium/PIL and converts it to RGB.

Under the current no-GPU PHP markerPDF scope, this lane owns the native parser boundary before any raster backend. A content-stream `Do` image invocation has exactly one XObject resource-name operand. If malformed content supplies two image resource-name operands, the PHP review must not choose one resource, paint both, or leak raster payload bytes into WordPress text.

Upstream references:

- `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py`
- `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/images.py`

## Behavior

`PdfTextExtractor::contentXObjectInvocationDetails()` now preserves review-only boundary evidence for malformed Image XObject `Do` operators with multiple resource-name operands:

- each named image resource receives a malformed operand review row;
- neither image is counted as invoked or painted;
- the review row reports `reason=ambiguous_do_resource_operands`, `resource_operand_indexes`, decoded `resource_names`, operand previews, CTM matrices, bboxes, and visible bboxes;
- the existing single-resource extra-operand path keeps `reason=extra_do_operands` and a scalar `resource_operand_index`;
- payload bytes remain excluded from plain text and review JSON.

## Red First

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectAmbiguousDoOperandBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PHP Warning: Undefined array key 0 ...
FAIL rejects ambiguous multi-name Image XObject Do operands as unpainted review-only boundaries
Expected: 1
Actual: 0
1 test files, 11 assertions, 1 failures
```

The current base kept both image resources uninvoked, but it dropped the malformed operand evidence for the ambiguous resource names.

## Verification

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfTextExtractor.php
```

```text
php -l lanes/markerpdf/tests/PdfImageXObjectAmbiguousDoOperandBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfImageXObjectAmbiguousDoOperandBoundaryCurrentBaseTest.php
```

```text
php -l lanes/markerpdf/examples/wordpress-pdf-image-xobject-ambiguous-do-operand-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-image-xobject-ambiguous-do-operand-currentbase.php
```

```text
php -r '$p="lanes/markerpdf/lane-status.json"; json_decode(file_get_contents($p), true); if (json_last_error() !== JSON_ERROR_NONE) { fwrite(STDERR, json_last_error_msg().PHP_EOL); exit(1); } echo "lane-status json valid\n";'
lane-status json valid
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectAmbiguousDoOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectDoOperandBoundaryCurrentBaseTest.php
Focused test run: 2 selected test files (root lock skipped)
PASS rejects ambiguous multi-name Image XObject Do operands as unpainted review-only boundaries
PASS records malformed Image XObject Do operands as unpainted review-only boundaries
2 test files, 118 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-image-xobject-ambiguous-do-operand-currentbase.php
```

The smoke exits 0 and emits `ambiguous_do_operand_policy=reject_malformed_image_xobject_do_operands`, `ambiguous_do_operand_reason=ambiguous_do_resource_operands`, `ambiguous_do_operand_indexes=[0,1]`, `decoy_image_unpainted=true`, `hero_image_unpainted=true`, `payload_in_visible_text=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

```text
git diff --check -- lanes/markerpdf
```

The diff whitespace check exits 0.

Root harness status: not run - isolated micro-slice.

## Status Delta

- Focused behavior tests: `3466 -> 3467` pass / `0` fail.
- Focused assertion count for the new test: red-first `11` assertions / `1` failure, then included in the adjacent malformed `Do` family run at `118` assertions / `0` failures.
- WordPress scenario count: `2812 -> 2813`.
- Mapped upstream denominator: unchanged; this refines the existing image XObject boundary behavior row.

## Non-Overlap

This does not repeat accepted single-resource malformed `Do` operand diagnostics (`extra_do_operands`), text-object `Do` suppression, compatibility-section `Do` suppression, valid sibling paint handling, malformed `cm` operand rejection, image subtype/type boundaries, image `/Alternates` operand boundaries, SMask/Mask/Metadata/OPI operand boundaries, optional-content image visibility, Form/pattern/Type3 image traversal, inline image tokenizer behavior, CTM placement/clipping, or raster/OCR/model execution.

The bounded behavior is only ambiguous multi-resource-name Image XObject `Do` operands before image invocation review.

## Dependency Closure

No new support component is needed. This patch reuses the native PHP content tokenizer, graphics-state/CTM tracking, Image XObject resource resolver, malformed operand review normalization, stream filter decoder, focused PHP tests, and WordPress smoke renderer.

Full upstream raster parity remains gated on PDFium/pypdfium/PIL or a future native raster backend, while live OCR, Surya, Texify, Torch/model workers, and external PDF tools remain intentionally out of scope.
