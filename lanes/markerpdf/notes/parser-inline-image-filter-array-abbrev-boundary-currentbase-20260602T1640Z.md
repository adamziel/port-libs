# markerPDF parser inline image filter array abbreviation boundary

Session: `port-dev-markerpdf-parser30pdf-20260602T1640Z`
Micro-slice: `parser-inline-image-filter-array-abbrev-boundary-currentbase-20260602T1640Z`
Base accepted HEAD: `2c21071f7e9064c624f93392d27c864177463373`

## Source Truth

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes visible PDF text extraction through `marker/pdf/extract_text.py` into the `pdftext` / PDF parser boundary. Inline image bytes are image payloads, not visible text, and PDF inline image dictionaries may use abbreviated keys and filter names such as `/F`, `/DP`, and `/Fl`.

This native slice keeps the already accepted inline-image DecodeParms behavior but fixes the filter-array boundary where a no-op `null` filter entry precedes an abbreviated real filter. The parser already accepts `null` filter-array entries as no-op values; the missing behavior was preserving those entries long enough to keep `/DecodeParms` array indexes aligned.

## Red-First Probe

Before the fix, the accepted-base fixture:

- used `BI /W ... /H 1 /CS /G /BPC 8 /F [ null /Fl ] /DP [ null << /Predictor 12 ... >> ] ID ... EI`;
- embedded compressed image bytes containing the literal byte sequence ` EI BT ... (Null Filter Array Noise) Tj ET`;
- returned only `Before Null Filter Array` because the real final `EI` was rejected after the predictor dictionary was shifted off the Flate filter.

## Implementation

`PdfTextExtractor::streamFilters()` now preserves `null` entries returned from filter arrays. `PdfTextExtractor::decodeStream()` consumes the aligned DecodeParms slot for each no-op filter and skips actual decoding for that entry. Real filters still decode exactly as before.

This lets inline image candidate validation apply the Flate predictor dictionary at the correct array index, reject fake `EI` bytes inside the compressed payload, accept the real final `EI`, and preserve following page text.

## Verification

- `php -l lanes/markerpdf/src/PdfTextExtractor.php` passed.
- `php -l lanes/markerpdf/tests/PdfTextExtractorTest.php` passed.
- `php -l lanes/markerpdf/examples/wordpress-pdf-inline-image-filter-array-abbrev-boundary-currentbase.php` passed.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfTextExtractorTest.php` passed: `1 test files, 585 assertions, 0 failures`.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfParserStreamFilterObjectBoundaryTest.php lanes/markerpdf/tests/PdfParserXrefStreamFilterDecodeParmsCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefObjectStreamFilterChainCurrentBaseTest.php` passed: `3 test files, 34 assertions, 0 failures`.
- `php lanes/markerpdf/examples/wordpress-pdf-inline-image-filter-array-abbrev-boundary-currentbase.php` emitted `fake_ei_inside_compressed_payload=true`, `visible_text_imported=true`, and `excluded_inline_image_text=true`.

## Non-Overlap

This does not repeat accepted raw `BI / ID / EI` inline image exclusion, direct `/F /Fl /DP << ... >>` inline image DecodeParms validation, indirect stream filter arrays, xref stream filter DecodeParms, object stream filter-chain recovery, nested object-stream filter fail-closed handling, image XObject filter exclusion, DCT/CCITT/JPX/JBIG2 image boundaries, or generic stream-filter fail-closed behavior.

The new behavior is specifically inline image `/F` arrays with no-op `null` entries and abbreviated `/Fl` values where `/DP` arrays must stay index-aligned before validating `EI` markers in compressed payload bytes.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP content tokenizer, inline-image dictionary parser, stream filter dispatcher, DecodeParms predictor decoder, and WordPress smoke path. Full live upstream parity remains gated on `pdftext`, `pypdfium2`/PDFium, Surya/Torch/model downloads, tabled-pdf, Texify, Streamlit/FastAPI runtimes, benchmark/model downloads, and optional OCR/rendering tools.
