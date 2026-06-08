# markerPDF encrypted permission parameter generation current-base

Micro-slice: `markerpdf-encrypted-permissions-preflight-current-base-20260608T073645Z`

Base accepted HEAD: `f9f8c785dc328d4e92c64eff78b33172c1cfb9fe`

## Source Truth

Upstream `sddai/markerPDF` delegates searchable PDF extraction to PDF parser/PDFium/pdftext boundaries before OCR/model stages. Under the current no-GPU markerPDF scope, encrypted PDFs stay preflight-only: review security metadata, block visible content until decryption support exists, and do not execute Python models, PDF actions, or external PDF tools.

PDF indirect references are generation-specific. Standard security-handler scalar parameters `/V`, `/R`, and `/Length` can be indirect objects, so permission preflight must preserve the referenced object generation and fail closed if a selected parameter points at a stale generation.

## Behavior

- `PdfMetadataExtractor` now emits declaration rows for well-formed indirect Standard security-handler scalar parameters without marking them malformed.
- The rows expose raw operand shape, reference object/generation, and resolved object/generation for `/Filter`, `/V`, `/R`, and `/Length` parameter review.
- Stale generation references for `/V`, `/R`, or `/Length` remain malformed parameter operands, blocking permission-bit trust even when `/P` itself would otherwise allow copy/extract after decryption.
- Added a WordPress smoke showing current generation-1 parameters are trusted for preflight metadata while stale generation-0 parameters fail closed, with encrypted text and authentication bytes redacted.

## Evidence

Focused test:

`php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionParameterGenerationOperandCurrentBaseTest.php`

Result: `1 test files, 186 assertions, 0 failures`.

Adjacent encrypted-permission family:

`php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermission*CurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionsPreflightCurrentBaseTest.php`

Result: `61 test files, 5761 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-encrypted-permission-parameter-generation-currentbase.php`

Result: exits 0 and emits current-generation parameter rows with generation `1`, stale-generation rows with unresolved generation `0`, `plain_text_blocked=true`, `executes_decryption=false`, `executes_permission_enforcement=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Syntax and whitespace:

- `php -l lanes/markerpdf/src/PdfMetadataExtractor.php`: no syntax errors.
- `php -l lanes/markerpdf/tests/PdfEncryptedPermissionParameterGenerationOperandCurrentBaseTest.php`: no syntax errors.
- `php -l lanes/markerpdf/examples/wordpress-pdf-encrypted-permission-parameter-generation-currentbase.php`: no syntax errors.
- `git diff --check -- lanes/markerpdf`: clean.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted `/Encrypt` generation selection, `/P` generation operands, unsigned/out-of-range `/P`, duplicate `/P`, missing `/P`, duplicate security-handler parameters, malformed scalar operands, crypt-filter role/default/method review, public-key recipient envelopes, encrypted associated-file redaction, or signature/DSS/DocMDP review. The bounded behavior is only generation provenance and fail-closed generation review for indirect Standard `/V`, `/R`, and `/Length` scalar parameters before permission preflight.

## Dependency Closure

No new support component is needed. This reuses the native PDF object scanner, xref-selected object owners, Standard encryption metadata extraction, and existing security preflight. Full password validation, decryption, permission enforcement, live OCR, Surya/Texify/Torch models, PDFium rendering, and external PDF tools remain intentionally out of scope for this no-GPU lane.
