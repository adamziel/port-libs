# markerPDF classic xref rebuild EOF boundary

Micro-slice: `markerpdf-classic-xref-rebuild-boundary-current-base-20260604T160858Z`

Session: `port-dev-markerpdf-xref-classic-rebuild-20260604T160858Z`

Base accepted HEAD: `c0868144ba60bde5110ecd6d5116db9601e1eb12`

## Source Truth

Upstream markerPDF delegates searchable-PDF text extraction through `marker/pdf/extract_text.py` into pdftext/PDFium. That leaves damaged `startxref` repair as a native PHP parser dependency boundary for this lane: tolerant classic xref rebuild can recover a valid latest table, but recovery must not cross the selected `startxref` / EOF boundary and let trailing garbage become the current document.

## Behavior

`PdfTextExtractor` and `PdfMetadataExtractor` now retain the byte offset of the selected `startxref` token and pass that as the upper bound for classic xref rebuild candidates. This preserves the accepted behavior for damaged `startxref 999999` and stale-but-valid pointers to earlier classic tables, while ignoring plausible-looking classic xref tables and trailers appended after `%%EOF`.

The focused fixture stores current catalog/page/text/XMP/Info objects before a damaged final `startxref`, then appends fake objects plus a fake classic xref table after `%%EOF`. Before the repair, WordPress text extraction selected the trailing fake root and emitted:

```text
Trailing garbage xref page
Post EOF root leak
```

After the repair, extraction and metadata review select only:

```text
Current EOF bounded page
Post EOF xref ignored
Current EOF Bounded XRef Title
Current EOF Bounded Info Title
```

## Verification

Red-first focused check before the source patch:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicRebuildBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rebuilds damaged startxref from the latest classic xref trailer boundary before WordPress text extraction
PASS rebuilds stale but valid startxref from the later classic xref trailer boundary before WordPress text extraction
FAIL bounds classic xref rebuild before trailing EOF garbage tables (lanes/markerpdf/tests/PdfXrefClassicRebuildBoundaryCurrentBaseTest.php)
Values are not identical
Expected: array (
  0 => 'Current EOF bounded page',
  1 => 'Post EOF xref ignored',
)
Actual: array (
  0 => 'Trailing garbage xref page',
  1 => 'Post EOF root leak',
)

1 test files, 19 assertions, 1 failures
```

Focused check after the source patch:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicRebuildBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rebuilds damaged startxref from the latest classic xref trailer boundary before WordPress text extraction
PASS rebuilds stale but valid startxref from the later classic xref trailer boundary before WordPress text extraction
PASS bounds classic xref rebuild before trailing EOF garbage tables

1 test files, 30 assertions, 0 failures
```

Adjacent xref/parser/metadata family:

```text
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg 'Pdf(Xref|Parser).*Test\.php|PdfTextExtractorTest\.php|PdfMetadataExtractorTest\.php' | sort)
Focused test run: 81 selected test files (root lock skipped)
81 test files, 2862 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-classic-rebuild-boundary-currentbase.php
```

Result: emitted two Gutenberg paragraphs for `Current EOF bounded page` and `Post EOF xref ignored`, with metadata booleans `uses_current_classic_trailer_root=true`, `keeps_current_metadata_root=true`, `keeps_current_info_root=true`, `excludes_post_eof_xref_page=true`, `excludes_post_eof_root_leak=true`, `excludes_post_eof_metadata=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Syntax and whitespace checks:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/src/PdfMetadataExtractor.php
php -l lanes/markerpdf/tests/PdfXrefClassicRebuildBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-xref-classic-rebuild-boundary-currentbase.php
git diff --check -- lanes/markerpdf
```

All passed.

## Non-Overlap

This does not repeat the accepted invalid `startxref 999999` classic rebuild slice, stale valid startxref-to-earlier-table repair, xref-stream `/Prev` generation repair, hybrid `/XRefStm` ownership, object-stream carrier generation repair, latest trailer root generation recovery, metadata-side nonzero generation repair, stream-owned fake xref offset rejection, xref-stream filter DecodeParms handling, or object-stream filter-chain operand recovery.

The bounded behavior here is specifically classic-table rebuild candidate selection before the selected `startxref` token so post-EOF classic xref/trailer garbage cannot become the current WordPress text or metadata root.

## Dependency Closure

No new support component is needed. This slice reuses the native direct-object scanner, selected-startxref parser, classic xref table parser, trailer parser, metadata extractor, page-tree walker, stream decoder, text-token extractor, and WordPress smoke renderer. Full upstream model parity remains dependency-gated by pdftext/PDFium, Surya/Torch OCR/layout/table models, Texify equation recognition, Streamlit/FastAPI model workers, benchmark/model downloads, and GPU/model execution; none were run for this no-GPU native PHP slice.
