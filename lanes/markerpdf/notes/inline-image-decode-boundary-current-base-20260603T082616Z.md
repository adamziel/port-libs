# markerPDF Inline Image Decode Boundary

Micro-slice: `markerpdf-inline-image-decode-boundary-current-base-20260603T082616Z`

Base accepted HEAD: `36c59c783187352a699b8099a3a132c271310611`

## Source Truth

Upstream markerPDF commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` delegates searchable PDF text extraction through `marker/pdf/extract_text.py` into pdftext/PDFium. At that dependency boundary, page inline image bytes are image payload, not visible text. The native content tokenizer therefore must not treat `BI ... ID ... EI` image data as text-showing operators or WordPress paragraph content.

Relevant PDF parser behavior: inline images may use abbreviated dictionary keys and values such as `/W`, `/H`, `/BPC`, `/CS /G`, `/F /Fl`, and `/DP`; `DecodeParms` can affect decoded sample bytes. A delimiter-looking ` EI ` inside raw or compressed image bytes is not the content stream terminator until the image payload boundary is satisfied.

## Implementation

`PdfTextExtractor::contentTokens()` now recognizes `BI` as an inline image boundary and delegates to a dictionary-aware skipper. The skipper:

- reads inline image dictionary tokens until `ID`;
- expands standard inline image key/value abbreviations to normal stream dictionary names;
- skips image data through a valid `EI` marker;
- validates verifiable filters with the existing stream decoder and Flate predictor handling;
- uses width, height, color space, image mask, and bits-per-component to avoid accepting early `EI` bytes before the expected sample boundary.

Text extraction then resumes after the real inline image boundary.

## Evidence

Focused behavior added two passing `PdfTextExtractorTest` cases and raised the focused extractor test from `63` to `76` assertions.

Commands run:

- `php -l lanes/markerpdf/src/PdfTextExtractor.php` passed.
- `php -l lanes/markerpdf/tests/PdfTextExtractorTest.php` passed.
- `php -l lanes/markerpdf/examples/wordpress-pdf-inline-image-decode-boundary-currentbase.php` passed.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfTextExtractorTest.php` passed: `1 test files, 76 assertions, 0 failures`.
- `php lanes/markerpdf/examples/wordpress-pdf-inline-image-decode-boundary-currentbase.php` emitted `fake_ei_inside_compressed_payload=true`, `visible_text_imported=true`, and `excluded_inline_image_text=true`.
- `php tools/run-tests.php lanes/markerpdf/tests` passed: `47 test files, 1009 assertions, 0 failures`.
- `git diff --check -- lanes/markerpdf` passed.

Root harness: not run - isolated micro-slice.

## Status Delta

- `lane-status.json` `phpPass`: `293 -> 295`.
- Manifest mapped source/dependency semantics: `177 -> 178`.
- Focused assertion evidence: markerPDF lane suite `996 -> 1009` assertions on this base.

## Non-Overlap

This does not execute OCR, Surya, Texify, Torch, PDFium, PIL, Streamlit, FastAPI, pdftext, Poppler, Ghostscript, or external PDF tools. It does not implement live raster decoding or image extraction. The bounded behavior is only the native searchable-PDF content tokenizer boundary that excludes inline image payload bytes, including Flate/DecodeParms early-`EI` cases, from visible WordPress text.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP content-stream tokenizer, stream filter dispatcher, DecodeParms predictor decoder, and WordPress smoke path. Full upstream model/benchmark parity remains gated on the existing heavy Python/PDF/model runtime dependencies; broader searchable PDF dictionary extraction remains behind the inactive `pdf-text-dictionary-core` row if a future rich slice opens.

## Next Task

Prefer a non-overlapping searchable-PDF parser gap: image XObject/form XObject text isolation, a remaining content-stream tokenizer edge, CMap/font encoding behavior, xref/object stream repair, page geometry, annotations/forms, or supplied-boundary table/equation handoff behavior.
