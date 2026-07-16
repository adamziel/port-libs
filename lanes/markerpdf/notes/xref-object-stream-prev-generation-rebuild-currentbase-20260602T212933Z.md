# markerPDF xref object-stream Prev generation rebuild current-base

Micro-slice: `xref-object-stream-prev-generation-rebuild-currentbase`
Session: `port-dev-markerpdf-xref60-20260602T212933Z`
Base accepted HEAD: `c3b759a859020b8775e124d837d858198d98558e`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes page text through `marker/pdf/extract_text.py::get_text_blocks()` into `pdftext.extraction.dictionary_output(...)`, and `naive_get_text()` delegates page text extraction to pypdfium. Source: <https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py>

PDFium object streams validate `/Type /ObjStm`, `/N`, and `/First`, then parse member objects from the selected carrier stream by archive index. That makes current carrier ownership, incremental `/Prev` merging, and object-stream member selection parser/dependency behavior for this native PHP lane. Source: <https://pdfium.googlesource.com/pdfium/+/refs/heads/main/core/fpdfapi/parser/cpdf_object_stream.cpp>

## Behavior

The focused fixture builds an incremental PDF where:

- the previous xref stream selects carrier `6 0` as a direct `/ObjStm` and maps page object `4` as a type-2 member of that carrier;
- the latest xref stream maps page object `4` as a type-2 member of carrier `6`, but intentionally omits a direct carrier row;
- the current revision contains a newer direct `6 1 /ObjStm` before the latest xref stream.

Before this repair, the `/Prev` direct carrier row for `6 0` could suppress the current scanned `6 1 /ObjStm`, so page `4` expanded from the stale previous carrier and leaked `Stale Prev carrier generation page` into WordPress paragraphs.

`PdfTextExtractor::xrefEntriesFromOffsetChain()` now suppresses previous free or direct carrier rows when the latest xref section has type-2 rows that name that carrier and a newer direct `/ObjStm` exists in the current revision window. Same-storage replay remains valid; the existing preserved-carrier test still passes.

After the repair, WordPress paragraph extraction emits only:

- `Current rebuilt carrier page`
- `Prev carrier generation ignored`

The stale previous carrier member, current object-stream member dictionary text, Python workers, pdftext, pypdfium, model execution, raster execution, and external PDF tools remain excluded.

## Evidence

Focused current-base test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamPrevGenerationRebuildCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rebuilds current object-stream carrier before stale Prev direct carrier generation

1 test files, 18 assertions, 0 failures
```

Adjacent xref/object-stream gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamPrevGenerationRebuildCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamPrevFreeCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamFreeEntryPrevCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevObjectStreamGenerationCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamGenerationPrevCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefIncrementalObjectStreamFreeRepairCurrentBaseTest.php lanes/markerpdf/tests/PdfObjectStreamXrefFreeEntryConflictTest.php lanes/markerpdf/tests/PdfXrefStreamPrevIndexGenerationCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamDuplicateZeroWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamType2IndexCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefHybridObjectStreamFreeOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefStreamObjectStreamGenerationRepairTest.php lanes/markerpdf/tests/PdfXrefHybridObjectStreamGenerationTest.php
Focused test run: 14 selected test files (root lock skipped)
14 test files, 207 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-object-stream-prev-generation-rebuild-currentbase.php
```

The smoke emitted `uses_current_rebuilt_carrier_page=true`, `ignores_prev_carrier_generation=true`, `excludes_stale_prev_carrier_page=true`, `excludes_stale_prev_member_metadata=true`, `compressed_entry_count=1`, `object_stream=6`, `page_count=1`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`, plus Gutenberg paragraphs for only the current text.

Syntax and status checks:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfXrefObjectStreamPrevGenerationRebuildCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-xref-object-stream-prev-generation-rebuild-currentbase.php
php -r '$json = json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true); if (!is_array($json)) { fwrite(STDERR, json_last_error_msg() . PHP_EOL); exit(1); }'
php -r '$json = json_decode(file_get_contents("lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"), true); if (!is_array($json)) { fwrite(STDERR, json_last_error_msg() . PHP_EOL); exit(1); }'
git diff --check -- lanes/markerpdf
```

All passed.

## Status Delta

`lane-status.json` moves `phpPass` and `wordpressScenarios` from `849` to `850`. `UPSTREAM_TEST_MANIFEST.json` moves mapped current-base behavior from `596` to `597 / 78` and records `pdfXrefObjectStreamPrevGenerationRebuildCurrentBase`.

Root harness status: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted current type-2 rows preserving a direct carrier when `/Prev` marks that carrier free, previous type-2 rows whose carrier was absent or compressed, current carrier replacement that already has a direct current carrier xref row, same-storage carrier replay, hybrid table free-entry precedence, generation-zero object-stream members rejected for nonzero page references, xref-stream `/Index` offset-owner repair, zero-width member-index recovery, or latest startxref precedence before stale appended rebuild streams.

The bounded behavior here is specifically a stale `/Prev` direct object-stream carrier generation row that would otherwise suppress an unlisted but current direct `/ObjStm` base needed by latest xref-stream type-2 member rows.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP direct-object scanner, startxref `/Prev` chain parser, xref-stream decoder, object-stream expander, page-tree walker, content stream decoder, text-token extractor, and WordPress smoke renderer. Full upstream markerPDF parity remains dependency-gated by `pdftext`, `pypdfium2`/PDFium, Surya/Torch, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers.
