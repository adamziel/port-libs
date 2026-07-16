# markerPDF xref generation repair boundary

Slice: `xref-generation-repair-boundary-currentbase-20260602T120021Z`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes PDF text extraction through `marker/pdf/extract_text.py`: `get_text_blocks()` calls `pdftext.extraction.dictionary_output(...)`, and `naive_get_text()` calls `pypdfium2` page text extraction. That makes hybrid xref tables, companion `/XRefStm` streams, `/Prev` chains, object generations, and object-stream membership parser/dependency behavior for the native PHP lane.

The local `.upstream-cache/markerpdf` checkout was not present in this worktree, so I source-checked the pinned upstream file over HTTPS and used the accepted lane manifest plus PDF 1.5 hybrid-reference semantics as the bounded source truth for this repair.

## Behavior

`PdfTextExtractor::xrefEntriesFromOffsetChain()` now keeps every current hybrid xref table row authoritative while merging the same revision's companion `/XRefStm`. Previously only table free rows were protected, so a companion xref stream could replace a current direct table row with a stale compressed object-stream row for the same object number.

The focused fixture has a previous xref-stream generation with a compressed stale page and a current xref table whose direct generation-1 row points at the live page. The current table also references a companion `/XRefStm` that falsely advertises object `4` as a compressed stale generation-0 page. After the repair, WordPress paragraph extraction emits only the current direct-generation page and excludes the stale compressed member.

## Evidence

Red baseline before source repair:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefGenerationRepairBoundaryTest.php
FAIL keeps current hybrid table direct generation before stale xref-stream object member
Actual: array (
  0 => 'Stale compressed previous generation page',
)
1 test files, 1 assertions, 1 failures
```

Focused green after repair:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefGenerationRepairBoundaryTest.php
1 test files, 8 assertions, 0 failures
```

Adjacent xref/object-stream gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefGenerationRepairBoundaryTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php lanes/markerpdf/tests/PdfXrefStreamObjectStreamGenerationRepairTest.php lanes/markerpdf/tests/PdfObjectStreamXrefFreeEntryConflictTest.php lanes/markerpdf/tests/PdfObjectStreamLengthFilterTest.php lanes/markerpdf/tests/PdfObjectStreamNestedTokenBoundaryTest.php
6 test files, 570 assertions, 0 failures
```

Full markerPDF lane:

```text
php tools/run-tests.php lanes/markerpdf/tests
65 test files, 3793 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-generation-repair-boundary.php
uses_current_direct_generation_page=true
keeps_hybrid_table_direct_entry=true
excluded_previous_compressed_generation_page=true
page_count=1
```

Changed PHP lint passed for:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfXrefGenerationRepairBoundaryTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-xref-generation-repair-boundary.php
```

Whitespace check:

```text
git diff --check -- lanes/markerpdf
passed
```

## Non-Overlap

This does not repeat accepted xref-stream `/Prev` exact-offset generation repair, invalid explicit xref-stream offset rejection, hybrid xref table free-entry conflict suppression, object-generation free-entry reuse guards, latest trailer `/Root` generation recovery, xref stream `/Index` and zero-width `/W` default decoding, latest startxref object-stream rebuild precedence, object-stream member-index repair, object-stream nested token-boundary parsing, or object-stream indirect `/Length`/`/Filter`/`/N`/`/First` recovery. The new behavior is specifically a current hybrid xref table direct row for a newer generation staying authoritative over a conflicting companion `/XRefStm` compressed stale row while `/Prev` still supplies unchanged objects.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP direct-object scanner, xref table/stream parser, `/Prev` chain merger, object-stream expander, stream decoder, page-tree walker, and content-token text extractor. Full upstream Python/model/benchmark parity remains dependency-gated by `pdftext`, `pypdfium2`, Surya/Torch, tabled, Texify, live benchmark/runtime tooling, and external rendering/OCR tools.
