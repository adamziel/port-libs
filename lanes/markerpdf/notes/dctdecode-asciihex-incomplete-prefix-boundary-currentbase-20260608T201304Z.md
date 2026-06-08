# DCTDecode ASCIIHex Incomplete Prefix Boundary Current Base

Slice: `markerpdf-dctdecode-filter-boundary-current-base-20260608T201304Z`

Base accepted HEAD: `94d7cef270e305ef6fc0f67053ec55d96bb371c3`

## Source Truth

Upstream markerPDF keeps image payload bytes out of searchable text extraction and hands image decoding off separately from text block extraction. This no-GPU port slice preserves that contract for native PHP stream parsing: an `/ASCIIHexDecode /DCTDecode` image whose ASCIIHex prefix decodes to an incomplete JPEG must remain review-only image data, even when the ASCIIHex EOD marker is followed by a fake `endstream/endobj` and text-looking payload bytes.

## Red-First Evidence

Before this patch, a stale `/Length` ending at the early ASCIIHex `>` marker let `PdfTextExtractor` accept the fake payload `endstream` and import a later fake text object:

`Before incomplete ASCIIHex DCT`
`Incomplete ASCIIHex DCT leak`
`After incomplete ASCIIHex DCT`

The first repair attempt excluded the fake text but over-scanned through the next real text object. The final implementation keeps scanning past decoded-but-incomplete DCT prefixes, rejects fake embedded stream terminators with the current-object close check, and stops at the current image object's recovered outer `endstream`.

## Behavior Added

- `PdfTextExtractor` now treats decoded-but-incomplete DCT prefix candidates as evidence to continue stream-boundary recovery rather than as a safe fallback terminator.
- The incomplete-prefix recovery returns the first current-object-closing `endstream` after the boundary guard, preserving complete JPEG prefix-filter fixtures where the first-filter terminator is also the legitimate outer stream terminator.
- Direct object scanning no longer reruns DCT recovery past a stream that already recovered a DCT terminator, preventing the image object from swallowing the following real text object.
- `PdfImageRenderer` now keeps scanning direct image preview strings for incomplete decoded DCT prefixes so review metadata records the full encoded stream instead of truncating at the fake early terminator.

## Focused Evidence

Commands run:

- `php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeAsciiHexIncompletePrefixBoundaryCurrentBaseTest.php`
  - `1 test files, 56 assertions, 0 failures`
- `php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg '/PdfDctDecode.*CurrentBaseTest\.php$')`
  - `35 test files, 2111 assertions, 0 failures`
- `php lanes/markerpdf/examples/wordpress-pdf-dctdecode-asciihex-incomplete-prefix-boundary-currentbase.php`
  - exits 0 and emits two WordPress paragraph blocks with `dctdecode_invalid_reason=missing_jpeg_eoi`, `dctdecode_image_payload_excluded_from_text=true`, and all execution flags false.
- `php -l lanes/markerpdf/src/PdfTextExtractor.php && php -l lanes/markerpdf/src/PdfImageRenderer.php && php -l lanes/markerpdf/tests/PdfDctDecodeAsciiHexIncompletePrefixBoundaryCurrentBaseTest.php && php -l lanes/markerpdf/examples/wordpress-pdf-dctdecode-asciihex-incomplete-prefix-boundary-currentbase.php`
  - all changed PHP files report no syntax errors.
- `php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'`
  - `lane-status json ok`
- `git diff --check -- lanes/markerpdf`
  - exits 0 with no whitespace errors.

Expected status delta:

- `phpPass`: `3463 -> 3464`
- `wordpressScenarios`: `2809 -> 2810`

## Non-Overlap

This does not repeat the accepted complete DCT marker boundary, padded/BOM DCT segment color, ASCII85/LZW/RunLength complete-prefix, post-EOI surplus, raw DCT, CCITTFax, xref Prev, metadata, annotation, form, OCR/model, or GPU slices. It is limited to native stream boundary recovery when a supported first filter decodes to an incomplete JPEG before DCTDecode.

## Dependency Closure

No new support component is needed. The patch reuses the existing native PHP stream filter decoders, JPEG marker boundary metadata, and image review-only handoff. GPU/OCR/model execution, external PDF tools, pypdfium, and PIL remain intentionally out of scope for markerPDF in this worker lane.

## Next Task

Continue with non-overlapping native PDF parser work: font/CMap fidelity, remaining stream-filter boundary cases, xref repair, metadata, outlines, annotations, forms, page geometry, and image/filter metadata.
