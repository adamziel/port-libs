# markerPDF hybrid xref direct-reference generation repair

Micro-slice: `xref-hybrid-reference-repair-currentbase-20260602T174411Z`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes PDF text extraction through `marker/pdf/extract_text.py`: `get_text_blocks()` delegates low-level parsing to `pdftext.extraction.dictionary_output(...)`, while `naive_get_text()` reads page text through pypdfium/PDFium. The PHP lane therefore owns the native parser/dependency boundary for xref traversal, indirect object generations, hybrid companion `/XRefStm` rows, page-tree references, and WordPress paragraph text before any Python models or external PDF tools run.

PDF indirect references include an object number and generation. A hybrid companion xref stream can advertise a stale direct generation-zero row for an object number while the current trailer-selected page tree references a newer generation, such as `4 1 R`. The stale `4 0` body must not satisfy that `4 1 R` page reference.

## Behavior

`PdfTextExtractor::withReferencedDirectGenerationObjects()` now repairs selected direct rows whose generation conflicts with a nonzero-generation reference in the current object graph. The existing object-stream repair is preserved: type-2 rows can still recover direct nonzero-generation pages before compressed generation-zero members expand.

The focused fixture builds a hybrid PDF where:

- the current catalog/pages objects are generation 1 and `/Kids` references `4 1 R`;
- a companion `/XRefStm` row advertises object `4` as a direct generation-zero row at the stale page offset;
- a direct `4 1` page and current content stream are present but not selected by object number alone.

Before the repair, WordPress text extraction emitted `Stale hybrid reference generation zero page`. After the repair, it emits only `Current hybrid reference page` and `Direct generation reference repaired`.

## Evidence

Red baseline before the parser repair:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefHybridReferenceRepairCurrentBaseTest.php
FAIL repairs hybrid xref direct rows when current page tree references a newer generation
Actual: array (
  0 => 'Stale hybrid reference generation zero page',
)
1 test files, 1 assertions, 1 failures
```

Focused green after the parser repair:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefHybridReferenceRepairCurrentBaseTest.php
PASS repairs hybrid xref direct rows when current page tree references a newer generation
1 test files, 9 assertions, 0 failures
```

Adjacent xref/object-stream generation gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefHybridReferenceRepairCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefHybridObjectStreamGenerationTest.php lanes/markerpdf/tests/PdfXrefGenerationRepairBoundaryTest.php lanes/markerpdf/tests/PdfXrefStreamObjectStreamGenerationRepairTest.php lanes/markerpdf/tests/PdfXrefStreamPrevHybridOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefHybridPrevTrailerSizeRepairCurrentBaseTest.php lanes/markerpdf/tests/PdfObjectStreamXrefFreeEntryConflictTest.php lanes/markerpdf/tests/PdfXrefIncrementalObjectStreamFreeRepairCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
9 test files, 668 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-hybrid-reference-repair-currentbase.php
uses_current_hybrid_reference_page=true
repairs_direct_generation_reference=true
excluded_stale_generation_zero_page=true
excluded_stale_generation_zero_metadata=true
page_count=1
executes_python_or_models=false
executes_external_pdf_tools=false
```

Required checks:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfXrefHybridReferenceRepairCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-xref-hybrid-reference-repair-currentbase.php
php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "json ok\n";'
git diff --check -- lanes/markerpdf
```

## Non-Overlap

This does not repeat accepted hybrid xref table direct-row precedence over stale compressed members, hybrid free-entry conflict precedence, xref-stream `/Prev` exact-offset generation repair, invalid explicit xref-stream offset rejection, object-stream omitted member-index repair, xref-stream `/Prev` hybrid object-stream owner replacement, underdeclared no-`/Index` trailer `/Size` repair, incremental free-entry suppression, incremental object-stream carrier repair, or stream-owned fake xref object rejection.

The bounded behavior is specifically a hybrid companion `/XRefStm` direct type-1 generation-zero row conflicting with an explicit generation-one page-tree reference.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP direct-object scanner, xref table/stream parser, hybrid `/XRefStm` merger, generation-aware object-reference repair, page-tree walker, stream decoder, content-token extractor, and WordPress smoke path. Full markerPDF parity remains dependency-gated by `pdftext`, `pypdfium2`/PDFium, Surya/Torch, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers.
