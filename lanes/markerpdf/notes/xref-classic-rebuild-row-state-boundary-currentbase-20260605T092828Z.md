# markerPDF classic xref row-state boundary

Micro-slice: `markerpdf-classic-xref-rebuild-boundary-current-base-20260605T092828Z`

Session: `port-dev-markerpdf-xref-classic-rebuild-20260605T092828Z`

Base accepted HEAD: `58d9511dd8bc830eb17ad085a2d55060773fb172`

## Source Truth

Upstream markerPDF delegates searchable-PDF extraction through parser-backed `pdftext`/PDFium before OCR or model fallback. In the current no-GPU native PHP scope, classic xref repair is the dependency boundary for WordPress page text, XMP/Info metadata, EmbeddedFiles, and attachment preflight.

Classic xref table rows use fixed offset/generation fields and a free/in-use state token. A row state must terminate as a token; punctuation-suffixed states such as `n.bad` or `f.bad` are malformed and must not form a rebuilt current xref table.

## Behavior

`PdfTextExtractor`, `PdfMetadataExtractor`, `PdfEmbeddedFileExtractor`, and `PdfAttachmentExtractor` now require classic xref row states to be followed only by whitespace, end-of-line, or a `%` row comment. This preserves benign row comments while rejecting punctuation-suffixed malformed rows during damaged `startxref` classic-table rebuild.

The focused fixture keeps a valid current classic xref table for page text, XMP/Info metadata, and an EmbeddedFiles source attachment. It appends a newer decoy table whose rows all end in `.bad` and whose trailer points to decoy page, metadata, and attachment objects. Before the source patch, an in-memory red check selected `Decoy punctuation row page` and `Decoy Punctuation Row Title`. After the patch, WordPress import stays on `Current row-state xref page`, `Current Row-State XRef Title`, and `current-row-state-xref.xml`.

## Verification

Focused classic rebuild test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicRebuildBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects classic xref rows with punctuation state suffixes during rebuild before WordPress imports
1 test files, 428 assertions, 0 failures
```

Adjacent xref/parser/metadata family:

```text
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg 'Pdf(Xref|Parser).*Test\.php|PdfTextExtractorTest\.php|PdfMetadataExtractorTest\.php|PdfEmbeddedFileExtractorTest\.php|PdfAttachmentExtractorTest\.php' | sort)
Focused test run: 104 selected test files (root lock skipped)
104 test files, 5806 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-classic-rebuild-row-state-boundary-currentbase.php
```

Result: emitted Gutenberg paragraphs for `Current row-state xref page` and `Punctuation row skipped`; smoke metadata reported `uses_current_page_text=true`, `metadata_title_current=true`, `embedded_file_current=true`, `attachment_summary_current=true`, `rejects_punctuation_row_state_table=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness status: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted damaged `startxref` repair, stale valid `startxref` repair, EOF-bounded trailing xref garbage, comments, commented startxref, array/composite-contained xref and startxref decoys, name-token startxref, name-delimited xref, name-offset startxref, linearized hint-range startxref exclusion, malformed row missing fields, malformed trailing subsections, literal-string decoys, stream-owned trailers, forward `/Prev` repair, xref-stream, hybrid, object-stream, free-entry, or generation repair behavior.

The bounded new behavior is only classic xref row state-token termination during damaged `startxref` rebuild across text, metadata, EmbeddedFiles, and attachment preflight import paths.

## Dependency Closure

No new support component is needed. This reuses the native PHP direct-object scanner, classic xref parser, trailer/root selector, page text extractor, XMP/Info metadata extractor, EmbeddedFiles extractor, attachment preflight path, and WordPress smoke renderer. Live OCR, Surya/Texify/Torch model execution, PDFium rendering, external OCR/rendering helpers, and exact upstream model benchmark parity remain intentionally outside the current no-GPU markerPDF scope.
