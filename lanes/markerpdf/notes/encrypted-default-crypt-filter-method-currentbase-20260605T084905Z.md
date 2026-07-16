# markerPDF encrypted default crypt-filter method preflight current-base

Micro-slice: `markerpdf-encrypted-permissions-preflight-current-base-20260605T084905Z`

Base accepted HEAD: `980ef492bfe4c1ebea9d77eeee80c623451a7e76`

## Source truth

Upstream `sddai/markerPDF` remains pinned at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`. The no-GPU PHP lane maps searchable-PDF parser and security preflight behavior before OCR/layout/model stages.

PDF crypt-filter dictionaries define `/CFM` as optional with a `/None` default in the PDF 1.7 reference. This slice applies that default only when `/CFM` is absent; explicitly malformed `/CFM` operands still remain unknown/unsupported fail-closed review metadata.

Source references:

- https://opensource.adobe.com/dc-acrobat-sdk-docs/pdfstandards/pdfreference1.7old.pdf
- https://www.verypdf.com/document/pdf-format-reference/txtidx0116.htm

## Behavior

- `PdfMetadataExtractor` now materializes omitted crypt-filter `/CFM` as `method=None`, `cfm_defaulted=true`, and `cfm_source=pdf_default_none`.
- `PdfSecurityPreflight` now exposes defaulted `/CFM` role/filter names in `crypt_filter_content_review` and treats those roles as identity crypt filters under the existing review-only encrypted-document boundary.
- Explicit malformed `/CFM []` operands still parse as unknown method fail-closed, preserving unsupported-crypt-filter coverage.
- Encrypted page text remains blocked, owner/user key material is not exposed, FileSpec strings selected by encrypted string filters stay redacted, and clear embedded-file payload fingerprints stay review-only metadata.

## Red-first evidence

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionDefaultCryptFilterMethodCurrentBaseTest.php
```

Before the source change:

```text
1 test files, 3 assertions, 1 failures
```

The failure showed omitted `/CFM` producing `copy_or_extract_allowed_but_crypt_filter_fail_closed` instead of the expected default `/None` review boundary.

## Focused verification

Focused test:

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionDefaultCryptFilterMethodCurrentBaseTest.php
```

Result:

```text
1 test files, 70 assertions, 0 failures
```

Adjusted unsupported-filter regression:

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionUnsupportedCryptFilterCurrentBaseTest.php
```

Result:

```text
1 test files, 62 assertions, 0 failures
```

Encrypted/security family:

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermission*.php lanes/markerpdf/tests/PdfEncryptedPermissionsPreflightCurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityPreflightTest.php lanes/markerpdf/tests/PdfSecurityPublicKeyPermissionCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataAssociatedFileOutputIntentEncryptXmpCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefTrailerEncryptPrevCurrentBaseTest.php lanes/markerpdf/tests/PdfParserTrailerEncryptIdPrecedenceCurrentBaseTest.php
```

Result:

```text
23 test files, 2188 assertions, 0 failures
```

Shared metadata extractor check:

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php
```

Result:

```text
1 test files, 865 assertions, 0 failures
```

WordPress smokes:

```sh
php lanes/markerpdf/examples/wordpress-pdf-encrypted-default-crypt-filter-method-currentbase.php
php lanes/markerpdf/examples/wordpress-pdf-encrypted-unsupported-crypt-filter-currentbase.php
```

The default-`/CFM` smoke emitted `stream_filter_method=None`, `stream_filter_cfm_defaulted=true`, `embedded_filter_method=None`, `embedded_filter_cfm_defaulted=true`, `encrypted_text_blocked=true`, `file_spec_strings_redacted=true`, `payload_hash_available=true`, `raw_key_material_exposed=false`, and all decryption/permission-enforcement/model/external-tool flags false.

PHP lint passed for:

```sh
php -l lanes/markerpdf/src/PdfMetadataExtractor.php
php -l lanes/markerpdf/src/PdfSecurityPreflight.php
php -l lanes/markerpdf/tests/PdfEncryptedPermissionDefaultCryptFilterMethodCurrentBaseTest.php
php -l lanes/markerpdf/tests/PdfEncryptedPermissionUnsupportedCryptFilterCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-encrypted-default-crypt-filter-method-currentbase.php
php -l lanes/markerpdf/examples/wordpress-pdf-encrypted-unsupported-crypt-filter-currentbase.php
```

## Non-overlap

This does not repeat accepted encrypted fail-closed text extraction, explicit `/CFM /None`, default `/StmF`/`/StrF`/`/EFF` role selection, invalid crypt-filter key lengths, AuthEvent default/mismatch review, unsupported crypt-filter methods, public-key recipient envelopes, Standard `/P` token/range/parameter/authentication trust review, xref `/Encrypt` precedence, encrypted associated-file redaction for explicit filters, or signature ByteRange/DSS/DocMDP review. The bounded behavior is only omitted `/CFM` method defaulting inside selected crypt-filter dictionaries.

## Dependency closure

No new support component is needed. This reuses native PDF dictionary parsing, encryption metadata extraction, crypt-filter preflight review, encrypted associated-file redaction, and WordPress smoke rendering. Full decryption/password validation, permission enforcement, live OCR, Surya/Texify/Torch model execution, PDFium rendering, and external PDF tools remain intentionally out of scope under the current no-GPU markerPDF lane.
