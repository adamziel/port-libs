# markerPDF Embedded Files Attachment Params Duplicate Current Base

Session: `port-dev-markerpdf-attachments-20260607T020345Z`
Micro-slice: `markerpdf-embedded-files-attachments-boundary-current-base-20260607T020345Z`
Base accepted HEAD: `beafb5b9ebe55f9aec9402f03ec049292424d83f`

## Source Truth

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text through `pdftext.dictionary_output()`/PDF page text extraction. Embedded-file payloads and FileSpec dictionaries are not visible paragraph text. In the native no-GPU PHP port, attachment preflight is a parser/review boundary: FileSpec `/EF` streams can be summarized for WordPress import, but ambiguous attachment metadata must fail closed before size, checksum, date, Mac, or related-file review rows are trusted.

The bounded PDF behavior in this slice is duplicate top-level `/Params` keys inside `/Type /EmbeddedFile` stream dictionaries. `/Params` carries `/Size`, `/CheckSum`, `/CreationDate`, `/ModDate`, and `/Mac` metadata, so conflicting dictionaries can make WordPress review choose stale or decoy attachment metadata while still pointing at a valid payload stream.

## Implementation

`PdfAttachmentExtractor` now rejects primary EmbeddedFile streams, related-file streams, and Mac resource-fork streams when their top-level stream dictionary contains duplicate `/Params` keys.

`PdfEmbeddedFileExtractor` now applies the same duplicate-`/Params` guard in both payload-decoding and encrypted review-only stream paths, so full embedded-file extraction and lightweight attachment summaries agree.

The focused fixture includes:

- one valid catalog EmbeddedFiles name-tree Source FileSpec with checksum/date metadata;
- one related `/RF` valid JSON review row;
- one primary EmbeddedFile stream with duplicate `/Params`, which must be excluded;
- one related EmbeddedFile stream with duplicate `/Params`, which must also be excluded;
- visible page text proving attachment payloads remain out of WordPress paragraphs.

## Red First

Before the production patch:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentParamsDuplicateBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL fails closed on duplicate EmbeddedFile Params dictionaries before attachment metadata review (lanes/markerpdf/tests/PdfAttachmentParamsDuplicateBoundaryCurrentBaseTest.php)
Values are not identical
Expected: 1
Actual: 2

1 test files, 1 assertions, 1 failures
```

After the production patch:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentParamsDuplicateBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS fails closed on duplicate EmbeddedFile Params dictionaries before attachment metadata review

1 test files, 65 assertions, 0 failures
```

## WordPress Smoke

```text
php lanes/markerpdf/examples/wordpress-pdf-attachment-params-duplicate-currentbase.php
```

The smoke emits `attachment_count=1`, `filename=valid-source.xml`, `related_filename=valid-related.json`, `duplicate_params_attachment_excluded=true`, `duplicate_params_related_file_excluded=true`, `attachment_payload_omitted_from_summary=true`, `visible_text_excludes_attachment_payloads=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Verification

```text
php -l lanes/markerpdf/src/PdfAttachmentExtractor.php
php -l lanes/markerpdf/src/PdfEmbeddedFileExtractor.php
php -l lanes/markerpdf/tests/PdfAttachmentParamsDuplicateBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-attachment-params-duplicate-currentbase.php
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentParamsDuplicateBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentExtractorTest.php lanes/markerpdf/tests/PdfEmbeddedFileExtractorTest.php lanes/markerpdf/tests/PdfAttachmentDuplicateFileSpecKeyBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentMacParamsBoundaryCurrentBaseTest.php
```

```text
php lanes/markerpdf/examples/wordpress-pdf-attachment-params-duplicate-currentbase.php
git diff --check -- lanes/markerpdf
```

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted EmbeddedFiles `/Limits`, PDFDocEncoding names, `/DL`, `/AFRelationship`, platform FileSpec names, `/EF` duplicate key fail-closed behavior, direct FileSpec mirror dedupe, encrypted EFF, related-file path/name-pair review, portfolio collection metadata, PieceInfo private streams, attachment annotation presentation, stream-filter DecodeParms predictor behavior, stream-filter terminator recovery, or `/Params /Mac` resource-fork review. The new boundary is specifically duplicate top-level `/Params` dictionaries on EmbeddedFile streams before primary and related attachment metadata review.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP PDF object/value parser, stream dictionary parser, EmbeddedFile stream decoder, FileSpec `/EF` and `/RF` handling, checksum/date metadata review, and WordPress smoke pattern. Full OCR, Surya/Texify/Torch, PDFium rendering, table-model inference, and exact model benchmark parity remain intentionally out of scope under the current no-GPU markerPDF direction.
