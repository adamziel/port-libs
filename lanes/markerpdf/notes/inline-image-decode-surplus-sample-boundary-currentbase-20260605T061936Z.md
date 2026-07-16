# markerPDF Inline Image Decode Surplus Sample Boundary

Micro-slice: `markerpdf-inline-image-decode-boundary-current-base-20260605T061936Z`

Base accepted HEAD: `aea5ab0e4d69f6f820fc58bbf243135a27928112`

## Source Truth

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text through parser-backed page extraction before image/OCR/model stages. At that boundary, `BI ... ID ... EI` inline image bytes are raster payload, while WordPress review metadata should keep enough native image/filter context for a later raster backend without leaking payload text into paragraphs.

The already accepted native tokenizer accepts supported filtered inline images once decoded bytes reach the declared `/W` x `/H` x component x `/BPC` sample floor. This slice makes the matching renderer/review boundary explicit: preview rows are still limited to declared samples, but oversized decoded payloads expose review-only surplus-byte metadata.

## Implementation

`PdfImageRenderer` now returns `image_sample_boundary` metadata from inline ImageMask, Indexed, and general inline output preview rows. The metadata reports expected/available pixels, expected/available samples, expected/decoded byte counts, surplus samples, surplus bytes, completeness, and whether decoded bytes were truncated to the declared sample floor.

The existing WordPress inline-image decode smoke now reports the oversized Flate inline image byte floor (`1`), decoded byte count (`64`), surplus byte count (`63`), declared-sample truncation, and first preview sample while preserving clean Gutenberg paragraph text.

## Red First

Before the renderer change, the focused regression failed because `image_sample_boundary` was absent from inline output previews for the accepted oversized Flate fixture. The text tokenizer already excluded the image payload; the missing behavior was review metadata for the decoded surplus bytes.

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 207 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageMalformedFilterPreviewCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineMaskCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineJpxSmaskDecodeCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineJpxColorKeyOutputPreviewCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineFilterPaletteAlphaCurrentBaseTest.php lanes/markerpdf/tests/PdfImageColorSpaceMaskInlineOutputPreviewCurrentBaseTest.php lanes/markerpdf/tests/PdfParserObjectStreamInlineImageFilterCurrentBaseTest.php lanes/markerpdf/tests/PdfParserInlineStreamJpxCMapRepairCurrentBaseTest.php lanes/markerpdf/tests/PdfParserInlineStreamOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfImageRendererTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
Focused test run: 14 selected test files (root lock skipped)
14 test files, 1822 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-inline-image-decode-boundary-currentbase.php
```

The smoke exits 0 and emits `oversized_inline_preview_declared_byte_floor=1`, `oversized_inline_preview_decoded_byte_count=64`, `oversized_inline_preview_surplus_byte_count=63`, `oversized_inline_preview_truncated_to_declared_samples=true`, `visible_text_imported=true`, `excluded_inline_image_text=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted malformed `BI` recovery, unfiltered sample-length `EI` validation, ASCII85 explicit terminator review, Flate DecodeParms delimiter validation, filtered sample-floor tokenizer acceptance, terminal whitespace sample handling, named ColorSpace tokenizer fallback, LZW DecodeParms preview rows, RunLength EOD validation, inline DCT/JPX/JBIG2/CCITT preview-only tokenizer framing, inline ImageMask preview rows, inline Indexed palette/alpha previews, indirect inline preview operand resolution, inline filter-array null alignment, object-stream inline-image repair, or image XObject payload exclusion.

The bounded behavior is specifically decoded inline-image preview metadata for surplus bytes beyond the declared sample floor.

## Dependency Closure

No new support component is needed. This reuses the native inline-image dictionary expander, stream filter decoder, packed-sample reader, Decode mapper, `PdfImageRenderer`, `PdfTextExtractor`, focused lane tests, and existing WordPress smoke path. Full live OCR/model/raster parity remains intentionally out of scope under the no-GPU markerPDF directive and remains gated on pdftext/PDFium/pypdfium2/PIL, Surya/Torch, tabled-pdf, Texify, runtime app/server workers, benchmark/model downloads, and external OCR/rendering helpers.
