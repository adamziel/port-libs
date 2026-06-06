# markerpdf classic xref rebuild plus-header current-base slice

Slice: `markerpdf-classic-xref-rebuild-boundary-current-base-20260606T040621Z`

Accepted base: `aacd91f0c62d29521f76ed00e1ea16c126d3b35d`

## Behavior

The native PHP classic xref-table rebuild path now accepts non-negative
plus-signed subsection headers such as `+20 +12` while keeping individual xref
rows strict fixed-width numeric `offset generation n/f` records.

This matters for damaged or stale `startxref` imports: when the latest classic
xref table uses plus-signed subsection header integers, the rebuild scan can now
select the current trailer root before WordPress page text, XMP/Info metadata,
and EmbeddedFiles/attachment preflight. The stale `/Prev` table remains
available only as the prior incremental section and does not leak stale page
text, stale metadata, or stale attachment names into the import.

## Source Truth

Upstream markerPDF delegates searchable PDF text extraction to native PDF
parsers/PDFium, where xref reconstruction is parser-owned. Under this no-GPU
markerPDF lane, the native PHP parser owns classic xref-table recovery for
searchable PDFs. PDF integer syntax permits a leading plus sign for
non-negative integers, so the bounded port behavior accepts `+` on classic
subsection `first object` and `count` integers only.

## Evidence

Red-first static probe before the source edit built a PDF with a stale previous
classic table, a current table headed by `+10 +6`, and a damaged final
`startxref`; extraction selected `Stale plus-header page`, metadata title was
`NULL`, and the latest object offset was skipped.

Focused verification after the source edit:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/src/PdfMetadataExtractor.php
php -l lanes/markerpdf/src/PdfEmbeddedFileExtractor.php
php -l lanes/markerpdf/src/PdfAttachmentExtractor.php
php -l lanes/markerpdf/src/PdfPagePropertyExtractor.php
php -l lanes/markerpdf/src/PdfXrefFreeObjectMap.php
php -l lanes/markerpdf/tests/PdfXrefClassicRebuildPlusHeaderBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-xref-classic-rebuild-plus-header-currentbase.php

php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicRebuildPlusHeaderBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rebuilds damaged classic startxref with plus-signed subsection header before current import
1 test files, 40 assertions, 0 failures

php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicRebuildPlusHeaderBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefClassicRebuildBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefClassicMalformedStartxrefBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefClassicZeroCountRebuildBoundaryCurrentBaseTest.php
Focused test run: 4 selected test files (root lock skipped)
4 test files, 762 assertions, 0 failures

php lanes/markerpdf/examples/wordpress-pdf-xref-classic-rebuild-plus-header-currentbase.php
Smoke emitted current WordPress paragraphs and flags: plus_signed_subsection_header_accepted=true, uses_current_classic_trailer_root=true, keeps_current_metadata_root=true, imports_current_attachment=true, stale_previous_xref_excluded=true, executes_python_or_models=false, executes_external_pdf_tools=false.
```

## Non-Overlap

This slice does not alter signed `startxref` operands, zero-count subsections,
PDF whitespace handling, comment-delimited tables, commented `startxref`
keywords, malformed row repair, row-state repair, overdeclared counts,
unterminated dictionaries or literal strings, stream-trailer decoys, forward
`/Prev` handling, xref streams, object-stream repair, OCR/model execution, or
external PDF tool behavior.

## Dependency Closure

No new support component is needed. The patch reuses the existing native PHP
parser, metadata extractor, embedded-file extractor, attachment preflight, and
WordPress smoke pattern. GPU/model OCR, Surya/Texify/Torch, PDFium parity runs,
and external PDF tools remain intentionally out of scope for this lane.
