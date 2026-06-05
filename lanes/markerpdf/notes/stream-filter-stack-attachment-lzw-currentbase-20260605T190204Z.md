# markerPDF Attachment LZW Stream Filter Stack Boundary

Micro-slice: `markerpdf-stream-filter-stack-boundary-current-base-20260605T190204Z`
Session: `port-dev-markerpdf-stream-filter-stack-20260605T190204Z`
Base accepted HEAD: `6eabc470c32c0f122118ac788fbbcb8021d0420e`

## Source Truth

- Upstream markerPDF routes searchable PDF and attachment-facing import decisions through native PDF parsing before OCR/model handoff.
- PDF stream filter stacks are applied in declared order. LZWDecode is a standard PDF stream filter and its end-of-data code is the bounded ownership marker for the encoded stream bytes.
- WordPress attachment preflight must decode safe native EmbeddedFile payloads without exposing attachment bytes in summaries, and it must reject non-whitespace surplus after a bounded stream terminator.

## Behavior

`PdfAttachmentExtractor` and `PdfEmbeddedFileExtractor` now decode `LZWDecode` and abbreviated `LZW` stages in EmbeddedFile filter stacks, including `/EarlyChange 0` DecodeParms, before downstream filters such as `FlateDecode`.

Both extractors also treat LZW as a bounded-end filter when validating attachment stream ownership. A valid LZW payload ending at the EOD code is accepted and decoded; an otherwise valid payload with non-whitespace surplus bytes after the EOD code is excluded from both the WordPress attachment summary and embedded-file payload extraction.

The focused fixture keeps one valid `/Filter [ /LZWDecode /FlateDecode ]` CSV attachment and one sibling attachment with the same stack plus post-EOD text-looking surplus bytes. The valid file is counted with checksum match state, while the surplus file and payload text are excluded from summaries, payload extraction, and visible page text.

## Red Probe

Before the source edit, after adding the LZW attachment fixture:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentStreamFilterStackBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS treats Identity Crypt as a byte-preserving attachment stream stack stage while rejecting private crypt filters
PASS rejects dictionary-valued attachment Filter operands before summary or payload extraction
FAIL decodes LZW attachment filter stacks while rejecting bytes after the LZW EOD code
Values are not identical
Expected: 1
Actual: 0
1 test files, 51 assertions, 1 failures
```

## Focused Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentStreamFilterStackBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS treats Identity Crypt as a byte-preserving attachment stream stack stage while rejecting private crypt filters
PASS rejects dictionary-valued attachment Filter operands before summary or payload extraction
PASS decodes LZW attachment filter stacks while rejecting bytes after the LZW EOD code
1 test files, 78 assertions, 0 failures
```

Adjacent attachment and EmbeddedFile filter family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentStreamFilterStackBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentStreamFilterPredictorCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentStreamFilterTerminatorBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentExtractorTest.php lanes/markerpdf/tests/PdfEmbeddedFileExtractorTest.php
5 test files, 1103 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-attachment-stream-filter-stack-boundary-currentbase.php
```

The smoke exits 0 and emits `lzw_attachment_count=1`, `lzw_filter_stack_decoded=true`, `lzw_filters=["LZWDecode","FlateDecode"]`, `lzw_payload_bytes_omitted_from_summary=true`, `lzw_surplus_attachment_rejected=true`, `lzw_surplus_payload_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Syntax checks:

```text
php -l lanes/markerpdf/src/PdfAttachmentExtractor.php
php -l lanes/markerpdf/src/PdfEmbeddedFileExtractor.php
php -l lanes/markerpdf/tests/PdfAttachmentStreamFilterStackBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-attachment-stream-filter-stack-boundary-currentbase.php
```

All changed PHP files reported no syntax errors.

Root harness status: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted page-content ASCII85/Flate stream-boundary recovery, text-stream LZWDecode extraction, inline-image LZW DecodeParms preview rows, inline-image post-EOD surplus rejection, attachment predictor DecodeParms, attachment ASCII85/Flate/Crypt stacks, attachment dictionary-valued Filter fail-closed behavior, attachment terminator rejection for ASCII85/ASCIIHex/RunLength/Flate, CMap filter EOD handling, object-stream filter ownership, xref-stream DecodeParms recovery, image filters, or encrypted PDF preflight.

The bounded behavior here is only EmbeddedFile attachment payloads whose filter stack includes LZWDecode before a downstream native filter and whose encoded bytes must end at the LZW EOD boundary before WordPress attachment review.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object parser, attachment FileSpec walker, DecodeParms resolver, stream-filter stack dispatcher, LZW byte decoder pattern, EmbeddedFile payload extractor, and WordPress smoke renderer. OCR, Surya/Texify/Torch, PDFium execution, model downloads, raster rendering, and external PDF tools remain intentionally outside this no-GPU markerPDF slice.
