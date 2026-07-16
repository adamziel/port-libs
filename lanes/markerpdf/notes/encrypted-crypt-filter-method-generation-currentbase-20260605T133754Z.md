# markerPDF encrypted crypt-filter method-generation preflight current-base

Micro-slice: `markerpdf-encrypted-permissions-preflight-current-base-20260605T133754Z`

Base accepted HEAD: `d93cb59e263d5bec6bba4ac974f8dbb66ee5ed6a`

## Source truth

Upstream `sddai/markerPDF` remains pinned at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`. Upstream conversion consumes low-level `pdftext` / PDF parser output before OCR, layout, table, and model stages, so encrypted-document admission is a native parser/security preflight boundary for this no-GPU PHP lane.

The PDF Standard security handler ties selected crypt-filter methods to handler generation: revision 4 crypt-filter dictionaries use legacy document methods such as `V2` / `AESV2`, while revision 5/6 AES-256 dictionaries use `AESV3`. Permission bits can still be syntactically decoded for review, but selected document streams/strings with a method incompatible with the Standard generation must fail closed before WordPress text import.

## Behavior

`PdfSecurityPreflight` now adds method-generation review to each selected crypt-filter content role:

- Standard AES-256 (`/V 5 /R 5|6`) document streams or strings selecting `V2` / `AESV2` fail closed with `standard_aes256_requires_aesv3_crypt_filter_review`.
- Standard revision 4 (`/V 4 /R 4`) document streams or strings selecting `AESV3` fail closed with `standard_revision4_disallows_aesv3_crypt_filter_review`.
- Identity / None filters remain review-compatible, and already-supported method, key-length, AuthEvent, role-declaration, and unsupported-filter checks keep their existing precedence.

When copy/extract permission bits are otherwise allowed, the permission preflight now reports `copy_extract_allowed_but_crypt_filter_preflight_blocked` and `blocked_by_incompatible_document_crypt_filter_method`. It still performs no password validation, decryption, permission enforcement, Python/model work, or external PDF tooling.

## Red-first evidence

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionCryptFilterMethodGenerationCurrentBaseTest.php
```

Before the source change:

```text
1 test files, 6 assertions, 2 failures
```

The failures showed AES-256 + legacy `AESV2` filters returning `copy_or_extract_allowed_but_decryption_required`, and revision-4 + `AESV3` returning `review_only_encrypted_document_boundary`.

## Focused verification

Focused test:

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionCryptFilterMethodGenerationCurrentBaseTest.php
```

Result:

```text
1 test files, 71 assertions, 0 failures
```

Adjacent encrypted/security sweep:

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermission*.php lanes/markerpdf/tests/PdfEncryptedPermissionsPreflightCurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityPreflightTest.php lanes/markerpdf/tests/PdfSecurityPublicKeyPermissionCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataAssociatedFileOutputIntentEncryptXmpCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefTrailerEncryptPrevCurrentBaseTest.php lanes/markerpdf/tests/PdfParserTrailerEncryptIdPrecedenceCurrentBaseTest.php
```

Result:

```text
31 test files, 2911 assertions, 0 failures
```

Shared metadata extractor check:

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php
```

Result:

```text
1 test files, 862 assertions, 0 failures
```

WordPress smoke:

```sh
php lanes/markerpdf/examples/wordpress-pdf-encrypted-crypt-filter-method-generation-currentbase.php
```

Result: emitted `content_extraction_boundary=blocked_by_incompatible_document_crypt_filter_method`, `crypt_filter_text_policy=crypt_filter_method_generation_mismatch_fail_closed`, `method_generation_fail_closed_role_names=["document_streams","document_strings"]`, `method_generation_fail_closed_filter_names=["LegacyDoc"]`, `raw_auth_material_exposed=false`, `executes_decryption=false`, `executes_permission_enforcement=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-overlap

This does not repeat accepted encrypted fail-closed text extraction, Standard `/V` / `/R` / top-level `/Length` parameter health, missing or duplicate `/P`, permission-word token/composite/range handling, unsigned/plus integer normalization, authentication-material readiness, duplicate auth material, AuthEvent mismatch/defaulting, invalid crypt-filter key length, omitted `/CFM` defaulting, unsupported/unknown crypt-filter methods, duplicate content-role declarations, public-key recipient envelopes, encrypted associated-file redaction, xref `/Encrypt` precedence, DSS/signature review, or table/source-boundary slices. The bounded behavior is only Standard handler generation compatibility for otherwise supported selected crypt-filter methods.

## Dependency closure

No new support component is needed. This reuses native PDF dictionary parsing, indirect scalar/name resolution, encryption metadata extraction, crypt-filter content-role review, text-extraction encryption blocking, and the existing WordPress security preflight. Full decryption/password validation, permission enforcement, live OCR, Surya/Texify/Torch model execution, PDFium rendering, and external PDF tools remain intentionally out of scope under the current no-GPU markerPDF lane.
