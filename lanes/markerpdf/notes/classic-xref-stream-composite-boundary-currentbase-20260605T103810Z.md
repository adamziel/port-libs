# markerPDF classic xref stream-composite boundary current base

Micro-slice: `markerpdf-classic-xref-rebuild-boundary-current-base-20260605T103810Z`
Session: `port-dev-markerpdf-xref-classic-rebuild-20260605T103810Z`
Base accepted HEAD: `7f71cfc6116b03249ff3e806369e892ec5de9b31`

## Source truth

Upstream `sddai/markerPDF` remains pinned at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`. The upstream no-GPU searchable-PDF boundary routes text through parser-backed page extraction before OCR/layout/model fallback; PDF cross-reference repair is therefore a native PHP dependency boundary for current page text, document metadata, and EmbeddedFiles review metadata.

Classic xref rebuild is only allowed to scan top-level xref tables. Raw stream bytes can contain PDF-looking delimiters, including an opening `[` with no matching close before the actual top-level xref table. Those bytes are payload, not syntax that may hide the current table or cause stale `/Prev` content to win.

## Change

`PdfTextExtractor`, `PdfMetadataExtractor`, and `PdfEmbeddedFileExtractor` now pass known direct-object body ranges into their classic xref keyword scanners. The scanners skip those ranges before comment/string/composite-token handling, matching the already-guarded `PdfAttachmentExtractor` path.

This prevents a stream-owned composite token from making the rebuild scan jump over a later top-level classic xref table. Current page text, XMP/Info metadata, and EmbeddedFiles attachments now remain selected when the final `startxref` is damaged.

## Red probe

Before the fix, an inline probe with a stream payload beginning with `[` before the current xref table extracted stale text:

`Stale hidden-array xref page / Stream array hid current xref`

The same probe also failed to find the current embedded file. After the fix, it selected:

`Current hidden-array xref page / Real xref after stream array selected`

and found `current-hidden-array-xref.xml`.

## Evidence

Focused test:

`php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicRebuildBoundaryCurrentBaseTest.php`

Result after the fix: `1 test files, 487 assertions, 0 failures`.

Adjacent xref/parser-xref family:

`php tools/run-tests.php lanes/markerpdf/tests/PdfXref*.php lanes/markerpdf/tests/PdfParserXref*.php`

Result after the fix: `71 test files, 2029 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-classic-xref-stream-composite-currentbase.php`

Result: emitted `stream_composite_boundary_skipped=true`, `embedded_file=current-stream-composite-xref.xml`, `attachment_count=1`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Full root harness: not run - isolated micro-slice.

## Non-overlap

This does not repeat accepted damaged `startxref`, stale valid `startxref`, EOF garbage, commented `xref`, commented/name/composite `startxref`, name-delimited `xref`, malformed row, punctuation row, comment row, trailing subsection, literal-string xref, stream-owned trailer, forward `/Prev`, xref-stream `/Prev`, object-stream, free-entry, hybrid, encryption, metadata, or attachment generation slices. The new boundary is specifically stream-payload composite tokens that otherwise make top-level classic xref keyword scanning skip the current valid table.

## Dependency closure

No new support component is needed. This reuses the native PHP direct-object scanner, classic xref table parser, text extractor, metadata extractor, embedded-file/attachment extractors, and WordPress smoke path. Live OCR, PDFium rendering, Surya/Torch/Texify models, Streamlit/FastAPI workers, and exact upstream model benchmark parity remain intentionally out of scope under the current no-GPU markerPDF directive.
