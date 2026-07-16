# markerPDF Stream Filter Error Boundaries

Slice: `markerpdf-stream-filter-error-boundaries-current-base-20260602T0750Z`

## Source Truth

Upstream markerPDF at `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` delegates native PDF page text extraction through `marker/pdf/extract_text.py` into pdftext/pypdfium document text APIs. At that boundary, filtered streams are decoded by the PDF parser before text extraction; undecodable, unsupported, image-only, or unresolved filtered payloads must not be treated as raw content-stream operators.

This is also the PDF parser safety boundary used by the existing lane image-filter and encrypted-PDF preflight slices: suspicious filtered bytes are excluded from visible WordPress paragraph text unless the native decoder can honor the declared filter chain.

## Implementation

`PdfTextExtractor::decodeStream()` now fails closed when a stream declares an unresolved indirect `/Filter`, an unsupported direct filter such as `/Crypt` or `/NoSuchDecode`, a malformed filter declaration, or a filter chain that becomes unsupported after a successful earlier decoder such as `/ASCIIHexDecode`.

Unfiltered streams still parse as raw content streams. Supported decoders and accepted image-filter exclusions remain unchanged: ASCIIHex, ASCII85, RunLength, LZW, Flate, DCT/JPEG, CCITT, JPX, and JBIG2 still follow the existing accepted behavior.

## WordPress Path

`examples/wordpress-pdf-stream-filter-error-boundary-import.php` models a WordPress PDF import with good page content around unsupported `/Crypt`, corrupt `/FlateDecode`, stacked unknown, and missing indirect filter streams. It emits only `Filter Boundary Visible` and `Filter Boundary Tail` as Gutenberg paragraphs while recording all exclusion flags as true.

## Dependency Closure

No new support component is needed. The slice reuses the native PDF object parser, stream dictionary/payload reader, stream-filter dispatcher, and content-token parser. Full upstream Python/model/benchmark parity remains dependency-gated on pdftext, pypdfium2, Surya/Torch/model downloads, tabled-pdf, Texify, and upstream benchmark tooling.

## Verification

- Red-first check before the fix: a `/Filter /Crypt` stream emitted `Unsupported filter leak`.
- `php -l lanes/markerpdf/src/PdfTextExtractor.php` passed.
- `php -l lanes/markerpdf/tests/PdfTextExtractorTest.php` passed.
- `php -l lanes/markerpdf/examples/wordpress-pdf-stream-filter-error-boundary-import.php` passed.
- `php lanes/markerpdf/examples/wordpress-pdf-stream-filter-error-boundary-import.php` emitted `Filter Boundary Visible` and `Filter Boundary Tail` with `unsupported_filter_excluded=true`, `corrupt_filter_excluded=true`, `stacked_unknown_filter_excluded=true`, and `missing_indirect_filter_excluded=true`.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfTextExtractorTest.php` passed: 1 file, 344 assertions, 0 failures.
- `php tools/run-tests.php lanes/markerpdf/tests` passed: 56 files, 2295 assertions, 0 failures.

## Non-Overlap

This does not repeat accepted ASCIIHex/ASCII85/RunLength/LZW/Flate predictor success-path decoding, DCT/CCITT/JPX/JBIG2 image-filter exclusions, indirect numeric DecodeParms predictor handling, encrypted-PDF fail-closed preflight, linearized hint-table byte-range exclusion, or startxref/xref object-stream rebuild precedence. The new behavior is specifically the declared-filter error boundary that prevents raw filtered bytes from leaking into WordPress text extraction when the declared chain cannot be honored.
