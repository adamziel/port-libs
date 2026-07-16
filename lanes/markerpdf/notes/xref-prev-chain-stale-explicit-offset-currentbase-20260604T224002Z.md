# markerPDF xref Prev-chain stale explicit-offset repair

Micro-slice: `markerpdf-xref-prev-chain-incremental-update-current-base-20260604T224002Z`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable-PDF text extraction through `marker/pdf/extract_text.py` into pdftext/PDFium-backed parsing. Under the current no-GPU markerPDF scope, the PHP lane owns the native xref parser boundary that selects current page, metadata, and attachment objects before WordPress import review.

PDF incremental updates can append replacement same-generation direct objects after a previous xref section, then publish a latest xref stream with `/Prev`. If a latest in-use row still points at an old pre-`/Prev` direct-object offset, that offset resolves to a real object but not the current update-span storage. The current update body between `/Prev` and the latest xref stream must win when it matches the same object number and generation.

## Implementation

`PdfTextExtractor`, `PdfMetadataExtractor`, and `PdfEmbeddedFileExtractor` now distinguish current-span offset owners from stale pre-`/Prev` offset owners. For latest xref-table or xref-stream in-use rows:

- an offset that resolves to a direct object inside the current update span is still treated as valid;
- an offset that resolves only to older pre-`/Prev` storage is repaired when a same object/generation body exists after `/Prev` and before the latest xref section;
- unresolved damaged offsets keep using the existing current-update repair path;
- rows without a matching current update body remain bounded and do not reinterpret unrelated objects.

The focused fixture keeps stale generation-zero catalog, page text, Info metadata, and EmbeddedFiles in a previous classic xref table, appends same-generation replacements, then emits a latest xref stream whose rows explicitly point back at the stale offsets. WordPress import now selects current text, current Info/catalog language, and the current attachment while excluding stale previous-section storage.

## Evidence

Red baseline after adding the focused case:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainIncrementalUpdateCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL repairs same-generation current update objects when xref-stream Prev rows point at stale explicit offsets
Expected: array (
  0 => 'Current valid-offset Prev page',
  1 => 'Stale offset repaired',
)
Actual: array (
  0 => 'Stale valid-offset Prev page',
)
1 test files, 89 assertions, 1 failures
```

Focused green after repair:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainIncrementalUpdateCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
7 PASS cases
1 test files, 105 assertions, 0 failures
```

Adjacent xref/object-stream gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXref*Test.php lanes/markerpdf/tests/PdfParserXref*Test.php lanes/markerpdf/tests/PdfObjectStream*Test.php lanes/markerpdf/tests/PdfParserObjectStream*Test.php
Focused test run: 58 selected test files (root lock skipped)
58 test files, 1001 assertions, 0 failures
```

Metadata/embedded-file gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadata*Test.php lanes/markerpdf/tests/PdfEmbedded*Test.php
Focused test run: 17 selected test files (root lock skipped)
17 test files, 1894 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-prev-chain-incremental-update-currentbase.php
```

The smoke emits `stale_explicit_offset_current_info_selected=true`, `stale_explicit_offset_current_language_selected=true`, `stale_explicit_offset_current_text_selected=true`, `stale_explicit_offset_current_attachment_selected=true`, `stale_explicit_offset_previous_storage_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness status: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted zero-offset xref-stream repair, latest classic xref-table zero-offset repair, malformed `/Index` direct-offset owner repair, generation-mismatch metadata guards, trailer `/Root` generation repair for EmbeddedFiles, hybrid `/XRefStm` precedence, classic xref rebuild startxref recovery, object-stream carrier generation repair, or object-stream `/Prev` free-carrier repair.

The bounded behavior here is specifically latest xref-stream `/Prev` in-use rows whose explicit offsets resolve to valid but stale pre-`/Prev` direct objects while current same-generation update bodies exist before the latest xref stream.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP direct-object scanner, xref table parser, xref-stream decoder, `/Prev` chain merger, Flate stream decoder, text extractor, metadata extractor, embedded-file extractor, and WordPress smoke renderer. Full upstream model parity remains dependency-gated by `pdftext`, `pypdfium2`/PDFium, Surya/Torch, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers; none were executed here.
