# markerpdf encrypted escaped crypt-filter names current-base

## Source-truth boundary

- Upstream `sddai/markerPDF` routes searchable PDF extraction through parser/rendering paths before any OCR/model fallback. In this no-GPU PHP lane, encrypted PDF content remains blocked and review-only unless native decryption is explicitly activated.
- PDF name objects may contain `#xx` escapes. Encryption preflight must preserve decoded crypt-filter names selected by `/CF`, `/StmF`, `/StrF`, and `/EFF`, including public-key `/Recipients`, without exposing recipient envelope bytes or attempting CMS/decryption.

## Implementation

- `PdfSecurityPreflight::encryptionReview()` now surfaces crypt-filter names on the top-level encryption review:
  - `declared_crypt_filter_count`
  - `declared_crypt_filter_names`
  - `selected_crypt_filter_names`
  - `crypt_filter_dictionary_declared_filter_names`
  - `crypt_filter_dictionary_selected_filter_names`
- The metadata parser already decodes escaped PDF names in the underlying encryption dictionary and crypt-filter content review. This patch makes that decoded security summary directly available to WordPress import preflight callers.
- Added a public-key encrypted fixture with escaped `/Filter`, `/SubFilter`, `/CF`, `/CFM`, `/AuthEvent`, `/Length`, `/Recipients`, `/StmF`, `/StrF`, and `/EFF` names. The fixture selects `DefaultCryptFilter`, reports one selected recipient envelope by hash, blocks text extraction, and keeps recipient bytes out of serialized metadata/report output.

## Non-overlap

This does not repeat accepted Standard authentication escaped-key parsing, `/Perms` digest review, duplicate or trailing `/P`, duplicate `/CF`, duplicate crypt-filter role/parameter fail-closed handling, public-key legacy recipient-source suppression, default `/EFF` selection, malformed `/EFF` attachment payload redaction, or live CMS/PKCS#7/decryption behavior.

The bounded behavior here is only escaped crypt-filter name selection and top-level encrypted preflight summary projection.

## Evidence

Focused:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionEscapedCryptFilterNamesCurrentBaseTest.php
```

Result: `1 test files / 42 assertions / 0 failures`.

Adjacent encrypted-permission regression:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionEscapedAuthKeysCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionPublicKeyDefaultCryptFilterCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionCryptFilterPreflightCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionEscapedCryptFilterNamesCurrentBaseTest.php
```

Result: `4 test files / 188 assertions / 0 failures`.

WordPress smoke:

```bash
php lanes/markerpdf/examples/wordpress-pdf-encrypted-escaped-crypt-filter-names-currentbase.php
```

Result: exits 0 and emits `declared_crypt_filter_names=["DefaultCryptFilter"]`, `selected_crypt_filter_names=["DefaultCryptFilter"]`, `selected_public_key_recipient_count=1`, `raw_recipient_material_exposed=false`, `executes_cms_parse=false`, `executes_decryption=false`, `executes_permission_enforcement=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Dependency closure

No new support component is needed. This reuses the native PHP PDF object parser, encryption dictionary parser, crypt-filter content review, public-key recipient inventory, `PdfSecurityPreflight`, `PdfTextExtractor` encrypted-content short circuit, and the existing WordPress smoke pattern.

Full Standard security-handler decryption, password validation, public-key CMS/PKCS#7 permission decoding, permission enforcement, signature validation, live OCR, Surya/Texify/Torch model execution, PDFium rendering, and external PDF tools remain intentionally out of scope for this no-GPU native parser slice.
