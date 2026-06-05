# markerPDF classic xref rebuild name-startxref boundary

Micro-slice: `markerpdf-classic-xref-rebuild-boundary-current-base-20260605T022214Z`

Session: `port-dev-markerpdf-xref-classic-rebuild-20260605T022214Z`

Base accepted HEAD: `81ae8aa0acd5a856ca43c67707b6fe6f933f1fbf`

## Source truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text extraction through `marker/pdf/extract_text.py` into pdftext/PDFium. The native PHP lane therefore owns the parser dependency boundary where PDF keyword tokens such as `startxref` select the current xref section before text, metadata, and attachment import.

PDF names such as `/startxref` are not `startxref` keywords. A tolerant classic xref rebuild may recover from a damaged numeric `startxref`, but it must not let a later top-level name token redirect the current trailer root to post-EOF or decoy classic xref tables.

## Behavior

`PdfTextExtractor`, `PdfMetadataExtractor`, and `PdfEmbeddedFileExtractor` now require regex-matched `startxref` candidates to also satisfy the native PDF keyword-boundary check. This rejects `/startxref` name-token decoys before classic xref rebuild chooses the latest valid top-level classic table.

The focused fixture appends:

- a current classic table with damaged `startxref 999999`;
- current catalog metadata and an EmbeddedFiles name-tree attachment;
- a later decoy classic table rooted at stale page, metadata, and attachment objects;
- a final `/startxref` PDF name token that points at the decoy table.

After the patch, WordPress import keeps the current page text, XMP/Info metadata, and current attachment while excluding the name-token decoy table.

## Verification

Focused current-base baseline before this slice:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicRebuildBoundaryCurrentBaseTest.php
```

Result:

```text
1 test files, 135 assertions, 0 failures
```

After adding the boundary:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicRebuildBoundaryCurrentBaseTest.php
```

Result:

```text
1 test files, 158 assertions, 0 failures
```

WordPress smoke:

```bash
php lanes/markerpdf/examples/wordpress-pdf-xref-classic-rebuild-name-startxref-currentbase.php
```

Result: emitted two Gutenberg paragraphs for `Current name-startxref page` and `Name startxref ignored`, with smoke booleans `uses_current_classic_trailer_root=true`, `keeps_current_metadata_root=true`, `keeps_current_info_root=true`, `imports_current_attachment=true`, `current_attachment_checksum_matches=true`, `excludes_name_startxref_page=true`, `excludes_name_startxref_metadata=true`, `excludes_name_startxref_attachment=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Adjacent extractor gate:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicRebuildBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php lanes/markerpdf/tests/PdfEmbeddedFileExtractorTest.php
```

Result:

```text
4 test files, 2041 assertions, 0 failures
```

Syntax and whitespace:

```bash
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/src/PdfMetadataExtractor.php
php -l lanes/markerpdf/src/PdfEmbeddedFileExtractor.php
php -l lanes/markerpdf/tests/PdfXrefClassicRebuildBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-xref-classic-rebuild-name-startxref-currentbase.php
git diff --check -- lanes/markerpdf
```

Result: all PHP files reported no syntax errors, and `git diff --check -- lanes/markerpdf` reported no whitespace errors.

## Non-overlap

This does not repeat accepted damaged-`startxref` latest classic rebuild, stale valid `startxref` rebuild, EOF-bounded trailing tables, commented `xref`/`startxref` skipping, array/composite-contained decoys, xref-stream `/Prev` generation repair, object-stream repair, stream-owned xref/startxref rejection, metadata trailer precedence, or attachment xref-chain generation repair. The new behavior is specifically `/startxref` PDF name-token rejection in the classic rebuild selector shared by text, metadata, and EmbeddedFiles import.

## Dependency closure

No new support component is needed. This slice reuses the native direct-object scanner, PDF keyword-boundary matcher, classic xref table/trailer parser, page-tree walker, metadata extractor, EmbeddedFiles name-tree extractor, stream decoder, and WordPress smoke path. GPU/model/OCR, pdftext, pypdfium2/PDFium, PIL, Surya/Torch, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external PDF tools were not run and remain intentionally out of scope for this no-GPU native PHP slice.
