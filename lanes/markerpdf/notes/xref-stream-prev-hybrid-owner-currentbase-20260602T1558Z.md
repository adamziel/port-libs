# markerPDF xref-stream Prev hybrid object-stream owner boundary

Micro-slice: `xref-stream-prev-hybrid-owner-currentbase-20260602T1558Z`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` keeps low-level PDF page text extraction behind `marker/pdf/extract_text.py`: `get_text_blocks()` delegates to `pdftext.extraction.dictionary_output(...)`, while `naive_get_text()` uses pypdfium page text extraction. Source: <https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py>

The native PHP lane therefore treats xref traversal, incremental `/Prev` sections, hybrid `/XRefStm` rows, and object-stream carrier ownership as parser/dependency behavior before WordPress paragraphs are emitted. PDFium's parser walks xref tables/streams from `startxref`, follows previous sections, and preserves xref table precedence over companion stream entries. Source: <https://pdfium.googlesource.com/pdfium/+/refs/heads/main/core/fpdfapi/parser/cpdf_parser.cpp>

## Behavior

`PdfTextExtractor::xrefEntriesFromOffsetChain()` now keeps type-2 entries from a previous xref section tied to that previous section's object-stream carrier. When the current xref section replaces the carrier object, the previous compressed-object row is skipped instead of binding to the new carrier bytes.

The focused fixture builds:

- a previous hybrid table whose companion `/XRefStm` is the only row for compressed page object `4`;
- an appended current xref stream with `/Prev` pointing to that hybrid table;
- a current replacement of object stream `6`;
- a current page tree that contains one valid current page plus a stale `4 0 R` kid.

Before the fix, the stale previous type-2 row expanded member `4` from the current replacement carrier and leaked `Current replaced object stream leak`. After the fix, only `Current xref stream owner page` and `Previous hybrid row skipped` reach WordPress output.

## Evidence

Red baseline before the parser fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefStreamPrevHybridOwnerCurrentBaseTest.php
FAIL keeps current xref-stream object-stream owner before stale Prev hybrid type-2 rows
Actual:
  0 => 'Current xref stream owner page',
  1 => 'Previous hybrid row skipped',
  2 => 'Current replaced object stream leak',
1 test files, 1 assertions, 1 failures
```

Focused green after the parser fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefStreamPrevHybridOwnerCurrentBaseTest.php
1 test files, 10 assertions, 0 failures
```

Adjacent xref/object-stream gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefStreamObjectStreamGenerationRepairTest.php lanes/markerpdf/tests/PdfXrefHybridObjectStreamGenerationTest.php lanes/markerpdf/tests/PdfXrefGenerationRepairBoundaryTest.php lanes/markerpdf/tests/PdfObjectStreamXrefFreeEntryConflictTest.php lanes/markerpdf/tests/PdfXrefStreamPrevIndexWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamType2IndexCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamTrailerBoundaryTest.php
7 test files, 61 assertions, 0 failures
```

Central extractor regression:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextExtractorTest.php lanes/markerpdf/tests/PdfXrefStreamPrevHybridOwnerCurrentBaseTest.php
2 test files, 588 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-stream-prev-hybrid-owner-currentbase.php
uses_current_xref_stream_owner_page=true
skips_previous_hybrid_type2_row=true
excluded_stale_previous_hybrid_page=true
excluded_current_replaced_object_stream_leak=true
page_count=1
executes_python_or_models=false
executes_external_pdf_tools=false
```

Changed-file lint and lane checks:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfXrefStreamPrevHybridOwnerCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-xref-stream-prev-hybrid-owner-currentbase.php
php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "json ok\n";'
git diff --check -- lanes/markerpdf
```

Root harness status: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted hybrid xref table direct/free-row precedence, explicit generation-one repair over companion `/XRefStm`, xref-stream `/Prev` duplicate `/Index` row handling, invalid explicit offset rejection, omitted type-2 member-index repair, unselected object-stream suppression, object-stream carrier fallback exclusion, stream-owned fake xref table rejection, or object-stream indirect filter-chain operand recovery.

The new behavior is specifically the latest xref-stream `/Prev` merge where a previous hybrid type-2 row names an object-stream carrier that the current xref stream has replaced.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP direct-object scanner, xref table/stream parser, `/Prev` merger, object-stream decoder, page-tree walker, stream decoder, content-token text extractor, and WordPress smoke path. Full upstream markerPDF parity remains dependency-gated by pdftext, pypdfium2/PDFium, Surya/Torch, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers.
