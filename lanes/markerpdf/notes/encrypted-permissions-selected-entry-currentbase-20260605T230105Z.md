# markerPDF encrypted permissions selected-entry current-base

Micro-slice: `markerpdf-encrypted-permissions-preflight-current-base-20260605T225719Z`
Session: `port-dev-markerpdf-encrypted-preflight-20260605T225719Z`
Base accepted HEAD: `d7b71434dec2c6a757eb3d2214aee89ec790a158`

## Source Truth

Upstream `sddai/markerPDF` remains pinned at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`. Its searchable-PDF path relies on pdftext/PDF parser extraction before model/OCR work. In the current no-GPU markerPDF scope, encrypted PDF handling is a native security preflight boundary: report encryption and permission metadata, block visible text before decryption, and do not validate passwords, enforce permissions, execute PDF actions, launch Python/model workers, or call external PDF tools.

## Behavior

PDF dictionaries can declare duplicate top-level `/P` Standard permission words. The existing preflight correctly failed closed and did not trust either value for import decisions, but the declaration review did not expose which top-level entry the parser selected for provenance. That made WordPress import review less inspectable when a later well-formed `/P` conflicted with an earlier denial.

`PdfMetadataExtractor::standardPermissionWordDeclarationReview()` now records the selected `/P` entry index, status, integer flag, signed value, unsigned value, and hex value. `PdfSecurityPreflight` continues to treat duplicate `/P` declarations as malformed, keeps `copy_or_extract_allowed` null, blocks text extraction without decryption, and exposes the selected entry only as review metadata.

## Verification

Red-first before implementation:

```bash
php -r 'require "tools/bootstrap.php"; ... duplicate-/P fixture ...'
```

Failed with `selected_entry_index missing`.

Passing after implementation:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionsPreflightCurrentBaseTest.php
```

Passed: 1 test file, 324 assertions, 0 failures.

Shared encrypted-permission/security guard:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermission*.php lanes/markerpdf/tests/PdfSecurityPreflightTest.php
```

Passed: 34 test files, 3196 assertions, 0 failures.

WordPress smoke:

```bash
php lanes/markerpdf/examples/wordpress-pdf-encrypted-duplicate-permission-preflight-currentbase.php
```

Passed with `selected_entry_index=1`, `selected_entry_status=well_formed_standard_permissions`, `selected_permission_hex=FFFFFFD4`, `policy=permissions_malformed_blocked_without_decryption`, `copy_or_extract_allowed=null`, and all decryption/model/external-tool flags false.

Syntax/diff checks:

```bash
php -l lanes/markerpdf/src/PdfMetadataExtractor.php
php -l lanes/markerpdf/tests/PdfEncryptedPermissionsPreflightCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-encrypted-duplicate-permission-preflight-currentbase.php
git diff --check -- lanes/markerpdf
```

Passed.

## Non-Overlap

This does not repeat encrypted text blocking, simple Standard permission-bit decoding, malformed reserved-bit review, unsigned `/P` normalization, indirect `/P` operands, duplicate `/Encrypt` handling, generation-exact `/Encrypt`, duplicate Standard handler parameters, public-key recipient permission envelopes, crypt-filter method/auth-event fail-closed behavior, or signature/DSS review. The new boundary is selected-entry provenance for duplicate Standard `/P` declarations while preserving fail-closed import policy.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF dictionary scanner, Standard permission-word decoder, metadata extractor, security preflight, text extractor, and WordPress smoke path. Full upstream parity remains gated by live `pdftext`, `pypdfium2`/PDFium, Surya/Torch/model downloads, tabled-pdf, Texify, Streamlit/FastAPI runtimes, benchmark workflow tooling, and external OCR/rendering helpers, all intentionally out of scope for this no-GPU slice.
