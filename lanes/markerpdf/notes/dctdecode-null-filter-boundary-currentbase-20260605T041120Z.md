# markerPDF DCTDecode Null-Filter Boundary

## Source Truth

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` separates searchable PDF text extraction (`marker/pdf/extract_text.py`) from raster image rendering (`marker/pdf/images.py`).
- PDF stream filter arrays are ordered decoder stacks. `null` filter entries are identity placeholders, and DecodeParms entries aligned to those placeholders must not suppress a valid later image boundary.

## Behavior

This native no-GPU slice fixes Flate-wrapped DCTDecode image stream boundary recovery when the filter array contains `null` identity slots before the DCT preview-only step:

```pdf
/Filter [ null /FlateDecode null /DCTDecode ]
/DecodeParms [ 99 0 R null 100 0 R << /ColorTransform 1 >> ]
```

The stale unresolved `DecodeParms` references are aligned only to `null` filter slots. `PdfTextExtractor::dctPrefixFilterEndstreamTerminatorOffset()` now asks the prefix decoder to align DecodeParms through `streamDecodeParmsForFilters()`, so null-slot operands are ignored while real prefix filters still validate their own parameters. This lets the Flate prefix decode prove the complete JPEG SOI/EOI boundary before the preview-only DCTDecode filter and prevents fake `endstream` objects embedded in JPEG bytes from leaking into WordPress paragraphs.

## Red-First Evidence

Before the change, the focused probe returned:

```php
[
    'Before Null DCT Prefix',
    'Null DCT Prefix Leak',
    'After Null DCT Prefix',
]
```

After the change:

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php
```

Result:

```text
1 test files, 149 assertions, 0 failures
```

The new PASS case is:

```text
PASS ignores null-filter DecodeParms slots before Flate-wrapped DCTDecode JPEG boundaries
```

WordPress smoke:

```sh
php lanes/markerpdf/examples/wordpress-pdf-dctdecode-null-filter-boundary-currentbase.php
```

The smoke emits `decodeparms_null_slots_ignored_before_dct_boundary=true`, `dctdecode_image_payload_excluded_from_text=true`, `raw_length_after_boundary_recovery=162`, and paragraphs `Before Null DCT Import` / `After Null DCT Import`, with all Python/model, pypdfium/PIL, and external PDF tool execution flags false.

## Non-Overlap

This does not repeat accepted direct DCTDecode raw JPEG SOI/EOI stream recovery, DCT alias inline image boundaries, DCT ColorTransform review, ASCII85/ASCIIHex/RunLength prefix EOD recovery, Flate-prefix DCT recovery without null slots, all-null stream filter arrays, generic content-stream null DecodeParms alignment, inline image null filter alignment, CCITT/JPX/JBIG2 image-filter exclusion, or broad stream-filter stack recovery. The bounded behavior is specifically null identity filter slots with stale DecodeParms operands before a Flate-wrapped DCTDecode image boundary.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, stream dictionary reader, stream filter resolver, DecodeParms parser, Flate decoder, DCT preview-only boundary checker, image XObject review path, and WordPress smoke renderer. Full raster parity remains gated on pypdfium2/PDFium or a future native JPEG raster backend; OCR/model execution remains intentionally out of scope under the current markerPDF no-GPU directive.
