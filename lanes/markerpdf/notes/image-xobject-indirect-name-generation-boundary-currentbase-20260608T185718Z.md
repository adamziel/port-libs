# Image XObject indirect name generation boundary current-base

Micro-slice: `markerpdf-image-xobject-boundary-current-base-20260608T185718Z`

Accepted base: `be1daac3955666cd7f4550d89b27b78d713e0ae0`

## Source Truth

Upstream markerPDF routes searchable PDF text extraction through native PDF parsing before OCR/model work. Under the current no-GPU markerPDF scope, Image XObject rows stay review-only and raster payload bytes must not become WordPress paragraph text.

PDF indirect references are object-number plus generation pairs. Image XObject `/Type` and `/Subtype` name operands may themselves be indirect references, and a higher-generation object with the same number must not override the exact generation named by the dictionary.

## Behavior

`PdfTextExtractor::pdfNameValueAt()` and `PdfTextExtractor::pdfNameValueAtStrictIndirectOperand()` now resolve indirect name operands through the exact-generation object lookup. This aligns Image XObject `/Type` and `/Subtype` name resolution with the existing exact-generation numeric and boolean operand paths.

The focused fixture proves an invoked Image XObject with `/Type 30 0 R` and `/Subtype 31 0 R` is still reviewed when stale higher-generation decoys `30 1 obj /NotXObject` and `31 1 obj /Form` are present. The image payload remains excluded from visible text and only its review hash is emitted.

## Red-First Evidence

Before the source edit, the focused test missed the image row:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectIndirectNameGenerationBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL resolves Image XObject Type and Subtype indirect names by exact object generation (lanes/markerpdf/tests/PdfImageXObjectIndirectNameGenerationBoundaryCurrentBaseTest.php)
Values are not identical
Expected: 1
Actual: 0

1 test files, 4 assertions, 1 failures
```

## Verification

Focused test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectIndirectNameGenerationBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS resolves Image XObject Type and Subtype indirect names by exact object generation

1 test files, 32 assertions, 0 failures
```

Adjacent Type/Subtype/name boundary checks:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectIndirectNameGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectIndirectNameOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectTypeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectExplicitSubtypeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectTopLevelSubtypeReviewBoundaryCurrentBaseTest.php
Focused test run: 5 selected test files (root lock skipped)
PASS keeps explicit non-Image XObject subtypes out of image-key fallback review
PASS resolves Image XObject Type and Subtype indirect names by exact object generation
PASS rejects tailed indirect Image XObject Type and Subtype name operands
PASS reports Image XObject stream subtypes from top-level dictionaries only
PASS rejects explicit non-XObject image stream Type values before image review

5 test files, 243 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-image-xobject-indirect-name-generation-currentbase.php
```

The smoke exits `0` and emits metadata with `exact_name_generation_resolved=true`, `higher_generation_name_decoys_rejected=true`, `payload_in_visible_text=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted exact XObject resource-reference generation, parent Form generation, SMask/Mask/Alternates generation, optional-content generation, indirect name operand tail rejection, explicit Type/Subtype fallback review, top-level dimension filtering, stream filter predictor BitsPerComponent validation, CCITT DecodeParms generation behavior, or OCR/model execution.

## Dependency Closure

No new support component is needed. The patch reuses the native PHP direct-object generation map and object reference resolver already used by integer and boolean PDF operands. It does not invoke pypdfium, PIL, OCR, Torch, Surya, Texify, Streamlit/FastAPI workers, external PDF tools, or live services.

## Next Task

Continue with non-overlapping native markerPDF parser behavior around image/filter metadata, font encodings/CMaps, xref repair, annotations/forms, page geometry, and supplied-boundary table/equation handoffs.
