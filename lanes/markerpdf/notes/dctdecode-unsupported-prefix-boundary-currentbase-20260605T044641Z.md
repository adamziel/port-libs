# markerPDF DCTDecode unsupported-prefix boundary

Micro-slice: `markerpdf-dctdecode-filter-boundary-current-base-20260605T044641Z`

Base accepted HEAD: `61d89320c957b78cadb6887799e6302745c11378`

## Source Truth

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text through `marker/pdf/extract_text.py::get_text_blocks()` and `naive_get_text()`, while raster image rendering is handled separately by `marker/pdf/images.py::render_image()`.
- Under the current no-GPU/no-external-renderer markerPDF scope, DCTDecode/JPEG bytes remain review-only image payloads. The native PHP parser still owns the object and stream boundary that prevents JPEG payload bytes or fake PDF stream/object tokens from becoming WordPress paragraphs.
- The accepted Identity `/Crypt` filter slice only permits `/Crypt` as a transparent stream stage when explicit Identity DecodeParms are present. A DCT image with an unsupported prefix filter such as plain `/Crypt` must therefore remain non-decodable, but visible JPEG SOI/EOI framing can still be used as an owner boundary to keep the image stream fail-closed.

## Behavior

`PdfTextExtractor::dctPrefixFilterEndstreamTerminatorOffset()` now falls back to raw JPEG SOI/EOI owner detection only when the prefix filter before `/DCTDecode` is not one of the native decoders. This keeps streams such as:

```text
/Filter [/Crypt /DCTDecode]
```

closed through the visible JPEG EOI when a stale `/Length` points at a fake `endstream` inside the payload. The bytes are still not decoded as image samples, `/Crypt` remains unsupported without explicit Identity DecodeParms, and the Image XObject review row reports `decoded_with_current_filters=false`.

## Red-First Evidence

A one-off probe on the accepted base before the source change built an image XObject with `/Filter [/Crypt /DCTDecode]`, a stale `/Length`, and a fake stream object before the JPEG EOI. It leaked the fake payload text:

```text
array (
  0 => 'Before Crypt DCT stream',
  1 => 'Crypt DCT unsupported leak',
  2 => 'After Crypt DCT stream',
)
```

After the patch the focused test imports only `Before Crypt DCT filter` and `After Crypt DCT filter`, while the review row keeps `filters=["Crypt","DCTDecode"]`, `preview_only_filters=["DCTDecode"]`, `native_raster_decode=false`, and `decoded_with_current_filters=false`.

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 171 assertions, 0 failures
```

Additional focused verification and syntax/diff checks were run before handoff; see final worker report for exact command output.

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfDctDecodeSegmentBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php lanes/markerpdf/tests/PdfImageRendererTest.php
Focused test run: 6 selected test files (root lock skipped)
6 test files, 1486 assertions, 0 failures
```

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-dctdecode-unsupported-prefix-boundary-currentbase.php
```

All changed PHP files reported no syntax errors.

```text
php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'
lane-status json ok

git diff --check -- lanes/markerpdf
```

`git diff --check -- lanes/markerpdf` produced no output.

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-dctdecode-unsupported-prefix-boundary-currentbase.php
```

The smoke emits `unsupported_prefix_filter_fail_closed=true`, `raw_jpeg_owner_boundary_used_for_review_only_stream=true`, `stale_length_fake_endstream_rejected=true`, `embedded_fake_object_rejected=true`, `dctdecode_image_payload_excluded_from_text=true`, `xobject_preview_only_filters=["DCTDecode"]`, `xobject_native_raster_decode=false`, `xobject_decoded_with_current_filters=false`, and all Python/model/PDFium/PIL/external-tool execution flags false.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted direct DCT SOI/EOI stream recovery, DCT APP-segment false-EOI handling, Flate or null-slot prefix DCT recovery, ASCIIHex early-EOD DCT recovery, indirect `/Filter /DCTDecode` owner boundaries, explicit Identity `/Crypt` content-stream decoding, unsupported `/Crypt` content-stream fail-closed behavior, inline-image unsupported filter tokenizer boundaries, DCT ColorTransform/Decode review, CCITT/JPX/JBIG2 image-filter boundaries, or live OCR/model/raster rendering.

The bounded behavior is specifically a preview-only DCT image stream whose first filter is unsupported or non-native while raw JPEG framing remains visible enough to close stream ownership before fallback WordPress text extraction.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP PDF object scanner, stream dictionary reader, filter-name resolver, DCT/JPEG boundary checker, Image XObject review path, and WordPress smoke renderer. Full decryption, crypt-filter security handlers, object-specific encryption keys, and JPEG raster parity remain gated on separate security/raster components; OCR/model execution remains intentionally out of scope and was not run.
