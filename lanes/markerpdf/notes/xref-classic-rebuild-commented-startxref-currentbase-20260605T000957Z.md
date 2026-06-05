# markerPDF classic xref rebuild commented startxref boundary

Micro-slice: `markerpdf-classic-xref-rebuild-boundary-current-base-20260605T000957Z`

Session: `port-dev-markerpdf-xref-classic-rebuild-20260605T000957Z`

Base accepted HEAD: `e5899325ac444d1e8dee1e7e7294afaa8b7e8ca7`

## Source Truth

Upstream markerPDF delegates searchable-PDF text extraction through `marker/pdf/extract_text.py` into pdftext/PDFium. In this native PHP no-GPU lane, the parser owns the PDF boundary before WordPress import: PDF comments begin with `%` and run to end-of-line, so a `startxref` token inside a comment must not select or move a classic xref rebuild window.

## Behavior

`PdfTextExtractor`, `PdfMetadataExtractor`, and `PdfEmbeddedFileExtractor` now preserve their existing regex-based latest `startxref` selection, direct-object guards, and xref-stream behavior, but skip any candidate whose `startxref` token begins after `%` on the same PDF line.

The focused fixture has a damaged real `startxref` after a current classic xref table, then appends post-EOF decoy page, XMP/Info metadata, EmbeddedFiles entries, and a decoy classic table. A final PDF comment line contains `% startxref` followed by the decoy table offset. Before the patch, that commented token selected the decoy table and imported `Commented startxref decoy page`. After the patch, text, metadata, and attachment extraction rebuild from the latest real classic table before the real startxref token.

## Verification

Red-first focused check before the source patch:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicRebuildBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rebuilds damaged startxref from the latest classic xref trailer boundary before WordPress text extraction
PASS rebuilds stale but valid startxref from the later classic xref trailer boundary before WordPress text extraction
PASS bounds classic xref rebuild before trailing EOF garbage tables
PASS rebuilds stale classic startxref before EmbeddedFiles name-tree attachment import
PASS skips commented xref keywords during classic rebuild before metadata root selection
FAIL skips commented startxref tokens before classic rebuild text metadata and attachment selection (lanes/markerpdf/tests/PdfXrefClassicRebuildBoundaryCurrentBaseTest.php)
Values are not identical
Expected: array (
  0 => 'Current commented startxref page',
  1 => 'Commented startxref ignored',
)
Actual: array (
  0 => 'Commented startxref decoy page',
  1 => 'Post EOF startxref leak',
)

1 test files, 60 assertions, 1 failures
```

Focused check after the source patch:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicRebuildBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rebuilds damaged startxref from the latest classic xref trailer boundary before WordPress text extraction
PASS rebuilds stale but valid startxref from the later classic xref trailer boundary before WordPress text extraction
PASS bounds classic xref rebuild before trailing EOF garbage tables
PASS rebuilds stale classic startxref before EmbeddedFiles name-tree attachment import
PASS skips commented xref keywords during classic rebuild before metadata root selection
PASS skips commented startxref tokens before classic rebuild text metadata and attachment selection

1 test files, 80 assertions, 0 failures
```

Adjacent xref/parser/metadata/embedded family:

```text
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg 'Pdf(Xref|Parser).*Test\.php|PdfTextExtractorTest\.php|PdfMetadataExtractorTest\.php|PdfEmbeddedFileExtractorTest\.php|PdfAttachmentExtractorTest\.php' | sort)
Focused test run: 87 selected test files (root lock skipped)
87 test files, 3793 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-classic-rebuild-commented-startxref-currentbase.php
```

Result: emitted two Gutenberg paragraphs for `Current commented startxref page` and `Commented startxref ignored`, with metadata booleans `uses_current_classic_trailer_root=true`, `keeps_current_metadata_root=true`, `keeps_current_info_root=true`, `imports_current_attachment=true`, `current_attachment_checksum_matches=true`, `excludes_commented_startxref_page=true`, `excludes_commented_startxref_metadata=true`, `excludes_commented_startxref_attachment=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Syntax checks:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/src/PdfMetadataExtractor.php
php -l lanes/markerpdf/src/PdfEmbeddedFileExtractor.php
php -l lanes/markerpdf/tests/PdfXrefClassicRebuildBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-xref-classic-rebuild-commented-startxref-currentbase.php
```

All passed.

Whitespace check:

```text
git diff --check -- lanes/markerpdf
```

Passed with no output.

## Non-Overlap

This does not repeat accepted invalid-`startxref` classic rebuild, stale valid startxref-to-later-classic-table repair, EOF-bounded post-EOF xref rejection, EmbeddedFiles selection through stale startxref repair, metadata-side commented `xref`/`trailer` table scanning, xref-stream `/Prev` generation repair, hybrid `/XRefStm` ownership, object-stream carrier generation repair, or latest trailer root generation recovery.

The bounded behavior here is specifically selecting the latest real `startxref` token when a later PDF comment line contains `startxref` plus a decoy classic table offset.

## Dependency Closure

No new support component is needed. This slice reuses the native direct-object scanner, startxref/xref table parser, trailer parser, metadata extractor, embedded-file extractor, page-tree walker, stream decoder, text-token extractor, and WordPress smoke renderer. Full upstream markerPDF parity remains gated by pdftext/PDFium, Surya/Torch OCR/layout/table models, Texify equation recognition, Streamlit/FastAPI model workers, benchmark/model downloads, and GPU/model execution; none were run for this no-GPU native PHP slice.
