# markerPDF Inline Image Flate Predictor Short-Row Boundary

Micro-slice: `markerpdf-inline-image-decode-boundary-current-base-20260605T144434Z`
Base accepted HEAD: `51459e38f0cb013b3051260a5ce3e3395d649067`

## Source Truth

Upstream `sddai/markerPDF` routes searchable PDF text through parser-backed page text before image, OCR, and model stages. Under the current no-GPU lane scope, the PHP port owns the native inline-image `BI`/`ID`/`EI` tokenizer boundary before WordPress paragraph rendering.

PDF inline-image payloads can be Flate streams with PNG predictor `/DecodeParms`. If the Flate member is complete but the predictor row is short, native RGB preview must fail closed, while the completed Flate member still owns post-stream raster surplus. Delimiter-looking `EI` bytes in that surplus must not reopen visible text parsing or swallow the later paragraph.

## Behavior

`PdfTextExtractor` now separates Flate member ownership from predictor preview validity. When the filtered inline-image candidate has a completed Flate member, post-stream surplus contains a fake `EI`, and predictor expansion fails, the tokenizer keeps the surplus image-owned until the later real inline-image terminator. The image preview path remains fail-closed because `PdfImageRenderer` still rejects the short predictor row before RGB output metadata.

The focused fixture uses:

```text
BI /W 3 /H 1 /CS /G /BPC 8 /F /Fl
   /DP << /Predictor 12 /Columns 3 /Colors 1 /BitsPerComponent 8 >> ID
   <zlib bytes for a short PNG predictor row> ZZ EI ... rawtail
EI
```

Before the fix, the same probe returned only `Before Predictor Short Row Inline`; the following `After Predictor Short Row Inline` paragraph was swallowed. After the fix, both paragraphs are preserved and `Predictor Short Row Inline Noise`, `ZZ EI`, and `rawtail` remain excluded from visible text.

## Evidence

Focused test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 409 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-inline-image-decode-boundary-currentbase.php
```

The smoke emits `predictor_short_row_payload_excluded_until_real_ei=true`, `predictor_short_row_preview_rejected=true`, `predictor_short_row_surplus_preview_rejected=true`, `visible_text_imported=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness status: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted Flate decoded sample-floor acceptance, Flate post-stream surplus with valid predictor output, invalid DecodeParms parameter fail-closed classification, ASCII85/ASCIIHex/LZW/RunLength EOD surplus handling, stacked native prefix filters, inline ImageMask/Indexed preview rows, DCT/JPX/JBIG2/CCITT review-only filters, Image XObject metadata, fonts/CMaps, xref repair, annotations, forms, metadata, OCR/model execution, or supplied-boundary table/equation handoffs.

The bounded behavior is specifically completed Flate inline-image stream ownership when PNG predictor DecodeParms fail on a short decoded row.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF content tokenizer, Flate member end detection, stream filter decoding, DecodeParms predictor validator, inline-image preview rejection path, and existing WordPress smoke renderer. Live OCR, Surya/Texify/Torch model execution, PDFium/PIL rasterization, external PDF tools, and exact upstream model benchmark parity remain intentionally outside the current no-GPU markerPDF scope.
