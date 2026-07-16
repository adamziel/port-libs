# markerPDF CCITT Fax Escaped Filter Boundary

Session: `port-dev-markerpdf-ccitt-fax-filter-20260605T082451Z`
Micro-slice: `markerpdf-ccitt-fax-filter-boundary-current-base-20260605T082451Z`
Base accepted HEAD: `d36e1e98e24a92bc490dde83eb92cd3f4021c21c`

## Source-Truth Boundary

Upstream `sddai/markerPDF` at the manifest-pinned commit routes searchable PDF
text through pdftext/PDFium text extraction and routes raster image bytes
through `marker/pdf/images.py::render_image()`. Under the current no-GPU native
PHP lane scope, CCITT Fax image payloads remain review-only, but renderer-side
filter metadata must still be accurate before WordPress media review.

PDF names can escape bytes with `#hh`, so `/Fil#74er /CCITT#46axDecode` is the
same top-level dictionary key/value pair as `/Filter /CCITTFaxDecode`. The
native renderer previously missed escaped `/Filter` and `/DecodeParms` keys in
image dictionaries and reported the image as unfiltered native raster metadata.

## Native Behavior Added

`PdfImageRenderer::extractPdfNameValue()` now isolates a leading balanced stream
or image dictionary and uses the token-aware dictionary reader to decode
top-level PDF name keys. This preserves existing stream-object bodies that have
trailing `stream ... endstream` data while allowing escaped top-level keys such
as:

- `/Fil#74er /CCITT#46axDecode`
- `/Decode#50arms << /K -1 /Columns 16 /Rows 2 ... >>`

Nested decoy dictionaries such as `/Nested << /Filter /FlateDecode ... >>` stay
ignored for the parent image review. The CCITT row remains preview-only,
`native_raster_decode=false`, and the fax payload remains excluded from visible
WordPress text.

## Evidence

Focused current-base probe before the source edit:

```text
php -r 'require "tools/bootstrap.php"; ... imageColorSpaceSoftMaskPlan("<< /Fil#74er /CCITT#46axDecode /Decode#50arms << ... >> >>")'
array (
  0 => array (),
  1 => array ('preview_only_filters' => array (), 'native_raster_decode' => true),
  2 => NULL,
  3 => array (),
)
```

Focused gate after the fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
...
PASS decodes escaped CCITT Fax filter and DecodeParms keys for renderer review metadata

1 test files, 283 assertions, 0 failures
```

Adjacent image/filter/text gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageRendererTest.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
Focused test run: 3 selected test files (root lock skipped)
3 test files, 1710 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-ccitt-fax-escaped-filter-currentbase.php
```

The smoke emits `renderer_filters=["CCITTFaxDecode"]`,
`renderer_preview_only_filters=["CCITTFaxDecode"]`,
`renderer_native_raster_decode=false`, `escaped_filter_key_decoded=true`,
`escaped_decodeparms_key_decoded=true`, `nested_decoy_filter_ignored=true`,
`payload_in_visible_text=false`, paragraphs `Before escaped CCITT filter` and
`After escaped CCITT filter`, and all Python/model/PDFium/PIL/external-tool
execution flags false.

Finish checks:

```text
php -l lanes/markerpdf/src/PdfImageRenderer.php
No syntax errors detected in lanes/markerpdf/src/PdfImageRenderer.php

php -l lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-pdf-ccitt-fax-escaped-filter-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-ccitt-fax-escaped-filter-currentbase.php

php -r 'foreach (["lanes/markerpdf/lane-status.json", "lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true); if (json_last_error() !== JSON_ERROR_NONE) { fwrite(STDERR, $file . ": " . json_last_error_msg() . PHP_EOL); exit(1); } echo $file . " OK\n"; }'
lanes/markerpdf/lane-status.json OK
lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json OK

git diff --check -- lanes/markerpdf
passed with no output
```

Root harness: not run - isolated micro-slice.

## Status Delta

- Focused PASS cases: `1615 -> 1616`.
- Focused assertions in `PdfCcittFaxFilterBoundaryCurrentBaseTest.php`: `266 -> 283`.
- WordPress scenarios: `1494 -> 1495`.
- Manifest current-base CCITT boundary behaviors: `1 -> 2`.

## Non-Overlap

This does not repeat accepted CCITT image-only stream exclusion, raw/valid
DecodeParms extraction, malformed or unresolved DecodeParms fail-closed review,
effective CCITT geometry metadata, inline CCITT review-only notes, inline
invalid DecodeParms review, inline null-filter DecodeParms alignment,
escaped-name/nested-decoy DecodeParms lookup, XObject compact DecodeParms
alignment, Flate-prefix CCITT boundary recovery, direct EOFB/RTC ownership,
nested CCITT soft-mask/mask/alternate image review, DCT/JPX/JBIG2 preview-only
image filters, or generic inline image payload exclusion. The new bounded
behavior is specifically renderer-side escaped `/Filter` and `/DecodeParms`
dictionary keys for CCITT Fax image preview metadata.

## Dependency Closure

No new support component is needed. This reuses the native PDF dictionary token
reader, image-renderer filter metadata planner, CCITT DecodeParms review builder,
Image XObject boundary review path, and WordPress smoke renderer. Full CCITT
raster parity remains gated on PDFium/PIL or a future native raster backend; no
Python, OCR, model, pypdfium, PIL, external PDF tool, live-service provider, or
GPU execution was run.
