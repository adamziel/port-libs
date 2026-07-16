# markerPDF Inline Image Tokenizer Tight ID Boundary

Micro-slice: `markerpdf-inline-image-tokenizer-boundary-current-base-20260605T061130Z`

Base accepted HEAD: `02b29ee7e89e42a1c2518ec8dddaabdb2f1c6960`

## Source Truth

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text extraction through parser/PDFium-backed text extraction before image, OCR, and model stages. At that boundary, inline image `BI ... ID ... EI` bytes are raster payload, not visible WordPress paragraph text.

The native no-GPU tokenizer already keeps early `EI` bytes closed until an inline image sample floor is satisfied. This slice covers the adjacent malformed/minified tokenizer boundary where the inline image `ID` operator is followed immediately by raster bytes instead of the normal whitespace separator. The recovery is bounded to already parsed image dictionaries and still relies on the declared sample floor before accepting an `EI` terminator.

## Implementation

`PdfTextExtractor::readInlineImageDictionary()` now recognizes the `ID` data boundary before reading the next dictionary key token. Strict `ID` plus whitespace remains unchanged. Tight `ID` without whitespace is recovered only after the dictionary has real top-level image keys, preventing nested `/DecodeParms` decoys from becoming inline image boundaries.

The focused fixture uses `/W 16 /H 1 /CS /G /BPC 8 IDabc...`, with a fake `EI BT ... Tj ET` after only three sample bytes and the real `EI` after the payload. Before this patch, `Tight ID Inline Payload Noise` leaked into WordPress text. After the patch, only the surrounding page text is imported by `extractTextLines()`, `extractTextRuns()`, `extractPlainText()`, and `naiveGetText()`.

## Red First

One-off current-base fixture before the source change:

```text
array (
  0 => 'Before Tight ID Boundary',
  1 => 'Tight ID Inline Payload Noise',
  2 => 'After Tight ID Boundary',
)
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS recovers tight ID inline image data boundaries before WordPress text extraction
1 test files, 163 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineMaskCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineJpxSmaskDecodeCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineJpxColorKeyOutputPreviewCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineFilterPaletteAlphaCurrentBaseTest.php lanes/markerpdf/tests/PdfParserObjectStreamInlineImageFilterCurrentBaseTest.php lanes/markerpdf/tests/PdfParserInlineStreamJpxCMapRepairCurrentBaseTest.php lanes/markerpdf/tests/PdfParserInlineStreamOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfImageRendererTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
Focused test run: 12 selected test files (root lock skipped)
12 test files, 1720 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-inline-image-tokenizer-boundary-currentbase.php
```

The smoke emits `tight_id_inline_payload_excluded_until_sample_boundary=true`, keeps `Tight ID Inline Payload Noise`, `IDabc`, and `rawtail` out of visible text, and reports `executes_python_or_models=false` plus `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted malformed `BI` preamble recovery, unfiltered sample-length `EI` validation, compact slash-delimited dictionaries, nested dictionary decoy rejection, JBIG2/CCITT/unsupported preview-only fallback boundaries, JPX/DCT framing, inline image review metadata, object-stream inline-image repair, stream-filter fail-closed behavior, DCTDecode filter boundaries, CCITT DecodeParms renderer metadata, filtered decoded sample-floor acceptance, or malformed inline `/Decode` preview rejection.

The bounded behavior is only tight `ID` data-boundary recovery after a real inline image dictionary has already been parsed.

## Dependency Closure

No new support component is needed. This reuses the native content tokenizer, inline-image dictionary parser, declared sample-size calculator, `PdfTextExtractor`, and WordPress smoke path. Full live OCR/model/raster parity remains intentionally out of scope under the no-GPU markerPDF directive and remains gated on pdftext/PDFium/pypdfium2/PIL, Surya/Torch, tabled-pdf, Texify, runtime app/server workers, benchmark/model downloads, and external OCR/rendering helpers.
