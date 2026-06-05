# markerpdf EmbeddedFile null-slot DecodeParms stream-filter boundary

Micro-slice: `markerpdf-stream-filter-stack-boundary-current-base-20260605T155314Z`
Base: `205bce50edd3fe6b394151a64344ea9de39b3aa1`

## Source truth

- Upstream `sddai/markerPDF` at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF extraction and artifact review before OCR/model handoff. This slice stays in the native no-GPU PDF parser/attachment-review boundary.
- PDF stream `/Filter` arrays are ordered stacks. `null` filter entries are identity placeholders, and `/DecodeParms` array entries correspond to filter slots. A malformed or unresolved parameter aligned to a `null` slot is not applied to bytes, while the same parameter aligned to a real filter remains unsafe and must fail closed.

## Behavior

`PdfEmbeddedFileExtractor` now preserves null stream-filter slots while aligning DecodeParms before extracting EmbeddedFile payload bytes:

```text
/Filter [ null /ASCIIHexDecode /FlateDecode ]
/DecodeParms [ 99 0 R null << /Predictor 12 /Columns 40 >> ]
```

The unresolved `99 0 R` is ignored because it belongs to the null identity slot. The ASCIIHex and Flate stages still decode in order, and the Flate predictor is applied before size/checksum review. A sibling stream with the unresolved `99 0 R` aligned to real `/FlateDecode` is rejected before extraction.

The metadata returned by attachment and embedded-file extractors still reports only real filters, for example `["ASCIIHexDecode","FlateDecode"]`, and embedded payload bytes remain out of WordPress summary HTML.

## Red-first evidence

Before the implementation edit, the new focused case failed in `PdfEmbeddedFileExtractor` while the public attachment summary already counted the safe row:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentStreamFilterPredictorCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS decodes Flate DecodeParms PNG predictors in attachment filter stacks before checksum review
PASS resolves indirect DecodeParms arrays for predictor attachment streams
FAIL ignores DecodeParms aligned to null attachment filters before embedded-file extraction (lanes/markerpdf/tests/PdfAttachmentStreamFilterPredictorCurrentBaseTest.php)
Values are not identical
Expected: 1
Actual: 0
PASS decodes singleton TIFF predictor DecodeParms for embedded attachment streams

1 test files, 83 assertions, 1 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentStreamFilterPredictorCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS decodes Flate DecodeParms PNG predictors in attachment filter stacks before checksum review
PASS resolves indirect DecodeParms arrays for predictor attachment streams
PASS ignores DecodeParms aligned to null attachment filters before embedded-file extraction
PASS decodes singleton TIFF predictor DecodeParms for embedded attachment streams

1 test files, 95 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentExtractorTest.php lanes/markerpdf/tests/PdfEmbeddedFileExtractorTest.php lanes/markerpdf/tests/PdfAttachmentStreamFilterPredictorCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php
Focused test run: 4 selected test files (root lock skipped)
4 test files, 1186 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-attachment-stream-filter-predictor-currentbase.php
```

The smoke emits `attachment_count=1`, `flate_predictor_decodeparms_applied=true`, `null_filter_decodeparms_slot_ignored=true`, `invalid_predictor_fail_closed=true`, `payload_bytes_omitted_from_summary=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Non-overlap

This does not repeat the accepted page-content stream-stack null-slot behavior, attachment-summary DecodeParms fail-closed behavior, attachment predictor decoding, unsupported EmbeddedFile terminal filters, CMap filter handling, xref stream filter operand recovery, image filter metadata review, or live OCR/model work. The bounded delta is specifically `PdfEmbeddedFileExtractor` preserving null filter slots for DecodeParms alignment before EmbeddedFile extraction and checksum review.

## Dependency closure

No new support component is needed. This reuses the native PHP PDF object parser, EmbeddedFiles/FileSpec traversal, stream filter stack decoder, Flate predictor reversal, and WordPress smoke renderer. Full upstream model/OCR/PDFium parity remains intentionally out of scope under the current markerPDF no-GPU direction and remains gated by pdftext, pypdfium2/PDFium, Surya/Torch, Texify, tabled-pdf, model downloads, Streamlit/FastAPI workers, and external OCR/rendering helpers; none were executed.
