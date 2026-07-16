# Classic Xref Rebuild Comment-Only Startxref Current Base

Date: 2026-06-08 UTC
Slice: `markerpdf-classic-xref-rebuild-boundary-current-base-20260608T061247Z`
Accepted base: `c000a2c6e88c31cb43d41a8d298fca54b32ce3da`

## Source Truth

PDF incremental-update repair has to treat the current classic xref table and
trailer as selectable when the table is chained to the earlier selected table
with `/Prev`, even if the final `startxref` token is present only inside a PDF
comment. This keeps searchable-PDF import on the current catalog, Info/XMP
metadata, and EmbeddedFiles name tree while still treating comment/name/object
`startxref` decoys as rebuild boundaries.

## Behavior

- Added a fixture with an older valid classic xref table selected by a visible
  `startxref`, followed by a newer current classic xref table whose trailer has
  `/Prev` back to the older table and whose final marker is `% startxref`.
- Updated the classic rebuild boundary helper in text, metadata, attachment,
  embedded-file, and free-object-map readers to admit the EOF-bounded current
  table only when that table directly declares the expected `/Prev`.
- Preserved existing comment/name/object-body decoy handling by keeping those
  tokens as rebuild boundaries unless a later current table is explicitly
  linked by `/Prev`.

## Evidence

Red-first:

`php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicRebuildCommentOnlyStartxrefCurrentBaseTest.php`

Failed with stale text selected from the earlier classic xref table:
`Stale comment-only startxref page` and `Comment marker blocked rebuild`.

After repair:

`php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicRebuildCommentOnlyStartxrefCurrentBaseTest.php`

Result: `1 test files, 29 assertions, 0 failures`.

Adjacent family:

`php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicRebuild*CurrentBaseTest.php lanes/markerpdf/tests/PdfXrefClassic*CurrentBaseTest.php`

Result: `23 test files, 1397 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-xref-classic-rebuild-comment-only-startxref-currentbase.php`

Result: exits 0 and reports current text, current XMP/Info metadata, current
EmbeddedFiles attachment, stale exclusion, `executes_python_or_models=false`,
and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat the accepted PageLabels same-lower extension slice, the
classic stale-startxref repair without comments, name-token startxref decoy
rejection, or commented/name xref table rejection. It covers the narrower
boundary where a later current classic xref table has a direct `/Prev` chain but
the final `startxref` marker is comment-only.

## Dependency Closure

No new support component is needed. The patch reuses the existing native PHP
classic xref table, trailer, metadata, text, attachment, embedded-file, and
free-object-map parsers. GPU/OCR/model execution, raster rendering, external
PDF tools, and live services remain intentionally out of scope for this lane.

Root harness: not run - isolated micro-slice.
