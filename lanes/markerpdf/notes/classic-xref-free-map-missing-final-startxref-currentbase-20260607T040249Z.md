# markerPDF classic xref free-map missing-final-startxref boundary

Micro-slice: `markerpdf-classic-xref-rebuild-boundary-current-base-20260607T040249Z`
Session: `port-dev-markerpdf-xref-classic-rebuild-20260607T040249Z`
Base accepted HEAD: `27db0d0fdf84daf246a72a97a79e230eec3fa716`

## Source Truth

Upstream markerPDF is pinned in the lane manifest at
`da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`. Its searchable-PDF path relies on
parser-backed text, metadata, attachment, and annotation extraction before any
OCR/model fallback. Under the current no-GPU scope, this PHP lane owns native
classic xref repair and annotation filtering so stale freed PDF annotations do
not become WordPress links or review rows.

## Behavior

Some damaged incremental PDFs keep an older valid `startxref`, append a newer
valid classic xref table and trailer before `%%EOF`, but omit the final
current `startxref` operand. The text/metadata/EmbeddedFiles extractors already
rebuild to the EOF-bounded current classic table in that case. The free-object
map used by link and annotation review still selected only the older
`startxref` table, so a stale annotation object freed by the current table
could survive and be promoted to WordPress metadata.

`PdfXrefFreeObjectMap` now computes the same bounded classic rebuild candidate:
it can extend an older selected `startxref` scan to the latest top-level
`%%EOF` when a later valid classic xref table exists and no top-level
`startxref` appears between that table and EOF. Post-EOF xref/trailer decoys
remain outside the free-row map, and xref-stream startxref targets continue to
fail closed to the declared stream target.

## Red-First Evidence

After adding the focused fixture and before changing `PdfXrefFreeObjectMap`:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicRebuildFreeObjectMapCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rebuilds damaged classic startxref for the free-object map before annotation review
PASS ignores literal-string xref decoy while rebuilding the free-object map before annotation review
PASS ignores name-delimited xref pseudo-table while rebuilding the free-object map before annotation review
FAIL uses EOF-bounded current classic xref for the free-object map when final startxref is missing
Missing final startxref repair must preserve current EOF-bounded free rows.

1 test files, 43 assertions, 1 failures
```

## Focused Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicRebuildFreeObjectMapCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rebuilds damaged classic startxref for the free-object map before annotation review
PASS ignores literal-string xref decoy while rebuilding the free-object map before annotation review
PASS ignores name-delimited xref pseudo-table while rebuilding the free-object map before annotation review
PASS uses EOF-bounded current classic xref for the free-object map when final startxref is missing

1 test files, 52 assertions, 0 failures
```

Adjacent classic-xref subset:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicRebuildFreeObjectMapCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefClassicRebuildPriorStartxrefMissingFinalBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefClassicRebuildStreamPayloadBoundaryCurrentBaseTest.php
Focused test run: 3 selected test files (root lock skipped)
PASS rebuilds damaged classic startxref for the free-object map before annotation review
PASS ignores literal-string xref decoy while rebuilding the free-object map before annotation review
PASS ignores name-delimited xref pseudo-table while rebuilding the free-object map before annotation review
PASS uses EOF-bounded current classic xref for the free-object map when final startxref is missing
PASS bounds rebuild after prior valid startxref when final current startxref is missing
PASS keeps stream-owned fake classic xref tables out of rebuild before WordPress attachment review

3 test files, 110 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-classic-rebuild-free-object-map-missing-final-startxref-currentbase.php
```

The smoke exits 0 and reports `current_text_selected=true`,
`stale_text_excluded=true`,
`free_object_map_rebuilt_to_current_eof_bounded_xref=true`,
`suppresses_stale_link_annotation=true`,
`suppresses_stale_review_annotation=true`,
`post_eof_xref_ignored_for_free_map=true`,
`excludes_stale_annotation_uri=true`, `executes_python_or_models=false`, and
`executes_external_pdf_tools=false`.

Root harness status: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted text/metadata/attachment missing-final-startxref
repair, damaged final-offset rebuild, literal-string xref decoys, name-delimited
xref pseudo-tables, stream-owned xref payloads, plus-signed subsection headers,
malformed first xref sections, partial trailing xref subsections, object-owned
startxref boundaries, xref-stream /Prev repair, object-stream repair, or
AcroForm NUL-whitespace work. The bounded behavior here is only the
free-object map used by link/annotation review when the current classic table
is EOF-bounded and the final current `startxref` is missing.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP direct
object scanner, classic xref table parser, free-object map, page text extractor,
link annotation extractor, annotation review extractor, and WordPress smoke
path. OCR, Surya/Texify/Torch model execution, PDFium rendering, external PDF
tools, and exact upstream model benchmark parity remain intentionally outside
the current no-GPU markerPDF scope.
