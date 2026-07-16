# markerPDF encrypted permission selected duplicate operand current-base slice

Micro-slice: `markerpdf-encrypted-permissions-preflight-current-base-20260608T065516Z`
Session: `port-dev-markerpdf-encrypted-preflight-20260608T065516Z`
Base accepted HEAD: `020e2ea23f5994952f6082bab5de6c073c83d6be`

## Source truth

Upstream `sddai/markerPDF` is pinned in the manifest at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`. Its searchable-PDF path relies on parser-backed text extraction before OCR/model stages. In this no-GPU PHP lane, encrypted PDF handling is a native security preflight boundary: record encryption/permission metadata, block visible encrypted text before decryption, and do not validate passwords, decrypt streams/strings, enforce permissions, execute PDF actions, run Python/model workers, or call external PDF tools.

## Behavior

PDF dictionaries may contain duplicate top-level Standard `/P` permission words. A stale earlier `/P` can also be malformed, for example a valid integer followed by a trailing indirect operand before a later selected `/P`.

`PdfMetadataExtractor::standardPermissionWordDeclarationReview()` now exposes the same aggregate review shape already used by neighboring Standard parameter reviews:

- selected-entry single/trailing state and trailing operand details;
- unique entry operand and raw operand shapes;
- malformed duplicate entry count, indexes, and statuses.

The import decision is unchanged. Duplicate `/P` entries still make permission bits unreliable, keep `copy_or_extract_allowed` null, block native text extraction until decryption/password support exists, and expose selected permission data only as review metadata.

## Verification

Red-first before implementation:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionSelectedDuplicateOperandBoundaryCurrentBaseTest.php
```

Failed with `1 test files, 32 assertions, 1 failures` because `selected_entry_single_value` was missing.

Passing after implementation:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionSelectedDuplicateOperandBoundaryCurrentBaseTest.php
```

Passed with `1 test files, 72 assertions, 0 failures`.

Adjacent encrypted-permission family:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermission*.php lanes/markerpdf/tests/PdfSecurityPreflightTest.php
```

Passed with `62 test files, 6141 assertions, 0 failures`.

WordPress smoke:

```bash
php lanes/markerpdf/examples/wordpress-pdf-encrypted-selected-duplicate-permission-operand-currentbase.php
```

Exited `0` and reported `selected_entry_index=1`, `selected_permission_hex=FFFFFFD4`, `malformed_entry_count=1`, `malformed_entry_indexes=[0]`, `policy=permissions_malformed_blocked_without_decryption`, `permission_bits_reliable=false`, and all decryption/model/external-tool flags false.

Root harness: not run - isolated micro-slice.

## Non-overlap

This does not repeat accepted encrypted text blocking, simple Standard permission-bit decoding, unsigned `/P` normalization, duplicate well-formed `/P` selected-entry provenance, direct or indirect trailing single `/P` operands, duplicate `/Encrypt`, generation-exact `/Encrypt`, duplicate Standard handler parameters, AES-256 length validation, crypt-filter method/AuthEvent fail-closed behavior, public-key recipient envelopes, signature/DSS review, or attachment EFF encryption policy. The new boundary is specifically aggregate review metadata for stale malformed duplicate `/P` operands while preserving later selected-entry provenance and fail-closed duplicate-permission policy.

## Dependency closure

No new support component is needed. This reuses the native PHP PDF dictionary scanner, Standard permission-word decoder, metadata extractor, security preflight, text extractor, and WordPress smoke path. Live OCR, Surya/Texify/Torch model execution, pypdfium/PDFium, PIL, Streamlit/FastAPI model workers, JavaScript/PDF action execution, and external PDF tools remain intentionally out of scope under the current no-GPU markerPDF directive.
