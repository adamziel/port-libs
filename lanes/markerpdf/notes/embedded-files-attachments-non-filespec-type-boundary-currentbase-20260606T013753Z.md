# markerPDF Attachment Non-FileSpec Type Boundary Current Base

Session: `port-dev-markerpdf-attachments-20260606T013753Z`
Micro-slice: `markerpdf-embedded-files-attachments-boundary-current-base-20260606T013753Z`
Base accepted HEAD: `a81844785028d1e754b06f6a3382bda72627fbd0`

## Source Truth

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text through `marker/pdf/extract_text.py`, delegating low-level searchable-PDF parsing to `pdftext.dictionary_output()`/PDFium page text before OCR/model fallback: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- The PDF file-attachment model uses FileSpec dictionaries to refer to embedded-file streams through `/EF`; pikepdf documents this as an attached file specification and Adobe's PDF Reference describes embedded files through the file-specification `/EF` entry: https://pikepdf.readthedocs.io/en/latest/topics/attachments.html and https://opensource.adobe.com/dc-acrobat-sdk-docs/pdfstandards/pdfreference1.5_v6.pdf

## Behavior

`PdfAttachmentExtractor` and `PdfEmbeddedFileExtractor` now reject typed dictionaries whose `/Type` is present and not `/Filespec` before trusting `/EF` entries.

The fixture covers three attachment slots:

- `/Names /EmbeddedFiles` pointing at a typed `/Page` dictionary with `/EF`.
- `/Names /EmbeddedFiles` containing a direct typed `/Catalog` dictionary with `/EF`.
- catalog `/AF` pointing at a typed `/Catalog` dictionary with `/EF`.

All three decoys are rejected. A legacy untyped FileSpec dictionary remains accepted, preserving older producer output that omits `/Type` while still carrying `/F`, `/AFRelationship`, and `/EF`.

## Red/Green Evidence

Red-first focused run:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentNonFileSpecTypeBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL fails closed on typed non-FileSpec dictionaries before WordPress attachment import
Values are not identical
Expected: 1
Actual: 4

1 test files, 1 assertions, 1 failures
```

After patch:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentNonFileSpecTypeBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS fails closed on typed non-FileSpec dictionaries before WordPress attachment import

1 test files, 42 assertions, 0 failures
```

Attachment-family sweep:

```text
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg '/Pdf(Attachment|EmbeddedFile).*Test\.php$' | sort)
Focused test run: 33 selected test files (root lock skipped)
...
33 test files, 2580 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-attachment-non-filespec-type-boundary-currentbase.php
```

Emits `attachment_count=1`, `embedded_file_count=1`, `typed_page_decoy_rejected=true`, `typed_catalog_decoy_rejected=true`, `untyped_legacy_filespec_preserved=true`, `payload_omitted_from_summary=true`, `visible_text_preserved=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Focused delta:

- `PdfAttachmentNonFileSpecTypeBoundaryCurrentBaseTest.php`: +1 PASS case, 42 assertions.
- `lane-status.json`: `phpPass 2321 -> 2322`, `wordpressScenarios 1991 -> 1992`.

## Non-Overlap

This does not repeat accepted EmbeddedFiles name-tree extraction, name-tree `/Limits` pruning, duplicate FileSpec key handling, escaped duplicate-key handling, platform `/EF` key selection, catalog/page `/AF` ingestion and mirror marking, FileAttachment annotation presentation metadata, object-stream attachment selection, generation/xref attachment repair, encrypted EFF redaction, related-file `/RF` summaries, portfolio `/Collection`, PieceInfo, XMP/OutputIntent, or attachment stream-filter work.

The bounded behavior is only typed non-FileSpec dictionary admission for FileSpec attachment slots.

## Dependency Closure

No new support component is needed. The patch reuses the native PHP PDF object scanner, parsed value model, raw dictionary parser, FileSpec filename selection, embedded-file stream decoding, checksum review, visible-text extractor, and WordPress smoke path. GPU/model execution, live OCR, Surya/Texify/Torch, PDFium rendering, Streamlit/FastAPI model workers, online services, and exact upstream model benchmark parity remain intentionally out of scope under the no-GPU markerPDF direction.
