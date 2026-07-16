# Attachment Stream-Filter Terminator Boundary Current Base

Micro-slice: `markerpdf-stream-filter-stack-boundary-current-base-20260605T175020Z`

Accepted base: `3235f8b726c92b836d2ca5705bd55b61ba8c1970`

## Behavior

Native EmbeddedFiles attachment review now rejects stream filter stacks when a bounded `ASCII85Decode`, `ASCIIHexDecode`, `RunLengthDecode`, or `FlateDecode` stage leaves non-whitespace bytes after its terminator/member. Valid bounded attachment streams still decode for checksum/content review through `PdfAttachmentExtractor` and `PdfEmbeddedFileExtractor`.

This maps the same fail-closed native parser boundary already used for visible content streams onto attachment imports, so malformed embedded file payloads cannot be partially decoded while hidden surplus bytes are ignored before WordPress import review.

## Evidence

Red-first:

`php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentStreamFilterTerminatorBoundaryCurrentBaseTest.php`

Result before source edit: `1 test files / 1 assertions / 1 failures`; the current base accepted `6` attachments instead of the expected `2`.

After source edit:

`php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentStreamFilterTerminatorBoundaryCurrentBaseTest.php`

Result: `1 test files / 55 assertions / 0 failures`.

Adjacent regression:

`php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentStreamFilterStackBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentStreamFilterPredictorCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterTrailingPayloadBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php`

Result: `4 test files / 387 assertions / 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-attachment-stream-filter-terminator-boundary-currentbase.php`

Result: emits `valid_attachments_selected=true`, `embedded_payloads_available_to_review=true`, `ascii85_surplus_attachment_excluded=true`, `runlength_surplus_attachment_excluded=true`, `flate_surplus_attachment_excluded=true`, `payload_bytes_omitted_from_summary=true`, `visible_text_kept_clean=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Dependency Closure

No new support component is needed. The patch reuses PHP zlib stream state functions already used in the markerPDF lane for bounded Flate member detection; no Python, OCR/model, pypdfium, Poppler, qpdf, or external PDF tooling is invoked.

## Non-Overlap

This slice avoids the accepted plus-signed object-stream header review, object-stream offset/generation repair, visible text stream trailing-payload boundary, stream-filter DecodeParms compact/null/default-crypt slices, inline-image tokenizer slices, CMap EOD slices, xref repair slices, and attachment predictor/Identity Crypt coverage. It specifically owns EmbeddedFiles attachment stream terminator surplus handling.

## Next

Continue no-GPU markerPDF work with non-overlapping native parser behavior around stream filters, metadata, xref repair, image/filter metadata, font/CMap extraction, annotations, forms, and page geometry.
