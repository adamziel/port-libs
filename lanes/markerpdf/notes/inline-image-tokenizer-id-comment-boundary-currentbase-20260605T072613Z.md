# markerPDF Inline Image Tokenizer ID Comment Boundary

Micro-slice: `markerpdf-inline-image-tokenizer-boundary-current-base-20260605T072613Z`

Base accepted HEAD: `17dbfeadf12027c4877b7ae89d1c4dadc1683066`

## Source Truth

Upstream `sddai/markerPDF` at commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF page text through `marker/pdf/extract_text.py` into parser/PDFium-backed text extraction before image, OCR, and model stages. At this native no-GPU boundary, `BI ... ID ... EI` inline image bytes are raster payload and must stay out of WordPress paragraph text.

PDF comments are lexical whitespace outside strings. This slice covers the adjacent tokenizer case where the inline image `ID` operator is followed immediately by a `% ... EOL` comment. The comment separates the `ID` token from image data; it should not be counted as an image sample, and delimiter-looking `EI` bytes inside the real payload must not reopen text parsing.

## Implementation

`PdfTextExtractor::inlineImageDataBoundaryOffset()` now recognizes an immediate PDF comment after `ID` and returns the first byte after the comment line ending as the inline-image data start. The existing `ID ` separator behavior remains unchanged, so `ID  EI` still preserves the second space as a legitimate image sample.

## Red First

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL treats immediate PDF comments after inline image ID as token separators before WordPress text extraction
Expected: ['Before Comment ID Boundary', 'After Comment ID Boundary']
Actual: ['Before Comment ID Boundary', 'Comment ID Inline Payload Noise', 'After Comment ID Boundary']
1 test files, 172 assertions, 1 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS treats immediate PDF comments after inline image ID as token separators before WordPress text extraction
1 test files, 180 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-inline-image-tokenizer-boundary-currentbase.php
```

The smoke exits 0 and emits `comment_after_id_inline_payload_excluded_until_sample_boundary=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted malformed `BI` preamble recovery, tight `ID` without whitespace recovery, tight `EI` exact-sample terminators, compact slash-delimited dictionaries, nested `/DecodeParms` decoys, unfiltered sample-floor validation, ASCII85/ASCIIHex/Flate/LZW/RunLength DecodeParms validation, JPX/DCT/JBIG2/CCITT preview-only framing, unsupported-filter fallback closure, visible-literal/TJ-array/ActualText fallback selection, image XObject exclusion, object-stream inline-image repair, or stream-filter fail-closed behavior.

The bounded new behavior is only immediate PDF-comment token separation after inline-image `ID`.

## Dependency Closure

No new support component is needed. This reuses the native content tokenizer, inline-image dictionary parser, PDF comment skipper, sample-floor validation, `PdfTextExtractor`, focused lane tests, and the existing WordPress smoke path. Live OCR/model/raster parity remains intentionally out of scope under the current no-GPU markerPDF directive and remains gated on pdftext/PDFium/pypdfium2/PIL, Surya/Torch, tabled-pdf, Texify, runtime app/server workers, benchmark/model downloads, and external OCR/rendering helpers.
