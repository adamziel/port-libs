# markerPDF Indexed Separation Soft-Mask Preview

Slice: `image-indexed-separation-softmask-currentbase-20260602T180302Z`

Base accepted HEAD: `25465d4bad4c4ed7e39379fb65c3e5365a4df98d`

## Source Truth

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes visible text through `marker/pdf/extract_text.py` / `pdftext.extraction.dictionary_output`, while page/image rendering goes through `marker/pdf/images.py` and `pypdfium` RGB rendering with annotations disabled. Image XObject color-space and alpha state therefore belongs to review/preview metadata in the native PHP port, not visible WordPress paragraph text.

- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/images.py
- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py

PDF image color-space source truth allows `/Indexed` to use a base color space such as `/Separation` or `/DeviceN`. The lookup bytes are base color-space samples; for spot colors that means tint values that must stay attached to the named colorants and tint-transform review metadata before any future RGB compositor applies the alternate color space.

## Implementation

`PdfImageRenderer` now preserves Indexed base alternate color-space metadata:

- `indexed_color_space.base_uses_alternate_color_space`
- `indexed_color_space.base_alternate_color_space`

It also adds `indexedAlternateColorantSamplePreview()`, which applies the image `/Decode` to the palette index, expands the Indexed lookup bytes into base Separation/DeviceN tint values, labels them by colorant name, and applies the matching soft-mask alpha sample. Tint-transform execution remains review-only.

The WordPress smoke `examples/wordpress-pdf-indexed-separation-softmask-currentbase.php` emits a review-only image block with `source_color_space=Indexed`, `base_color_space=Separation`, `palette_index=2`, `Spot Orange=0.752941`, and `soft_mask_alpha=0.74902` without Python, models, pypdfium/PIL, or external PDF tools.

## Non-Overlap

This does not repeat accepted Indexed `/DeviceRGB` default Decode and soft-mask alpha clipping, Indexed ICCBased JBIG2 preview boundaries, soft-mask transfer functions over Indexed transparency groups, direct Separation/DeviceN alternate image color-space review, decoded DeviceN ICCBased stream rows, color-key mask conflict handling, calibrated soft-mask review, DCTDecode CMYK/YCCK Decode handling, or inline Indexed/JBIG2/ImageMask payload exclusion.

The bounded new behavior is specifically an Indexed palette whose base color space is Separation/DeviceN, so spot-color palette entries are not flattened into anonymous base components before WordPress image review metadata.

## Verification

- `php tools/run-tests.php lanes/markerpdf/tests/PdfImageRendererTest.php` passed: `1 test files, 422 assertions, 0 failures`.
- `php lanes/markerpdf/examples/wordpress-pdf-indexed-separation-softmask-currentbase.php` passed and emitted Indexed Separation soft-mask review metadata with execution flags false.
- `php -l lanes/markerpdf/src/PdfImageRenderer.php` passed.
- `php -l lanes/markerpdf/tests/PdfImageRendererTest.php` passed.
- `php -l lanes/markerpdf/examples/wordpress-pdf-indexed-separation-softmask-currentbase.php` passed.
- `php -r 'foreach (["lanes/markerpdf/lane-status.json","lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"] as $f) { json_decode(file_get_contents($f), true); if (json_last_error()) { fwrite(STDERR, $f.": ".json_last_error_msg().PHP_EOL); exit(1); } echo $f." ok\n"; }'` passed.
- `git diff --check -- lanes/markerpdf` passed.
- Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses the native PDF dictionary parser, image `/Decode` planner, Indexed palette lookup handling, Separation/DeviceN alternate colorant metadata, and soft-mask alpha sample preview. Full upstream runner parity remains blocked by the Python/model stack: pdftext, pypdfium2/PIL rendering, Surya/Torch models, tabled-pdf, Texify, benchmark tooling, and live multiprocessing/server/app execution.
