# markerPDF classic xref rebuild comment boundary

Micro-slice: `markerpdf-classic-xref-rebuild-boundary-current-base-20260604T233737Z`

Session: `port-dev-markerpdf-xref-classic-rebuild-20260604T233737Z`

Base accepted HEAD: `cb23e2485cd549063944df19de56bf77da035ccd`

## Source Truth

Upstream markerPDF delegates searchable-PDF extraction through `marker/pdf/extract_text.py` into pdftext/PDFium. The native PHP lane therefore owns the parser boundary where damaged `startxref` classic-table rebuild chooses the current xref/trailer root before WordPress text and metadata import. PDF comments begin with `%` and must not supply `xref` or `trailer` keywords.

## Behavior

`PdfMetadataExtractor` now uses token-aware classic xref table and trailer scanning when rebuilding around a damaged `startxref`, matching the existing `PdfTextExtractor` behavior. Candidate `xref` and `trailer` tokens inside PDF comments, literal strings, and hex strings are skipped before the metadata root is selected.

The focused fixture keeps a valid current classic xref table rooted at current page/XMP/Info objects, then appends decoy objects and a comment line whose text is `% xref` followed by plausible xref rows and a trailer rooted at the decoy metadata. The final `startxref` is damaged. Text extraction already selected the current page; before this patch, metadata rebuilt through the commented `xref` token and reported `Comment XRef Decoy Title`. After the patch, metadata stays on `Current Comment-Bounded XRef Title` and `Current Comment-Bounded Info Title`.

## Verification

Red-first focused check before the source patch:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicRebuildBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rebuilds damaged startxref from the latest classic xref trailer boundary before WordPress text extraction
PASS rebuilds stale but valid startxref from the later classic xref trailer boundary before WordPress text extraction
PASS bounds classic xref rebuild before trailing EOF garbage tables
PASS rebuilds stale classic startxref before EmbeddedFiles name-tree attachment import
FAIL skips commented xref keywords during classic rebuild before metadata root selection (lanes/markerpdf/tests/PdfXrefClassicRebuildBoundaryCurrentBaseTest.php)
Values are not identical
Expected: 'Current Comment-Bounded XRef Title'
Actual: 'Comment XRef Decoy Title'

1 test files, 51 assertions, 1 failures
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

1 test files, 57 assertions, 0 failures
```

Adjacent xref/parser/metadata family:

```text
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg 'Pdf(Xref|Parser).*Test\.php|PdfTextExtractorTest\.php|PdfMetadataExtractorTest\.php' | sort)
Focused test run: 82 selected test files (root lock skipped)
82 test files, 3021 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-classic-rebuild-comment-boundary-currentbase.php
```

Result: emitted two Gutenberg paragraphs for `Current comment bounded page` and `Comment xref ignored`, with metadata booleans `uses_current_classic_trailer_root=true`, `keeps_current_metadata_root=true`, `keeps_current_info_root=true`, `excludes_commented_xref_page=true`, `excludes_commented_xref_metadata=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Syntax checks:

```text
php -l lanes/markerpdf/src/PdfMetadataExtractor.php
php -l lanes/markerpdf/tests/PdfXrefClassicRebuildBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-xref-classic-rebuild-comment-boundary-currentbase.php
```

All passed.

Whitespace check:

```text
git diff --check -- lanes/markerpdf
```

Passed with no output.

## Non-Overlap

This does not repeat accepted invalid-`startxref` classic rebuild, stale valid startxref-to-later-classic-table repair, EOF-bounded post-EOF xref rejection, EmbeddedFiles selection through stale startxref repair, parser comment-only trailer dictionaries in `PdfTextExtractor`, xref-stream `/Prev` generation repair, hybrid `/XRefStm` ownership, object-stream carrier generation repair, or latest trailer root generation recovery.

The bounded behavior here is specifically metadata-side classic rebuild candidate selection when an otherwise plausible latest xref table begins at an `xref` keyword inside a PDF comment before the selected damaged `startxref` token.

## Dependency Closure

No new support component is needed. This slice reuses the native direct-object scanner, classic xref table parser, trailer parser, metadata extractor, page-tree walker, stream decoder, text-token extractor, and WordPress smoke renderer. Full upstream markerPDF parity remains gated by pdftext/PDFium, Surya/Torch OCR/layout/table models, Texify equation recognition, Streamlit/FastAPI model workers, benchmark/model downloads, and GPU/model execution; none were run for this no-GPU native PHP slice.
