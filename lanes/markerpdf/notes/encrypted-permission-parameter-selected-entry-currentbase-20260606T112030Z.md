# markerPDF encrypted permission parameter selected-entry current-base

Micro-slice: `markerpdf-encrypted-permissions-preflight-current-base-20260606T112030Z`
Session: `port-dev-markerpdf-encrypted-preflight-20260606T112030Z`
Base accepted HEAD: `c8b7f20f2fd086ce67b2aa94b2b1421611b99f67`

## Source Truth

- Upstream `sddai/markerPDF` remains pinned at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- Its searchable-PDF path relies on parser-backed PDF text extraction before OCR/layout/model work.
- Under the current no-GPU markerPDF scope, encrypted PDFs are handled by native PHP security preflight: report encryption and permission metadata, block visible text before decryption, and do not validate passwords, enforce permissions, execute PDF actions, launch Python/model workers, or call external PDF tools.
- PDF dictionaries can contain duplicate top-level security-handler parameters. The lane already fails closed for duplicate Standard `/Length`; this slice makes the selected duplicate entry reviewable without trusting it.

## Behavior

`PdfMetadataExtractor::standardSecurityHandlerParameterDeclarationReview()` now records selected-entry provenance on Standard security-handler parameter declaration rows:

- `selected_entry_status`
- `selected_entry_operand_shape`
- `selected_entry_resolved`
- `selected_entry_integer`
- `selected_integer_value`
- `selected_name_value`

Each parameter entry also carries review-only `integer`, `integer_value`, and `name_value` fields. A duplicate `/Length 40 /Length 128` still fails closed as malformed Standard handler parameters, keeps `permission_bits_reliable=false`, leaves `copy_or_extract_allowed=null`, blocks text extraction without decryption, and redacts owner/user authentication material.

## Verification

Red-first before implementation:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionParameterSelectedEntryCurrentBaseTest.php
```

Failed as expected: `1 test files, 27 assertions, 1 failures`, with `selected_entry_status` missing.

Passing after implementation:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionParameterSelectedEntryCurrentBaseTest.php
```

Passed: `1 test files, 66 assertions, 0 failures`.

Adjacent duplicate-parameter focused check:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionDuplicateParameterCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionParameterSelectedEntryCurrentBaseTest.php
```

Passed: `2 test files, 118 assertions, 0 failures`.

Encrypted-permission/security family:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermission*.php lanes/markerpdf/tests/PdfSecurityPreflightTest.php
```

Passed: `45 test files, 4371 assertions, 0 failures`.

WordPress smoke:

```bash
php lanes/markerpdf/examples/wordpress-pdf-encrypted-permission-parameter-selected-entry-currentbase.php
```

Passed with `selected_length_entry_index=1`, `selected_length_entry_status=standard_security_handler_parameter_entry_well_formed`, `selected_length_integer_value=128`, `policy=permissions_malformed_blocked_without_decryption`, `copy_or_extract_allowed=null`, and all decryption/model/external-tool flags false.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat encrypted text blocking, Standard permission-bit decoding, missing `/P`, duplicate `/P` selected-entry provenance, unsigned/plus `/P` normalization, out-of-range `/P`, reserved-bit review, indirect/commented/composite `/P` operands, duplicate `/Encrypt`, generation-exact `/Encrypt`, duplicate Standard handler parameter fail-closed policy, malformed Standard parameter operands, public-key recipient permission envelopes, crypt-filter method/AuthEvent/Length fail-closed behavior, EncryptMetadata boundaries, or signature/DSS review. The bounded behavior is only selected-entry provenance for duplicate Standard security-handler parameters, proven with duplicate `/Length`.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF dictionary scanner, Standard security-handler parameter reviewer, Standard permission preflight, encrypted text guard, and WordPress smoke renderer. Full upstream parity remains gated by live `pdftext`, `pypdfium2`/PDFium, Surya/Torch/model downloads, tabled-pdf, Texify, Streamlit/FastAPI runtimes, benchmark workflow tooling, and external OCR/rendering helpers, all intentionally out of scope for this no-GPU slice.
