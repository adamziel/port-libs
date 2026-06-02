# markerPDF xref object-stream repair trailer boundary

Slice: `xref-object-stream-repair-trailer-boundary-currentbase-20260602T1310Z`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes native text extraction through `marker/pdf/extract_text.py`, which delegates structured text to `pdftext.extraction.dictionary_output(...)` and naive page text to `pypdfium2` page text extraction. The PHP lane therefore treats xref traversal, object-stream membership, and latest trailer page-tree selection as parser/dependency boundaries before WordPress paragraphs are emitted.

The local `.upstream-cache/markerpdf` checkout referenced by the manifest was not present in this isolated worktree. Source truth was checked through the pinned upstream GitHub source and the dependency model: `pdftext` is built on `pypdfium2`, and `pypdfium2` binds PDFium for document/page parsing. For PDF 1.5 object streams, compressed object members are selected by type-2 xref entries; a current trailer/xref chain should not let an unselected `/ObjStm` member overwrite the latest trailer-selected page tree.

## Behavior

`PdfTextExtractor::objectsFromObjectStreams()` now expands object-stream members unconditionally only when no xref entries are available at all, preserving existing no-xref rebuild fallback. Once the parser has selected any current xref entries, an `/ObjStm` is expanded only if at least one selected type-2 row points into that object stream. Selected compressed members still import normally, omitted member-index repair still works, and explicit xref table/free/direct rows remain authoritative.

The focused fixture has a current trailer `/Root 1 0 R`, direct catalog/page/font/content objects, and a listed direct object stream whose members try to overwrite object `2 0` with a stale page tree. The current xref table contains no selected type-2 rows for that object stream. Before the repair, the stale object-stream page leaked into WordPress text. After the repair, only the current direct page tree is rendered.

## Evidence

Red baseline before the parser guard:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamTrailerBoundaryTest.php
FAIL keeps current trailer page tree before unselected object-stream repair fallback
Actual:
  0 => 'Current trailer boundary page',
  1 => 'Direct page tree repaired',
  2 => 'Stale unselected object stream page',
1 test files, 1 assertions, 1 failures
```

Focused green after the parser guard:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamTrailerBoundaryTest.php
1 test files, 9 assertions, 0 failures
```

Adjacent xref/object-stream gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamTrailerBoundaryTest.php lanes/markerpdf/tests/PdfXrefStreamObjectStreamGenerationRepairTest.php lanes/markerpdf/tests/PdfXrefGenerationRepairBoundaryTest.php lanes/markerpdf/tests/PdfObjectStreamXrefFreeEntryConflictTest.php lanes/markerpdf/tests/PdfObjectStreamLengthFilterTest.php lanes/markerpdf/tests/PdfObjectStreamNestedTokenBoundaryTest.php lanes/markerpdf/tests/PdfParserStreamFilterObjectBoundaryTest.php
7 test files, 65 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-object-stream-trailer-boundary-import.php
uses_current_trailer_boundary_page=true
repairs_direct_page_tree=true
excluded_unselected_object_stream_page=true
excluded_unselected_page_tree_overwrite=true
page_count=1
```

Changed PHP lint passed:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfXrefObjectStreamTrailerBoundaryTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-xref-object-stream-trailer-boundary-import.php
```

Central text extractor gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextExtractorTest.php
1 test files, 559 assertions, 0 failures
```

JSON and whitespace checks passed:

```text
php -r 'json_decode(...)' lanes/markerpdf/lane-status.json lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json
git diff --check -- lanes/markerpdf
```

Root harness status: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted object-stream indirect `/Length`/`/Filter`/`/N`/`/First` recovery, object-stream nested token parsing, omitted type-2 member-index repair, xref-stream `/Prev` exact-offset generation repair, invalid explicit xref-stream offset rejection, current hybrid table generation precedence over companion `/XRefStm`, current hybrid table free-entry conflict precedence, latest trailer `/Root` generation recovery, or startxref object-stream rebuild precedence. The new behavior is specifically the boundary that prevents unselected object-stream members from overriding the current trailer-selected direct page tree when the parser already has selected xref entries.

## Dependency Closure

No new support component is needed. This reuses the native direct-object scanner, xref table/stream parser, object-stream decoder, page-tree walker, and content-token extractor. Full upstream Python/model/benchmark parity remains dependency-gated by `pdftext`, `pypdfium2`/PDFium, Surya/Torch, tabled, Texify, Streamlit/FastAPI runtime paths, and benchmark/model download tooling.
