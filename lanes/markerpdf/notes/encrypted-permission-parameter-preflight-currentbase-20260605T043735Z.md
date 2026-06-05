# markerPDF encrypted permission parameter preflight current-base

Micro-slice: `markerpdf-encrypted-permissions-preflight-current-base-20260605T043735Z`

Base accepted HEAD: `14aad85c2edfecb0743214dea60386dec4cd43bb`

## Source truth

Upstream `sddai/markerPDF` is still pinned at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`. The upstream conversion path consumes low-level `pdftext`/PDF parser output before OCR/layout/model stages, so encrypted-document admission is a native parser/security preflight boundary for this no-GPU PHP lane. The PDF Standard security handler uses `/V`, `/R`, and top-level `/Length` to define the encryption algorithm/revision and whether the `/P` permission word can be interpreted coherently.

## Behavior

`PdfMetadataExtractor` now records a review-only `standard_security_handler_parameter_review` for Standard encryption dictionaries. It checks whether `/V` and `/R` are present, supported, compatible, and whether top-level `/Length` is valid for the Standard handler generation. It never validates passwords, authenticates `/Perms`, decrypts content, enforces permissions, or exposes raw authentication bytes.

`PdfSecurityPreflight` now treats malformed Standard handler parameters as a fail-closed permission preflight source before trusting parsed permission bits. WordPress import review reports `standard_security_handler_malformed_parameters`, marks `permission_bits_reliable=false`, masks `copy_or_extract_allowed`, and keeps encrypted text blocked.

The adjacent revision-bit test fixture was corrected from `/V 1 /R 2 /Length 128` to the valid legacy `/Length 40` so it continues to cover revision-gated permission bits instead of the new malformed key-length boundary.

## Focused verification

Focused test:

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionsPreflightCurrentBaseTest.php
```

Result:

```text
1 test files, 184 assertions, 0 failures
```

Adjacent encrypted-permission/security sweep:

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermission*.php lanes/markerpdf/tests/PdfEncryptedPermissionsPreflightCurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityPreflightTest.php lanes/markerpdf/tests/PdfSecurityPublicKeyPermissionCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataAssociatedFileOutputIntentEncryptXmpCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefTrailerEncryptPrevCurrentBaseTest.php lanes/markerpdf/tests/PdfParserTrailerEncryptIdPrecedenceCurrentBaseTest.php
```

Result:

```text
17 test files, 1570 assertions, 0 failures
```

WordPress smoke:

```sh
php lanes/markerpdf/examples/wordpress-pdf-encrypted-permission-parameter-preflight-currentbase.php
```

Result: emitted `parameter_status=malformed_standard_security_handler_parameters_review`, `key_length_status=invalid_standard_security_handler_key_length_review`, `encrypted_text_blocked=true`, `permission_bits_reliable=false`, and all decryption/permission-enforcement/model/external-tool flags false.

## Non-overlap

This does not repeat accepted Standard permission bit decoding, unsigned permission-word normalization, duplicate `/P` handling, reserved-bit malformed review, public-key recipient permission envelopes, crypt-filter role/AuthEvent/key-length review, encrypted associated-file metadata redaction, xref `/Encrypt` precedence, or inline-image/parser/text extraction slices. The bounded behavior is only Standard security-handler `/V` `/R` top-level `/Length` parameter health before trusting encrypted permission bits.

## Dependency closure

No new support component is needed. This reuses native PDF dictionary parsing, indirect scalar resolution, metadata extraction, text-extraction encryption blocking, and the existing security preflight. Full upstream/model parity remains intentionally out of scope for this no-GPU markerPDF lane: no live OCR, Surya/Texify/Torch/model workers, PDFium rendering, or external PDF tools were run.
