# Encrypted Trailing Encrypt Operand Current Base

Slice: `markerpdf-encrypted-permissions-preflight-current-base-20260607T052334Z`
Base: `550247110a887312a3b109ca0dd0e71dc7fcd728`

## Source Truth

- Upstream markerPDF routes searchable PDF text through native PDF parsing/PDFium-style extraction and requires password/decryption for encrypted content before extracting protected page text.
- This no-GPU markerPDF lane only ports native PHP parser and converter behavior. It does not run OCR, Surya, Texify, Torch, PDF actions, external PDF tools, or model workers.
- A PDF trailer or xref-stream trailer `/Encrypt` entry is a single dictionary or indirect dictionary reference. If extra top-level operands follow the selected `/Encrypt` operand, the security preflight must fail closed before trusting Standard permission bits.

## Red-First Finding

A one-off current-base probe with:

`trailer << /Root 1 0 R /Encrypt 5 0 R 6 0 R >>`

showed object `5 0 R` resolving as the encryption dictionary and exposed decoded Standard permission review metadata:

- `encrypt_operand_status=encrypt_dictionary_indirect_reference_resolved`
- `permission_hex=FFFFFFD4`
- `copy_or_extract_allowed=true`

That was too trusting because the selected `/Encrypt` operand was malformed/ambiguous.

## Patch

- `PdfMetadataExtractor` now reviews top-level dictionary operands for trailing non-key tokens and treats multi-token `/Encrypt` operands as `encrypt_dictionary_trailing_operand_review`.
- `PdfSecurityPreflight` now carries the trailing `/Encrypt` review fields into encryption and permission preflight output and adds `encrypt_dictionary_trailing_operand` to review reasons.
- Added classic trailer and xref-stream trailer coverage proving permission bits are not decoded when `/Encrypt 5 0 R 6 0 R` is encountered.
- Added a WordPress smoke that emits review-only metadata and no encrypted page/auth bytes.

## Verification

- `php -l lanes/markerpdf/src/PdfMetadataExtractor.php` => no syntax errors
- `php -l lanes/markerpdf/src/PdfSecurityPreflight.php` => no syntax errors
- `php -l lanes/markerpdf/tests/PdfEncryptedPermissionTrailingEncryptOperandCurrentBaseTest.php` => no syntax errors
- `php -l lanes/markerpdf/examples/wordpress-pdf-encrypted-trailing-encrypt-preflight-currentbase.php` => no syntax errors
- `php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionTrailingEncryptOperandCurrentBaseTest.php` => 1 test file, 100 assertions, 0 failures
- `php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionDuplicateTrailerEncryptCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionIndirectTrailingOperandCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionCompositeOperandCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionsPreflightCurrentBaseTest.php` => 4 test files, 532 assertions, 0 failures
- `php lanes/markerpdf/examples/wordpress-pdf-encrypted-trailing-encrypt-preflight-currentbase.php` => exits 0
- `git diff --check -- lanes/markerpdf` => clean

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP PDF dictionary parser, xref-stream trailer handling, encryption metadata, and security preflight review paths.

## Non-Overlap

This does not repeat object-stream expansion, xref row-alignment repair, duplicate `/Encrypt` entries, indirect `/P` malformed operands, crypt-filter role preflight, OCR/model execution, or attachment payload handling. It only covers malformed multi-token `/Encrypt` operand preflight before Standard permissions are decoded.
