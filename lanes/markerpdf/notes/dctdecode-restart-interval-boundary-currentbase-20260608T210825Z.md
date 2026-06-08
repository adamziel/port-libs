# DCTDecode Restart-Interval Boundary Current Base

Slice: `markerpdf-dctdecode-filter-boundary-current-base-20260608T210825Z`
Base accepted HEAD: `cd5159929d4d9ea027f78c9318d6d3a12cd98c82`

## Source Truth

Native PDF image review needs to treat `/DCTDecode` streams as JPEG marker-framed payloads, not as generic byte strings. JPEG DRI (`FF DD`) segments carry a restart interval in their length-coded payload, while APP segment payload bytes are opaque data even when they contain marker-looking `FF D0` or `FF 00` sequences.

This patch keeps DCT images review-only, adds marker-structured metadata for DRI restart intervals, and prevents length-coded APP payload bytes from becoming false restart-marker or byte-stuffed metadata in both `PdfTextExtractor` and `PdfImageRenderer`.

## Non-Overlap

This does not repeat scalar `/DCTDecode` filter ownership, DecodeParms array/tail handling, inline-image DCT tails, SOS scan-data payload clipping, post-EOI surplus clipping, marker-fill SOI recovery, or Form XObject inline-image fallback behavior. It owns the narrower DRI restart-interval metadata boundary and the marker-payload false-positive guard for native image review.

## Evidence

Red-first focused run before source implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeRestartIntervalBoundaryCurrentBaseTest.php
=> 1 test file / 13 assertions / 1 failure
```

Focused verification after implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeRestartIntervalBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfDctDecodeSosMarkerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfDctDecodeMarkerFillBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfDctDecodeRendererStreamBoundaryCurrentBaseTest.php
=> 5 test files / 886 assertions / 0 failures

php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecode*CurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDctDecode*CurrentBaseTest.php
=> 39 test files / 2224 assertions / 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-dctdecode-restart-interval-boundary-currentbase.php
=> exits 0 with dri_marker_seen=true, jpeg_restart_interval=4, app_payload_marker_bytes_rejected=true
```

## Dependency Closure

No new support component is needed. The slice reuses native PHP byte scanning and existing DCT/JPEG boundary helpers. No OCR, GPU/model execution, pypdfium/PIL, external PDF tools, or online services were used.

## Next Task

Continue with non-overlapping native PDF parser work: font/CMap width behavior, xref repair, metadata, annotations/forms, page geometry, image/filter metadata beyond DCT DRI boundaries, or supplied-boundary table/equation handoffs.
