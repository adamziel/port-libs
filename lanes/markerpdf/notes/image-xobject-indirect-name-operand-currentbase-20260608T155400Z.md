# Image XObject Indirect Name Operand Boundary Current Base

Micro-slice: `markerpdf-image-xobject-boundary-current-base-20260608T155400Z`

Accepted base: `86df6fefba691ff921a8e11a304488be957a19c7`

## Source Truth

Upstream markerPDF keeps searchable text extraction separate from image raster handoff. Under the current no-GPU markerPDF scope, this lane owns the native PDF parser boundary before an Image XObject stream is admitted into image review/rendering metadata.

PDF Image XObjects are XObject streams with `/Subtype /Image`; an explicit `/Type`, when present, must be `/XObject`. Direct operand tails were already rejected. This slice closes the same boundary for indirect name operands so `/Subtype 20 0 R` where object 20 is `/Image 99 0 R`, or `/Type 21 0 R` where object 21 is `/XObject 99 0 R`, does not smuggle a tailed object into image review.

## Behavior

- `PdfTextExtractor::isImageStreamDictionary()` now resolves `/Type` and `/Subtype` with the strict indirect-name operand helper.
- Indirect `/Type` or `/Subtype` objects must contain one standalone PDF name, not a name followed by extra operands.
- Tailed indirect `/Subtype` and tailed indirect `/Type` resources are excluded from Image XObject review rows, decoded hashes, and WordPress-visible output.
- Valid indirect name objects such as `/Type 22 0 R` with object 22 `/XObject` and `/Subtype 23 0 R` with object 23 `/Image` remain accepted.

## Red First

Before the source fix, the new fixture admitted all three resource streams as Image XObjects instead of rejecting the two tailed indirect-name operands.

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectIndirectNameOperandBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL rejects tailed indirect Image XObject Type and Subtype name operands (lanes/markerpdf/tests/PdfImageXObjectIndirectNameOperandBoundaryCurrentBaseTest.php)
Values are not identical
Expected: 1
Actual: 3
1 test files, 4 assertions, 1 failures
```

## Evidence

Focused test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectIndirectNameOperandBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects tailed indirect Image XObject Type and Subtype name operands
1 test files, 37 assertions, 0 failures
```

Adjacent Image XObject boundary checks:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectIndirectNameOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectMalformedSubtypeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectTypeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectResourceEntryTailBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectDuplicateSubtypeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectEntryWrapperBoundaryCurrentBaseTest.php
Focused test run: 6 selected test files (root lock skipped)
6 test files, 260 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-image-xobject-indirect-name-operand-currentbase.php
```

The smoke exits 0 and emits `indirect_subtype_tail_rejected=true`, `indirect_type_tail_rejected=true`, `valid_indirect_name_image_reviewed=true`, `payload_in_visible_text=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted Image XObject explicit Type rejection, malformed direct Subtype values, duplicate Subtype rejection, direct resource-entry tails, indirect resource entry wrappers, Form XObject traversal, optional content, masks/SMask/alternates/metadata/OPI review, CTM/bbox placement, color-space Decode handling, Type3 CharProc images, encrypted fail-closed review, or generic raster decoding.

The bounded behavior is only strict indirect name object validation for `/Type` and `/Subtype` inside image stream dictionaries.

## Dependency Closure

No new support component is needed. The implementation reuses the native PHP PDF dictionary scanner, top-level operand boundary helpers, strict indirect-name operand resolver, stream decoders, Image XObject review rows, and WordPress smoke renderer. Full pixel/raster parity remains gated on PDFium/pypdfium/PIL or a future native raster backend. Live OCR, Surya/Texify/Torch model execution, Streamlit/FastAPI workers, external PDF tools, and exact upstream model benchmark parity remain intentionally out of scope under the current no-GPU markerPDF direction.
