# markerPDF xref hybrid object-stream generation repair

Slice: `xref-hybrid-objectstream-generation-currentbase-20260602T1357Z`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes native PDF page text through `marker/pdf/extract_text.py`: `get_text_blocks()` delegates to `pdftext.extraction.dictionary_output(...)`, and `naive_get_text()` reads text pages through pypdfium. Source: <https://github.com/sddai/markerPDF/blob/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py>

That makes xref traversal, object-stream membership, and object generation handling a parser/dependency boundary for the PHP lane. PDF object-stream members are generation-zero objects; when the current trailer-selected graph explicitly references a higher-generation direct object such as `4 1 R`, a stale companion `/XRefStm` type-2 row for compressed `4 0` must not satisfy that reference.

## Behavior

`PdfTextExtractor` now recovers direct nonzero-generation objects that are explicitly referenced by the selected current object graph before expanding object streams. Object-stream expansion then skips a compressed member if that object number has already been satisfied by this referenced direct-generation repair.

The focused fixture builds a hybrid PDF with:

- current catalog/pages objects in generation 1;
- a page tree referencing `4 1 R`;
- a real direct `4 1` page whose contents are `9 1`;
- a companion `/XRefStm` row advertising object `4` as type-2 member index `0` in object stream `6`;
- a stale object-stream member for generation-zero page `4 0` pointing at stale content.

Before the repair, the native text extractor rendered the stale compressed generation-zero page. After the repair, WordPress paragraph import emits only `Current hybrid generation page` and `Referenced generation one recovered`.

## Evidence

Red baseline before source repair:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefHybridObjectStreamGenerationTest.php
FAIL keeps referenced generation one direct page before stale hybrid object-stream generation zero
Actual: array (
  0 => 'Stale compressed generation zero page',
)
1 test files, 1 assertions, 1 failures
```

Focused green after repair:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefHybridObjectStreamGenerationTest.php
1 test files, 9 assertions, 0 failures
```

Adjacent xref/object-stream gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefGenerationRepairBoundaryTest.php lanes/markerpdf/tests/PdfXrefStreamObjectStreamGenerationRepairTest.php lanes/markerpdf/tests/PdfObjectStreamXrefFreeEntryConflictTest.php lanes/markerpdf/tests/PdfXrefObjectStreamTrailerBoundaryTest.php lanes/markerpdf/tests/PdfObjectStreamLengthFilterTest.php lanes/markerpdf/tests/PdfObjectStreamNestedTokenBoundaryTest.php lanes/markerpdf/tests/PdfParserStreamFilterObjectBoundaryTest.php
7 test files, 65 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-hybrid-object-stream-generation-currentbase.php
uses_current_hybrid_generation_page=true
recovers_referenced_generation_one_page=true
excluded_stale_compressed_generation_zero_page=true
excluded_stale_compressed_generation_zero_metadata=true
page_count=1
executes_python_or_models=false
executes_external_pdf_tools=false
```

Required isolated-lane checks:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfXrefHybridObjectStreamGenerationTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-xref-hybrid-object-stream-generation-currentbase.php
php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "json ok\n";'
git diff --check -- lanes/markerpdf
```

## Non-Overlap

This does not repeat accepted hybrid xref table direct-row precedence, hybrid free-entry conflict precedence, unselected object-stream trailer-boundary suppression, xref-stream `/Prev` exact-offset generation repair, invalid explicit xref-stream offset rejection, omitted type-2 member-index repair, object-stream nested-token parsing, object-stream indirect `/Length`/`/Filter`/`/N`/`/First` recovery, latest trailer `/Root` generation recovery, current xref-stream trailer metadata, or encrypted metadata preflight.

The new behavior is specifically the hybrid companion `/XRefStm` type-2 generation-zero member conflicting with an explicit generation-one page reference in the current trailer-selected graph.

## Dependency Closure

No new support component is needed. This slice reuses the native direct-object scanner, xref table/stream parser, object-stream decoder, generation-aware object-reference repair, page-tree walker, stream decoder, and content-token text extractor. Full upstream markerPDF parity remains dependency-gated by `pdftext`, `pypdfium2`/PDFium, Surya/Torch, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, and benchmark/model download tooling.
