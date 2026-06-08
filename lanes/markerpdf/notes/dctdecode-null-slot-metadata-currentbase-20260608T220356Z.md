# DCTDecode Null-Slot Metadata Boundary

Slice: `markerpdf-dctdecode-filter-boundary-current-base-20260608T220356Z`

Base: `744d742adbbbf391182231a7a5b4f2d0d558edc2`

## Source Truth

- Upstream boundary: `marker.pdf.extract_text.get_text_blocks` treats image payloads as non-text, and `marker.pdf.images.render_image` delegates raster execution separately from searchable text extraction.
- Native no-GPU port boundary: keep DCT/JPEG image bytes review-only while preserving enough filter metadata for WordPress import and image preview review.
- Relevant parser behavior: PDF `/Filter` arrays may include `null` no-op entries; `/DecodeParms` arrays still align to raw filter-array slots. The native review now reports both the non-null DCT filter index and the raw slot metadata when null operands precede DCTDecode.

## Behavior

- `PdfTextExtractor` now passes raw image filter slots into DCTDecode boundary review for page Image XObjects, nested mask/alternate image reviews, and alternate-stream review paths.
- `PdfImageRenderer` now passes raw image filter slots into image color-space/soft-mask planning before DCTDecode boundary review.
- DCT boundary metadata conditionally adds:
  - `raw_filter_slot_index`
  - `filter_stack_slot_count`
  - `null_filter_slot_count_before_dctdecode`
- The new fields are emitted only when null filter slots appear before the DCTDecode slot, preserving existing exact boundary arrays for ordinary non-null filter stacks.

## Verification

- Red-first: `php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeNullSlotMetadataBoundaryCurrentBaseTest.php`
  - Failed before the patch because `dctdecode_filter_boundary` omitted `raw_filter_slot_index`, `filter_stack_slot_count`, and `null_filter_slot_count_before_dctdecode`.
- Focused pass: `php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeNullSlotMetadataBoundaryCurrentBaseTest.php`
  - `1 test files, 40 assertions, 0 failures`
- Adjacent DCT boundary family: `php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeNullSlotMetadataBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfDctDecodeNativePrefixAliasBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserIntegratedBoundaryRegressionCurrentBaseTest.php`
  - `4 test files, 811 assertions, 0 failures`
- PHP lint:
  - `php -l lanes/markerpdf/src/PdfImageRenderer.php`
  - `php -l lanes/markerpdf/src/PdfTextExtractor.php`
  - `php -l lanes/markerpdf/tests/PdfDctDecodeNullSlotMetadataBoundaryCurrentBaseTest.php`
  - `php -l lanes/markerpdf/examples/wordpress-pdf-dctdecode-null-filter-boundary-currentbase.php`
- JSON validation:
  - `lanes/markerpdf/lane-status.json` parses with `JSON_THROW_ON_ERROR`
  - `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json` parses with `JSON_THROW_ON_ERROR`
- WordPress smoke: `php lanes/markerpdf/examples/wordpress-pdf-dctdecode-null-filter-boundary-currentbase.php`
  - exits 0 and emits `dctdecode_raw_filter_slot_index=3`, `dctdecode_filter_stack_slot_count=4`, `dctdecode_null_filter_slot_count_before_boundary=2`, `dctdecode_image_payload_excluded_from_text=true`, and all execution flags false.
- Whitespace: `git diff --check -- lanes/markerpdf`
  - exits 0.

## Non-Overlap

This does not rework existing DCTDecode JPEG marker clipping, DCTDecode null DecodeParms alignment, inline-image DCT boundaries, or Flate/LZW native-prefix decoding. It only adds raw filter-slot review metadata at the DCTDecode boundary when null `/Filter` entries precede the DCT slot.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP PDF dictionary parser, stream filter stack parser, Flate prefix decoder, DCT/JPEG boundary review, and image review metadata paths. No Python, OCR/model, multiprocessing, PDFium/PIL, external PDF tools, or live-service provider tests were run.

## Next

Continue non-overlapping native markerPDF stream/filter work around remaining image/filter metadata, CMaps/fonts, xref repair, annotations/forms, page geometry, and supplied-boundary table/equation handoffs under the current no-GPU scope.
