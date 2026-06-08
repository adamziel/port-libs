# Named Destinations UTF-8 BOM Boundary Current Base

Micro-slice: `markerpdf-named-destinations-boundary-current-base-20260608T073948Z`

Base: `2754d86eb105729f15180756c0192f0180869ecd`

## Source Truth

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`, `marker/pdf/extract_text.py::get_text_blocks`, keeps PDF page text blocks and TOC metadata separate through `pdftext.dictionary_output` plus `get_pdf_toc`: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- Upstream `marker/cleaners/toc.py::get_pdf_toc` delegates outline/navigation extraction to the PDF engine and emits title, level, and page metadata rather than page text: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/cleaners/toc.py
- PDF 2.0 text strings may use a UTF-8 BOM. For native no-GPU WordPress import, named destinations remain review/navigation metadata; malformed string encodings must not promote stale destinations or visible paragraph text.

## Change

- `PdfNamedDestinationExtractor`, `PdfActionReviewExtractor`, `PdfOutlineExtractor`, and `PdfMetadataExtractor` now decode valid UTF-8 BOM PDF text strings before falling back to PDFDocEncoding.
- Malformed UTF-8 BOM strings now fail closed as empty strings.
- `PdfOutlineExtractor` now rejects empty decoded destination names in its name-tree map and destination lookup so malformed BOM keys cannot become an empty-name outline target.
- Added a focused fixture covering catalog `/Names /Dests`, outline `/Dest`, link annotation `/Dest`, legacy `/Dests`, and a malformed UTF-8 BOM decoy.
- Added a WordPress smoke proving decoded destination names are review-only, malformed BOM destinations are excluded, safe URI links still promote, and no Python/model/external PDF tools run.

## Red-First Evidence

`php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationUtf8BomBoundaryCurrentBaseTest.php`

Before source changes: `1 test files, 4 assertions, 2 failures`.

Failure mode: valid UTF-8 BOM destination names decoded as PDFDocEncoding mojibake, and malformed UTF-8 BOM key `Stale Key` promoted as a local destination.

## Verification

- `php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationUtf8BomBoundaryCurrentBaseTest.php` => `1 test files, 45 assertions, 0 failures`.
- `php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg '/PdfNamedDestination.*Test\\.php$' | sort)` => `56 test files, 1808 assertions, 0 failures`.
- `php lanes/markerpdf/examples/wordpress-pdf-named-destination-utf8-bom-boundary-currentbase.php` => exits 0 and emits `malformed_utf8_bom_destination_excluded=true`, `visible_text_excludes_destination_metadata=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted name-tree `/Limits` byte comparisons, duplicate key replacement/rejection, malformed UTF-16 fail-closed behavior, PDFDocEncoding destination names, indirect arrays, action dictionary aliases/cycles, outline destination target context, annotation geometry, xref repair, or page text extraction. The bounded behavior is only PDF 2.0 UTF-8 BOM text-string decoding for named-destination keys/operands and malformed UTF-8 BOM exclusion.

## Dependency Closure

No new support component is needed. The patch reuses the native PHP PDF tokenizer, text-string decoder, destination name-tree walkers, page-index mapping, outline review, action review, metadata summary, link annotation promotion, and WordPress smoke renderer. GPU/model execution, OCR, Surya/Texify/Torch, PDFium/PIL raster execution, JavaScript action execution, and external PDF tools remain intentionally out of scope.
