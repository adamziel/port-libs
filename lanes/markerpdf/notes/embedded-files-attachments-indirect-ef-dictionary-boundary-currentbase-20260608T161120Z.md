# markerPDF Embedded Files Indirect EF Dictionary Boundary Current Base

Session: `port-dev-markerpdf-attachments-20260608T161120Z`
Micro-slice: `markerpdf-embedded-files-attachments-boundary-current-base-20260608T161120Z`
Base accepted HEAD: `5f385153306ae68f081cbb8d67375beb9645b190`

## Source Truth

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` keeps searchable PDF page text on the parser/PDFium-facing path before OCR/model fallback. Embedded-file payloads and FileSpec dictionaries are attachment review metadata for importers, not visible page text.

At the native PHP boundary, a FileSpec `/EF` value is a dictionary mapping platform keys such as `/F` and `/UF` to embedded-file streams. If `/EF` is indirect, the referenced object must resolve to one top-level dictionary. An object like `50 0 obj << /F 11 0 R >> 12 0 R endobj` is ambiguous: accepting the dictionary prefix would trust one stream while ignoring a tailed top-level operand.

## Implementation

- `PdfAttachmentExtractor::embeddedFileStreamReference()` now resolves `/EF` through an exact-dictionary guard for indirect references before selecting an embedded-file stream key.
- `PdfEmbeddedFileExtractor::embeddedFileFromFileSpecValue()` now uses an exact dictionary resolver for `/EF`, so the payload-returning inventory and lightweight attachment summary agree.
- Direct `/EF << /F 11 0 R 12 0 R >>` behavior remains covered by the existing dictionary-body trailing-operand guard.

## Evidence

Red-first focused run after adding the test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentIndirectEfDictionaryBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL rejects indirect FileSpec EF dictionary objects with trailing operands before WordPress attachment review (lanes/markerpdf/tests/PdfAttachmentIndirectEfDictionaryBoundaryCurrentBaseTest.php)
Values are not identical
Expected: 1
Actual: 2

1 test files, 1 assertions, 1 failures
```

Focused green after implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentIndirectEfDictionaryBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects indirect FileSpec EF dictionary objects with trailing operands before WordPress attachment review

1 test files, 63 assertions, 0 failures
```

Adjacent attachment and embedded-file family:

```text
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg '/Pdf(Attachment|EmbeddedFile|EmbeddedFiles).*Test\.php$' | sort)
Focused test run: 70 selected test files (root lock skipped)
70 test files, 5112 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-attachment-indirect-ef-dictionary-boundary-currentbase.php
```

The smoke exits `0` and emits `malformed_indirect_ef_dictionary_rejected=true`, `valid_indirect_ef_dictionary_preserved=true`, `payload_bytes_omitted_from_summary=true`, `payload_text_excluded_from_visible_text=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Status Delta

- PHP behavior tests: `3278 -> 3279`.
- WordPress scenarios: `2673 -> 2674`.
- Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted direct `/EF` dictionary trailing-operand rejection, duplicate FileSpec/EF keys, direct catalog/page `/AF` FileSpec duplicate-key handling, indirect `/AF` array exact-object rejection, indirect `/Names` or `/Kids` exact-array rejection, name-tree limits/order behavior, encrypted EFF redaction, related-file `/RF`, stream-filter decoding, object-stream/xref attachment selection, Portfolio/PieceInfo/XMP/OutputIntent metadata, annotation presentation, outline/navigation, image/font/CMap behavior, OCR, or supplied table/equation handoffs. The bounded behavior is only exact top-level dictionary resolution for indirect FileSpec `/EF` objects before attachment stream selection.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object/value parser, generation-aware object resolver, raw dictionary scanner, FileSpec parser, embedded-file stream decoder, attachment summary sanitizer, embedded-file inventory, and WordPress smoke pattern. Live OCR, Surya/Texify/Torch model execution, PDFium rendering, decryption, external PDF tools, media playback, Streamlit/FastAPI workers, and exact upstream model benchmark parity remain intentionally out of scope under the current no-GPU markerPDF direction.
