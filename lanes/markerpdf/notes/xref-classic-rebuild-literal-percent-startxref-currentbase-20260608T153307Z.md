# Classic Xref Rebuild Literal Percent Startxref Current Base

Date: 2026-06-08 UTC
Slice: `markerpdf-classic-xref-rebuild-boundary-current-base-20260608T153307Z`
Accepted base: `115e94fb0f21b20d880b87458c0d29bcb825d9db`

## Source Truth

PDF lexical comments begin at a top-level `%` token, not at a percent sign that
appears inside a literal string. Classic xref rebuild therefore must not treat a
producer-style literal such as `(producer note 100% complete before startxref)`
as a line comment when deciding whether the following current `startxref` token
is selectable.

## Behavior

- Added a fixture with current text, XMP/Info metadata, EmbeddedFiles, and a
  free-object row selected by a classic xref table whose `startxref` line is
  preceded by a top-level literal containing `%`.
- Added a later decoy xref table and trailer after the current table. Before the
  repair, the comment-boundary helper skipped the current `startxref` because it
  saw `%` earlier on the same line and the decoy table could win.
- Updated text, metadata, embedded-file, attachment, and free-object-map classic
  rebuild readers to scan for top-level PDF comments token-aware, working
  alongside existing direct-object ownership checks and skipping literal
  strings, composite containers, and hex strings while preserving real
  top-level comments as boundaries.

## Evidence

Red-first:

`php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicRebuildLiteralPercentStartxrefCurrentBaseTest.php`

Failed with decoy xref text selected:
`Decoy literal-percent startxref page` and `Percent comment leak`.
Observed result: `1 test files, 5 assertions, 1 failures`.

After repair:

`php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicRebuildLiteralPercentStartxrefCurrentBaseTest.php`

Result: `1 test files, 31 assertions, 0 failures`.

Classic xref family:

`php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassic*CurrentBaseTest.php`

Result: `29 test files, 1549 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-xref-classic-rebuild-literal-percent-startxref-currentbase.php`

Result: exits 0 and reports `literal_percent_not_comment=true`,
`uses_current_page_text=true`, `metadata_title_current=true`,
`embedded_file_current=true`, `free_row_current=true`,
`executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted classic xref rebuild EOF-boundary, comment-only
startxref, literal xref-table decoy, name-token startxref, object-owned
startxref, or stream-payload decoy repairs. It covers the narrower lexical
boundary where `%` inside a top-level literal string must not comment out a
following current `startxref` token on the same line.

## Dependency Closure

No new support component is needed. The patch reuses the existing native PHP
classic xref table, trailer, token-boundary, metadata, text, attachment,
embedded-file, and free-object-map parsers. GPU/OCR/model execution, raster
rendering, external PDF tools, live services, and encrypted/password validation
remain intentionally out of scope for this lane.

Root harness: not run - isolated micro-slice.
