# DCTDecode Marker Fill Boundary Current Base

Slice: `markerpdf-dctdecode-filter-boundary-current-base-20260606T212007Z`
Base: `9e6fd7a643da41e2535da077c68b60f0a50014b8`

## Source Truth

- In-scope upstream behavior is native searchable-PDF/image stream handling for `DCTDecode` XObjects before WordPress text handoff.
- JPEG streams may contain fill `0xff` bytes before marker codes. This slice treats `ff ff d8` as a fill-prefixed SOI marker for boundary review and terminator recovery.
- No GPU/model/OCR path is involved.

## Red-First Evidence

Command:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeMarkerFillBoundaryCurrentBaseTest.php
```

Before the fix:

```text
1 test files, 1 assertions, 1 failures
```

The failure showed the fake APP-segment payload text `Marker-fill DCT payload leak` being emitted between the expected before/after page text because the fill-prefixed JPEG SOI was classified as missing.

## Implementation

- `PdfTextExtractor` and `PdfImageRenderer` now share the same local marker-start rule for DCT review:
  - skip PDF stream whitespace;
  - accept one or more `0xff` fill bytes followed by `0xd8` as JPEG SOI;
  - continue using marker-length parsing to find the real EOI before accepting `endstream`;
  - annotate `jpeg_marker_fill_byte_count` in DCT stream boundary metadata.
- The new test fixture uses a stale `/Length` ending at a fake `endstream` inside a length-coded JPEG APP segment and verifies the recovered raw stream length reaches the true final EOI for both extractor and renderer review metadata.

## Verification

Focused red/green:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeMarkerFillBoundaryCurrentBaseTest.php
```

After fix:

```text
1 test files, 63 assertions, 0 failures
```

DCT boundary family:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecode*Boundary*Test.php lanes/markerpdf/tests/PdfImageRendererTest.php
```

Result:

```text
16 test files, 1751 assertions, 0 failures
```

WordPress smoke:

```bash
php lanes/markerpdf/examples/wordpress-pdf-dctdecode-marker-fill-boundary-currentbase.php
```

Result: exits `0` and reports `jpeg_marker_fill_soi_accepted`, `stale_length_fake_endstream_rejected`, `xobject_raw_length_recovered`, `renderer_raw_length_recovered`, and `dctdecode_image_payload_excluded_from_text` as `true`.

## Non-Overlap

This does not repeat existing DCT coverage for APP-segment fake EOI, SOS entropy fake `endstream`, DCT DecodeParms, DCT aliases, prefix filters, duplicate filters, malformed filter operands, or renderer direct DCT review. The new boundary is specifically JPEG marker fill before SOI combined with stale `/Length` and fake in-payload stream/object syntax.

## Dependency Closure

No new support component is needed. The patch reuses the existing native PHP stream parser, DCT review boundary scanner, and renderer metadata path. It does not shell out, execute Python, call OCR/model code, use pypdfium/PIL, or depend on external PDF tooling.

## Next Task

Continue with non-overlapping native markerPDF parser behavior around stream filters, fonts/CMaps, xref repair, metadata, annotations/forms, page geometry, and image/filter review metadata.
