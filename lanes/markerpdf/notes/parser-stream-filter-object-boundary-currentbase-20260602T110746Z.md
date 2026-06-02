# markerPDF Parser Stream Filter Object Boundary

Slice: `parser-stream-filter-object-boundary-currentbase-20260602T110746Z`

## Source Truth

Upstream markerPDF at `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes native PDF text extraction through `marker/pdf/extract_text.py` into `pdftext`/PDF parser behavior before block cleanup. At that parser boundary, stream bytes belong to the current xref-selected object definition. Stale free objects, superseded generations, and stream-looking tokens inside another stream payload must not become independent fallback page content.

This slice targets the native PHP fallback stream path used when a lightweight PDF has no recoverable page `/Contents` tree. The page-content path already resolves current object maps; the risk was the raw fallback scanner.

## Implementation

`PdfTextExtractor::allDecodedStreams()` now enumerates live direct object definitions selected by the existing current xref/startxref logic, in file order, instead of scanning every raw `<< ... >> stream` byte sequence in the PDF file. It skips linearized hint objects, embedded-file payloads, and PieceInfo private payloads by live object number before decoding.

`PdfTextExtractor::streamDictionaryAndPayload()` now decodes only a top-level object dictionary immediately followed by the `stream` keyword. This prevents fake nested stream dictionaries inside inline-image data or other stream payload bytes from being treated as separate fallback content streams.

## WordPress Scenario

`examples/wordpress-pdf-parser-stream-filter-object-boundary.php` models a WordPress PDF import where the current xref table marks a Flate-compressed stale stream object free while a current stream contains inline-image bytes with fake nested stream tokens. The smoke emits only `Current filtered object boundary` and `Current base fallback`, with `stale_filtered_stream_excluded=true`, `nested_stream_tokens_excluded=true`, and no Python/models/external PDF tools.

## Verification

- `php -l lanes/markerpdf/src/PdfTextExtractor.php` passed.
- `php -l lanes/markerpdf/tests/PdfParserStreamFilterObjectBoundaryTest.php` passed.
- `php -l lanes/markerpdf/examples/wordpress-pdf-parser-stream-filter-object-boundary.php` passed.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfParserStreamFilterObjectBoundaryTest.php` passed with 1 file / 14 assertions / 0 failures.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfTextExtractorTest.php lanes/markerpdf/tests/PdfParserStreamFilterObjectBoundaryTest.php lanes/markerpdf/tests/PdfTokenStreamObjectBoundaryTest.php lanes/markerpdf/tests/PdfObjectStreamNestedTokenBoundaryTest.php` passed with 4 files / 554 assertions / 0 failures.
- `php lanes/markerpdf/examples/wordpress-pdf-parser-stream-filter-object-boundary.php` emitted the expected Gutenberg paragraphs and exclusion flags.
- `php tools/run-tests.php lanes/markerpdf/tests` passed with 64 files / 3471 assertions / 0 failures.

## Dependency Closure

No new support component is needed. This reuses the native PHP direct-object scanner, xref table/stream current-base selection, stream decoder, filter dispatcher, inline-image skipper, and content-token text parser. Full upstream Python/model/benchmark parity remains dependency-gated by `pdftext`, `pypdfium2`, Surya/Torch models, tabled-pdf, Texify, runtime server/app tooling, and live benchmark dependencies.

## Non-Overlap

This does not repeat accepted token-aware direct stream owner lookup, object-stream nested token boundaries, stale `/Length` recovery, declared-filter error boundaries, indirect `/Filter` arrays, object-stream `/Length`/`/Filter` recovery, startxref/xref stream precedence, linearized hint-table exclusion, PieceInfo private-stream exclusion, or AcroForm/widget appearance review metadata. The new behavior is specifically fallback stream enumeration through current live direct object definitions plus top-level stream dictionary parsing.

## Next Task

Continue with non-overlapping markerPDF parser/import fidelity gaps: xref/object stream recovery, page/resource boundaries, font/CMap edges, metadata/security review, annotation/rich-media review, and image/color-space planning that can ship with focused PHP evidence.
