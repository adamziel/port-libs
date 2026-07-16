# markerPDF classic xref rebuild array-decoy boundary current-base

Micro-slice: `markerpdf-classic-xref-rebuild-boundary-current-base-20260605T011635Z`

Session: `port-dev-markerpdf-xref-classic-rebuild-20260605T011635Z`

Base accepted HEAD: `eb4138334533440c812a776581c0e24e758e3656`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable-PDF text and document metadata through parser-backed pdftext/PDFium object loading before WordPress-facing conversion. Under the current no-GPU markerPDF scope, the native PHP lane owns the equivalent xref repair boundary for searchable text, XMP/Info metadata, and EmbeddedFiles review without running OCR, models, Python workers, PDFium, or external PDF tools.

Classic xref table rebuild must only select top-level `xref ... trailer <<...>>` sections. Bytes that spell `xref` and `trailer` inside PDF arrays or dictionaries are composite value data, not rebuild candidates.

## Behavior

`PdfTextExtractor`, `PdfMetadataExtractor`, and `PdfEmbeddedFileExtractor` now skip composite PDF array and dictionary tokens while scanning for classic xref table and trailer keywords. This keeps malformed array-contained xref table decoys from replacing the latest top-level xref table when the final `startxref` is damaged.

The focused fixture appends valid current page text, XMP/Info metadata, and an EmbeddedFiles source attachment through a top-level classic xref table. It then appends newer decoy objects and places a syntactically table-shaped `xref ... trailer` block inside a top-level array before the final damaged `startxref`. The repaired path selects the current WordPress paragraph text, metadata, and attachment while excluding the array decoy root.

## Evidence

Red-first focused run after adding the fixture and before the parser patch:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicRebuildBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL skips array-contained xref table decoys during classic rebuild before WordPress imports (lanes/markerpdf/tests/PdfXrefClassicRebuildBoundaryCurrentBaseTest.php)
Values are not identical
Expected: array (
  0 => 'Current array-bounded page',
  1 => 'Array xref skipped',
)
Actual: array (
  0 => 'Array decoy xref page',
  1 => 'Array root leak',
)

1 test files, 92 assertions, 1 failures
```

Final focused run:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicRebuildBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
7 PASS cases
1 test files, 112 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-classic-xref-rebuild-import.php
```

emits `paragraphs=["Current Classic XRef Import","Array decoy ignored"]`, `metadata_title="Current Classic XRef Import"`, `embedded_file="current-classic-xref.xml"`, `classic_xref_table_repaired=true`, `composite_xref_decoy_skipped=true`, `array_decoy_stream_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness status: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted damaged `startxref`, stale valid `startxref`, EOF-bounded trailing garbage, comment-prefixed xref, commented startxref, EmbeddedFiles stale classic pointer, `/Prev` chain, xref-stream, object-stream, hybrid, free-entry, or generation repair behavior.

The bounded new behavior is specifically classic xref rebuild candidate scanning across composite PDF array/dictionary token boundaries before WordPress text, metadata, and attachment import.

## Dependency Closure

No new support component is needed. This reuses native PHP direct-object scanning, classic xref table parsing, trailer/root selection, page text extraction, XMP/Info metadata extraction, EmbeddedFiles name-tree extraction, and the existing WordPress smoke renderer. Full upstream model/OCR/runtime parity remains out of scope under the current no-GPU markerPDF directive and dependency-gated by `pdftext`, `pypdfium2`/PDFium, Surya/Torch, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers.
