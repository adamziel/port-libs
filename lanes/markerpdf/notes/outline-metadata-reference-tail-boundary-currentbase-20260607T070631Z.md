# markerpdf-outline-metadata-boundary-current-base-20260607T070631Z

Accepted base: `292d5976e030b6a4dcfa5a457736d0b25d5a6de5`

Scope: native no-GPU markerPDF PDF parser metadata boundary. Outline root and item `/Metadata` operands are now accepted only as a single indirect metadata-stream reference. If a root or item dictionary has a value such as `/Metadata 8 0 R 10 0 R /Next ...`, the extractor records a review-only rejection before hashing or accepting object `8` as clean outline metadata.

Source truth: upstream Marker treats document TOC/navigation as structured metadata rather than visible body text; this PHP slice maps that contract to native PDF outline metadata parsing while keeping the current no-GPU scope. The PDF parser boundary is the PDF dictionary single-value operand rule for `/Metadata` references.

Red-first evidence:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataReferenceTailBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL rejects outline root and item Metadata references with trailing operands
Expected: 'rejected_malformed_outline_root_metadata_operand'
Actual: 'reviewed_outline_root_metadata_stream'
FAIL keeps tailed outline Metadata operands out of navigation and visible WordPress text
Expected: 'rejected_malformed_outline_item_metadata_operand'
Actual: 'reviewed_outline_item_metadata_stream'
1 test files, 16 assertions, 2 failures
```

Focused verification after fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataReferenceTailBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects outline root and item Metadata references with trailing operands
PASS keeps tailed outline Metadata operands out of navigation and visible WordPress text
1 test files, 61 assertions, 0 failures
```

Adjacent outline metadata subset:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataReferenceTailBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataReferenceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataRootMetadataStreamBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataStreamBoundaryCurrentBaseTest.php
Focused test run: 5 selected test files (root lock skipped)
13 PASS lines
5 test files, 238 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-outline-metadata-reference-tail-currentbase.php
exits 0; root/item metadata statuses are rejected, trailing object 10 is review-only, metadata payloads are excluded, and no Python/models/OCR or external PDF tools execute.
```

Dependency closure: no new support component is needed. This reuses the existing native PDF tokenizer, dictionary operand scanner, outline metadata extractor, navigation review path, and stream-payload exclusion logic.

Root harness: not run - isolated micro-slice.
