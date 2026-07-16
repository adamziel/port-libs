# markerPDF DCTDecode Indirect Filter Boundary

Micro-slice: `markerpdf-dctdecode-filter-boundary-current-base-20260605T033801Z`

Base accepted HEAD: `b3a326b364ff3b996dfb76ae85c81120026fd222`

## Source Truth

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text through `marker/pdf/extract_text.py` before image rendering, OCR, layout, or model stages. Raster image payloads are rendered separately through `marker/pdf/images.py::render_image()`.

Under the current native no-GPU markerPDF scope, DCTDecode/JPEG bytes remain review-only image payloads. The PHP parser still owns the stream-owner boundary that prevents JPEG bytes, fake `endstream/endobj`, and fake object headers from becoming WordPress paragraphs.

## Behavior

The previous DCT owner recovery handled direct `/Filter /DCTDecode` and prefix-filter stacks whose filter operands were already resolvable during the preliminary direct-object scan. A direct image stream with `/Filter 6 0 R` where `6 0 obj /DCTDecode` appears after the owner stream could not resolve the helper early enough. If a stale `/Length` pointed at a fake `endstream` inside the JPEG payload, the scanner split the image early and later fallback extraction could promote fake object text.

`PdfTextExtractor::dctStreamEndstreamTerminatorOffset()` now uses raw JPEG SOI/EOI framing for image-shaped streams when the stream filter operand is unresolved or all-null during the preliminary owner scan. This keeps the owner range closed until the real DCT/JPEG EOI-adjacent `endstream` token, while final image review still resolves the indirect `/DCTDecode` helper from the completed object map.

## Red-First Evidence

Before the patch, the focused probe returned:

```text
["Before indirect DCT filter","Indirect DCT filter leak","After indirect DCT filter"]
```

The fake middle paragraph came from a JPEG payload that contained:

```text
endstream
endobj
9 0 obj
...
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 122 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfDctDecodeSegmentBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserIndirectDecodeParmsFilterOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserInlineStreamLengthFilterRepairCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php lanes/markerpdf/tests/PdfImageRendererTest.php
Focused test run: 8 selected test files (root lock skipped)
8 test files, 1435 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-dctdecode-indirect-filter-boundary-currentbase.php
```

The smoke emits `filter_helper_after_stream=true`, `raw_jpeg_boundary_used_for_preliminary_owner_scan=true`, `stale_length_fake_endstream_rejected=true`, `dctdecode_image_payload_excluded_from_text=true`, `xobject_raw_length_recovered=true`, paragraphs `["Before Indirect DCT Import","After Indirect DCT Import"]`, and all Python/model, pypdfium/PIL, and external PDF tool execution flags false.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted DCT preview-only filter classification, DCT DecodeParms ColorTransform review, direct DCT SOI/EOI recovery, NUL-padded direct or prefix-filter DCT recovery, ASCIIHex early-EOD DCT recovery, Flate-prefix DCT recovery, inline DCT tokenizer framing, DCT APP-segment false-EOI handling, CCITT/JPX/JBIG2 image-filter exclusion, or generic indirect Filter/DecodeParms owner recovery for native-decodable streams.

The bounded new behavior is specifically image-shaped DCT/JPEG stream ownership when an indirect `/Filter` helper appears after the owner stream and the preliminary scan cannot yet resolve it.

## Dependency Closure

No new support component is needed. This reuses the native PDF stream owner scanner, image-stream dictionary detector, JPEG SOI/EOI boundary checker, final indirect filter resolver, image XObject review metadata, and WordPress smoke path. Full JPEG raster parity remains gated on pypdfium2/PDFium/PIL or a future native raster backend; OCR/model execution remains intentionally out of scope and was not run.
