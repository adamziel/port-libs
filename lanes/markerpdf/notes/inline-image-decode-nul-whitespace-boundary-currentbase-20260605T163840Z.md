# markerPDF inline image NUL whitespace decode boundary

Micro-slice: `markerpdf-inline-image-decode-boundary-current-base-20260605T163840Z`

Base accepted HEAD: `000bf54c5eccb0fdfbc28df281e79b5ddbd4a2f2`

## Source truth

- Upstream `sddai/markerPDF` remains pinned in the lane manifest at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- Upstream markerPDF routes searchable PDF text through parser/PDF backend boundaries before OCR/model fallbacks. Under the no-GPU markerPDF scope, the PHP lane owns safe native PDF content-stream tokenization and review-only inline image metadata.
- PDF lexical whitespace includes NUL (`0x00`) in addition to tab, line feed, form feed, carriage return, and space. Inline `BI ... ID ... EI` image data must stay image-owned, and supported inline filter decoders should accept NUL as whitespace around ASCII85/ASCIIHex EOD boundaries.

## Implementation

- `PdfImageRenderer` now treats NUL as PDF whitespace when validating native inline image filter EOD boundaries.
- `PdfImageRenderer` and `PdfTextExtractor` now strip/skip NUL as PDF whitespace in ASCIIHex and ASCII85 decoders.
- The focused test adds an inline image pair proving:
  - `z~>\0EI` closes an ASCII85 inline image and decodes four zero samples for preview metadata.
  - `41\0>` decodes as ASCIIHex sample `0x41`.
  - the raw inline image bytes stay out of WordPress paragraphs while the surrounding text survives.

## Red-first evidence

Before the fix, the renderer rejected valid NUL-whitespace filter boundaries:

```bash
php -r 'require "tools/bootstrap.php"; $class = "PortLibs\\MarkerPDF\\PdfImageRenderer"; $r = new $class(); foreach (["A85" => ["/W 4 /H 1 /CS /G /BPC 8 /F /A85 /D [0 1]", "z~>\0"], "AHx" => ["/W 1 /H 1 /CS /G /BPC 8 /F /AHx /D [0 1]", "41\0>"]] as $name => [$dict, $payload]) { try { $r->inlineImageColorSpaceMaskOutputPreviewRows($dict, $payload, [], 4); echo $name, " ok\n"; } catch (Throwable $e) { echo $name, " ", get_class($e), ": ", $e->getMessage(), "\n"; } }'
```

Output:

```text
A85 InvalidArgumentException: Inline image prefix filters must be complete before output preview.
AHx InvalidArgumentException: Inline image prefix filters must be complete before output preview.
```

## Verification

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php
```

Result:

```text
1 test files, 463 assertions, 0 failures
```

```bash
php lanes/markerpdf/examples/wordpress-pdf-inline-image-decode-boundary-currentbase.php
```

Result: smoke emitted `pdf_nul_whitespace_inline_filter_boundary=true`, `ascii85_nul_eod_preview_decoded=true`, `asciihex_nul_whitespace_preview_decoded=true`, `visible_text_imported=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Broader inline/image-renderer focused run:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageRendererTest.php
```

Result:

```text
3 test files, 1289 assertions, 0 failures
```

Diff whitespace check:

```bash
git diff --check -- lanes/markerpdf
```

Result: passed with no output.

## Non-overlap

This does not repeat accepted malformed `BI` recovery, unfiltered sample-length `EI` validation, ASCII85 explicit terminator review, ASCII85/ASCIIHex post-EOD surplus fail-closed preview behavior, Flate DecodeParms exact-boundary validation, filtered sample-floor recovery, inline DCT/JPX/JBIG2/CCITT tokenizer framing, inline ImageMask preview rows, inline Indexed palette/alpha previews, indirect inline preview operand resolution, inline filter-array null alignment, object-stream inline-image repair, or image XObject payload exclusion.

The bounded behavior is specifically PDF NUL whitespace around native inline ASCII85/ASCIIHex decode boundaries.

## Dependency closure

No new support component is needed. This reuses the native PHP content tokenizer, inline image dictionary parser, stream filter decoders, `PdfTextExtractor`, `PdfImageRenderer`, and the WordPress smoke path. Full live OCR/model/raster parity remains intentionally out of scope under the no-GPU markerPDF directive and remains gated on pdftext/PDFium/pypdfium2/PIL, Surya/Torch, tabled-pdf, Texify, runtime app/server workers, benchmark/model downloads, and external OCR/rendering helpers.
