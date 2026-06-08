# markerPDF Classic XRef Comment-Only Review Boundary

Slice: `markerpdf-classic-xref-rebuild-boundary-current-base-20260608T215137Z`

Accepted base: `6f8463809fe932bed047f1bc503ab1bca68687f8`

## Behavior

Upstream markerPDF routes searchable-PDF imports through parser-backed PDF object
selection before any OCR/model fallback. In the native no-GPU PHP lane,
annotation and link action review must follow the same classic xref rebuild
boundary as text extraction.

A damaged producer can append a current classic xref table with `/Prev` pointing
to an older valid section, then leave the final `startxref` marker on a PDF
comment line. The comment-only marker is not selectable, but the EOF-bounded
current xref table is still the current revision boundary for downstream
annotation review. `PdfClassicXrefRebuilder` now admits that current table only
when its trailer explicitly `/Prev`-links to the selected earlier table, keeping
unlinked post-EOF garbage excluded.

## Red-First Evidence

Before the source change, an ad hoc red probe replaced the existing
object-owned annotation-review fixture tail with:

```text
% startxref
<current-xref-offset>
%%EOF
```

Text extraction already selected `Current object-owned review page`, but
annotation/link review selected the stale previous URI:

```text
annotation_uri => https://stale.example.com/object-owned-review
link_uri => https://stale.example.com/object-owned-review
```

After the source change, the focused fixture selects the current review rows:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicRebuildReviewCommentOnlyStartxrefCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS uses comment-only final startxref as an EOF-boundary for annotation review rebuilds

1 test files, 24 assertions, 0 failures
```

Adjacent xref/review regression:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicRebuildReviewCommentOnlyStartxrefCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefClassicRebuildActionReviewBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefClassicRebuildReviewObjectOwnedBoundaryCurrentBaseTest.php
Focused test run: 3 selected test files (root lock skipped)
PASS rebuilds damaged classic startxref before annotation action review and WordPress link promotion
PASS uses comment-only final startxref as an EOF-boundary for annotation review rebuilds
PASS uses ignored object-owned startxref only as a classic rebuild boundary before annotation review

3 test files, 68 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-classic-rebuild-review-comment-only-startxref-currentbase.php
```

The smoke exits 0 and emits `final_startxref_is_comment_only=true`,
`current_table_prev_links_previous=true`, `annotation_uri_current=true`,
`additional_action_current=true`, `markdown_link_current=true`,
`excludes_stale_uri=true`, `excludes_stale_javascript=true`,
`executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted text/metadata/attachment comment-only startxref
rebuild, damaged startxref action review, object-owned annotation review,
post-EOF garbage exclusion, malformed or missing startxref repair, private-tail
exclusion, stream/composite/literal/name-owned token guards, xref-stream Prev
repair, object-stream repair, table geometry, OCR, or model work. The bounded
behavior is only the shared annotation/link review xref helper admitting an
EOF-bounded current classic table when a comment-only final marker is paired
with an explicit `/Prev` link back to the older selected table.

## Dependency Closure

No new support component is needed. This reuses the native PHP direct-object
scanner, classic xref table/trailer parser, annotation/link review extractors,
Markdown span promotion, and WordPress block smoke renderer. GPU/OCR/model
execution, pypdfium/PIL rendering, JavaScript execution, and external PDF tools
remain intentionally out of scope.
