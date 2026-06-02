# markerPDF xref /Prev stream generation repair

Slice: `markerpdf-xref-prev-stream-generation-repair-current-base-20260602T072352Z`

## Source Truth

Upstream markerPDF delegates native PDF text extraction to `pdftext`/`pypdfium2` in `marker/pdf/extract_text.py`, so xref traversal, object generation repair, and compressed-stream object selection are parser/dependency boundaries in this PHP lane. The local upstream cache recorded in the manifest was unavailable in this isolated worktree; the slice uses the accepted lane manifest/notes plus PDF xref-stream semantics already mapped by the lane as source truth.

## Behavior

`PdfTextExtractor` now prefers an exact byte offset from a direct xref entry before using the generation field as a fallback selector. This repairs incremental xref-stream `/Prev` chains where the current stream row uses a zero-width generation field or otherwise omits the generation, but its offset points at the current generation object body. The previous stream can still contribute unchanged objects such as shared font resources, while stale previous-generation catalog/page/content objects do not leak into WordPress paragraph extraction.

The focused fixture appends generation-1 catalog/page/content objects after a previous xref stream and writes the current xref stream with `/Prev` plus `/W [1 4 0]`. Before the repair, `extractTextLines()` selected the generation-0 page from the previous xref stream. After the repair, it emits only `Current generation stream page` and `Offset repaired generation`.

## Evidence

Red baseline before source repair:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextExtractorTest.php
FAIL repairs xref stream Prev generation rows by exact offsets before WordPress text extraction
Actual: array (
  0 => 'Stale previous stream generation page',
)
1 test files, 425 assertions, 1 failures
```

Focused green after repair:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextExtractorTest.php
1 test files, 431 assertions, 0 failures
```

Adjacent xref/object-stream focused set:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextExtractorTest.php lanes/markerpdf/tests/PdfObjectStreamLengthFilterTest.php lanes/markerpdf/tests/PdfObjectStreamNestedTokenBoundaryTest.php
3 test files, 448 assertions, 0 failures
```

Full markerPDF lane check:

```text
php tools/run-tests.php lanes/markerpdf/tests
59 test files, 2517 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-prev-stream-generation-repair-import.php
uses_current_generation_stream_page=true
repairs_zero_width_generation_by_offset=true
excluded_previous_stream_generation_page=true
```

Changed PHP lint passed for:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfTextExtractorTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-xref-prev-stream-generation-repair-import.php
```

Whitespace check:

```text
git diff --check -- lanes/markerpdf
passed
```

## Non-Overlap

This does not repeat accepted hybrid xref `/Prev` object-stream override, free-generation suppression, latest trailer `/Root` generation recovery, xref stream `/Index` and zero-width `/W` default decoding, object-stream nested token-boundary parsing, object-stream indirect `/Length`/`/Filter`/`/N`/`/First` recovery, or latest startxref object-stream rebuild precedence. The new behavior is specifically direct object generation repair by exact xref byte offset across an xref-stream `/Prev` chain.

## Dependency Closure

No new support component is needed. The slice reuses the native PDF object scanner, xref table/stream parser, stream decoder, page-tree walker, and content-token text extractor. Full upstream Python/model/benchmark parity remains dependency-gated by `pdftext`, `pypdfium2`, Surya/Torch, tabled, Texify, and live benchmark/runtime tooling.
