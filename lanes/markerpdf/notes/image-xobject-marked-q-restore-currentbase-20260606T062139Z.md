# markerPDF Image XObject marked-content q/Q restore boundary

Session: `port-dev-markerpdf-image-xobject-20260606T062139Z`
Micro-slice: `markerpdf-image-xobject-boundary-current-base-20260606T062139Z`
Base accepted HEAD: `9ad62bbe4e7fbecd3bd98f43017c2a6dc597e8c8`

## Source Truth

Pinned upstream markerPDF keeps searchable PDF text extraction separate from image rendering: text comes through the PDF text/pdftext boundary, while image XObjects are handed to the image rendering path and must not leak raster stream bytes into WordPress paragraphs.

For the native PHP no-GPU boundary, `q`/`Q` restores the PDF graphics state, including CTM and clipping. Marked-content sequences are not part of graphics state, so a `/Figure ... BDC` sequence opened inside `q` and still live after `Q` must remain attached to a following image `/Do` review row.

## Behavior

`PdfTextExtractor::restoreImageInvocationGraphicsStatesPreservingCurrentPath()` now preserves the live marked-content stack when `Q` restores image-invocation state. This keeps Image XObject review metadata for tags, MCIDs, and Alt text when the graphics state is restored before the image is painted.

The focused fixture proves:

- the image payload stays out of visible text and review JSON;
- the image remains an invoked review-only Image XObject;
- the review row keeps `/Figure`, MCID `7`, and Alt text after `Q`;
- no Python, GPU/model, pypdfium/PIL, or external PDF tools run.

## Red First

Before the source change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectMarkedContentQRestoreBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL preserves marked-content image review across q Q graphics-state restore (lanes/markerpdf/tests/PdfImageXObjectMarkedContentQRestoreBoundaryCurrentBaseTest.php)
Values are not identical
Expected: true
Actual: false

1 test files, 15 assertions, 1 failures
```

The pre-fix review counted the image invocation but dropped `marked_content_review_only`.

## Verification

Focused after source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectMarkedContentQRestoreBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS preserves marked-content image review across q Q graphics-state restore

1 test files, 30 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-image-xobject-marked-q-restore-currentbase.php
```

The smoke exits 0 and emits `marked_content_preserved_after_q_restore=true`, `marked_content_alt_text_preserved=true`, `image_payloads_excluded_from_text=true`, `image_payloads_excluded_from_review_json=true`, `visible_paragraphs_preserved=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Adjacent Image XObject family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectCmOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectEntryWrapperBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectFormResourceProvenanceCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectFormSubtypeNameBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectMarkedContentQRestoreBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectOpiProxyBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectPatternWrapperCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectType3CharProcBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectType3CharProcCMapBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceImageXObjectInheritanceCurrentBaseTest.php
Focused test run: 11 selected test files (root lock skipped)
11 test files, 1462 assertions, 0 failures
```

Syntax and status checks:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfTextExtractor.php

php -l lanes/markerpdf/tests/PdfImageXObjectMarkedContentQRestoreBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfImageXObjectMarkedContentQRestoreBoundaryCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-pdf-image-xobject-marked-q-restore-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-image-xobject-marked-q-restore-currentbase.php

php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status.json valid\n";'
lane-status.json valid

git diff --check -- lanes/markerpdf
0 failures
```

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted Image XObject resource inheritance, Form XObject traversal, Form subtype name escapes, resource wrappers, malformed `Do` operands, text-object/compatibility-section gates, optional-content filtering, artifact suppression, page/clip geometry, q/Q current-path clipping, ExtGState transparency, image masks, soft masks, alternate images, OPI proxy metadata, Type3 CharProc image review, pattern-wrapper image review, page labels, annotations, forms, xref repair, OCR, or model behavior. The bounded behavior is only preserving marked-content review state across `Q` before an Image XObject `Do`.

## Dependency Closure

No new support component is needed. The patch reuses the native PHP PDF object scanner, content tokenizer, graphics-state/image invocation state tracker, Image XObject review metadata path, stream filter decoders, and WordPress smoke renderer. Full live raster parity remains gated on PDFium/PIL or a future native raster backend, and OCR/model execution remains intentionally out of scope under the current no-GPU markerPDF directive.
