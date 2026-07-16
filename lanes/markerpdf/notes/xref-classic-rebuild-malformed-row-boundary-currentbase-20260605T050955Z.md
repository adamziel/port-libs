# markerPDF classic xref rebuild malformed row boundary

Micro-slice: `markerpdf-classic-xref-rebuild-boundary-current-base-20260605T050955Z`

Session: `port-dev-markerpdf-xref-classic-rebuild-20260605T050955Z`

Base accepted HEAD: `de3977d12ff1d59781a2e8ab61ab27832f03b3f6`

## Source Truth

Upstream markerPDF delegates searchable PDF text extraction through `marker/pdf/extract_text.py` into pdftext/PDFium. For the native PHP lane, classic xref rebuild is therefore a parser boundary: a damaged `startxref` can be repaired to a later well-formed top-level classic xref table, but a malformed table-shaped candidate must not partially supply a trailer/root for WordPress imports.

## Behavior

`PdfTextExtractor`, `PdfMetadataExtractor`, `PdfEmbeddedFileExtractor`, and `PdfAttachmentExtractor` now reject classic xref table candidates whose declared subsection contains malformed nonblank rows or ends before the declared row count is satisfied. Whitespace between rows remains tolerated.

The focused fixture appends:

- a current well-formed classic xref table rooted at object `1`;
- a later table-shaped decoy rooted at object `20`;
- one malformed nonblank row inside the decoy's declared `20 12` subsection;
- a damaged final `startxref 999999`.

Before the fix, damaged-startxref rebuild selected the partial decoy table and emitted `Malformed-row decoy page` / `Partial xref row leak`. After the fix, the malformed table is ineligible and extraction preserves `Current malformed-row xref page` / `Malformed rebuild table skipped`, current XMP/Info metadata, current EmbeddedFiles, and current attachment preflight summaries.

## Verification

Red-first focused check before the source patch:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicRebuildBoundaryCurrentBaseTest.php
```

Result:

```text
1 test files, 231 assertions, 1 failures
```

Focused check after the source patch:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicRebuildBoundaryCurrentBaseTest.php
```

Result:

```text
1 test files, 256 assertions, 0 failures
```

Adjacent xref family:

```bash
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg 'Pdf(Xref|ParserXref|ParserStreamDictionaryXref|ParserStreamFilterXref|ParserStreamLengthStartxref|ParserTrailerXref|NamedDestinationXref|OutlineMetadataXref)' | sort)
```

Result:

```text
68 test files, 1537 assertions, 0 failures
```

WordPress smoke:

```bash
php lanes/markerpdf/examples/wordpress-pdf-classic-xref-rebuild-malformed-row-currentbase.php
```

Result: emitted Gutenberg paragraphs for the current malformed-row xref import and metadata booleans `malformed_table_rejected=true`, `current_classic_xref_import_kept=true`, `partial_xref_candidate_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted classic xref rebuild selection, stale valid startxref repair, EOF-bounded rebuild, commented xref/startxref rejection, array/composite/name-token decoy rejection, name-offset repair, `/Prev` incremental update repair, xref-stream generation repair, hybrid xref ownership, object-stream repair, trailer metadata precedence, or stream-owned fake xref rejection. The new behavior is specifically malformed row rejection inside a declared classic xref subsection during damaged-startxref rebuild.

## Dependency Closure

No new support component is needed. This slice reuses the native direct-object scanner, classic xref table parser, trailer parser, page-tree walker, metadata extractor, embedded-file extractor, attachment preflight, and WordPress smoke path. Full upstream markerPDF parity remains gated by pdftext/PDFium, Surya/Torch OCR/layout/table models, Texify equation recognition, Streamlit/FastAPI model workers, benchmark/model downloads, and GPU/model execution; none were run for this no-GPU native PHP slice.
