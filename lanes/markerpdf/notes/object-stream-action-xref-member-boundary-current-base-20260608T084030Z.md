# Object Stream Action Xref Member Boundary Current Base

Base accepted HEAD: `4b01f722ab0979bb02bbf54f86e6d0f4c3bbc7af`

Micro-slice: `markerpdf-object-stream-xref-parser-current-base-20260608T084030Z`

## Source Truth

- PDF 1.5 xref-stream type-2 entries select compressed objects from `/Type /ObjStm` streams by object-stream number and member index.
- Object-stream member offsets are relative to the byte after `/First` and must identify top-level object member boundaries, not bytes inside another member literal string, comment, array, dictionary, or hex string.
- The main text/link object-stream readers already fail closed for non-boundary member offsets; this patch carries the same boundary rule into `PdfActionReviewExtractor`, which is the review-only action resolver used before WordPress link annotation promotion.

## Patch

- `PdfActionReviewExtractor` now parses object-stream headers with explicit unsigned-integer tokens and rejects trailing header garbage instead of collecting arbitrary digit runs.
- Type-2 compressed action members now require a top-level token boundary before the member body is parsed.
- Later member offsets that are not token boundaries are ignored as slice terminators.
- Added a focused fixture where annotation `/A 8 0 R` points at an xref type-2 member offset inside another compressed member literal string containing a fake URI action dictionary.
- Added a WordPress smoke proving the safe direct link is preserved while fake/stale compressed action payloads stay out of review output and visible text.

## Evidence

Red-first:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfActionReviewObjectStreamOffsetBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL rejects action object-stream member offsets inside literal strings before WordPress link promotion
Values are not identical
Expected: array (
  0 => 6,
)
Actual: array (
  0 => 6,
  1 => 7,
)

1 test files, 2 assertions, 1 failures
```

Focused green:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfActionReviewObjectStreamOffsetBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects action object-stream member offsets inside literal strings before WordPress link promotion

1 test files, 19 assertions, 0 failures
```

Adjacent object-stream boundary run:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAnnotationLinkObjectStreamReviewBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationObjectStreamBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamHeaderOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamMemberTailBoundaryCurrentBaseTest.php
Focused test run: 4 selected test files (root lock skipped)
PASS uses xref-stream object-stream Link annotation bodies for annotation review before stale direct bodies
PASS resolves indirect object-stream decode operands before WordPress link annotation review
PASS rejects annotation object-stream member offsets inside literal strings before WordPress link promotion
PASS uses xref-stream object-stream Link annotation bodies before stale direct annotation bodies
PASS rejects object streams whose N integer has a trailing top-level operand
PASS rejects object streams whose First integer has a trailing top-level operand
PASS rejects object-stream members with trailing top-level operands before WordPress text extraction

4 test files, 163 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-action-object-stream-offset-boundary-currentbase.php
exit 0; marker summary reports link_annotation_objects=[6], malformed_action_offset_excluded=true, stale_direct_action_excluded=true, action_payload_text_excluded_from_visible_text=true, executes_python_or_models=false, executes_external_pdf_tools=false, executes_pdf_actions=false.
```

Syntax/status checks:

```text
php -l lanes/markerpdf/src/PdfActionReviewExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfActionReviewExtractor.php

php -l lanes/markerpdf/tests/PdfActionReviewObjectStreamOffsetBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfActionReviewObjectStreamOffsetBoundaryCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-pdf-action-object-stream-offset-boundary-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-action-object-stream-offset-boundary-currentbase.php

php -r '$p="lanes/markerpdf/lane-status.json"; json_decode(file_get_contents($p), true); if (json_last_error() !== JSON_ERROR_NONE) { fwrite(STDERR, json_last_error_msg()."\n"); exit(1); } echo "lane-status json ok\n";'
lane-status json ok
```

Final combined focused check:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfActionReviewObjectStreamOffsetBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAnnotationLinkObjectStreamReviewBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationObjectStreamBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamHeaderOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamMemberTailBoundaryCurrentBaseTest.php
Focused test run: 5 selected test files (root lock skipped)
PASS rejects action object-stream member offsets inside literal strings before WordPress link promotion
PASS uses xref-stream object-stream Link annotation bodies for annotation review before stale direct bodies
PASS resolves indirect object-stream decode operands before WordPress link annotation review
PASS rejects annotation object-stream member offsets inside literal strings before WordPress link promotion
PASS uses xref-stream object-stream Link annotation bodies before stale direct annotation bodies
PASS rejects object streams whose N integer has a trailing top-level operand
PASS rejects object streams whose First integer has a trailing top-level operand
PASS rejects object-stream members with trailing top-level operands before WordPress text extraction

5 test files, 182 assertions, 0 failures
```

Whitespace check:

```text
git diff --check -- lanes/markerpdf
exit 0
```

## Dependency Closure

No new support component is needed. This reuses native PHP xref-stream decoding, object-stream filter decoding, and PDF action review parsing already present under `lanes/markerpdf/src`.

No GPU/model execution, OCR, raster rendering, external PDF tools, live service calls, or PDF action execution were used or added.

## Non-Overlap

This patch does not change text extraction, XMP metadata, annotation object body expansion, page geometry, image filters, or model/OCR behavior. It is scoped to action-review object-stream member selection before URI action review and WordPress link annotation promotion.
