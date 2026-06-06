# DCTDecode Malformed Filter Boundary Current Base

Slice: `markerpdf-dctdecode-filter-boundary-current-base-20260606T192903Z`
Base accepted HEAD: `25ea07f71d9d374a0547131630b25b485b558f60`

## Behavior

Malformed nested image `/Filter` operands such as `[[/DCTDecode]]` now keep the
current image XObject review sentinel contract and explicitly mark complete raw
JPEG owner streams as DCT preview boundaries. The extractor reports
`filters_resolved=false`, `filters=["MalformedFilterOperand"]`,
`filter_operand_policy="reject_malformed_filter_operands"`,
`malformed_filter_operand_count=1`, `raw_dct_preview_boundary=true`, and no
native raster decode claim.

This keeps the searchable-PDF text path aligned with the renderer's
fail-closed DCT boundary metadata while preserving the full owner stream for
review-only image metadata. Embedded fake objects after stale `endstream`
decoys remain excluded from visible text and WordPress paragraphs.

## Evidence

Red-first focused run before the patch:

`php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php`

Result: `1 test files, 693 assertions, 1 failures`.

Failure: the DCT image XObject test still expected an empty public filter list
for malformed operands, while current accepted image-filter boundary behavior
surfaces the `MalformedFilterOperand` review sentinel.

After the patch:

`php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php`

Result: `1 test files, 706 assertions, 0 failures`.

Adjacent DCT current-base family:

`php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecode*CurrentBaseTest.php`

Result: `14 test files, 1175 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-dctdecode-malformed-filter-boundary-currentbase.php`

Result: emitted `malformed_filter_operand_rejected=true`,
`xobject_raw_dct_preview_boundary=true`, `renderer_raw_dct_preview_boundary=true`,
`stale_length_fake_endstream_rejected=true`,
`embedded_fake_object_rejected=true`, `executes_python_or_models=false`, and
`executes_external_pdf_tools=false`.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP PDF
stream/filter parser, existing DCT JPEG boundary scanner, image XObject review
metadata, and renderer preview boundary behavior. No GPU/model execution,
OCR, pypdfium, PIL, or external PDF tools were used.

## Non-Overlap

This slice does not repeat accepted table geometry, CCITTFax operand-boundary,
duplicate filter declaration, DCT DecodeParms, DCT post-EOI clipping, or
renderer-only malformed DCT work. It is limited to extractor-side malformed
DCT filter operand boundary metadata and the matching WordPress smoke.
