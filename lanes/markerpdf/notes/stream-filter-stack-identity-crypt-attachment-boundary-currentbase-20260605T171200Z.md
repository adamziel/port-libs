# markerPDF attachment stream-filter stack identity-Crypt boundary

Micro-slice: `markerpdf-stream-filter-stack-boundary-current-base-20260605T171200Z`

Base accepted HEAD: `653192ad10f457ea19611d2f9d5658960027a3aa`

## Source truth

- Upstream `sddai/markerPDF` is pinned in the lane manifest at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- Upstream markerPDF delegates searchable PDF parsing to `pdftext`/PDFium before OCR/model stages. Under the current no-GPU scope, this lane owns the native PHP PDF parser boundary for stream filters, embedded files, and WordPress attachment review.
- PDF stream filter stacks can include `/Crypt`; the identity crypt filter is byte-preserving, while named private crypt filters require decryption support and must fail closed in this no-decryption native boundary.

## Behavior

- `PdfAttachmentExtractor` now accepts `/Crypt` as a stream-filter stack stage only when DecodeParms are omitted, empty, or name `/Identity`.
- `PdfEmbeddedFileExtractor` now applies the same identity-only Crypt pass-through before subsequent ASCII85/Flate attachment payload decoding.
- `/Crypt` with `/Name /PrivateCF` remains unsupported and suppresses that attachment row instead of exposing raw encrypted or undecoded bytes.

## Evidence

Red-first before source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentStreamFilterStackBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL treats Identity Crypt as a byte-preserving attachment stream stack stage while rejecting private crypt filters
Expected: 1
Actual: 0
1 test files, 1 assertions, 1 failures
```

Focused after source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentStreamFilterStackBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS treats Identity Crypt as a byte-preserving attachment stream stack stage while rejecting private crypt filters
1 test files, 35 assertions, 0 failures
```

Adjacent stream-filter and encryption family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentStreamFilterStackBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentStreamFilterPredictorCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentEncryptedEmbeddedFileBoundaryCurrentBaseTest.php
4 test files, 451 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-attachment-stream-filter-stack-boundary-currentbase.php
```

The smoke emits `filters=["Crypt","ASCII85Decode","FlateDecode"]`, `identity_crypt_stage_applied=true`, `private_crypt_payload_suppressed=true`, `payload_bytes_omitted_from_summary=true`, `payload_content_exposed=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-overlap

This does not repeat accepted text-stream stack recovery, Flate/ASCII85/ASCIIHex/RunLength attachment decoding, Flate predictor DecodeParms attachment decoding, encrypted EFF document-level suppression, or private attachment payload exposure review. The bounded behavior is only identity `/Crypt` as a byte-preserving stage inside embedded-file stream-filter stacks before checksum and declared-size review.

## Dependency closure

No new support component is needed. This reuses the native PHP PDF object parser, stream filter stack decoders, attachment summary path, embedded-file extractor, and WordPress smoke pattern. Non-identity crypt filters, Standard security-handler decryption, public-key decryption, model/OCR execution, PDFium rendering, and external PDF tools remain outside the current no-GPU/no-decryption markerPDF scope.
