# markerPDF Classic XRef Action Trailer Comment Boundary

Micro-slice: `markerpdf-classic-xref-rebuild-boundary-current-base-20260609T000634Z`
Session: `port-dev-markerpdf-xref-classic-rebuild-20260609T000634Z`
Base accepted HEAD: `35d557737dc1b88c45279aeb585788c53834812d`

## Source Truth

Pinned upstream markerPDF routes searchable PDF text and document metadata through parser layers before any OCR/model handoff. In the native no-GPU PHP scope, classic xref-table repair must preserve the current parser boundary before WordPress link/action review: a `trailer` token inside a PDF comment is not the xref table trailer and must not truncate subsequent current xref rows.

## Implementation

- `PdfActionReviewExtractor::xrefTableTrailerKeywordOffset()` now scans xref-table bodies token-by-token and skips comments, literal strings, composite array/dictionary tokens, and hex strings before accepting a top-level `trailer << ... >>`.
- `PdfClassicXrefRebuilder::xrefTableTrailerKeywordOffset()` now uses the same token-aware trailer scan for the shared classic rebuild helper used by annotation/link xref selection.
- Added a focused fixture where a repaired classic xref table contains a legal PDF comment `% trailer << ... >>` between the page rows and the current action rows. Without the fix, action-review xref parsing accepted the comment-contained trailer, dropped rows 7-9, and stale duplicate URI/JavaScript action objects appended after EOF won.
- Added a WordPress smoke proving current URI/mailto link promotion reaches block output while stale duplicate URI/JavaScript actions stay review-excluded and no PDF actions, Python, OCR/models, PDFium/PIL, or external PDF tools execute.

## Red-First Evidence

After adding the focused test and correcting the constructor usage:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicRebuildActionTrailerCommentBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL skips comment-contained trailer tokens while rebuilding classic xref rows before action review (lanes/markerpdf/tests/PdfXrefClassicRebuildActionTrailerCommentBoundaryCurrentBaseTest.php)
Condition is not true

1 test files, 2 assertions, 1 failures
```

## Verification

Focused test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicRebuildActionTrailerCommentBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS skips comment-contained trailer tokens while rebuilding classic xref rows before action review

1 test files, 18 assertions, 0 failures
```

Broader xref/action regression:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicRebuildActionTrailerCommentBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefClassicRebuildActionReviewBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefClassicRebuildBoundaryCurrentBaseTest.php
Focused test run: 3 selected test files (root lock skipped)
...
3 test files, 702 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-classic-rebuild-action-trailer-comment-currentbase.php
```

Reports `current_text_selected=true`, `current_link_selected=true`, `current_additional_action_selected=true`, `wordpress_markdown_link_selected=true`, `stale_action_excluded=true`, `executes_python_or_models=false`, `executes_external_pdf_tools=false`, and `executes_pdf_actions=false`.

Hygiene:

```text
php -l lanes/markerpdf/src/PdfActionReviewExtractor.php && php -l lanes/markerpdf/src/PdfClassicXrefRebuilder.php && php -l lanes/markerpdf/tests/PdfXrefClassicRebuildActionTrailerCommentBoundaryCurrentBaseTest.php && php -l lanes/markerpdf/examples/wordpress-pdf-xref-classic-rebuild-action-trailer-comment-currentbase.php
No syntax errors detected in lanes/markerpdf/src/PdfActionReviewExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfClassicXrefRebuilder.php
No syntax errors detected in lanes/markerpdf/tests/PdfXrefClassicRebuildActionTrailerCommentBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-xref-classic-rebuild-action-trailer-comment-currentbase.php

php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status.json OK\n";'
lane-status.json OK

git diff --check -- lanes/markerpdf
```

## Non-Overlap

This does not repeat accepted damaged startxref rebuild selection, stale valid startxref repair, EOF-bounded post-garbage exclusion, EmbeddedFiles name-tree repair, commented xref keywords, commented startxref tokens, array/composite/name-token xref decoys, linearized hint-range startxref exclusion, malformed row rejection, punctuation-state row rejection, comment-only xref row skipping, completed-subsection preservation, stream-owned trailer dictionaries, stream-owned composite skipping, malformed non-hex opener recovery, explicit root row repair, malformed subsection headers, overdeclared trailer-ended rows, forward Prev pointer repair, or Image XObject metadata operand work. The bounded behavior is only `trailer` tokens inside PDF comments while rebuilding a current classic xref table before action-review row selection.

## Dependency Closure

No new support component is needed. This reuses native PHP PDF byte scanning, classic xref-table repair, action review, annotation/link promotion, Markdown block conversion, and the WordPress smoke path. Live OCR, Surya/Texify/Torch model execution, PDFium/pypdfium runtime rendering, decryption, action execution, and external PDF tools remain intentionally out of scope for this markerPDF no-GPU slice.
