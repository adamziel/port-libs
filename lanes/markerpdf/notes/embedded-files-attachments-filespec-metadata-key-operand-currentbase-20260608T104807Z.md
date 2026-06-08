# Embedded Files Attachments FileSpec Metadata Key Operand Boundary

Session: `port-dev-markerpdf-attachments-20260608T104807Z`
Micro-slice: `markerpdf-embedded-files-attachments-boundary-current-base-20260608T104807Z`
Base accepted HEAD: `4af637c3364e3f16eef0a1d2e1a204436022069d`

## Source Truth

Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text through PDF parser/runtime dependencies before model stages. Under the current no-GPU markerPDF scope, native PHP owns the PDF parser and WordPress-safe attachment preflight boundary. FileSpec `/FS`, `/ID`, and `/V` are review metadata; they must not become visible text and should fail closed when duplicate or malformed top-level operands make their values ambiguous.

## Behavior

`PdfAttachmentExtractor` and `PdfEmbeddedFileExtractor` now treat FileSpec-local `/FS`, `/ID`, and `/V` as a bounded review-metadata key set. If any of those keys are duplicated or leave extra top-level operands before the next dictionary key, only that metadata is omitted. Valid `/F`, `/EF`, stream bytes, relationship metadata, checksums, and WordPress attachment rows remain available when they are otherwise well formed.

This preserves import review for a valid embedded source file while excluding decoy metadata such as duplicate `/FS /Launch`, trailing identifier bytes, and trailing `/V` operands from both summary and low-level review rows.

## Red-First Evidence

Before the fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentFileSpecMetadataKeyOperandBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL omits malformed FileSpec local metadata while preserving valid embedded attachment rows
Values are not identical
Expected: false
Actual: true

1 test files, 22 assertions, 1 failures
```

After the fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentFileSpecMetadataKeyOperandBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS omits malformed FileSpec local metadata while preserving valid embedded attachment rows

1 test files, 80 assertions, 0 failures
```

Adjacent attachment metadata/file-spec family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentFileSpecMetadataBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentFileSpecMetadataKeyOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentFilenamePathBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentDirectNameTreeFileSpecBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentAnnotationFileSpecBoundaryCurrentBaseTest.php
Focused test run: 5 selected test files (root lock skipped)
PASS fails closed on direct FileAttachment annotation FileSpec duplicate keys before WordPress attachment review
PASS fails closed on direct inline EmbeddedFiles FileSpec duplicate keys before attachment summary import
PASS carries FileSpec file-system identifier and volatility metadata in attachment preflight
PASS propagates FileSpec metadata through embedded-file review rows without visible text leakage
PASS omits malformed FileSpec local metadata while preserving valid embedded attachment rows
PASS adds safe basename review for path shaped FileSpec names in attachment preflight
PASS propagates safe basename review through embedded-file rows without visible text leakage

5 test files, 260 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-attachment-filespec-metadata-key-operand-currentbase.php
```

The smoke exits `0` and emits `ambiguous_attachment_preserved=true`, `ambiguous_metadata_omitted=true`, `valid_metadata_preserved=true`, `payload_bytes_omitted_from_summary=true`, `decoy_metadata_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted catalog/page/annotation `/AF` extraction, EmbeddedFiles name-tree mirror marking, direct FileSpec duplicate filename or `/EF` key rejection, related-file `/RF` pair parsing, FileSpec metadata happy-path extraction, path-shaped filename review, stream-filter attachment boundaries, encrypted EFF redaction, portfolio `/Collection`, PieceInfo/XMP/OutputIntent attachment metadata, or xref/object-stream attachment selection. The new boundary is only malformed FileSpec-local `/FS`, `/ID`, and `/V` metadata operands while preserving otherwise valid attachment rows.

## Dependency Closure

No new support component is needed. The patch reuses native PHP PDF object scanning, dictionary/value parsing, FileSpec `/EF` parsing, embedded-file stream decoding, checksum review, and existing WordPress attachment smoke paths. Python, CUDA, OCR, Surya/Texify/Torch models, pypdfium/PIL rendering, live app/server workers, decryption, and external PDF tools were not run.
