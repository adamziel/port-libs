# markerPDF xref-stream Prev invalid explicit-offset boundary

Slice: `xref-stream-prev-generation-repair-boundaries-currentbase-20260602T094327Z`

## Source Truth

Upstream `sddai/markerPDF` at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` delegates native PDF parsing and text extraction to `pypdfium2` and `pdftext.extraction.dictionary_output` in `marker/pdf/extract_text.py`. The PHP lane therefore treats xref traversal, direct-object byte offsets, object generations, and `/Prev` incremental update merging as parser/dependency boundaries.

PDF xref-stream type-1 rows describe direct objects by byte offset. This slice tightens the existing accepted `/Prev` generation repair: an explicit current xref-stream offset must match a scanned direct object boundary before generation fallback can select an object. Rows whose offset field is omitted can still use the existing generation fallback path.

## Behavior

`PdfTextExtractor` now tracks whether a type-1 xref offset was explicit. If an explicit offset does not match a direct object definition for that object number, the row is treated as unavailable instead of falling back to a stale previous-generation definition from a `/Prev` xref stream.

The focused fixture keeps a valid current page and valid current content stream while also referencing an object whose current xref-stream row has a zero-width generation field and an explicit invalid byte offset inside the previous generation's stale stream. Before this change, the extractor emitted the stale `/Prev` text. After the change, only the valid current-offset content reaches WordPress paragraph extraction.

## Evidence

Red baseline before the parser fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextExtractorTest.php
FAIL rejects invalid explicit xref stream offsets before stale Prev generation fallback
Actual: array (
  0 => 'Current valid xref offset page',
  1 => 'Stale invalid offset resurrection',
)
1 test files, 467 assertions, 1 failures
```

Focused green after the parser fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextExtractorTest.php
1 test files, 473 assertions, 0 failures
```

Adjacent xref/object-stream focused gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextExtractorTest.php lanes/markerpdf/tests/PdfObjectStreamLengthFilterTest.php lanes/markerpdf/tests/PdfObjectStreamNestedTokenBoundaryTest.php lanes/markerpdf/tests/PdfObjectStreamXrefFreeEntryConflictTest.php
4 test files, 498 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-prev-stream-offset-boundary-import.php
uses_current_valid_xref_offset_page=true
rejects_invalid_explicit_xref_offset=true
excluded_stale_prev_generation_content=true
```

Changed PHP lint passed:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfTextExtractorTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-xref-prev-stream-offset-boundary-import.php
```

JSON and whitespace checks passed:

```text
jq empty lanes/markerpdf/lane-status.json lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json
git diff --check -- lanes/markerpdf
```

Root harness status: not run - isolated micro-slice.

## Non-Overlap

This does not repeat the accepted exact-offset generation repair for valid current rows, hybrid xref table free-entry precedence, object-generation free-entry reuse guards, latest trailer `/Root` generation recovery, xref stream `/Index` and zero-width `/W` defaults, startxref chain precedence, object-stream nested token-boundary parsing, or object-stream indirect `/Length`/`/Filter`/`/N`/`/First` recovery.

The new behavior is specifically the fail-closed boundary when a current xref-stream type-1 row has an explicit invalid offset and a `/Prev` chain still contains a stale same-object generation.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PDF object scanner, xref table/stream parser, `/Prev` chain merger, stream decoder, page-tree walker, and content-token text extractor. Full upstream Python/model/benchmark parity remains dependency-gated by `pdftext`, `pypdfium2`, Surya/Torch, tabled, Texify, live Streamlit/FastAPI paths, and benchmark/runtime tooling.
