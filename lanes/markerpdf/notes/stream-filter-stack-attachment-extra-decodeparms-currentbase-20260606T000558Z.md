# markerPDF attachment stream-filter extra DecodeParms boundary

Micro-slice: `markerpdf-stream-filter-stack-boundary-current-base-20260606T000558Z`
Base accepted HEAD: `996d008a6d589439433524500ecf697af2eedb4a`

## Source Truth

- Upstream `sddai/markerPDF` at pinned manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes PDF attachment and searchable-text handling through PDF parser/runtime dependencies before model stages.
- Under the current no-GPU markerPDF scope, native PHP owns embedded-file stream filter review. PDF `/Filter` and `/DecodeParms` arrays are ordered by slot; a non-null DecodeParms entry that is not consumed by any real filter is ambiguous and must fail closed before WordPress import exposes attachment metadata or file content.

## Behavior

`PdfAttachmentExtractor` and `PdfEmbeddedFileExtractor` now reject embedded-file streams where `/DecodeParms` includes an extra non-null entry beyond the declared real filter stack.

Focused fixture shape:

```text
/Filter [ /ASCII85Decode /FlateDecode ]
/DecodeParms [ null null << /Predictor 1 >> ]
```

Before this slice, that ambiguous attachment was accepted because the extra dictionary looked like default parameters. After this slice, both attachment summary and extracted embedded-file content suppress that file, while a neighboring valid attachment with aligned `[ null null ]` DecodeParms remains available with checksum and size metadata.

## Red First

After adding the focused test before the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentStreamFilterStackBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS treats Identity Crypt as a byte-preserving attachment stream stack stage while rejecting private crypt filters
PASS rejects dictionary-valued attachment Filter operands before summary or payload extraction
PASS decodes LZW attachment filter stacks while rejecting bytes after the LZW EOD code
FAIL rejects extra non-null DecodeParms entries in attachment filter stacks before summary or payload extraction
Expected: 1
Actual: 2

1 test files, 79 assertions, 1 failures
```

## Evidence

Focused test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentStreamFilterStackBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS treats Identity Crypt as a byte-preserving attachment stream stack stage while rejecting private crypt filters
PASS rejects dictionary-valued attachment Filter operands before summary or payload extraction
PASS decodes LZW attachment filter stacks while rejecting bytes after the LZW EOD code
PASS rejects extra non-null DecodeParms entries in attachment filter stacks before summary or payload extraction

1 test files, 104 assertions, 0 failures
```

Focused adjacent attachment family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentStreamFilterStackBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentStreamFilterPredictorCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentStreamFilterTerminatorBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentExtractorTest.php lanes/markerpdf/tests/PdfEmbeddedFileAttachmentGenerationBoundaryCurrentBaseTest.php
Focused test run: 5 selected test files (root lock skipped)
5 test files, 823 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-attachment-stream-filter-stack-boundary-currentbase.php
```

The smoke emits `extra_decodeparms_attachment_rejected=true`, `extra_decodeparms_payload_excluded=true`, `valid_attachment_after_extra_decodeparms_preserved=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted text stream-filter stack recovery, compact DecodeParms arrays, unresolved DecodeParms entries aligned to null text filters, DCTDecode missing-slot review, identity Crypt attachment stacks, dictionary-valued attachment Filter rejection, LZW EOD attachment stack boundaries, attachment predictor DecodeParms, encrypted attachment suppression, or model/OCR execution.

The bounded behavior is specifically extra non-null DecodeParms entries in embedded-file attachment streams that are not aligned to any real filter slot.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP PDF object parser, attachment summary path, embedded-file extractor, stream filter stack decoders, DecodeParms alignment helpers, checksum review, and WordPress smoke path. Non-identity crypt filters, Standard security-handler decryption, public-key decryption, OCR, Surya/Texify/Torch model execution, pypdfium, PIL, Poppler, Ghostscript, and external PDF tools remain outside the current no-GPU/no-decryption markerPDF scope and were not run.
