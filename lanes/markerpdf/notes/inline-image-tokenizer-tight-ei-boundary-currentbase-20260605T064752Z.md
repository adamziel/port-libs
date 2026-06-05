# markerPDF Inline Image Tokenizer Tight EI Boundary

Micro-slice: `markerpdf-inline-image-tokenizer-boundary-current-base-20260605T064752Z`

Base accepted HEAD: `513c457363a14f83e08080a9ac834402b5c747ec`

## Source Truth

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text through parser-backed page text before image/OCR/model stages. At that boundary, `BI ... ID ... EI` inline image bytes are raster payload and must not become WordPress paragraph text, while text after the inline image must remain importable.

The native no-GPU tokenizer already recovered tight `ID` data separators and whitespace-delimited sample-floor `EI` terminators. This slice covers the adjacent minified boundary where an unfiltered inline image reaches its exact declared sample floor immediately before `EI`, for example `IDxEI`, without whitespace before the `EI` operator.

## Red First

Before the source change, the focused fixture swallowed the text after the inline image:

```text
FAIL recovers tight EI inline image terminators after exact sample floors before WordPress text extraction
Expected: array (
  0 => 'Before Tight EI Boundary',
  1 => 'After Tight EI Boundary',
)
Actual: array (
  0 => 'Before Tight EI Boundary',
)
```

The fixture used:

```text
BI /W 1 /H 1 /CS /G /BPC 8 IDxEI
BT /F1 12 Tf 72 704 Td (After Tight EI Boundary) Tj ET
```

## Implementation

`PdfTextExtractor::skipInlineImage()` now recognizes a tight `EI` terminator only when:

- the candidate `EI` is followed by a normal token delimiter;
- the byte before `EI` is not whitespace, so the normal end-marker path did not apply;
- the inline image has no stream filters; and
- the raw candidate length exactly matches the declared decoded sample floor or named-color-space minimum floor.

This keeps preview-only and filtered payloads on the existing safer tokenizer paths while recovering minified unfiltered image data before WordPress paragraph parsing resumes.

## Verification

Focused tokenizer test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 171 assertions, 0 failures
```

Adjacent inline-image/text extractor family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineMaskCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineJpxSmaskDecodeCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineJpxColorKeyOutputPreviewCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineFilterPaletteAlphaCurrentBaseTest.php lanes/markerpdf/tests/PdfParserObjectStreamInlineImageFilterCurrentBaseTest.php lanes/markerpdf/tests/PdfParserInlineStreamJpxCMapRepairCurrentBaseTest.php lanes/markerpdf/tests/PdfParserInlineStreamOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfImageRendererTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
Focused test run: 12 selected test files (root lock skipped)
12 test files, 1747 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-inline-image-tokenizer-boundary-currentbase.php
```

The smoke emits `tight_ei_inline_terminator_recovers_after_exact_sample_floor=true`, keeps `After Tight EI Boundary` in visible text, excludes `IDxEI` and `xEI`, and reports `executes_python_or_models=false` plus `executes_external_pdf_tools=false`.

Syntax, status, and diff checks:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-inline-image-tokenizer-boundary-currentbase.php
php -r '$p="lanes/markerpdf/lane-status.json"; json_decode(file_get_contents($p), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'
git diff --check -- lanes/markerpdf
```

All passed.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted malformed `BI` preamble recovery, tight `ID` data-boundary recovery, slash-delimited compact inline-image dictionaries, nested dictionary decoy rejection, whitespace-delimited early `EI` sample-floor behavior, terminal-whitespace sample handling, ASCII85/Flate/LZW/RunLength DecodeParms validation, DCT/JPX/JBIG2/CCITT preview-only framing, unsupported-filter closure, marked-content fallback selection, object-stream inline-image repair, image XObject payload exclusion, or inline image review metadata.

The bounded behavior is only unfiltered tight `EI` terminator recovery after the exact sample floor has already been reached.

## Dependency Closure

No new support component is needed. This reuses the native content tokenizer, inline-image dictionary parser, declared sample-size calculator, `PdfTextExtractor`, focused lane tests, and WordPress smoke path. Full live OCR/model/raster parity remains intentionally out of scope under the no-GPU markerPDF directive and remains gated on pdftext/PDFium/pypdfium2/PIL, Surya/Torch, tabled-pdf, Texify, runtime app/server workers, benchmark/model downloads, and external OCR/rendering helpers.
