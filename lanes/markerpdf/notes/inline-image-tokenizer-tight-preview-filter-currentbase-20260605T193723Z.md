# markerPDF Inline Image Tight Preview Filter Terminator Boundary

Micro-slice: `markerpdf-inline-image-tokenizer-boundary-current-base-20260605T193723Z`

Base accepted HEAD: `6f05ed9ef56a3e997ebab442f86ef1aa7076de74`

## Source Truth

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text through parser-backed PDF text extraction before image/OCR/model stages. At that boundary, inline image payload bytes between `BI ... ID` and the real `EI` image terminator are raster data, not WordPress-visible paragraph text.

The native PHP lane already treats DCTDecode, JPXDecode, JBIG2Decode, and CCITTFaxDecode inline images as preview-only tokenizer boundaries when raster/model execution is unavailable. This slice covers the tight terminator variant: a complete DCT/JPX payload can be followed immediately by `EI` without whitespace after the final image bytes, and the following text object must remain visible.

This stays inside the no-GPU markerPDF scope: no OCR, Surya, Texify, Torch, pypdfium/PDFium execution, Poppler, Ghostscript, Python, model workers, raster rendering, or external PDF tools were run.

## Implementation

`PdfTextExtractor::skipInlineImage()` now uses a tight-boundary helper for compact `EI` candidates. The helper first preserves the existing unfiltered declared sample-floor behavior. If that does not match, it resolves the inline image filters and accepts tight closure only when the existing preview-filter state machine reports a complete DCT, JPX, or CCITT payload.

The focused fixture adds two one-page inline images:

- `/F /DCTDecode` with a minimal JPEG SOI/EOI payload immediately followed by `EI`;
- `/F /JPXDecode` with a minimal JPX signature/end marker payload immediately followed by `EI`.

Both following text objects must be extracted, while `DCTDecode`, `JPXDecode`, raw image bytes, and `EI` markers stay out of WordPress paragraph text.

The WordPress smoke extends the existing inline-image tokenizer boundary example with the same tight DCT/JPX cases and emits `tight_preview_filter_terminators_preserve_following_text=true`.

## Red First

On the accepted base, a manual current-base probe with a tight DCT inline image:

```text
BI /W 1 /H 1 /CS /RGB /BPC 8 /F /DCTDecode ID
\xff\xd8\xff\xd9EI
BT /F1 12 Tf 72 700 Td (After Tight DCT) Tj ET
```

returned only:

```text
array (
  0 => 'Before Tight DCT',
)
```

The tokenizer swallowed the following text because the compact `EI` branch only accepted exact unfiltered sample-floor candidates.

## Verification

Syntax and lane status checks:

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
php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'
lane-status json ok
```

Baseline focused run before source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 342 assertions, 0 failures
```

Focused inline-image tokenizer run after implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 353 assertions, 0 failures
```

Adjacent inline-image boundary family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageMalformedFilterPreviewCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineJpxSmaskDecodeCurrentBaseTest.php lanes/markerpdf/tests/PdfParserObjectStreamInlineImageFilterCurrentBaseTest.php
Focused test run: 6 selected test files (root lock skipped)
6 test files, 922 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-inline-image-tokenizer-boundary-currentbase.php
```

The smoke exits 0 and emits `tight_preview_filter_terminators_preserve_following_text=true`, `visible_text_imported=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Diff whitespace check:

```text
git diff --check -- lanes/markerpdf
```

No whitespace errors; command exited 0.

Root harness: not run - isolated micro-slice.

## Status Delta

- Focused inline-image tokenizer assertions: `342 -> 353`.
- Focused PASS cases: `+1`.
- `phpPass`: `2177 -> 2178`.
- `wordpressScenarios`: `1875 -> 1876`.

## Non-Overlap

This does not repeat accepted malformed `BI` recovery, tight `ID` separators, immediate comments after `ID`, PDF NUL whitespace, unfiltered tight `EI` sample-floor closure, nested modifier-dictionary decoys, text-object `BI` decoys, slash-delimited `EI`, unsupported-filter closure, visible literal/TJ-array/ActualText boundaries, post-terminator comments, stray later `EI` recovery, same-line or graphics-state/XObject/clipping/shading wrapped stray `EI` recovery, ASCII85/ASCIIHex/Flate/LZW/RunLength EOD surplus handling, malformed DecodeParms fail-closed behavior, direct JBIG2/CCITT preview-filter closure, wrapped preview-filter chains, image preview metadata, object-stream inline-image repair, Image XObject payload exclusion, or OCR/model behavior.

The bounded behavior is specifically tight compact `EI` closure for complete DCTDecode and JPXDecode preview-only inline images before following WordPress text extraction.

## Dependency Closure

No new support component is needed. This reuses the native PHP content tokenizer, inline-image dictionary parser, stream-filter parser, existing DCT/JPX/CCITT preview-filter completion checks, `PdfTextExtractor`, focused lane tests, and the WordPress smoke renderer. Full upstream markerPDF model/PDFium raster parity remains intentionally gated by the current no-GPU/no-live-model scope.
