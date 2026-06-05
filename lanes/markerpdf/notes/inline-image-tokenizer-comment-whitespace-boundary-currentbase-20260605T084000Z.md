# markerPDF Inline Image Tokenizer Comment Whitespace Boundary

Micro-slice: `markerpdf-inline-image-tokenizer-boundary-current-base-20260605T084000Z`

Base accepted HEAD: `ef4cf6dacf5f14d3905927d3fba9b6ca3557990c`

## Source Truth

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF page text through `marker/pdf/extract_text.py` into parser/PDFium-backed extraction before image, OCR, and model stages. At that boundary, `BI ... ID ... EI` inline image bytes are raster payload and must stay out of WordPress paragraph text.

PDF comments are lexical whitespace outside strings. The previous current-base slice accepted a `% ... EOL` comment immediately after inline-image `ID` as the separator. This slice covers the adjacent byte boundary: after that comment line ending has supplied the separator, a following whitespace byte is image sample data and must not be consumed as a second separator.

## Red First

The accepted base probe used:

```text
BI /W 4 /H 1 /CS /G /BPC 8 ID% comment after ID
 abcEI
BT /F1 12 Tf 72 704 Td (After Comment Whitespace Boundary) Tj ET
```

Before the patch, native text extraction returned only:

```text
array (
  0 => 'Before Comment Whitespace Boundary',
)
```

The parser had consumed the leading space sample after the comment line ending, rejected the tight `EI` terminator because only three image bytes remained, and swallowed the following WordPress paragraph.

## Implementation

`PdfTextExtractor::readInlineImageDictionary()` now reports whether the `ID` separator still needs to be consumed. Normal `ID ` keeps the existing one-whitespace consumption. Tight `ID` recovery and comment-bounded `ID%...EOL` recovery now mark the data boundary as already positioned at the first image byte, so leading whitespace raster samples remain part of the inline image payload.

The existing dictionary preamble scanner was updated to pass through the new private flag without changing its detection behavior.

## Verification

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfTextExtractor.php
```

```text
php -l lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php
```

```text
php -l lanes/markerpdf/examples/wordpress-pdf-inline-image-tokenizer-boundary-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-inline-image-tokenizer-boundary-currentbase.php
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 198 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageMalformedFilterPreviewCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineMaskCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineJpxSmaskDecodeCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineJpxColorKeyOutputPreviewCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineFilterPaletteAlphaCurrentBaseTest.php lanes/markerpdf/tests/PdfParserObjectStreamInlineImageFilterCurrentBaseTest.php lanes/markerpdf/tests/PdfParserInlineStreamJpxCMapRepairCurrentBaseTest.php lanes/markerpdf/tests/PdfParserInlineStreamOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfImageRendererTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
Focused test run: 13 selected test files (root lock skipped)
13 test files, 1856 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-inline-image-tokenizer-boundary-currentbase.php
```

The smoke exits 0 and emits `comment_after_id_leading_whitespace_sample_preserved_for_tight_ei=true`, `visible_text_imported=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted malformed `BI` recovery, early `EI` sample-floor checks, tight `ID` without whitespace, immediate comment-after-`ID` payload exclusion, tight `EI` exact-sample terminators without comments, compact slash-delimited dictionaries, nested dictionary decoys, JBIG2/raw-JBIG2/CCITT/unsupported-filter boundaries, visible literal/TJ-array/marked-content `EI` recovery, post-terminator comment `EI` exclusion, named color-space fallbacks, ASCIIHex/RunLength/Flate/LZW decode boundaries, DCT/JPX preview framing, object-stream inline-image repair, or inline image review metadata.

The bounded behavior is specifically preserving a leading whitespace image sample after a comment-bounded inline-image `ID` separator so tight `EI` can close at the true declared sample floor.

## Dependency Closure

No new support component is needed. This reuses the native content tokenizer, inline-image dictionary parser, PDF comment skipper, sample-floor validation, `PdfTextExtractor`, focused lane tests, and the existing WordPress smoke path. Live OCR/model/raster parity remains intentionally out of scope under the current no-GPU markerPDF directive and remains gated on pdftext/PDFium/pypdfium2/PIL, Surya/Torch, tabled-pdf, Texify, runtime app/server workers, benchmark/model downloads, and external OCR/rendering helpers.
