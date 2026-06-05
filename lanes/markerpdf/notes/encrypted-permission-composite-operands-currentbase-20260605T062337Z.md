# Encrypted Permission Composite Operands Current Base

Micro-slice: `markerpdf-encrypted-permissions-preflight-current-base-20260605T062337Z`

Base accepted HEAD: `cbee5993ac487ee180c9498bae22759f9cbd4213`

## Source Truth

- Upstream inventory remains `sddai/markerPDF` at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- The current markerPDF lane is native no-GPU parser/converter work only. No live OCR, Surya, Texify, Torch, PDFium raster execution, external PDF tools, or model workers were used.
- PDF Standard security handler `/P` is a scalar integer permission word. Array and dictionary operands are malformed composite permission metadata, not permission grants.

## Behavior

- `PdfMetadataExtractor` now records the Standard `/P` operand shape in `standard_permission_word_review` entries.
- Direct arrays and dictionaries, and indirect references resolving to arrays or dictionaries, now report `permission_word_composite_operand_review`.
- `PdfSecurityPreflight` maps that entry status to the top-level review reason `permission_word_composite_operand`.
- The preflight remains fail closed: encrypted text stays blocked, permission bits are unreliable, copy/extract grants stay null, and owner/user/key material is not exposed.

## Red-First Evidence

Before the source change:

`php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionCompositeOperandCurrentBaseTest.php`

Result: `1 test file / 6 assertions / 2 failures`; both composite `/P` operands were collapsed into `permission_word_non_integer`.

## Verification

After the source change:

`php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionCompositeOperandCurrentBaseTest.php`

Result: `1 test file / 88 assertions / 0 failures`.

Adjacent encrypted/security sweep:

`php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionCompositeOperandCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionsPreflightCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionPlusIntegerCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionIndirectOperandsCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionWordRangeCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionUnsignedWordCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionRevisionBitCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionCryptFilterPreflightCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionAuthEventCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionCryptFilterLengthCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionUnsupportedCryptFilterCurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityPreflightTest.php`

Result: `12 test files / 1511 assertions / 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-encrypted-permission-composite-preflight-currentbase.php`

Result: both array and dictionary composite permission operands emitted `permission_word_composite_operand`, `standard_security_handler_malformed_permissions`, `blocked_encrypted_permissions_malformed`, `permission_bits_reliable=false`, no raw encryption material, no decrypted text, and no Python/model/external-tool execution.

Whitespace check:

`git diff --check -- lanes/markerpdf`

Result: clean.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This slice does not repeat accepted encrypted permission work for scalar `/P` words, signed/unsigned integer bounds, plus-signed integers, unresolved indirect references, duplicate integer words, revision-bit review, public-key dictionaries, auth-event review, crypt-filter length/name/unsupported cases, or encrypted metadata source priority. It only adds composite operand classification for malformed Standard `/P` values.

## Dependency Closure

No new support component is needed. The behavior reuses existing native PDF dictionary/object resolution and encrypted-PDF preflight metadata. GPU/model/OCR and external PDF parser execution remain intentionally out of scope.
