# Annotation Object-Stream Header Comments Current Base

## Source Truth

MarkerPDF's searchable-PDF path should preserve native PDF annotation and link
review metadata before any OCR/model fallback. In PDF object streams, the
decoded object-stream header is a sequence of object-number/member-offset pairs,
and PDF comments are lexical whitespace. A `%` comment that contains digits must
not contribute fake header pairs before the real compressed annotation member.

## Implementation

- `PdfAnnotationExtractor` now parses object-stream header member pairs with a
  delimiter-aware integer scanner that skips PDF whitespace and comments instead
  of collecting every digit run with `/\d+/`.
- `PdfLinkAnnotationExtractor` uses the same parser for the link-promotion
  object loader, so WordPress span linking and annotation review stay aligned.
- The existing top-level member-offset ownership guard remains unchanged:
  this slice only fixes comment handling in the `/ObjStm` header.

## Red-First Evidence

Before the source fix, the new current-base fixture failed because both
annotation extraction and link promotion dropped object `7` after reading
comment digits as the member offset:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfAnnotationObjectStreamHeaderCommentCurrentBaseTest.php
```

Result:

```text
Focused test run: 1 selected test files (root lock skipped)
FAIL parses annotation object-stream header comments before WordPress link promotion (lanes/markerpdf/tests/PdfAnnotationObjectStreamHeaderCommentCurrentBaseTest.php)
Values are not identical
Expected: 1
Actual: 0

1 test files, 1 assertions, 1 failures
```

After the parser fix:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfAnnotationObjectStreamHeaderCommentCurrentBaseTest.php
```

Result:

```text
Focused test run: 1 selected test files (root lock skipped)
PASS parses annotation object-stream header comments before WordPress link promotion

1 test files, 27 assertions, 0 failures
```

Focused delta: +1 focused PASS case and +26 assertions over the red-first
failure point. Dashboard-visible lane-status intent: `phpPass` +1 and
`wordpressScenarios` +1 after clean integration.

## WordPress Smoke

Added:

```bash
php lanes/markerpdf/examples/wordpress-pdf-annotation-object-stream-header-comment-currentbase.php
```

The smoke builds a current xref-stream type-2 Link annotation whose object-stream
header starts with a digit-bearing `%` comment. It verifies that only the
compressed current Link annotation is promoted to Gutenberg markdown and that
the stale direct annotation object plus review-only annotation payload text stay
out of visible WordPress text.

## Non-Overlap

This does not repeat accepted annotation object-stream offset ownership guards,
Link annotation object-stream promotion, stale/free annotation xref suppression,
duplicate action-key review, annotation dictionary duplicate-key selection,
named-destination/action review, metadata/attachment/AcroForm object-stream
expansion, generic text xref object-stream header comment handling, plus-signed
headers, `/First` boundary validation, stream-filter operand recovery, or
xref `/Prev` carrier repair. The bounded behavior here is only comment-aware
object-stream header member parsing in annotation review and Link annotation
WordPress span promotion.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP PDF
lexer helpers in the annotation/link extractors, runs no Python, no OCR/models,
no PDF actions, and no external PDF tools.
