# markerPDF Stream Length Endstream Recovery

Slice: `markerpdf-stream-length-endstream-recovery-current-base-20260602T0623Z`

## Source Truth

Upstream markerPDF at `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes `marker/pdf/extract_text.py::naive_get_text()` through `pypdfium2` page text extraction. At that boundary, the PDF parser recovers page text from real PDF streams before markerPDF block cleanup runs; the native PHP port should therefore tolerate stale direct or indirect `/Length` values when a bounded `endstream` terminator is available, while continuing to honor declared filter chains and image/filter exclusions.

## Implementation

`PdfTextExtractor::streamPayloadAt()` now treats a declared `/Length` as authoritative only when the declared byte end lands at an `endstream` keyword after an optional stream-ending line break. If the length is stale, truncated, or overshoots the object body, the reader recovers the payload from a bounded line-delimited `endstream` terminator, preferring markers near the declared end. The same payload reader is reused for CMap streams.

Unsupported or corrupt filters still fail closed through `decodeStream()`, so endstream recovery cannot turn `/Crypt` or unknown filtered payload bytes into visible WordPress text.

## WordPress Path

`examples/wordpress-pdf-stream-length-endstream-recovery-import.php` models a WordPress PDF import with stale short Flate `/Length`, stale raw `/Length`, a missing `/Length` stream containing the word `endstream` inside visible text, a valid declared length with the same word, and an unsupported filtered stale-length stream. It emits recovered Gutenberg paragraphs only for visible content and records exclusion flags without executing Python, pypdfium, pdftext, models, or external PDF tools.

## Dependency Closure

No new support component is needed. This slice reuses the existing native PDF object scanner, stream dictionary parser, indirect integer resolver, filter dispatcher, CMap parser, and content-token text extractor. Full upstream benchmark/model parity remains gated on pdftext, pypdfium2/PDFium, Surya/Torch/model downloads, tabled-pdf, Texify, and benchmark runner dependencies.

## Verification

- Red-first probe before the fix: a `/Filter /FlateDecode` stream with `/Length` five bytes short returned `''` instead of `Recovered Length Stream\nEndstream Fallback`.
- `php -l lanes/markerpdf/src/PdfTextExtractor.php` passed.
- `php -l lanes/markerpdf/tests/PdfTextExtractorTest.php` passed.
- `php -l lanes/markerpdf/examples/wordpress-pdf-stream-length-endstream-recovery-import.php` passed.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfTextExtractorTest.php` passed with 1 file, 363 assertions, and 0 failures.
- `php lanes/markerpdf/examples/wordpress-pdf-stream-length-endstream-recovery-import.php` emitted recovered Gutenberg paragraphs plus `unsupported_filter_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.
- `php tools/run-tests.php lanes/markerpdf/tests` passed with 58 files, 2360 assertions, and 0 failures.
- `git diff --check -- lanes/markerpdf` passed.

## Non-Overlap

This does not repeat accepted length-bounded ASCIIHex/RunLength success-path decoding, stream-filter error-boundary exclusion, indirect DecodeParms predictor handling, object-stream indirect `/Length`/`/Filter`/`/N`/`/First` recovery, latest startxref xref-chain precedence, linearized hint-table exclusion, image-filter exclusions, or encrypted-PDF fail-closed preflight. The new behavior is specifically stale or missing stream `/Length` recovery at the native PDF payload boundary.
