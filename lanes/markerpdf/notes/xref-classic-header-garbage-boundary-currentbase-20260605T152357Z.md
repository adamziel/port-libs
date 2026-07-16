# Classic XRef Header-Garbage Rebuild Boundary

Micro-slice: `markerpdf-classic-xref-rebuild-boundary-current-base-20260605T152357Z`
Session: `port-dev-markerpdf-xref-classic-rebuild-20260605T152357Z`
Base accepted HEAD: `0220dd4558ea5903a383929b21ada9236db370a7`

## Scope

This patch keeps markerPDF in the native no-GPU searchable-PDF parser scope. It rejects classic xref-table candidates that begin with a malformed non-comment subsection header before any valid subsection has been parsed.

The boundary matters during damaged `startxref` rebuild: a later pseudo-`xref` block with an invalid leading line must not be allowed to skip forward to a later valid-looking subsection and replace the current WordPress import root, XMP/Info metadata, EmbeddedFiles name tree, or attachment summary.

## Source Truth

Classic PDF xref tables are structured as `xref`, one or more numeric subsection headers, and fixed-width row entries. Comments and blank lines may be skipped, but a substantive non-header line before the first subsection makes that `xref` candidate malformed. Upstream markerPDF's searchable-PDF path depends on parser-backed extraction before OCR/model fallback, so this is a native parser repair boundary rather than a Surya/Texify/Torch task.

## Change

`xrefTableRows()` in the native text, metadata, EmbeddedFiles, and attachment extractors now returns `null` when the first substantive row after `xref` is not a subsection header. Completed subsections still tolerate later malformed/trailing lines, preserving the existing trailing-subsection boundary behavior.

The focused fixture keeps a valid current xref table before decoy objects, then appends a later malformed pseudo-`xref` section:

`xref`
`not a valid xref subsection header before later-looking rows`
`20 12`

Before this patch, the rebuild path skipped the malformed first line, accepted the `20 12` subsection, and selected decoy text/metadata/attachments. After this patch, the malformed pseudo-section is rejected and the current table remains authoritative.

## Evidence

Red-first focused run before parser changes:

`php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicRebuildBoundaryCurrentBaseTest.php`

Result: `1 test files, 579 assertions, 1 failures`. The new fixture selected `Header garbage decoy page` and `Malformed header root leak`.

Final focused run after implementation:

`php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicRebuildBoundaryCurrentBaseTest.php`

Result: `1 test files, 605 assertions, 0 failures`.

Adjacent xref/parser-xref family:

`php tools/run-tests.php lanes/markerpdf/tests/PdfXref*.php lanes/markerpdf/tests/PdfParserXref*.php`

Result: `84 test files, 2566 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-xref-classic-rebuild-header-garbage-currentbase.php`

Result: emitted `current_classic_xref_import_kept=true`, `decoy_xref_section_rejected=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Full root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted damaged/out-of-file `startxref`, stale older-table `startxref`, post-EOF xref garbage, comment-row xref parsing, commented `startxref`, array/composite/name/literal/string decoys, malformed xref rows inside a declared subsection, punctuation row suffixes, comment-only rows, trailing malformed subsections after a valid subsection, stream-owned trailer/composite, malformed hex opener, damaged root row, or forward `/Prev` repair. The new behavior is limited to malformed leading subsection headers before the first valid classic xref subsection.

## Dependency Closure

No new support component is needed. The patch reuses the existing native PHP object scanner, classic xref-table parser, text extractor, metadata extractor, EmbeddedFiles/attachment extractors, and WordPress smoke path. OCR, GPU/model execution, external PDF tools, online services, and exact upstream model benchmark parity remain intentionally out of scope under the current markerPDF directive.
