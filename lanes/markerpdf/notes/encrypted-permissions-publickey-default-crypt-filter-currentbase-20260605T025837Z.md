# markerpdf encrypted permissions public-key default crypt-filter preflight current base

Micro-slice: `markerpdf-encrypted-permissions-preflight-current-base-20260605T025837Z`

## Source truth

- Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` delegates searchable-PDF text extraction to parser-backed PDF text before OCR/model stages. This native no-GPU lane keeps encrypted content fail-closed unless a future decryption component is explicitly available.
- PDF crypt-filter security dictionaries default omitted `/StmF` and `/StrF` to `Identity`; omitted `/EFF` inherits the stream crypt filter selected by `/StmF`.
- Public-key `adbe.pkcs7.s5` permissions are carried in recipient envelopes on the crypt filters selected for document streams, strings, and embedded-file streams. The PHP lane inventories and hashes those envelopes but does not parse CMS, decrypt, validate passwords, or enforce permissions.

## Implementation

- `PdfMetadataExtractor::publicKeyRecipientReview()` now receives defaulted encryption metadata.
- `publicKeyRecipientCryptFilterSelection()` now includes defaulted crypt-filter roles, so omitted `/EFF` appears as `embedded_file_filter` with source `pdf_default_stream_filter`.
- Recipient envelopes remain deduplicated by crypt-filter name. If `/StmF`, `/StrF`, and defaulted `/EFF` all select the same public-key filter, the selected recipient count is not inflated.
- Selection rows now expose `filter_source`, `filter_defaulted`, `defaulted_content_filters`, and `content_filter_sources` as review metadata only.

## Focused test and smoke evidence

Red-first:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionPublicKeyDefaultCryptFilterCurrentBaseTest.php
```

Failed before implementation after 25 assertions: expected `embedded_file_filter => DefaultCryptFilter` in public-key recipient selection; actual selection only contained `stream_filter` and `string_filter`.

After implementation:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionPublicKeyDefaultCryptFilterCurrentBaseTest.php
```

Passed with `1 test files, 44 assertions, 0 failures`.

Adjacent encrypted permission/security family:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfSecurityPreflightTest.php lanes/markerpdf/tests/PdfSecurityPublicKeyPermissionCurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityPublicKeyDssPermissionBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionIndirectOperandsCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionDefaultCryptFilterCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionCryptFilterPreflightCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionPublicKeyDefaultCryptFilterCurrentBaseTest.php
```

Passed with `7 test files, 855 assertions, 0 failures`.

WordPress smoke:

```bash
php lanes/markerpdf/examples/wordpress-pdf-publickey-default-crypt-filter-permission-currentbase.php
```

Reports encrypted text blocked, permission policy `public_key_recipient_permissions_blocked_without_private_key`, defaulted `embedded_file_filter => DefaultCryptFilter`, selected recipient count `2`, raw recipient material not exposed, and no CMS parsing, decryption, permission enforcement, Python/model execution, or external PDF tools.

## Non-overlap

This does not repeat accepted encrypted fail-closed extraction, direct or unsigned Standard `/P` permission review, duplicate `/P` handling, indirect encryption operands, Standard authentication material readiness, explicit `/EFF` crypt-filter review, default `/EFF` associated-file redaction, unsupported crypt-filter method fail-closed aggregation, public-key top-level recipient review, public-key DSS permission boundaries, xref `/Prev` Encrypt inheritance, or signature ByteRange/DSS/DocMDP/FieldMDP review. The bounded behavior is specifically defaulted `/EFF` participation in public-key crypt-filter recipient permission selection.

## Dependency closure

No new support component is needed. This reuses the native PDF object scanner, encryption metadata extractor, crypt-filter default materialization, public-key recipient envelope inventory, security preflight review, and WordPress smoke renderer. Full CMS recipient parsing, decryption/password validation, permission enforcement, live OCR, Surya/Texify/Torch models, PDFium rendering, and exact upstream model benchmark parity remain intentionally out of scope under the current no-GPU markerPDF directive.
