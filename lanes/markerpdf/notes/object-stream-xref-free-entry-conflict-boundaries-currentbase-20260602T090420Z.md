# markerPDF object-stream xref free-entry conflict boundaries

Slice: `object-stream-xref-free-entry-conflict-boundaries-currentbase-20260602T090420Z`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` reaches PDF page text through `marker/pdf/extract_text.py::naive_get_text()` using pypdfium page text, and routes structured text through `get_text_blocks()` using pdftext dictionary output. This native PHP lane owns the PDF parser/dependency boundary before WordPress paragraphs are produced.

For PDF 1.5 object-stream/xref recovery, type-0 xref rows are free entries. A current hybrid-reference xref table free row must therefore remain authoritative when the companion `/XRefStm` contains a conflicting stale type-2 object-stream row for the same object number. Object-stream members are imported only when the current xref entry selects that stream/index, not when a free entry reserves the object for reuse.

## Behavior

`PdfTextExtractor::xrefEntriesFromOffsetChain()` now preserves explicit current table free entries while merging a hybrid `/XRefStm`. Non-conflicting xref-stream rows still import compressed members, and previous `/Prev` chain entries still fill only missing current entries.

The focused fixture builds a hybrid PDF where page object `4 0` is referenced by the current page tree, the current xref table marks `4 0` free with generation `2`, and the companion xref stream falsely advertises object `4` as member index `0` in object stream `6`. Before the fix, the stale object-stream page was extracted. After the fix, only the current direct page is emitted for WordPress import.

## Evidence

Red baseline after adding the focused test and before the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfObjectStreamXrefFreeEntryConflictTest.php
FAIL honors current hybrid xref free entry before conflicting object stream member
Actual:
  0 => 'Stale conflicting object stream page',
  1 => 'Current direct conflict page',
  2 => 'Free entry boundary kept'
1 test files, 1 assertions, 1 failures
```

Focused green after the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfObjectStreamXrefFreeEntryConflictTest.php
1 test files, 8 assertions, 0 failures
```

Adjacent object-stream/xref parser set:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfObjectStreamXrefFreeEntryConflictTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php lanes/markerpdf/tests/PdfObjectStreamLengthFilterTest.php lanes/markerpdf/tests/PdfObjectStreamNestedTokenBoundaryTest.php
4 test files, 486 assertions, 0 failures
```

Full lane-scoped markerPDF PHP check:

```text
php tools/run-tests.php lanes/markerpdf/tests
61 test files, 2864 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-object-stream-free-entry-conflict-import.php
current_hybrid_free_entry_wins=true
excluded_conflicting_object_stream_member=true
preserved_current_direct_page=true
page_count=1
```

Changed PHP lint passed for:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfObjectStreamXrefFreeEntryConflictTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-object-stream-free-entry-conflict-import.php
```

## Non-Overlap

This does not repeat accepted object-stream token-boundary parsing, object-stream indirect `/Length`/`/Filter`/`/N`/`/First` recovery, object-generation free-entry suppression in a current xref table, xref-stream `/Prev` generation repair by exact byte offset, latest trailer `/Root` recovery, startxref object-stream rebuild precedence, xref stream `/Index`/zero-width `/W` handling, or linearized hint-table exclusion. The new behavior is specifically a current hybrid xref table free row conflicting with the same update's `/XRefStm` type-2 object-stream row.

## Dependency Closure

No new support component is needed. The slice reuses the native PDF direct-object scanner, xref table/stream parser, object-stream expander, stream decoder, page-tree walker, and content-token text extractor. Full upstream Python/model/benchmark parity remains dependency-gated by pdftext, pypdfium2, Surya/Torch, tabled, Texify, live benchmark/runtime tooling, and external rendering/OCR tools.
