# Inline Image Tokenizer ImageMask Sample-Floor Current Base

Micro-slice: `markerpdf-inline-image-tokenizer-boundary-current-base-20260608T152256Z`

Base accepted HEAD: `71b9b5d33e2e3f2482d3351186b2396df20d9ff5`

## Source Truth

Upstream markerPDF keeps searchable PDF text extraction separate from raster image/OCR/model fallback. In the native no-GPU PHP lane, inline-image bytes owned by `BI ... ID ... EI` must stay out of WordPress paragraphs while valid text after the actual image terminator remains visible.

PDF inline ImageMask dictionaries can use `/ImageMask true` without `/ColorSpace` or `/BitsPerComponent`; their tokenizer sample floor is one packed bit per pixel. This slice records the current-base boundary for tight `EI` tokens against that packed stencil floor:

- `/W 8 /H 1 /IM true` closes at tight `\x80EI` and preserves following text.
- `/W 9 /H 1 /IM true` does not close at the same one-byte tight `EI`; payload-looking text remains image-owned until the later real terminator.

## Change

- Added `PdfInlineImageTokenizerImageMaskBoundaryCurrentBaseTest.php` with two focused ImageMask tokenizer cases.
- Added `wordpress-pdf-inline-image-tokenizer-imagemask-currentbase.php` as a WordPress smoke for the same import path.
- Updated lane status for two focused PASS cases and one WordPress scenario.

No production source change was needed: the current native tokenizer already derives ImageMask sample floors from `/ImageMask true` and tight `EI` ownership. This patch makes the behavior countable on the accepted base without Python, OCR, GPU/model code, PDFium/PIL raster rendering, or external PDF tools.

## Verification

`php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerImageMaskBoundaryCurrentBaseTest.php`

Result: `1 test files, 22 assertions, 0 failures`.

`php lanes/markerpdf/examples/wordpress-pdf-inline-image-tokenizer-imagemask-currentbase.php`

Result: smoke exits 0 and emits `tight_imagemask_sample_floor_text_preserved=true`, `premature_tight_imagemask_ei_payload_excluded_until_floor=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted malformed `BI` recovery, tight `ID`, ordinary unfiltered RGB/Gray tight `EI`, terminal whitespace sample floors, tight DCT/JPX/JBIG2 preview-filter terminators, whitespace-delimited JBIG2 ImageMask fallback, inline ImageMask preview-row rendering metadata, CCITT/JBIG2 payload exclusion, decoded surplus sample metadata, text-positioning after preview fallback, stream filters, image review metadata, xref repair, annotations, forms, tables, equations, OCR, or model execution.

The bounded behavior here is only unfiltered inline ImageMask tokenizer ownership when `/ImageMask true` supplies the packed one-bit sample floor and `/ColorSpace` plus `/BitsPerComponent` are absent.

## Dependency Closure

No new support component is needed. This reuses native PHP content-stream tokenization, inline-image dictionary parsing, ImageMask boolean parsing, packed sample-floor geometry, text extraction, and the lane WordPress smoke pattern. Full live OCR/model/raster parity remains intentionally out of scope under the current markerPDF no-GPU directive.
