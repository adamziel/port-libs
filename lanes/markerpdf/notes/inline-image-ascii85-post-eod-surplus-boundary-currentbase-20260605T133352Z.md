# markerPDF inline image ASCII85 post-EOD surplus boundary current base

Micro-slice: `markerpdf-inline-image-decode-boundary-current-base-20260605T133352Z`

Base accepted HEAD: `d9125af6a016500c53c9d723eacb8808f8b9e63a`

## Source truth

Upstream `sddai/markerPDF` remains pinned in the lane manifest at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`. The no-GPU searchable-PDF path obtains page text through `marker/pdf/extract_text.py::get_text_blocks()` via `pdftext.extraction.dictionary_output(...)` and the upstream `naive_get_text()` page-text fallback. The native PHP lane owns the PDF parser boundary that decides which page content tokens become WordPress paragraphs before any OCR/model stage.

Relevant PDF behavior for this slice: inline-image data begins after `ID` and ends at a delimiter `EI`, but filtered image data can contain delimiter-looking `EI` bytes. ASCII85 payloads have an explicit `~>` EOD marker. If malformed post-EOD surplus bytes after `~>` contain fake `EI` plus text-like operators, the tokenizer must not reopen visible text parsing at that fake marker; it should keep the image payload closed until the later real inline-image terminator. RGB/image preview remains fail-closed for post-EOD surplus.

## Implementation

- `PdfTextExtractor::inlineImageCandidateMatchesDictionary()` now checks ASCII85 post-EOD surplus alongside the existing ASCIIHex, Flate, LZW, and native-stack surplus boundaries.
- The new `inlineAscii85CandidateReachesSampleFloorBeforePostEodSurplus()` helper:
  - applies only to single ASCII85/A85 filter inline images with a declared decoded sample floor;
  - requires an ASCII85 `~>` EOD marker;
  - requires delimiter-looking `EI` in non-whitespace post-EOD surplus, so ordinary `~> EI` valid terminators are not reclassified;
  - decodes only through the `~>` EOD and accepts the later real terminator when the decoded bytes satisfy the declared sample floor.
- The WordPress smoke now includes an inline image with `z~>ZZ EI ... real EI`, preserving the following paragraph while excluding surplus payload text.

## Red Evidence

Before the source fix, a focused probe with:

```text
BI /W 4 /H 1 /CS /G /BPC 8 /F /A85 ID z~>ZZ EI ... real EI
```

extracted only the before paragraph:

```text
array (
  0 => 'Before A85 Post EOD Inline Image',
)
```

The after paragraph was swallowed because the tokenizer rejected the fake `EI` candidate after `~>` but never recovered at the real `EI`.

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php
1 test files, 386 assertions, 0 failures

php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDctDecodeFilterBoundaryCurrentBaseTest.php
3 test files, 669 assertions, 0 failures

php lanes/markerpdf/examples/wordpress-pdf-inline-image-decode-boundary-currentbase.php
emits visible_text_imported=true, fake_ei_inside_ascii85_post_eod_surplus_payload=true, ascii85_post_eod_surplus_payload_excluded_until_real_ei=true, inline_filter_post_eod_surplus_preview_rejected=true, executes_python_or_models=false, and executes_external_pdf_tools=false.
```

## Non-Overlap

This does not repeat accepted inline-image abbreviation/DecodeParms decoding, ASCII85 missing-EOD preview rejection, fake `EI` before ASCII85 `~>`, ASCIIHex surplus before EOD, Flate post-stream surplus, stacked native filter surplus, LZW post-EOD surplus, RunLength EOD preview boundaries, DCT/JPX/JBIG2/CCITT preview-only tokenizer boundaries, malformed filter operands, null-filter DecodeParms slots, or Image XObject metadata/review behavior.

The bounded new behavior is specifically ASCII85 inline-image post-EOD surplus bytes after `~>` that contain delimiter-looking `EI` before the later real inline-image terminator.

## Dependency Closure

No new support component is needed. This reuses the native PHP content-stream tokenizer, inline-image dictionary parser, ASCII85 decoder, decoded sample-floor calculation, image preview fail-closed boundary, and WordPress smoke path. Full upstream/model parity remains intentionally out of scope for this no-GPU markerPDF lane: no live OCR, Surya/Texify/Torch/model workers, PDFium rendering, or external PDF tools were run.
