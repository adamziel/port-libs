# markerPDF classic xref free-object map name-delimited boundary

Micro-slice: `markerpdf-classic-xref-rebuild-boundary-current-base-20260607T012122Z`
Session: `port-dev-markerpdf-xref-classic-rebuild-20260607T012122Z`
Accepted base: `4841a8141eb09153691392303a67ae59443e4510`

## Source truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` delegates searchable PDF parsing to `pdftext.extraction.dictionary_output(...)` and PDFium page text before OCR/model stages. Under the current no-GPU scope, this lane owns the native PHP parser boundary where xref-selected objects, annotation free rows, and WordPress link/review metadata are recovered without Python, PDFium, OCR, Surya, Texify, or Torch execution.

PDF classic xref tables use the literal `xref` keyword as a table header followed by PDF whitespace or a comment. A name-delimited token such as `xref/Decoy` is not a classic xref table header. `PdfTextExtractor` already enforced this stricter boundary during damaged `startxref` rebuilds; `PdfXrefFreeObjectMap` did not, so its standalone free-row map could accept a pseudo-table and resurrect stale annotation objects.

## Behavior

The new fixture builds:

- a previous classic xref section where annotation object `7` is live;
- a current incremental section with damaged final `startxref 999999`, updated page/content rows, and object `7` marked free;
- a later top-level `xref/Decoy` pseudo-table that points object `7` back to the stale annotation.

Before the source fix, `PdfXrefFreeObjectMap::freeObjectNumbers()` selected the pseudo-table during classic rebuild and lost the current free row. After the fix, `PdfXrefFreeObjectMap::xrefTableSectionAt()` requires the byte after `xref` to be PDF whitespace or `%`, matching `PdfTextExtractor`. The current free row is preserved, `PdfLinkAnnotationExtractor` does not promote the stale URI, and `PdfAnnotationExtractor` emits no stale review annotation.

## Red-first evidence

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicRebuildFreeObjectMapCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rebuilds damaged classic startxref for the free-object map before annotation review
PASS ignores literal-string xref decoy while rebuilding the free-object map before annotation review
FAIL ignores name-delimited xref pseudo-table while rebuilding the free-object map before annotation review (lanes/markerpdf/tests/PdfXrefClassicRebuildFreeObjectMapCurrentBaseTest.php)
Name-delimited xref pseudo-tables must not replace the current free-row map.

1 test files, 28 assertions, 1 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicRebuildFreeObjectMapCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rebuilds damaged classic startxref for the free-object map before annotation review
PASS ignores literal-string xref decoy while rebuilding the free-object map before annotation review
PASS ignores name-delimited xref pseudo-table while rebuilding the free-object map before annotation review

1 test files, 37 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-classic-rebuild-free-object-map-name-delimited-currentbase.php
```

The smoke emits `name_delimited_xref_decoy_ignored_for_free_map=true`, `suppresses_stale_link_annotation=true`, `suppresses_stale_review_annotation=true`, `excludes_stale_annotation_uri=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-overlap

This does not repeat accepted text/metadata classic rebuild behavior for damaged or stale `startxref`, post-EOF xref garbage, object-owned `startxref`, stream-owned fake xref payloads, comments, literal strings, arrays, composite tokens, name-token `startxref`, name-delimited xref rejection in `PdfTextExtractor`, or literal-string free-object map decoys. The bounded behavior is only the standalone free-object map path rejecting `xref/Name` pseudo-tables before WordPress annotation/link review.

## Dependency closure

No new support component is needed. This slice reuses the native PHP direct-object scanner, classic xref table parser, `/Prev` chain walker, free-object map, annotation/link extractors, and WordPress smoke path. Remaining markerPDF parity gaps stay bounded by the current no-GPU scope: live OCR, Surya/Texify/Torch model execution, PDFium rendering, external OCR/rendering helpers, Streamlit/FastAPI model workers, and exact upstream model benchmark parity are intentionally out of scope.
