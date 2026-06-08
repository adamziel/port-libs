# markerPDF encrypted indirect parameter trailing-operand current-base

Micro-slice: `markerpdf-encrypted-permissions-preflight-current-base-20260608T082217Z`

Base accepted HEAD: `6c29be4bda70f43b52fe8fb02b6dc807643e8db3`

## Source Truth

Upstream `sddai/markerPDF` delegates searchable PDF extraction to the PDF parser/PDFium/pdftext boundary before OCR/model stages. Under the current no-GPU markerPDF scope, encrypted PDFs stay preflight-only: review security metadata, block visible content until decryption support exists, and do not execute Python models, PDF actions, password validation, permission enforcement, or external PDF tools.

PDF dictionary scalar operands must resolve to one top-level PDF object. Standard security-handler `/V`, `/R`, and `/Length` values may be indirect, but an indirect object that starts with a scalar and then carries another top-level token is ambiguous security metadata and must fail closed before `/P` permission bits are trusted.

## Behavior

- `PdfMetadataExtractor` now checks resolved indirect Standard security-handler parameter values for single-token shape.
- Indirect `/V`, `/R`, and `/Length` objects such as `4 /ShadowVersion`, `4 10 0 R`, and `128 256` now produce `standard_security_handler_parameter_trailing_operand_review` rows.
- The parameter declaration review marks those rows as malformed, sets `fail_closed=true`, and reports malformed parameter names `V`, `R`, and `Length`.
- `PdfSecurityPreflight` keeps encrypted content blocked with `permissions_malformed_blocked_without_decryption`, reports `standard_security_handler_parameter_operands_malformed`, and does not trust otherwise copy-allowing `/P` bits.
- Added a WordPress smoke proving the same malformed indirect parameter objects are review-only metadata and do not leak page text or authentication bytes.

## Red-First Evidence

Before the source change, an ad hoc probe of the focused fixture showed indirect `/V`, `/R`, and `/Length` rows as `standard_security_handler_parameter_entry_well_formed`, with declaration status `well_formed_indirect_standard_security_handler_parameter_entries_review`. The broader policy was malformed only because integer extraction returned missing version/revision, so the review lacked the real operand-malformation boundary.

After the source change, the focused fixture reports each selected indirect parameter row as `standard_security_handler_parameter_trailing_operand_review` and includes `malformed_standard_security_handler_parameter_entries` in the preflight violations.

## Evidence

Focused test:

`php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionIndirectParameterTrailingOperandCurrentBaseTest.php`

Result: `1 test files, 119 assertions, 0 failures`.

Adjacent parameter tests:

`php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionIndirectParameterTrailingOperandCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionParameterGenerationOperandCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionParameterTrailingOperandCurrentBaseTest.php`

Result: `3 test files, 491 assertions, 0 failures`.

Encrypted-permission family:

`php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermission*CurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionsPreflightCurrentBaseTest.php`

Result: `62 test files, 5880 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-encrypted-indirect-parameter-trailing-operand-currentbase.php`

Result: exits 0 and emits `plain_text_blocked=true`, `parameters_well_formed=false`, `malformed_parameter_names=["V","R","Length"]`, `executes_decryption=false`, `executes_permission_enforcement=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Syntax and whitespace:

- `php -l lanes/markerpdf/src/PdfMetadataExtractor.php`: no syntax errors.
- `php -l lanes/markerpdf/tests/PdfEncryptedPermissionIndirectParameterTrailingOperandCurrentBaseTest.php`: no syntax errors.
- `php -l lanes/markerpdf/examples/wordpress-pdf-encrypted-indirect-parameter-trailing-operand-currentbase.php`: no syntax errors.
- `git diff --check -- lanes/markerpdf`: clean.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted direct `/Length` trailing operands, Standard `/P` indirect trailing operands, Standard scalar parameter generation selection, stale parameter generations, duplicate Standard parameters, non-integer parameter operands, crypt-filter parameter trailing operands, crypt-filter role/default/method review, public-key recipient envelopes, encrypted associated-file redaction, or signature/DSS/DocMDP review. The bounded behavior is only resolved-object trailing operands for indirect Standard `/V`, `/R`, and `/Length` parameters before permission preflight.

## Dependency Closure

No new support component is needed. This reuses the native PDF object scanner, xref-selected object owner resolver, Standard encryption metadata extraction, scalar operand reviewer, security preflight, and WordPress smoke harness. Full password validation, decryption, permission enforcement, live OCR, Surya/Texify/Torch models, PDFium rendering, and external PDF tools remain intentionally out of scope for this no-GPU lane.
