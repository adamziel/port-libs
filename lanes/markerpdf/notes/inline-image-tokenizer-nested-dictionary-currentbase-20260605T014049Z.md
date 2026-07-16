# markerPDF Inline Image Tokenizer Nested Dictionary Boundary

Micro-slice: `markerpdf-inline-image-tokenizer-boundary-current-base-20260605T014049Z`

Base accepted HEAD: `e0d64a597b377814c637cda70ae27b61f47a2236`

## Source Truth

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text extraction through parser-backed PDF text extraction before image/OCR/model stages. At that boundary, a valid `BI ... ID ... EI` inline image is raster payload and must not become WordPress paragraph text.

The complementary tokenizer rule is that malformed `BI` operator text must not become an inline-image boundary just because image-looking names appear inside a nested operand dictionary. Inline-image image evidence must come from top-level image dictionary keys, not nested `/Width`, `/Filter`, or `/BitsPerComponent` decoys inside modifier dictionaries.

## Implementation

`PdfTextExtractor::inlineImageDictionaryHasImageKeys()` now checks top-level inline-image dictionary keys with the existing PDF dictionary-depth scanner instead of a broad regex over the assembled dictionary text.

The guard also treats `/Decode`, `/DecodeParms`, and `/Interpolate` as modifiers, not primary image evidence on their own. Existing valid boundaries with top-level `/Width`, `/Height`, `/ColorSpace`, `/BitsPerComponent`, `/ImageMask`, or `/Filter` still close through the existing tokenizer path.

## Red First

Before the final source guard, the focused fixture failed because `/DP << /Width 1 /Filter /FlateDecode /BitsPerComponent 8 >>` was treated as an inline image boundary:

```text
FAIL keeps malformed BI nested dictionary decoys from becoming inline image boundaries
Expected: Before Nested Dictionary Decoy, Nested Dictionary Decoy Text Survives, After Nested Dictionary Decoy
Actual: Before Nested Dictionary Decoy, After Nested Dictionary Decoy
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 90 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineMaskCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineJpxSmaskDecodeCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineJpxColorKeyOutputPreviewCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineFilterPaletteAlphaCurrentBaseTest.php lanes/markerpdf/tests/PdfParserObjectStreamInlineImageFilterCurrentBaseTest.php lanes/markerpdf/tests/PdfParserInlineStreamJpxCMapRepairCurrentBaseTest.php lanes/markerpdf/tests/PdfParserInlineStreamOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfImageRendererTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
Focused test run: 12 selected test files (root lock skipped)
12 test files, 1537 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-inline-image-tokenizer-boundary-currentbase.php
```

The smoke emitted `malformed_bi_nested_dictionary_decoy_preserved_as_text_boundary=true`, `compact_slash_delimited_inline_image_excluded=true`, `real_inline_image_payload_excluded=true`, `visible_text_imported=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted malformed `BI` preamble recovery, slash-delimited compact inline-image dictionaries, early `EI` sample-floor behavior, Flate/DecodeParms validation, filter-array null alignment, DCT/JPX/JBIG2/CCITT preview-only framing, raw JBIG2 boundaries, object-stream inline-image filter repair, or image XObject payload exclusion.

The new boundary is specifically malformed `BI` text where image-like names appear only inside nested modifier dictionaries and must not trigger inline-image skipping.

## Dependency Closure

No new support component is needed. This reuses the native PHP content tokenizer, PDF dictionary-depth scanner, inline-image dictionary parser, `PdfTextExtractor`, focused lane tests, and WordPress smoke path. Live OCR/model/raster parity remains intentionally out of scope under the current no-GPU markerPDF directive and remains gated on pdftext/PDFium/pypdfium2/PIL, Surya/Torch, tabled-pdf, Texify, runtime app/server workers, benchmark/model downloads, and external OCR/rendering helpers.
