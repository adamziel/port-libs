# markerPDF classic xref comment-delimiter boundary current base

Micro-slice: `markerpdf-classic-xref-rebuild-boundary-current-base-20260605T164042Z`

Session: `port-dev-markerpdf-xref-classic-rebuild-20260605T164042Z`

Base accepted HEAD: `5461f13312d11b720990563e5f589783adb6e304`

## Source truth

Upstream markerPDF delegates searchable-PDF extraction through parser-backed `pdftext`/PDFium behavior before model-dependent OCR/layout work. In the native no-GPU PHP lane, classic xref repair is the parser dependency boundary for current page text, XMP/Info metadata, EmbeddedFiles, and WordPress attachment preflight.

PDF comments begin with `%` and are token-delimiting whitespace. A classic xref table can therefore begin with `xref% comment` followed by a newline and subsection rows. Native classic rebuild should accept that current table when the final `startxref` is damaged instead of falling back to an older valid table and stale WordPress import roots.

## Implementation

`PdfTextExtractor`, `PdfMetadataExtractor`, `PdfEmbeddedFileExtractor`, and `PdfAttachmentExtractor` now accept `%` immediately after a real `xref` keyword as comment whitespace. Name-token and pseudo-keyword guards still reject `/xref`, `xref/Decoy`, composite-contained xref bytes, comments before the xref token, stream-owned payload bytes, and non-delimited keyword matches.

The focused fixture appends stale page/XMP/Info/EmbeddedFiles data with a valid earlier table, then appends current objects and a current classic xref table written as `xref% current table comment`. The damaged final `startxref 999999` forces rebuild. Current page text, metadata, and attachment summaries now win; stale roots stay excluded.

## Evidence

Red-first focused failure before implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicCommentDelimiterBoundaryCurrentBaseTest.php
FAIL accepts PDF comment delimiters after classic xref keywords during rebuild before WordPress imports
Actual text lines: Stale comment-delimiter xref page / Comment delimiter root leak
1 test files, 3 assertions, 1 failures
```

Focused pass after implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicCommentDelimiterBoundaryCurrentBaseTest.php
PASS accepts PDF comment delimiters after classic xref keywords during rebuild before WordPress imports
1 test files, 29 assertions, 0 failures
```

Adjacent classic-xref family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicCommentDelimiterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefClassicRebuildBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefClassicMalformedStartxrefBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefClassicSignedStartxrefBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefClassicZeroCountRebuildBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefClassicGenerationOffsetBoundaryCurrentBaseTest.php
6 test files, 753 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-classic-xref-comment-delimiter-currentbase.php
```

Emits `current_classic_xref_import_kept=true`, `comment_delimiter_xref_accepted=true`, `metadata_title_current=true`, `embedded_file_current=true`, `stale_xref_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`, plus two current Gutenberg paragraph comments.

Root harness: not run - isolated micro-slice.

## Non-overlap

This does not repeat accepted commented xref lines before the keyword, commented `startxref` tokens, malformed or signed `startxref` operands, stale numeric startxref repair, zero-count sections, malformed or punctuation rows, comment-only row lines, trailing malformed subsections, name/composite/literal/stream-owned xref decoys, stream-owned trailers, post-startxref trailers, linearized hint ranges, generation-offset repair, forward `/Prev` repair, xref-stream `/Prev`, object-stream, hybrid, encryption, metadata, attachment generation, font/CMap, image/filter, annotation/form, OCR, or supplied-boundary table/equation work. The bounded behavior is only `%` as a comment delimiter immediately after a real classic `xref` keyword.

## Dependency closure

No new support component is needed. This reuses the native PHP direct-object scanner, PDF keyword-boundary checks, classic xref table/trailer parser, page-tree walker, metadata extractor, EmbeddedFiles extractor, attachment preflight, stream decoder, text-token extractor, and WordPress smoke path. Live OCR, PDFium rendering, Surya/Torch/Texify models, Streamlit/FastAPI workers, and exact upstream model benchmark parity remain intentionally out of scope under the current no-GPU markerPDF directive.
