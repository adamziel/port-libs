# Classic XRef Comment-Row Rebuild Boundary

Micro-slice: `markerpdf-classic-xref-rebuild-boundary-current-base-20260605T100450Z`

Accepted base: `8be86cd41bb40ca9b82306af945c892eeca809a2`

## Scope

This patch keeps markerPDF in the no-GPU native parser scope. It handles a classic PDF syntax boundary: `%` comments are whitespace around classic xref-table subsection headers and rows. A damaged final `startxref` now lets the classic rebuild path select the current xref table even when that table contains comment-only rows or an inline subsection-header comment.

The change is applied to the native text, metadata, EmbeddedFiles, and attachment xref readers so page text, XMP/Info metadata, file attachments, and WordPress attachment preflight all agree on the current table instead of falling back to stale `/Prev` entries.

## Source Truth

PDF comments begin with `%` and end at the line break, and are treated as whitespace outside strings and streams. The upstream markerPDF path for searchable PDFs relies on native PDF parser/extractor behavior before OCR/model fallbacks, so this boundary belongs in the native parser rather than in Surya/Texify/Torch execution.

## Evidence

Red-first focused run before parser changes:

`php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicRebuildBoundaryCurrentBaseTest.php`

Result: `1 test files, 431 assertions, 1 failures`. The new fixture selected stale `/Prev` page text because the current xref subsection included comment-only rows.

Intermediate text-only parser fix:

`php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicRebuildBoundaryCurrentBaseTest.php`

Result: `1 test files, 436 assertions, 1 failures`. Page text selected the current table, but metadata still selected the stale trailer.

Final focused run:

`php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicRebuildBoundaryCurrentBaseTest.php`

Result: `1 test files, 457 assertions, 0 failures`.

Adjacent xref/trailer family:

`php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg '/(PdfXref|PdfParserXref|PdfMetadata.*Trailer|PdfAttachmentTrailer|PdfAcroFormFieldsTrailer|PdfNamedDestinationTrailer|PdfOutlineMetadataTrailer).*Test\.php$' | sort)`

Result: `76 test files, 2142 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-xref-classic-rebuild-comment-row-currentbase.php`

Result: emitted `uses_current_page_text=true`, `metadata_title_current=true`, `info_title_current=true`, `embedded_file_current=true`, `attachment_summary_current=true`, `skips_xref_comment_lines=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat the accepted damaged/stale startxref, post-EOF xref, comments-as-keyword, commented startxref, array/composite/name decoy, name-offset startxref, linearized hint, malformed row, punctuation row, trailing subsection, literal-string xref, stream-owned trailer, or forward `/Prev` boundaries. The new behavior is limited to comment-only xref-table rows and subsection-header comments during current-table rebuild.

## Dependency Closure

No new support component is needed. The patch reuses the existing bounded native PHP xref-table readers. OCR, GPU/model execution, external PDF tooling, and exact upstream model benchmark parity remain intentionally out of scope for this markerPDF lane.
