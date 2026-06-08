# markerPDF attachment stream filter helper tail boundary

## Source Truth

PDF stream `/Filter` operands can be names, arrays, or indirect references to those values. For attachment summaries, an indirect helper object must resolve to exactly one top-level PDF value before the decoder stack is trusted. Objects with trailing top-level operands such as `/ASCII85Decode /FlateDecode` or `[ /ASCII85Decode /FlateDecode ] /RunLengthDecode` are malformed and must fail closed.

## Behavior

`PdfAttachmentExtractor` now resolves indirect stream operand helper objects by reparsing the helper object body and requiring the full body, aside from whitespace and comments, to be consumed by a single value. That keeps exact chained helpers such as `24 0 R -> 26 0 R -> [ /ASCII85Decode /FlateDecode ] % comment` importable while rejecting helper objects that only look valid because the first parsed token was a filter value.

This closes a lightweight attachment-summary boundary: `PdfEmbeddedFileExtractor` already refused payload extraction for these malformed helpers, but `PdfAttachmentExtractor::attachmentSummary()` still counted them and exposed attachment names/metadata. The summary and payload paths now agree before WordPress attachment review.

## Evidence

Red-first focused run before the source fix:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentStreamFilterHelperTailBoundaryCurrentBaseTest.php
```

Failed with `Expected: 1` / `Actual: 3` for the attachment count, proving the two malformed helper attachments were admitted into the summary.

Passing focused verification after the fix:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentStreamFilterHelperTailBoundaryCurrentBaseTest.php
```

Result: `1 test files, 32 assertions, 0 failures`.

Adjacent attachment stream-filter family verification:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentStreamFilterStackBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentStreamFilterPredictorCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentStreamFilterTerminatorBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentStreamFilterHelperTailBoundaryCurrentBaseTest.php
```

Result: `4 test files, 631 assertions, 0 failures`.

WordPress smoke:

```bash
php lanes/markerpdf/examples/wordpress-pdf-attachment-stream-filter-helper-tail-currentbase.php
```

The smoke exits 0 with `malformed_helper_attachments_rejected=true`, `valid_exact_helper_attachment_imported=true`, `summary_payload_bytes_omitted=true`, `payload_extracted_only_for_valid_helper=true`, `malformed_payloads_excluded=true`, `visible_text_preserved=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted page-content stream stacks, xref-stream filter stacks, image-only DCT/CCITT boundaries, null filter arrays, predictor DecodeParms, Crypt identity/default behavior, duplicate stream-key handling, EOD comment boundaries, indirect null filters, singleton DecodeParms, attachment unknown `/EF` keys, generation-specific EF stream selection, or malformed dictionary filters. The bounded behavior is only exact single-value validation for indirect attachment stream `/Filter` helper operands before attachment summaries and payload extraction.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP object scanner, value parser, stream dictionary parser, attachment summary extractor, embedded-file extractor, stream-filter decoder, checksum metadata path, and WordPress smoke pattern. Live OCR, Surya/Texify/Torch model execution, PDFium rendering, external OCR/rendering helpers, Streamlit/FastAPI workers, and exact upstream model benchmark parity remain intentionally out of scope under the current no-GPU markerPDF direction.

## Next Task

Continue non-overlapping native PDF parser work around remaining searchable-PDF fidelity gaps: font/CMap boundaries, xref repair, metadata/outlines/forms, page geometry, image/filter metadata, and supplied-boundary table/equation handoffs.
