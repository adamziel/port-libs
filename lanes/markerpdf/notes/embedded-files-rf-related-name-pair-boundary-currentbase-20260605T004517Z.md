# markerPDF Embedded Files RF Related-Name Pair Boundary Current Base

Session: `port-dev-markerpdf-attachments-20260605T004517Z`

Micro-slice: `markerpdf-embedded-files-attachments-boundary-current-base-20260605T004517Z`

Base accepted HEAD: `a86c655e11e452aca09869ef7f95a1d8b2f99b2c`

## Source Truth

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` extracts visible searchable PDF text through PDF text APIs; FileSpec attachment payloads and related-file payloads stay out of visible text.
- PDF FileSpec `/RF` related-file dictionaries use the same platform keys as `/EF`, and array values may be filename plus EmbeddedFile stream pairs. WordPress import preflight needs those related filenames as review metadata without promoting related payload bytes into Gutenberg content.

## Behavior

`PdfAttachmentExtractor` and `PdfEmbeddedFileExtractor` now treat `/RF` arrays as logical related-file name/stream pairs when that shape is present:

- `[(style.css) 6 0 R]` records `related_filename=style.css`;
- pair indexes are logical indexes starting at `0`, not raw array offsets;
- existing bare stream lists such as `[6 0 R 7 0 R]` remain supported;
- related payload bytes remain omitted from attachment summaries and review rows.

## Red-First Evidence

Before the patch, a direct probe against `/RF << /F [(style.css) 6 0 R] >>` produced one related row with `related_file_index=1` and no `related_filename`, because the filename string was skipped and the stream reference inherited the raw array offset.

## Focused Verification

```bash
php -l lanes/markerpdf/src/PdfAttachmentExtractor.php && php -l lanes/markerpdf/src/PdfEmbeddedFileExtractor.php && php -l lanes/markerpdf/tests/PdfAttachmentRelatedFileNamePairBoundaryCurrentBaseTest.php && php -l lanes/markerpdf/examples/wordpress-pdf-attachments-preflight.php
```

Result: all four files reported no syntax errors.

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentRelatedFileNamePairBoundaryCurrentBaseTest.php
```

Result: `1 test files, 72 assertions, 0 failures`.

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentExtractorTest.php lanes/markerpdf/tests/PdfEmbeddedFileExtractorTest.php lanes/markerpdf/tests/PdfAttachmentFileSpecAssociatedAFRelationshipChecksumCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentRelatedFileNamePairBoundaryCurrentBaseTest.php
```

Result: `4 test files, 744 assertions, 0 failures`.

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormResourceActionFileSpecCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentExtractorTest.php lanes/markerpdf/tests/PdfAttachmentFileSpecAssociatedAFRelationshipChecksumCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentRelatedFileNamePairBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfEmbeddedFileExtractorTest.php lanes/markerpdf/tests/PdfMetadataAssociatedFileOutputIntentEncryptXmpCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataAssociatedFileSchemaCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataAssociatedPieceInfoOutputIntentCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataNameTreePieceInfoOutputIntentCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataPdfaAssociatedNameTreeCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataPieceInfoAssociatedXmpCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataPortfolioAssociatedPieceInfoChecksumCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataPortfolioPieceInfoOutputIntentCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataXmpNameTreeAssociatedSchemaCurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityAcroFormDssActionAttachmentBundleCurrentBaseTest.php
```

Result: `15 test files, 1308 assertions, 0 failures`.

```bash
php lanes/markerpdf/examples/wordpress-pdf-attachments-preflight.php
```

Result: emitted `attachment_count=4`, `related_file_name_pair_preflight=true`, `related_file_payload_omitted=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted platform FileSpec filename selection, `/AFRelationship` mapping, checksum review, catalog/page `/AF` mirror marking, `/Limits` pruning, terminal EOF object bounding, xref-selected attachment rows, Portfolio/PieceInfo/XMP/OutputIntent metadata, or existing bare `/RF` stream-list summaries. The new boundary is only related-file filename/stream pair parsing for FileSpec `/RF` dictionaries.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object/value parsers, EmbeddedFile stream decoding, checksum review, and existing WordPress attachment preflight smoke. GPU/model execution, PDFium rendering, live OCR, Surya/Texify/Torch model paths, and exact upstream model benchmark parity remain intentionally out of scope for this markerPDF no-GPU slice.
