# Encrypted Permission Generation Reference Current Base

Slice: `markerpdf-encrypted-permissions-preflight-current-base-20260605T100652Z`

Accepted base: `4ea5e49a8af97f98c8b9c93bbfe2de90c6ddd478`

## Source Truth

PDF trailer `/Encrypt n g R` references are generation-specific. An incremental update can keep a stale object number alive at generation 0 while the latest trailer names the current encryption dictionary at a later generation. Permission preflight must select the trailer-named generation before reviewing Standard security-handler `/P` bits.

This patch stays within the current no-GPU markerPDF scope: native PDF metadata/security preflight only, no decryption, OCR, model execution, or external PDF tools.

## Behavior

- Added generation-aware resolution for indirect trailer `/Encrypt` dictionaries.
- Included `/Encrypt` in latest-trailer direct generation repair alongside `Root` and `Info`.
- Exposed `object_generation` in extracted encryption metadata and the security preflight review.
- Added a fixture where the xref row still points object `5` at generation `0` with copy denied, while the latest trailer names `/Encrypt 5 1 R` with copy allowed after decryption.
- Added a WordPress smoke that keeps encrypted text blocked while reporting the generation-exact preflight boundary.

## Evidence

Red-first focused gap:

`php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionGenerationReferenceCurrentBaseTest.php`

Result before source patch: `1 test files, 3 assertions, 1 failures`; the stale generation was selected and reported `copy_or_extract_denied`.

Focused result after source patch:

`php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionGenerationReferenceCurrentBaseTest.php`

Result: `1 test files, 34 assertions, 0 failures`.

Adjacent encrypted preflight family:

`php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionGenerationReferenceCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionsPreflightCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionIndirectOperandsCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionCompositeOperandCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionUnsignedWordCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionWordRangeCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionRevisionBitCurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityPreflightTest.php lanes/markerpdf/tests/PdfXrefTrailerEncryptPrevCurrentBaseTest.php lanes/markerpdf/tests/PdfParserTrailerEncryptIdPrecedenceCurrentBaseTest.php`

Result: `10 test files, 1287 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-encrypted-permission-generation-currentbase.php`

Result: emits `encrypt_object_generation=1`, `permission_policy=copy_extract_allowed_after_decryption`, `content_extraction_boundary=blocked_until_decryption_password_available`, `executes_decryption=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat crypt-filter role declaration, unsigned `/P`, malformed `/P`, Standard parameter validation, trailer `/Encrypt null`, `/Prev` inheritance, public-key recipient, signature/DSS, or authentication-material review slices. It owns only generation-exact indirect `/Encrypt` resolution before permission preflight.

## Dependency Closure

No new support component is needed. The patch reuses the existing direct-object definition, trailer generation repair, encryption metadata extractor, and security preflight components. Full password validation/decryption and GPU/model/OCR parity remain intentionally out of scope for this no-GPU native preflight slice.
