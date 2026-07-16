# markerpdf encrypted permission Filter operand current-base

Micro-slice: `markerpdf-encrypted-permissions-preflight-current-base-20260606T044343Z`

## Source truth

- Upstream `sddai/markerPDF` remains pinned in the lane manifest at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- Upstream conversion relies on parser/PDFium/pdftext extracted content before OCR/layout/model work. In this native no-GPU PHP lane, encrypted PDF admission is a parser/security preflight boundary before text is exposed to WordPress import.
- PDF Standard security-handler `/Filter` is a PDF name object. Literal or hex strings spelling `Standard` are malformed security-handler parameters and must not make decoded `/P` permission bits reliable.

## Behavior

- `PdfMetadataExtractor::standardSecurityHandlerParameterEntryStatus()` now treats only name operands as well-formed `/Filter` parameter entries.
- Literal-string and hex-string `/Filter` operands are still recognized as `Standard` for review context, but Standard parameter review reports:
  - `malformed_parameter_names=["Filter"]`
  - `standard_security_handler_filter_non_name_operand_review`
  - `malformed_standard_security_handler_parameter_entries`
- `PdfSecurityPreflight` therefore fails closed with `permissions_malformed_blocked_without_decryption`, clears trusted permission bits, sets `copy_or_extract_allowed=null`, and keeps encrypted text and authentication material out of visible WordPress output.

## Evidence

Red before source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionFilterOperandCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL fails closed when Standard Filter is a literal string operand
FAIL fails closed when Standard Filter is a hex string operand
1 test files, 6 assertions, 2 failures
```

Focused after implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionFilterOperandCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS fails closed when Standard Filter is a literal string operand
PASS fails closed when Standard Filter is a hex string operand
1 test files, 164 assertions, 0 failures
```

Adjacent encrypted-permission/security family:

```text
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg 'PdfEncryptedPermission.*CurrentBaseTest\.php$' | sort) lanes/markerpdf/tests/PdfEncryptedPermissionsPreflightCurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityPreflightTest.php
41 test files, 3853 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-encrypted-filter-operand-currentbase.php
```

Expected smoke flags include `text_blocked=true`, `permission_policy=permissions_malformed_blocked_without_decryption`, `malformed_parameter_names=["Filter"]`, `filter_operand_shapes=["literal_string"]`, `permission_bits_reliable=false`, `copy_or_extract_allowed=null`, `raw_auth_material_exposed=false`, `executes_decryption=false`, `executes_permission_enforcement=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-overlap

This does not repeat accepted unsigned/plus `/P` normalization, direct/composite/unresolved/out-of-range `/P` operands, duplicate `/P`, reserved-bit review, missing `/P`, duplicate Standard parameters, malformed `/Length` operands, Standard version/revision mismatch, V1/V2/V5 key-length defaults, authentication material readiness, duplicate auth material, permission digest operands, crypt-filter dictionary/role/default/AuthEvent/key-length/method-generation checks, public-key recipient envelopes, duplicate trailer `/Encrypt`, encrypted associated-file redaction, signature/DSS/DocMDP review, OCR/model execution, PDFium rendering, or external PDF tooling.

The bounded behavior is specifically malformed Standard `/Filter` operand shape before WordPress import permission reliance.

## Dependency closure

No new support component is needed. This reuses the native PDF object scanner, token-aware dictionary value parser, Standard encryption metadata extraction, permission preflight, encrypted-text guard, and WordPress smoke renderer. Full Standard password validation, decryption, `/Perms` authentication, public-key CMS decoding, permission enforcement, live OCR, Surya/Texify/Torch model execution, PDFium rendering, Streamlit/FastAPI workers, and external PDF tools remain intentionally out of scope under the current no-GPU markerPDF direction.
