# Encrypted Related Attachment Boundary

Micro-slice: `markerpdf-embedded-files-attachments-boundary-current-base-20260605T100752Z`

Accepted base: `f59109b94a1cc6b17840cbe8f0d7e8d59419aa53`

## Source Truth

Upstream `sddai/markerPDF` at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` keeps searchable PDF text extraction on the parser/page-text path before model fallback. In the current no-GPU PHP lane, embedded files and FileSpec dictionaries are native parser preflight metadata for WordPress review, not visible Gutenberg text and not model input.

PDF FileSpec dictionaries can contain `/EF` for the primary embedded stream and `/RF` for related embedded-file streams. PDF crypt-filter encryption separates ordinary streams (`/StmF`), strings (`/StrF`), and embedded-file streams (`/EFF`). When `/EFF` selects an encrypted crypt filter, both primary `/EF` and related `/RF` embedded-file payloads must stay review-only without decoding, hashing, or exposing raw encrypted bytes.

## Behavior

`PdfAttachmentExtractor` now passes the selected encrypted-attachment policy into FileSpec `/RF` related-file parsing. If `/EFF` suppresses embedded-file streams, related-file rows are retained with:

- `source=filespec_related_files`
- `rf_key`, `related_file_index`, `stream_object_id`
- related filename pairs when FileSpec strings are clear
- `encrypted_payload_suppressed=true`
- shared `encryption_policy`
- no payload bytes, size, hash, checksum, content type, filter list, or decoded dates

This keeps WordPress import review aware that encrypted sidecars exist while preserving the existing fail-closed no-decryption/no-payload boundary.

## Verification

Focused encrypted related-file slice:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentEncryptedRelatedFileBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS keeps encrypted FileSpec related-file rows as review-only metadata without decoding payloads

1 test files, 74 assertions, 0 failures
```

Adjacent attachment family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachment*Test.php
Focused test run: 13 selected test files (root lock skipped)
36 PASS cases
13 test files, 1043 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-attachment-encrypted-related-files-currentbase.php
```

Result: exits 0 and emits `attachment_count=1`, `total_bytes=0`, `main_payload_suppressed=true`, `related_file_count=2`, `related_stream_object_ids=[6,7]`, `related_payloads_suppressed=[true,true]`, `payload_content_exposed=false`, `executes_decryption=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted encrypted `/EFF` primary attachment redaction, default `/EFF` crypt-filter role metadata, catalog/page `/AF` extraction, FileAttachment annotation mirrors, clear related-file `/RF` summaries, object-stream FileSpec repair, generation repair, name-tree `/Limits`, EOF bounds, xref selection, platform `/EF` key selection, or fallback visible-text payload exclusion.

The bounded behavior is only encrypted FileSpec `/RF` related embedded-file rows in the lightweight WordPress attachment preflight path.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP PDF object parser, selected trailer encryption policy, FileSpec `/EF` and `/RF` walkers, related-file name-pair review, and WordPress smoke pattern. Full upstream model parity remains intentionally out of scope: no live OCR, PDFium/pdftext execution, Surya/Torch, Texify, table models, decryption, Streamlit/FastAPI workers, or external PDF tools were run.
