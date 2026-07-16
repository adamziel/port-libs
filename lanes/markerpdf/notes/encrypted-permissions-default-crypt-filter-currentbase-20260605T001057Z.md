# markerpdf encrypted permissions default crypt-filter preflight current base

Micro-slice: `markerpdf-encrypted-permissions-preflight-current-base-20260605T001057Z`

## Source truth

- Upstream `sddai/markerPDF` delegates PDF text/security boundary handling to the PDF parser stack before model and Markdown stages. This native PHP lane keeps encrypted searchable-PDF content fail-closed unless a future decryption component is explicitly available.
- PDF Reference 1.7 encryption dictionary behavior: for crypt-filter encryption dictionaries, `/StmF` and `/StrF` default to `Identity`, and if `/EFF` is omitted, embedded-file streams use the default stream crypt filter from `/StmF`.
  - https://opensource.adobe.com/dc-acrobat-sdk-docs/pdfstandards/pdfreference1.7old.pdf
  - https://www.verypdf.com/document/pdf-format-reference/txtidx0116.htm
  - https://printtechnologies.org/standards/files/pdf-reference-1.6-1.pdf

## Implementation

- `PdfMetadataExtractor` now materializes crypt-filter defaults for encryption versions 4 and 5:
  - missing `/StmF` -> `Identity`;
  - missing `/StrF` -> `Identity`;
  - missing `/EFF` -> inherited stream filter from `/StmF`.
- The defaulted metadata feeds both `PdfSecurityPreflight::crypt_filter_content_review()` and encrypted associated-file redaction, so WordPress import review correctly distinguishes encrypted FileSpec strings from embedded payload streams that are clear because `/EFF` inherited an identity `/StmF` filter.
- The slice remains preflight-only: it does not decrypt PDF text, validate passwords, enforce permissions, execute PDF actions, run Python/model code, or expose raw owner/user key material or embedded payload bytes.

## Focused test and smoke evidence

Red-first:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionDefaultCryptFilterCurrentBaseTest.php
```

Failed before the implementation with `Expected: 'ClearStreams' / Actual: NULL` for the omitted `/EFF` role, after 13 assertions.

After implementation:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionDefaultCryptFilterCurrentBaseTest.php
```

Passed with `1 test files, 64 assertions, 0 failures`.

Adjacent metadata/security regression set:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php lanes/markerpdf/tests/PdfEncryptedPermissionDefaultCryptFilterCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionCryptFilterNoneCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionCryptFilterPreflightCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionIndirectOperandsCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionUnsignedWordCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionsPreflightCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataAssociatedFileOutputIntentEncryptXmpCurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityPreflightTest.php
```

Passed with `9 test files, 1784 assertions, 0 failures`.

WordPress smoke:

```bash
php lanes/markerpdf/examples/wordpress-pdf-encrypted-default-crypt-filter-preflight-currentbase.php
```

Reported encrypted text blocked, permission policy `copy_extract_allowed_after_decryption`, `/EFF` defaulted from `/StmF` to `ClearStreams`, embedded-file payload policy `identity_filter_review_only_payload_boundary`, FileSpec strings redacted, payload hash available, payload content omitted, raw key material not exposed, and no Python/model/external PDF tool execution.

## Non-overlap

This does not repeat accepted encrypted fail-closed extraction, direct signed `/P -44` preflight, unsigned `/P` normalization, indirect encryption operand resolution, malformed reserved-bit review, unsupported handler review, Standard authentication digest hashing, public-key recipient permission review, xref `/Prev` Encrypt inheritance, explicit CFM `/None` handling, explicit `/EFF` crypt-filter content-role review, encrypted associated-file metadata redaction for explicitly encrypted filters, or signature ByteRange/DSS/DocMDP/FieldMDP review. The bounded behavior is specifically PDF default crypt-filter role resolution for omitted `/EFF`, plus `/StmF` and `/StrF` default metadata.

## Dependency closure

No new support component is needed. This reuses the native PDF object scanner, encryption metadata extractor, crypt-filter preflight review, attachment metadata redaction, and WordPress smoke renderer. Full decryption/password validation, permission enforcement, live OCR, Surya/Texify/Torch models, PDFium rendering, and exact upstream model benchmark parity remain intentionally out of scope for the current no-GPU markerPDF lane.
