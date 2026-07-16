# markerPDF classic xref literal-string boundary current-base

Micro-slice: `markerpdf-classic-xref-rebuild-boundary-current-base-20260605T062136Z`

Session: `port-dev-markerpdf-xref-classic-rebuild-20260605T062136Z`

Base accepted HEAD: `5c4de3577dc9c01a4a6de93e42f5472d4ba8d811`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable-PDF extraction through `marker/pdf/extract_text.py` into parser-backed pdftext/PDFium behavior before OCR/model fallback. In this no-GPU native PHP lane, classic xref repair is a parser dependency boundary before WordPress import.

PDF literal strings are scalar tokens. A direct `startxref` numeric offset that lands on bytes spelling `xref` inside a literal string must not select that string body as a classic xref table, even if the following bytes look like valid subsection rows and a trailer.

## Behavior

`PdfTextExtractor`, `PdfMetadataExtractor`, `PdfEmbeddedFileExtractor`, and `PdfAttachmentExtractor` now reject `xrefTableSectionAt()` candidates when the direct offset lands inside a PDF comment, literal string, hex string, array, or dictionary. The existing rebuild scan already skipped those token bodies; this slice closes the direct-offset path used when the final `startxref` value itself points into a string-contained fake table.

The focused fixture appends:

- a current top-level classic xref table rooted at current page text, XMP/Info metadata, and an EmbeddedFiles source attachment;
- later decoy page, metadata, Filespec, and EmbeddedFile objects;
- a literal string containing `xref ... trailer << /Root 20 0 R /Info 27 0 R >>`;
- a final `startxref` whose numeric value points at the `xref` bytes inside that literal string.

Before the patch, `PdfTextExtractor` selected `Literal xref decoy page` and `String xref root leak`. After the patch, all four import paths keep the current page text, metadata, and attachment while excluding the string-contained decoy root.

## Verification

Red-first focused run before the source change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicRebuildBoundaryCurrentBaseTest.php
FAIL skips literal-string xref table decoys when startxref points inside a PDF string before WordPress imports
Expected: ['Current literal-string xref page', 'Literal xref decoy skipped']
Actual: ['Literal xref decoy page', 'String xref root leak']
1 test files, 288 assertions, 1 failures
```

Focused run after the source change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicRebuildBoundaryCurrentBaseTest.php
1 test files, 313 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-classic-xref-literal-string-boundary-currentbase.php
```

Result: exited `0` and emitted `literal_string_xref_decoy_skipped=true`, `current_classic_xref_import_kept=true`, `decoy_import_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness status: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted damaged/stale classic startxref rebuild, EOF-bounded trailing xref garbage, comment-contained xref skipping, array/composite xref decoys, `/startxref` name-token rejection, `xref/Decoy` name-delimited pseudo-tables, linearized hint-range startxref exclusion, malformed row rejection, xref-stream `/Prev` generation repair, object-stream repair, metadata trailer precedence, or attachment xref-chain generation repair.

The bounded behavior is specifically direct `startxref` offsets into PDF literal-string scalar tokens during classic xref table selection.

## Dependency Closure

No new support component is needed. This slice reuses the native direct-object scanner, PDF token boundary scanners, classic xref table/trailer parser, page-tree walker, metadata extractor, EmbeddedFiles name-tree extractor, attachment preflight path, stream decoder, and WordPress smoke renderer. GPU/model/OCR, pdftext execution, pypdfium2/PDFium execution, PIL, Surya/Torch, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external PDF tools were not run and remain intentionally out of scope for this no-GPU native PHP slice.
