# markerPDF classic xref free-map header-garbage boundary

## Scope

Lane: `markerpdf`
Micro-slice: `markerpdf-classic-xref-rebuild-boundary-current-base-20260608T131058Z`
Accepted base: `eaf19e1f6617047d412ce09c461d8bd2634185f2`

Upstream markerPDF is pinned in the lane manifest at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`. Under the current no-GPU scope, this PHP lane owns native searchable-PDF parser boundaries before any OCR/model fallback. This slice covers classic xref rebuild behavior used by the lightweight free-object map before WordPress link/annotation promotion.

## Behavior

`PdfXrefFreeObjectMap::xrefTableRows()` now rejects a classic xref table candidate whose first substantive line after `xref` is malformed non-comment text. Before this patch, the free-object parser skipped that leading garbage and accepted a later valid-looking subsection header. That could let a damaged final `startxref` select a later decoy table that revived a stale annotation object, while `PdfTextExtractor` already rejected the same malformed-leading table and selected the current page text.

The new fixture keeps:

- a previous revision with live link annotation object `7`;
- a current revision with current page text and object `7` marked free;
- a damaged final `startxref`;
- a later malformed-leading decoy classic xref table containing a plausible `7 1` in-use row.

After the patch, the free-object map rejects the malformed-leading decoy, rebuilds to the current xref table, marks object `7` free, and suppresses stale link/review annotation metadata.

## Red-First Evidence

Before the source change:

```text
$ php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicRebuildFreeObjectMapHeaderGarbageCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL rejects malformed-leading classic xref sections before free-object map annotation review (lanes/markerpdf/tests/PdfXrefClassicRebuildFreeObjectMapHeaderGarbageCurrentBaseTest.php)
The free-object map must reject malformed-leading decoy xref sections.

1 test files, 5 assertions, 1 failures
```

## Verification

After the source change:

```text
$ php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicRebuildFreeObjectMapHeaderGarbageCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects malformed-leading classic xref sections before free-object map annotation review

1 test files, 14 assertions, 0 failures
```

Adjacent classic xref/free-map boundary tests:

```text
$ php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicRebuildFreeObjectMapHeaderGarbageCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefClassicRebuildFreeObjectMapCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefClassicRebuildFreeObjectMapWhitespaceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefClassicMalformedSubsectionHeaderBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefClassicZeroCountRebuildBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefClassicRebuildPlusHeaderBoundaryCurrentBaseTest.php
Focused test run: 6 selected test files (root lock skipped)
PASS rejects punctuation-tailed classic xref subsection headers before WordPress imports
PASS rebuilds damaged classic startxref for the free-object map before annotation review
PASS ignores literal-string xref decoy while rebuilding the free-object map before annotation review
PASS ignores name-delimited xref pseudo-table while rebuilding the free-object map before annotation review
PASS uses EOF-bounded current classic xref for the free-object map when final startxref is missing
PASS bounds malformed final startxref free-object rebuild before post-EOF annotation decoys
PASS skips commented trailer tokens while rebuilding the free-object map before annotation review
PASS rejects punctuation-suffixed free-object xref rows before WordPress annotation review
PASS rejects malformed-leading classic xref sections before free-object map annotation review
PASS normalizes PDF whitespace in rebuilt classic free-object xref rows before annotation review
PASS rebuilds damaged classic startxref with plus-signed subsection header before current import
PASS rejects zero-count classic xref subsections during rebuild before WordPress imports

6 test files, 219 assertions, 0 failures
```

WordPress smoke:

```text
$ php lanes/markerpdf/examples/wordpress-pdf-xref-classic-rebuild-free-object-map-header-garbage-currentbase.php
<!-- markerpdf-xref-classic-free-map-header-garbage-smoke {"scenario":"wordpress-pdf-xref-classic-rebuild-free-object-map-header-garbage-currentbase","native_boundary":"free-object classic xref rebuild rejects malformed-leading decoy tables before annotation promotion","damaged_startxref_operand":999999,"current_xref_after_previous":true,"header_garbage_xref_after_current":true,"current_text_selected":true,"free_annotation_preserved_from_current_xref":true,"suppresses_stale_link_annotation":true,"suppresses_stale_review_annotation":true,"excludes_stale_annotation_uri":true,"executes_python_or_models":false,"executes_external_pdf_tools":false} -->
```

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted damaged `startxref`, stale valid startxref, post-EOF garbage, literal/name/composite xref decoys, malformed active subsection rows, punctuation row suffixes, comment rows, zero-count subsections, whitespace-delimited rows, free annotation `/Prev` chain selection, or the pending truncated-subsection handoff. The bounded behavior is only malformed non-comment content before the first valid subsection in the lightweight free-object map parser.

## Dependency Closure

No new support component is needed. This reuses the native PHP classic xref scanner, xref free-object map, text extractor, link annotation extractor, annotation review extractor, and WordPress smoke path. GPU/model/OCR execution, Surya/Texify/Torch, PDFium rendering, external PDF tools, JavaScript actions, and live service/provider tests were not run and remain intentionally out of scope for this markerPDF no-GPU slice.
