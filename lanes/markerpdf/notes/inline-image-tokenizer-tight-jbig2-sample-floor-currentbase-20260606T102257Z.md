# markerPDF Inline Image Tight JBIG2 Sample Floor Boundary

Micro-slice: `markerpdf-inline-image-tokenizer-boundary-current-base-20260606T102257Z`

Base accepted HEAD: `b97f6bf2feb7e372488837f87839f3624967856e`

## Source Truth

Upstream markerPDF routes searchable PDF text through parser-backed PDF text extraction before image/OCR/model fallback. Under the current no-GPU PHP lane, native content-stream tokenization must keep `BI ... ID ... EI` inline-image bytes out of WordPress paragraphs while preserving valid text after an inline-image terminator.

PDF inline images use `EI` as the end operator. This lane already recovered tight unfiltered sample-floor terminators and tight DCT/JPX preview-filter terminators. This slice maps the adjacent JBIG2 preview-only ImageMask case where declared geometry gives a one-byte sample floor and the `EI` operator is tight against the sample byte: `\x80EI BT ...`.

## Red-First Evidence

After adding the focused regression and before the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php
FAIL recovers tight JBIG2 sample-floor inline image terminators before WordPress text extraction
Expected: ['Before Tight JBIG2 Sample Floor', 'Visible Tight JBIG2 Sample Floor', 'After Tight JBIG2 Sample Floor']
Actual: ['Before Tight JBIG2 Sample Floor']
1 test files, 474 assertions, 1 failures
```

## Implementation

`PdfTextExtractor::skipInlineImage()` now lets tight JBIG2 candidates seed the existing preview fallback only when all of these are true:

- the inline image uses `JBIG2Decode`;
- the candidate is still preview-only/incomplete;
- declared geometry provides an expected decoded sample floor;
- the bytes before the preview filter are exactly that floor length.

That exact-floor guard prevents later tight `EI` bytes after surplus content from overriding an earlier safer fallback, while preserving the new `\x80EI BT ...` boundary.

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php
1 test files, 483 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageMalformedFilterPreviewCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineMaskCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineJpxSmaskDecodeCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineJpxColorKeyOutputPreviewCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineFilterPaletteAlphaCurrentBaseTest.php lanes/markerpdf/tests/PdfParserObjectStreamInlineImageFilterCurrentBaseTest.php lanes/markerpdf/tests/PdfParserInlineStreamJpxCMapRepairCurrentBaseTest.php lanes/markerpdf/tests/PdfParserInlineStreamOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfImageRendererTest.php
12 test files, 1979 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-inline-image-tokenizer-boundary-currentbase.php | rg "tight_jbig2|raw_jbig2_segment|After Raw JBIG2|Visible Tight JBIG2"
```

The smoke exited 0 and emitted a metadata comment containing `tight_jbig2_sample_floor_terminator_preserves_text=true` and `raw_jbig2_segment_payload_excluded_until_safe_boundary=true`, plus Gutenberg paragraphs for `After Raw JBIG2 Boundary` and `Visible Tight JBIG2 Sample Floor`. The accumulated smoke still emits a pre-existing generic `visible_text_imported=false` flag, so the handoff evidence uses the slice-specific tight-JBIG2 flag and the raw-JBIG2 regression guard.

## Non-Overlap

This does not repeat malformed `BI` recovery, tight `ID`, unfiltered tight `EI`, tight DCT/JPX preview terminators, whitespace-delimited JBIG2 fallback, raw JBIG2 surplus closure, CCITT fallback, unsupported-filter fallback, post-terminator comment handling, same-line content-prefix handling, or graphics/text/marked-content operator validator slices. The bounded behavior is exact sample-floor tight `EI` fallback for preview-only JBIG2 ImageMask inline images.

## Dependency Closure

No new support component is needed. This reuses native PHP content-stream tokenization, inline-image dictionary parsing, existing JBIG2 preview-only classification, sample-floor geometry calculation, and the existing WordPress smoke. Live OCR/model/PDFium/PIL/JBIG2 raster decoding and exact upstream benchmark parity remain intentionally out of scope under the no-GPU markerPDF directive.
