# markerPDF EmbeddedFiles Attachment Decoded-Length Boundary

Session: `port-dev-markerpdf-attachments-20260605T122914Z`
Micro-slice: `markerpdf-embedded-files-attachments-boundary-current-base-20260605T122914Z`
Base accepted HEAD: `77bfa9dd28a95036d98d3aece3867d3cc948ad95`

## Source Truth

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text through parser-backed pdftext/PDFium extraction. Embedded FileSpec payload bytes and related-file payloads are not visible paragraph text in that path.
- PDF EmbeddedFile stream dictionaries may carry `/DL` decoded-length metadata separately from FileSpec `/EF` references and embedded-file `/Params /Size`. WordPress import review should preserve that decoded-length claim and match state without treating it as visible text or replacing checksum/declared-size review.
- This slice stays inside the current native no-GPU markerPDF scope. It does not run live OCR, PDFium, Surya/Torch, Texify, table models, external PDF tools, model workers, attachment actions, or decryption.

## Implementation

`PdfAttachmentExtractor` now records stream-dictionary `/DL` as `decoded_length` with `decoded_length_matches` for:

- primary EmbeddedFiles/name-tree, catalog `/AF`, page `/AF`, and FileAttachment summary rows;
- FileSpec `/RF` related-file rows;
- clear identity-crypt-filter attachment rows while redacting `/DL` with other payload-derived metadata when `/EFF` suppresses embedded-file streams.

`PdfEmbeddedFileExtractor` now records the same `/DL` metadata on rich embedded-file rows and related-file review rows. Associated-file provenance payloads also include decoded-length match state, keeping `/DL` visible to import reviewers without promoting attachment bytes into document metadata roots or page text.

## Verification

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentDecodedLengthBoundaryCurrentBaseTest.php
```

Result:

```text
Focused test run: 1 selected test files (root lock skipped)
PASS carries EmbeddedFile stream DL decoded-length review metadata through attachment summaries

1 test files, 50 assertions, 0 failures
```

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentDecodedLengthBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentExtractorTest.php lanes/markerpdf/tests/PdfEmbeddedFileExtractorTest.php lanes/markerpdf/tests/PdfAttachmentEncryptedRelatedFileBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentFileSpecAssociatedAFRelationshipChecksumCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentRelatedFileNamePairBoundaryCurrentBaseTest.php
```

Result:

```text
6 test files, 1117 assertions, 0 failures
```

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachment*Test.php lanes/markerpdf/tests/PdfEmbeddedFile*Test.php
```

Result:

```text
18 test files, 1665 assertions, 0 failures
```

```bash
php lanes/markerpdf/examples/wordpress-pdf-attachment-decoded-length-boundary-currentbase.php
```

Result: emitted `attachment_count=1`, `decoded_length_matches=true`, `related_decoded_length_matches=false`, `provenance_decoded_length_matches=true`, `attachment_payload_omitted=true`, `related_payload_omitted=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted platform filename source selection, `/EF` key selection, `/AFRelationship` mapping, `/Params /Size`, checksum review, FileSpec `/FS`/`/ID`/`/V`, related-file filename pairs, encrypted `/EFF` payload suppression, catalog/page `/AF` mirror marking, FileAttachment annotation presentation/mirroring, EmbeddedFiles `/Limits` pruning, EOF/xref/object-stream attachment selection, portfolio `/Collection`, FileSpec `/CI`, PieceInfo/XMP/OutputIntent provenance, or fallback text payload exclusion.

The bounded behavior is only stream-dictionary `/DL` decoded-length review metadata for already-selected EmbeddedFile streams.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object/value parsers, FileSpec and EmbeddedFile stream selection, existing stream decoding, related-file walkers, checksum review, encrypted attachment redaction, provenance summaries, and WordPress smoke pattern. Full upstream runner parity remains dependency-gated by pdftext, pypdfium2/PDFium, Surya/Torch OCR/layout/table models, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external rendering/OCR helpers; none were executed for this bounded native PHP slice.
