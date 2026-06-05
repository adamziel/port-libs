# markerPDF Embedded Files Attachment Generation Boundary Current Base

## Source Truth

Upstream markerPDF routes searchable PDF parsing through pdftext/PDFium before model and OCR stages. Under the current no-GPU markerPDF scope, this lane owns native PDF parser boundaries that decide which attachment metadata is safe for WordPress import before any model handoff.

PDF indirect references include both object number and generation. Incremental updates can leave generation-zero FileSpec and EmbeddedFile objects in a previous xref section while the selected current catalog and name tree use generation-one objects with the same object numbers. A current attachment graph can also contain stale generation-zero decoy references. Full embedded-file extraction must reject those generation-mismatched references instead of resolving by object number alone.

## Implementation

- `PdfEmbeddedFileExtractor` now tracks the selected generation for each object number while building its xref-selected direct and object-stream object inventory.
- Full embedded-file indirect resolution now requires `N G R` references to match the selected generation before resolving:
  - catalog `/Root`;
  - name-tree/FileSpec dictionaries;
  - EmbeddedFile stream objects;
  - filter and DecodeParms helper operands;
  - FileSpec metadata streams, OutputIntent profiles, and PieceInfo private streams.
- The new focused fixture keeps stale generation-zero name-tree and catalog `/AF` attachment rows alongside current generation-one rows. Before the fix, the full extractor resolved `4 0 R` to the selected `4 1` FileSpec and let the stale name-tree key win. After the fix, only generation-one attachment rows reach full extraction, metadata review, and lightweight summary output.

## Red-First Evidence

Before the source patch:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfEmbeddedFileAttachmentGenerationBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL keeps generation-exact EmbeddedFiles rows across full and lightweight attachment review (lanes/markerpdf/tests/PdfEmbeddedFileAttachmentGenerationBoundaryCurrentBaseTest.php)
Values are not identical
Expected: 'generation-one-name.csv'
Actual: 'generation-zero-name.csv'

1 test files, 2 assertions, 1 failures
```

After the source patch:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfEmbeddedFileAttachmentGenerationBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS keeps generation-exact EmbeddedFiles rows across full and lightweight attachment review

1 test files, 47 assertions, 0 failures
```

Focused adjacent gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfEmbeddedFileAttachmentGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfEmbeddedFileExtractorTest.php lanes/markerpdf/tests/PdfEmbeddedFileEofBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentGenerationReferenceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentExtractorTest.php lanes/markerpdf/tests/PdfAttachmentFileSpecAssociatedAFRelationshipChecksumCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentDirectFileSpecNameTreeMirrorBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataAssociatedFileOutputIntentEncryptXmpCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataAssociatedFileSchemaCurrentBaseTest.php
Focused test run: 9 selected test files (root lock skipped)
...
9 test files, 1183 assertions, 0 failures
```

Attachment family gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachment*.php lanes/markerpdf/tests/PdfEmbeddedFile*.php lanes/markerpdf/tests/PdfMetadataAssociatedFile*.php lanes/markerpdf/tests/PdfMetadataAssociatedPieceInfoOutputIntentCurrentBaseTest.php
Focused test run: 31 selected test files (root lock skipped)
...
31 test files, 2378 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-embedded-files-generation-boundary-currentbase.php
```

The smoke exits `0` and reports `embedded_file_count=2`, `attachment_count=2`, `stale_generation_name_tree_excluded=true`, `stale_generation_catalog_af_excluded=true`, `payload_bytes_omitted_from_summary=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted lightweight generation-reference attachment preflight, xref offset-owner generation repair, object-stream FileSpec extraction, EOF-bounded extraction, direct FileSpec mirror dedupe, duplicate invalid name-tree keys, platform `/EF` key selection, PDFDocEncoding filename decoding, encrypted `/EFF` redaction, related-file review, portfolio/PieceInfo/XMP/OutputIntent metadata, or page/FileAttachment annotation mirrors. The bounded behavior is only generation-exact indirect reference resolution inside the full `PdfEmbeddedFileExtractor` path and the cross-check that full extraction, metadata review, and lightweight summary stay aligned.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, xref-chain repair, object-stream decoder, EmbeddedFiles name-tree walker, FileSpec parser, stream filter decoder, metadata extractor, lightweight attachment summary, and WordPress smoke renderer. Live OCR, Surya/Texify/Torch model execution, PDFium rendering, external OCR/rendering helpers, decryption, Streamlit/FastAPI workers, and exact upstream model benchmark parity remain intentionally outside the current no-GPU markerPDF scope.
