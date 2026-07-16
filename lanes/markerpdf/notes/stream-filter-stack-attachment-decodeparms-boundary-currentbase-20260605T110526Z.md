# markerpdf attachment DecodeParms stream-filter boundary current base

Slice: `markerpdf-stream-filter-stack-boundary-current-base-20260605T110526Z`
Base: `4d4145c84343a3b3d02a26c922d711205e8e3014`
Scope: native no-GPU markerPDF attachment preflight stream decoding.

## Source Truth

- Upstream `sddai/markerPDF` at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text extraction before OCR/layout/model stages; attachment payload handling in this PHP lane must remain native preflight/review work and must not invoke OCR, GPU models, or external PDF tools.
- PDF stream `/Filter` stacks apply decoders in order. `/DecodeParms` entries correspond to filter entries; unsupported non-default parameters cannot be treated as identity before size/checksum review.

## Behavior

`PdfAttachmentExtractor` now preserves stream filter slots, including `null` identity slots, before decoding embedded attachment streams. Attachment preflight accepts absent, null, empty, or default `/DecodeParms` values for the currently supported attachment decoders, but fails closed when a real filter has non-default parameters this extractor does not implement, such as:

```text
/Filter [ /ASCIIHexDecode /FlateDecode ]
/DecodeParms [ null << /Predictor 12 /Columns 8 >> ]
```

Before this patch, the native attachment summary decoded ASCIIHex and Flate bytes, then counted `unsafe-predictor.bin` with a matching checksum. After the patch, that attachment row is excluded before checksum review, while a sibling `/Predictor 1` stack remains accepted.

## Red Probe

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentExtractorTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL fails closed on unsupported attachment DecodeParms before checksum review
Values are not identical
Expected: 1
Actual: 2
1 test files, 436 assertions, 1 failures
```

## Evidence

```text
php -l lanes/markerpdf/src/PdfAttachmentExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfAttachmentExtractor.php
```

```text
php -l lanes/markerpdf/tests/PdfAttachmentExtractorTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfAttachmentExtractorTest.php
```

```text
php -l lanes/markerpdf/examples/wordpress-pdf-attachment-decodeparms-boundary-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-attachment-decodeparms-boundary-currentbase.php
```

Focused direct test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentExtractorTest.php
1 test files, 454 assertions, 0 failures
```

Adjacent attachment/filter boundary run:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentExtractorTest.php lanes/markerpdf/tests/PdfEmbeddedFileExtractorTest.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php
3 test files, 1064 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-attachment-decodeparms-boundary-currentbase.php
```

The smoke emits `safe_attachment_preserved=true`, `default_decodeparms_accepted=true`, `unsupported_decodeparms_rejected=true`, `unsafe_checksum_excluded=true`, `payload_bytes_omitted_from_summary=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat the accepted generic text-stream extra `/DecodeParms` fail-closed behavior, the EmbeddedFile extractor unsupported-terminal-filter boundary, CMap filter EOD handling, image filter metadata review, inline image tokenizer boundaries, xref stream DecodeParms behavior, or OCR/model execution. The patch is limited to `PdfAttachmentExtractor` attachment-summary payload decoding before declared-size and checksum review.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object parser, attachment FileSpec walker, stream filter decoders, and WordPress smoke renderer. Predictor transforms for attachment payload streams remain a future native decoder extension; until implemented, non-default `/DecodeParms` are an in-scope fail-closed condition rather than a reason to invoke external tools, OCR, GPU models, or live services.
