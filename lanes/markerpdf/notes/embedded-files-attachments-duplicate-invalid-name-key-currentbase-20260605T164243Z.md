# markerpdf embedded-files attachments duplicate invalid name-key boundary current base

Session: `port-dev-markerpdf-attachments-20260605T164243Z`
Micro-slice: `markerpdf-embedded-files-attachments-boundary-current-base-20260605T164243Z`
Base accepted HEAD: `d11e64ae6f006601d89d9cc168745be63321b45d`

## Source truth

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF page text through parser-backed `pdftext` extraction before model/OCR fallback. Embedded file payloads and FileSpec dictionaries are not visible-page text.
- In this no-GPU native PHP lane, `/Names /EmbeddedFiles` and FileSpec attachment preflight are review-only parser boundaries for WordPress imports. Payload bytes stay out of Gutenberg text and summaries while filenames, hashes, relationship roles, and object ids remain review metadata.
- PDF name-tree duplicate keys are malformed. The importer should keep the first successfully parsed attachment row for a key, but a malformed first duplicate FileSpec with no `/EF` should not suppress a later valid embedded source attachment.

## Implementation

`PdfAttachmentExtractor` now tracks seen EmbeddedFiles name-tree keys only after `attachmentFromFileSpecValue()` successfully returns an attachment. This preserves the existing first-valid-duplicate behavior while allowing preflight to continue past malformed duplicate FileSpecs that cannot produce an attachment row.

The focused fixture has:

- a catalog `/Names << /EmbeddedFiles 6 0 R >>`;
- a duplicate `/Names [(source.xml) 20 0 R (source.xml) 10 0 R]`;
- object `20 0 R` as a malformed FileSpec with no `/EF`;
- object `10 0 R` as the valid WordPress source FileSpec with an EmbeddedFile stream;
- visible page text proving attachment payloads do not leak into page extraction.

## Red-first evidence

Before the source change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentDuplicateInvalidNameKeyBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL continues past malformed first duplicate EmbeddedFiles name-tree FileSpecs before attachment preflight (lanes/markerpdf/tests/PdfAttachmentDuplicateInvalidNameKeyBoundaryCurrentBaseTest.php)
Values are not identical
Expected: 1
Actual: 0

1 test files, 1 assertions, 1 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentDuplicateInvalidNameKeyBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS continues past malformed first duplicate EmbeddedFiles name-tree FileSpecs before attachment preflight

1 test files, 35 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentDuplicateInvalidNameKeyBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentExtractorTest.php lanes/markerpdf/tests/PdfEmbeddedFileExtractorTest.php
Focused test run: 3 selected test files (root lock skipped)
...
3 test files, 910 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-attachment-duplicate-invalid-name-key-currentbase.php
```

The smoke exits `0` and emits `invalid_first_duplicate_skipped=true`, `valid_duplicate_file_spec_recovered=true`, `payload_omitted_from_summary=true`, `visible_text_preserved=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-overlap

This does not repeat accepted EOF-bounded attachment scanning, platform EF-key order, duplicate valid name-tree first-row behavior, indirect name-key resolution, indirect Names/Kids arrays, xref/object-stream attachment selection, catalog/page `/AF` mirror marking, related-file review, encrypted EFF redaction, portfolio/PieceInfo attachment metadata, or pdftext supplied-boundary layout/order behavior. The bounded behavior is only that an invalid first duplicate EmbeddedFiles name-tree FileSpec cannot consume the key before a later valid attachment row is parsed.

## Dependency closure

No new support component is needed. This reuses the native PHP PDF object/value parser, name-tree traversal, FileSpec parsing, stream decoding, checksum review, text extractor, and WordPress smoke path. Live OCR, Surya/Texify/Torch model execution, PDFium rendering, external OCR/rendering helpers, Streamlit/FastAPI workers, and exact upstream model benchmark parity remain intentionally outside the current no-GPU markerPDF scope.
