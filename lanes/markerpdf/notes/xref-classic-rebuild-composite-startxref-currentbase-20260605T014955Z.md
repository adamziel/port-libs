# markerPDF classic xref rebuild composite startxref boundary

Micro-slice: `markerpdf-classic-xref-rebuild-boundary-current-base-20260605T014955Z`

Session: `port-dev-markerpdf-xref-classic-rebuild-20260605T014955Z`

Base accepted HEAD: `cc7fd13b239c01bb3ecb5d1e841d059e64608127`

## Source Truth

Upstream markerPDF delegates searchable PDF parsing through pdftext/PDFium before model stages. In this native no-GPU PHP lane, the xref parser owns the file-structure boundary: `startxref` is a top-level file trailer token and must not be accepted from inside PDF composite syntax such as arrays or dictionaries.

## Behavior

`PdfTextExtractor`, `PdfMetadataExtractor`, and `PdfEmbeddedFileExtractor` now skip `startxref` candidates whose token is owned by a top-level PDF composite token. The guard reuses each parser's existing comment, literal-string, hex-string, array, dictionary, and direct-object body scanners, so direct stream payload bytes do not hide valid top-level `startxref` tokens.

The metadata parser's composite skipper also now advances over array tokens using the full token length returned by its local `readPdfArrayAt()` helper instead of adding two extra bytes. That preserves existing array-contained xref decoy behavior while letting a following top-level `startxref` remain eligible.

The focused fixture has:

- a current classic xref table with a damaged `startxref 999999`;
- current page text, XMP/Info metadata, and EmbeddedFiles attachment rows;
- later stale page/metadata/attachment objects;
- an array-contained fake classic xref table plus an array-contained `startxref` pointing at that fake table.

Before the fix, the red probe selected stale visible text, stale XMP/Info, and the stale embedded file:

```json
{
    "lines": [
        "Composite startxref stale page",
        "Composite startxref leak"
    ],
    "title": "Composite Startxref Stale Title",
    "info_title": "Composite Startxref Stale Info",
    "file": "stale-composite.xml"
}
```

After the fix, the parser rebuilds from the current top-level classic table and ignores the composite-owned token.

## Verification

Focused xref rebuild test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicRebuildBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rebuilds damaged startxref from the latest classic xref trailer boundary before WordPress text extraction
PASS rebuilds stale but valid startxref from the later classic xref trailer boundary before WordPress text extraction
PASS bounds classic xref rebuild before trailing EOF garbage tables
PASS rebuilds stale classic startxref before EmbeddedFiles name-tree attachment import
PASS skips commented xref keywords during classic rebuild before metadata root selection
PASS skips commented startxref tokens before classic rebuild text metadata and attachment selection
PASS skips array-contained xref table decoys during classic rebuild before WordPress imports
PASS skips composite-contained startxref tokens before classic rebuild WordPress imports

1 test files, 135 assertions, 0 failures
```

Adjacent parser/xref/metadata checks:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicRebuildBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefOffsetOwnerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefCurrentBaseRepairBoundaryTest.php lanes/markerpdf/tests/PdfMetadataTrailerInfoNameTreeCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataTrailerIdLangViewerPreferenceCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php lanes/markerpdf/tests/PdfXrefTrailerEncryptPrevCurrentBaseTest.php
Focused test run: 7 selected test files (root lock skipped)
7 test files, 1095 assertions, 0 failures
```

Embedded/attachment checks:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfEmbeddedFileExtractorTest.php lanes/markerpdf/tests/PdfAttachmentExtractorTest.php lanes/markerpdf/tests/PdfAttachmentFileSpecAssociatedAFRelationshipChecksumCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentObjectStreamCurrentBaseTest.php
Focused test run: 4 selected test files (root lock skipped)
4 test files, 698 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-classic-xref-composite-startxref-currentbase.php
```

Result: emits two Gutenberg paragraphs for `Current composite startxref page` and `Composite startxref ignored`; smoke metadata reports `uses_current_page_text=true`, `skips_composite_startxref_token=true`, `metadata_title_current=true`, `embedded_file_current=true`, `excludes_decoy_metadata=true`, `excludes_decoy_attachment=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat invalid-`startxref` rebuild, stale-valid startxref repair, EOF-bounded xref garbage rejection, commented `startxref` rejection, commented `xref` keyword rejection, array-contained xref table keyword rejection, stream-owned xref offset rejection, xref-stream `/Prev` generation repair, hybrid `/XRefStm` ownership, object-stream carrier repair, or latest trailer root generation recovery. The bounded behavior is specifically startxref token ownership when the token itself is inside PDF composite syntax.

## Dependency Closure

No new support component is needed. This slice reuses the native direct-object scanner, PDF lexical token scanners, classic xref table parser, trailer parser, metadata extractor, embedded-file extractor, page-tree walker, stream decoder, and WordPress smoke renderer. GPU/model execution, live OCR, Surya/Torch layout/table models, Texify equation recognition, Streamlit/FastAPI model workers, pypdfium rendering parity, and external PDF tools remain intentionally out of scope for this no-GPU markerPDF lane.
