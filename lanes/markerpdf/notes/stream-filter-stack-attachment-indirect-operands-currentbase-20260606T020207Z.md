# markerPDF attachment stream-filter stack indirect operands boundary

Micro-slice: `markerpdf-stream-filter-stack-boundary-current-base-20260606T020207Z`

Base accepted HEAD: `28ce1248504d246cd7ef6530c0bb360adf7265f0`

## Source Truth

- Upstream `sddai/markerPDF` is pinned in the lane manifest at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- Upstream markerPDF delegates searchable PDF parsing to `pdftext`/PDFium before OCR/layout/model stages. Under the current no-GPU scope, this lane owns the native PHP PDF parser boundary for stream filters, embedded files, and WordPress attachment review.
- PDF stream dictionaries may store `/Filter` and `/DecodeParms` operands through indirect objects. Native attachment extraction must follow valid operand chains while rejecting cycles and unresolved real-filter parameters before payload bytes are exposed.

## Behavior

`PdfAttachmentExtractor` now recursively resolves indirect stream-filter operands for embedded-file attachment payload decoding:

```text
/Filter [ 20 0 R 21 0 R ]
20 0 obj
22 0 R
endobj
22 0 obj
/ASCII85Decode
endobj
```

The same boundary resolves chained indirect `/DecodeParms` arrays and dictionaries for the real filter that consumes them:

```text
/DecodeParms 30 0 R
30 0 obj
31 0 R
endobj
31 0 obj
[ null 32 0 R ]
endobj
```

Cyclic `/Filter` operands fail closed and suppress that attachment row. Unresolved or cyclic DecodeParms aligned to a `null` filter slot remain ignored as identity placeholders, while the same unresolved parameter on a real filter still rejects the stream.

## Red-First Evidence

After adding the focused regression and before the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentStreamFilterStackBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS treats Identity Crypt as a byte-preserving attachment stream stack stage while rejecting private crypt filters
PASS rejects dictionary-valued attachment Filter operands before summary or payload extraction
PASS decodes LZW attachment filter stacks while rejecting bytes after the LZW EOD code
PASS rejects extra non-null DecodeParms entries in attachment filter stacks before summary or payload extraction
FAIL resolves chained indirect Filter and DecodeParms operands while failing closed on filter cycles
Values are not identical
Expected: 1
Actual: 0
1 test files, 105 assertions, 1 failures
```

## Evidence

Focused after source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentStreamFilterStackBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS treats Identity Crypt as a byte-preserving attachment stream stack stage while rejecting private crypt filters
PASS rejects dictionary-valued attachment Filter operands before summary or payload extraction
PASS decodes LZW attachment filter stacks while rejecting bytes after the LZW EOD code
PASS rejects extra non-null DecodeParms entries in attachment filter stacks before summary or payload extraction
PASS resolves chained indirect Filter and DecodeParms operands while failing closed on filter cycles
1 test files, 131 assertions, 0 failures
```

Adjacent attachment/embedded-file stream-filter family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentStreamFilterPredictorCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentStreamFilterStackBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentStreamFilterTerminatorBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfEmbeddedFileAttachmentGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfEmbeddedFileEofBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfEmbeddedFileExtractorTest.php
Focused test run: 6 selected test files (root lock skipped)
6 test files, 842 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-attachment-stream-filter-stack-boundary-currentbase.php
```

The smoke emits `indirect_operand_attachment_decoded=true`, `indirect_operand_filters=["ASCII85Decode","FlateDecode"]`, `indirect_operand_payload_bytes_omitted_from_summary=true`, `cyclic_filter_operand_rejected=true`, `cyclic_filter_operand_payload_excluded=true`, `indirect_operand_visible_text_preserved=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted page text stream-filter stack recovery, attachment identity `/Crypt`, attachment LZW surplus-byte rejection, malformed dictionary-valued `/Filter`, extra DecodeParms fail-closed behavior, predictor DecodeParms, terminal payload rejection, object-stream/xref-stream filter recovery, image-filter preview metadata, or OCR/model execution.

The bounded behavior is specifically chained indirect `/Filter` and `/DecodeParms` operands for embedded-file attachment streams plus cyclic filter fail-closed handling in the attachment summary path.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object parser, stream filter stack decoders, DecodeParms alignment logic, attachment summary path, embedded-file extractor, and WordPress smoke renderer. Non-identity crypt filters, Standard security-handler decryption, public-key decryption, model/OCR execution, PDFium rendering, and external PDF tools remain outside the current no-GPU/no-decryption markerPDF scope.
