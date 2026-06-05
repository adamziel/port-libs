# markerPDF classic xref rebuild name-delimited boundary

Micro-slice: `markerpdf-classic-xref-rebuild-boundary-current-base-20260605T032856Z`

Session: `port-dev-markerpdf-xref-classic-rebuild-20260605T032856Z`

Base accepted HEAD: `9c236555f1b2ca3e0d63b5c5e217c1306139dab6`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF extraction through `marker/pdf/extract_text.py` into `pdftext`/PDFium: <https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py>. The no-GPU native PHP lane owns the parser dependency boundary before WordPress import.

PDF classic xref tables begin with the `xref` keyword as a table header. A name-delimited pseudo-token such as `xref/Decoy` is not a classic xref table header and must not become the latest repair table when `startxref` is damaged.

## Behavior

`PdfTextExtractor`, `PdfMetadataExtractor`, and `PdfEmbeddedFileExtractor` now require whitespace after the classic `xref` keyword before parsing subsection rows. This preserves valid `xref\n... trailer` repair while rejecting top-level `xref/Decoy` pseudo-tables before text, metadata, and EmbeddedFiles selection.

The focused fixture appends:

- a current classic xref table rooted at current page text, XMP/Info metadata, and an EmbeddedFiles name-tree attachment;
- a later top-level `xref/Decoy` pseudo-header followed by plausible xref rows and a decoy trailer;
- a damaged final `startxref 999999`.

Before the patch, the current base accepted the pseudo-table and imported the decoy page root. After the patch, WordPress import keeps the current page text, metadata, and attachment while excluding decoy page, metadata, and attachment objects.

## Verification

Red-first focused run before the source change:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicRebuildBoundaryCurrentBaseTest.php
```

Result:

```text
FAIL skips name-delimited xref pseudo-tables before classic rebuild WordPress imports
Expected: ['Current name-delimited xref page', 'Name-delimited xref ignored']
Actual: ['Name-delimited xref decoy page', 'Delimited xref root leak']
1 test files, 161 assertions, 1 failures
```

Focused run after the source change:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicRebuildBoundaryCurrentBaseTest.php
```

Result:

```text
1 test files, 181 assertions, 0 failures
```

Adjacent xref/extractor family:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfXref*Test.php lanes/markerpdf/tests/PdfTextExtractorTest.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php lanes/markerpdf/tests/PdfEmbeddedFileExtractorTest.php
```

Result:

```text
50 test files, 2946 assertions, 0 failures
```

WordPress smoke:

```bash
php lanes/markerpdf/examples/wordpress-pdf-xref-classic-rebuild-name-delimited-currentbase.php
```

Result: emitted Gutenberg paragraphs for `Current name-delimited xref page` and `Name-delimited xref ignored`, with smoke booleans `uses_current_page_text=true`, `skips_name_delimited_xref_table=true`, `metadata_title_current=true`, `embedded_file_current=true`, `excludes_decoy_metadata=true`, `excludes_decoy_attachment=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Syntax and whitespace:

```bash
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/src/PdfMetadataExtractor.php
php -l lanes/markerpdf/src/PdfEmbeddedFileExtractor.php
php -l lanes/markerpdf/tests/PdfXrefClassicRebuildBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-xref-classic-rebuild-name-delimited-currentbase.php
git diff --check -- lanes/markerpdf
```

Result: all changed PHP files reported no syntax errors, and `git diff --check -- lanes/markerpdf` reported no whitespace errors.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted damaged-`startxref` latest classic rebuild, stale valid `startxref` rebuild, EOF-bounded trailing table rejection, commented `xref`/`startxref` skipping, array-contained xref decoys, composite-contained `startxref` decoys, `/startxref` name-token rejection, stream-owned xref/startxref rejection, xref-stream `/Prev` generation repair, object-stream repair, metadata trailer precedence, or attachment xref-chain generation repair.

The bounded behavior is specifically classic xref table header validation for name-delimited pseudo-tokens such as `xref/Decoy`.

## Dependency Closure

No new support component is needed. This slice reuses the native direct-object scanner, PDF keyword-boundary matcher, classic xref table/trailer parser, page-tree walker, metadata extractor, EmbeddedFiles name-tree extractor, stream decoder, and WordPress smoke renderer. GPU/model/OCR, pdftext execution, pypdfium2/PDFium execution, PIL, Surya/Torch, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external PDF tools were not run and remain intentionally out of scope for this no-GPU native PHP slice.
