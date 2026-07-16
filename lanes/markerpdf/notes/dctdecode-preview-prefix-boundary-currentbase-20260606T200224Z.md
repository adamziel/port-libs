# DCTDecode Preview-Prefix Boundary Current Base

## Slice

- Lane: `markerpdf`
- Micro-slice: `markerpdf-dctdecode-filter-boundary-current-base-20260606T200224Z`
- Accepted base: `a213d12bcad4e5ead54f882edb566fd2d7e1093c`
- Upstream source truth: pinned `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` treats images as non-text conversion inputs; this no-GPU PHP port keeps image raster/model work review-only while exposing native parser/filter metadata for WordPress import decisions.

## Behavior

PDF stream filters are ordered. If a preview-only filter such as `/JPXDecode` appears before terminal `/DCTDecode`, native PHP cannot reach the JPEG/DCT stage by applying only supported prefix filters. The renderer and text-extractor Image XObject review rows now preserve that distinction with:

- `preview_only_filters_before_dctdecode`
- `pre_dctdecode_preview_filters_block_native_prefix_decode`

This is separate from existing terminal DCTDecode review metadata and from post-DCT filter blocking. WordPress media review can now tell that the native prefix chain was blocked before DCTDecode rather than treating the DCTDecode terminal itself as the only review reason.

## Evidence

Red-first focused run on the accepted base failed because the DCT boundary omitted the pre-prefix flag:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodePreviewPrefixBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL marks preview-only filters before DCTDecode as unreachable native prefix stages
1 test files, 4 assertions, 1 failures
```

Focused run after the implementation passed:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodePreviewPrefixBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS marks preview-only filters before DCTDecode as unreachable native prefix stages
1 test files, 23 assertions, 0 failures
```

WordPress smoke added:

```text
php lanes/markerpdf/examples/wordpress-pdf-dctdecode-preview-prefix-boundary-currentbase.php
```

The smoke reports `pre_dctdecode_preview_filters_block_native_prefix_decode=true`, `xobject_boundary_matches_renderer=true`, `dctdecode_image_payload_excluded_from_text=true`, and no Python/model/OCR/external PDF execution.

## Non-Overlap

This does not repeat accepted DCTDecode direct terminal review, DecodeParms, null filter slots, malformed stream, escaped filter-name, post-EOI/comment/SOS, renderer stream boundary, native prefix filters, post-DCT filter, duplicate filter, CCITT, JPX/JBIG2 generic review, OCR/model, or raster execution clusters. It only adds the missing pre-DCT preview-only prefix boundary flag for native filter-chain reachability.

## Dependency Closure

No new support component is needed. The patch reuses the existing native PHP parser, image filter metadata, renderer review plan, and text-extractor XObject review paths. Full raster decoding and model/OCR execution remain intentionally out of scope under the no-GPU markerPDF directive.
