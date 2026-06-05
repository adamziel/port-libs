# markerPDF Inline Image Decode Malformed Filter Boundary

Micro-slice: `markerpdf-inline-image-decode-boundary-current-base-20260605T025635Z`

Base accepted HEAD: `138f4be69644756800069e6b54dd0c178419b02d`

## Source Truth

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text through parser-backed PDF extraction before image/OCR/model stages. At that boundary, `BI ... ID ... EI` inline image bytes are image payload, not WordPress paragraph text.

The native PHP port already validates unfiltered, Flate/DecodeParms, ASCII85, LZW, RunLength, DCT, JPX, JBIG2, CCITT, and unsupported-filter inline image boundaries. This slice covers a remaining unsafe decode boundary: malformed or unresolved `/Filter` operands must not be treated as if no filter exists, because the parser cannot safely interpret delimiter-looking `EI` bytes inside an undecodable inline image payload.

## Red First

Before the source change, an ad-hoc focused probe leaked payload text:

```text
array (
  0 => 'Before Malformed Filter Inline',
  1 => 'Malformed Filter Inline Noise',
  2 => 'After Malformed Filter Inline',
)
```

The fixture used a malformed filter array operand:

```text
BI /W 8 /H 1 /CS /G /BPC 8 /F [ << /Bad true >> ] ID
abc EI BT /F1 12 Tf 72 660 Td (Malformed Filter Inline Noise) Tj ET rawtail
EI
```

The same resolver path also applies to unresolved inline image filter references such as `/F 99 0 R`.

## Implementation

`PdfTextExtractor::inlineImageCandidateMatchesDictionary()` now rejects `streamFilters() === null` instead of accepting the first `EI` as an unfiltered image boundary.

`PdfTextExtractor::inlineImageCandidateIsIncompletePreviewOnly()` treats malformed or unresolved filter chains as fail-closed image payloads, matching the existing unsupported-filter tokenizer stance.

`PdfTextExtractor::inlineImageIncompletePreviewCandidateReachedSampleFloor()` now lets malformed/unresolved filter fallbacks use the declared raw sample floor. That preserves text between consecutive inline images: once enough inline payload bytes have been consumed and the tokenizer sees a following `BI` preamble, it can close the previous malformed image at the safer fallback boundary instead of swallowing interstitial WordPress text.

## Evidence

Focused behavior:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS requires ASCII85 inline image end marker before accepting delimiter-looking EI bytes
PASS requires ASCII85 inline image review payload terminator before RGB preview decoding
PASS decodes Flate DecodeParms inline image payload before accepting EI boundaries
PASS accepts filtered inline image EI after decoded sample floor is reached
PASS decodes LZW DecodeParms inline image payload before Indexed RGB preview
PASS requires RunLength EOD before inline image decode preview accepts supplied samples
PASS fails closed on malformed inline image filter operands before WordPress text extraction
PASS fails closed on unresolved inline image filter operands before WordPress text extraction
PASS closes malformed inline image filter fallbacks before the next inline image preamble
PASS resolves current indirect inline image decode operands before Indexed RGB preview
PASS resolves current indirect inline ImageMask geometry before stencil preview

1 test files, 132 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-inline-image-decode-boundary-currentbase.php
```

The smoke emits `malformed_inline_filter_operand_payload_excluded_until_safe_boundary=true`, `unresolved_inline_filter_operand_payload_excluded_until_safe_boundary=true`, `visible_text_imported=true`, `excluded_inline_image_text=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`, with clean Gutenberg paragraphs before and after both malformed inline images.

Inline-image adjacent family, excluding the broad all-purpose `PdfTextExtractorTest.php` file:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineMaskCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineJpxSmaskDecodeCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineJpxColorKeyOutputPreviewCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineFilterPaletteAlphaCurrentBaseTest.php lanes/markerpdf/tests/PdfParserObjectStreamInlineImageFilterCurrentBaseTest.php lanes/markerpdf/tests/PdfParserInlineStreamJpxCMapRepairCurrentBaseTest.php lanes/markerpdf/tests/PdfParserInlineStreamOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfImageRendererTest.php
Focused test run: 11 selected test files (root lock skipped)
11 test files, 981 assertions, 0 failures
```

Broader adjacent command including `lanes/markerpdf/tests/PdfTextExtractorTest.php` was not used as passing handoff evidence because an unrelated xref case failed with `xrefEntryInheritedFromPreviousSection(): Argument #2 ($previousOffset) must be of type int, null given` and a PHP warning at `PdfTextExtractor.php:15236`. This patch does not alter the xref path.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted malformed `BI` preamble recovery, unfiltered inline image sample-length validation, ASCII85 explicit terminator review, Flate DecodeParms delimiter validation, filtered sample-floor acceptance, indirect inline preview operand resolution, LZW DecodeParms preview rows, RunLength EOD handling, DCT/JPX/JBIG2/CCITT preview-only tokenizer framing, unsupported `/Crypt` filter boundaries, inline ImageMask preview rows, inline Indexed palette/alpha previews, filter-array null alignment, object-stream inline-image repair, image XObject payload exclusion, or standalone stream-filter fail-closed behavior.

The bounded behavior is specifically malformed or unresolved inline image `/Filter` operands at native `BI`/`ID`/`EI` decode boundaries.

## Dependency Closure

No new support component is needed. This reuses the native PHP content tokenizer, inline image dictionary parser, stream filter resolver, declared sample-size calculator, `PdfTextExtractor`, focused tests, and the existing WordPress smoke path. Full OCR/model/raster parity remains intentionally out of scope under the no-GPU markerPDF directive and remains gated on pdftext/PDFium/pypdfium2/PIL, Surya/Torch, tabled-pdf, Texify, runtime app/server workers, benchmark/model downloads, and external OCR/rendering helpers.
