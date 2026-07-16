# markerPDF xref-stream Prev hybrid generation recovery current-base

Micro-slice: `xref-stream-prev-hybrid-generation-recovery-currentbase`
Session: `port-dev-markerpdf-xref67-20260602T214849Z`
Base accepted HEAD: `46b872b82e6663ed85da04f0c1274e2577b1e5b5`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes page text extraction through `marker/pdf/extract_text.py`: `get_text_blocks()` delegates parser-selected text dictionaries to `pdftext.extraction.dictionary_output(...)`, and `naive_get_text()` delegates bounded page text to pypdfium. Source: <https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py>

Relevant dependency behavior is PDFium parser behavior: it starts from `startxref`, loads cross-reference tables/streams, follows previous sections, treats hybrid `/XRefStm` rows as update-section input, and parses object-stream members through selected object-stream carriers. Sources: <https://pdfium.googlesource.com/pdfium/+/refs/heads/main/core/fpdfapi/parser/cpdf_parser.cpp> and <https://pdfium.googlesource.com/pdfium/+/refs/heads/main/core/fpdfapi/parser/cpdf_object_stream.cpp>

## Behavior

The focused fixture builds:

- a previous hybrid xref table whose companion `/XRefStm` maps page object `4` as a type-2 member of carrier object stream `6`;
- a latest xref stream with `/Prev` pointing to that hybrid table;
- current catalog/pages/content rows plus a malformed current carrier row for object `6` whose explicit offset is invalid and whose generation byte is noisy;
- a current page tree that includes one current direct page and the previous compressed page.

Before the repair, the malformed latest carrier row was treated as a carrier replacement, so the previous hybrid type-2 page row was skipped and WordPress extraction lost `Previous hybrid compressed page recovered`.

`PdfTextExtractor::xrefEntriesFromOffsetChain()` now lets a previous hybrid-selected `/ObjStm` carrier survive only when the current carrier row selects no direct object, the previous carrier row selects a real direct `/Type /ObjStm`, and previous type-2 rows need that carrier. Valid current carrier replacements still win. The review path now exposes `current_carrier_invalid_generation_recovered=true` and `preserved_previous_carrier_after_invalid_current_generation`.

After the repair, WordPress paragraph extraction emits only:

- `Current stream Prev hybrid page`
- `Hybrid carrier generation recovered`
- `Previous hybrid compressed page recovered`

The stale previous direct page, compressed member dictionary text, Python workers, pdftext, pypdfium, model execution, raster execution, action execution, decryption, and external PDF tools remain excluded.

## Evidence

Red baseline before source repair:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefStreamPrevHybridGenerationRecoveryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL recovers Prev hybrid object-stream members when current xref-stream carrier row has generation noise (lanes/markerpdf/tests/PdfXrefStreamPrevHybridGenerationRecoveryCurrentBaseTest.php)
Values are not identical
Expected: array (
  0 => 'Current stream Prev hybrid page',
  1 => 'Hybrid carrier generation recovered',
  2 => 'Previous hybrid compressed page recovered',
)
Actual: array (
  0 => 'Current stream Prev hybrid page',
  1 => 'Hybrid carrier generation recovered',
)

1 test files, 1 assertions, 1 failures
```

Focused green after repair:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefStreamPrevHybridGenerationRecoveryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS recovers Prev hybrid object-stream members when current xref-stream carrier row has generation noise

1 test files, 18 assertions, 0 failures
```

Adjacent xref/object-stream gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefStreamPrevHybridGenerationRecoveryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefStreamPrevHybridOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefStreamPrevIndexGenerationCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamPrevGenerationRebuildCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefHybridGenerationRepairCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefIncrementalObjectStreamFreeRepairCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamFreeEntryPrevCurrentBaseTest.php lanes/markerpdf/tests/PdfObjectStreamXrefFreeEntryConflictTest.php lanes/markerpdf/tests/PdfXrefObjectStreamType2IndexCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefStreamObjectStreamGenerationRepairTest.php
Focused test run: 10 selected test files (root lock skipped)
10 test files, 108 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-stream-prev-hybrid-generation-recovery-currentbase.php
uses_current_stream_prev_hybrid_page=true
recovers_previous_hybrid_compressed_page=true
excludes_stale_previous_hybrid_carrier_page=true
excludes_compressed_member_dictionary_text=true
preserved_type2_entry_count=1
invalid_current_carrier_recovered=true
page_count=2
```

Status delta: `phpPass` / `wordpressScenarios` move `870 -> 871`; mapped markerPDF semantics move `614 -> 615 / 78` with `pdfXrefStreamPrevHybridGenerationRecoveryCurrentBase`.

Root harness status: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted same-offset carrier preservation, rebuilt current object-stream carrier precedence, current carrier replacement with a valid direct row, previous type-2 rows whose carrier was absent or compressed, hybrid table free/direct precedence over companion `/XRefStm`, current hybrid generation-one page repair, underdeclared `/Size` root generation recovery, incremental free-entry suppression, object-stream free-entry `/Prev` handling, xref-stream duplicate `/Index` preservation, xref-stream invalid explicit offset rejection for ordinary direct objects, or object-stream member-index recovery.

The bounded behavior here is specifically a latest xref stream `/Prev` chain into a previous hybrid table where the current carrier row is malformed and selects no object, so it must not suppress the previous hybrid-selected object-stream carrier required by inherited type-2 page rows.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP direct-object scanner, startxref `/Prev` chain parser, hybrid xref table/stream merger, xref-stream decoder, object-stream carrier expander, page-tree walker, stream decoder, text-token extractor, and WordPress smoke renderer. Full upstream markerPDF parity remains dependency-gated by `pdftext`, `pypdfium2`/PDFium, Surya/Torch, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers.
