# Encrypted Permission Object-Body Tail Preflight

Slice: `markerpdf-encrypted-permissions-preflight-current-base-20260608T104807Z`

Accepted base: `4af637c3364e3f16eef0a1d2e1a204436022069d`

## Source Truth

- PDF encryption dictionaries are security metadata. A trailer `/Encrypt 5 0 R` reference must resolve to one encryption dictionary object body before Standard permissions are trusted.
- This slice covers the native no-GPU PDF parser boundary only: a referenced `/Encrypt` object with a valid dictionary followed by another top-level operand such as `6 0 R` is ambiguous and must fail closed before permission decoding.
- No OCR, Surya, Texify, Torch, decryption/password validation, Python model execution, raster rendering, or external PDF tools are involved.

## Behavior

- `PdfMetadataExtractor` now reviews the entire referenced `/Encrypt` object body, not only the first dictionary token.
- If extra top-level operands remain after the dictionary, the encryption entry is marked `malformed_encrypt_dictionary`, `encrypt_dictionary_resolved=false`, and `encrypt_operand_status=encrypt_dictionary_trailing_operand_review`.
- `PdfSecurityPreflight` already maps that status to `permissions_unknown_blocked_without_decryption` and `encrypt_dictionary_trailing_operand`, so page text, Standard auth bytes, decoy permission words, and supplied-page/model pipelines stay blocked.

## Evidence

- Red-first probe on the accepted base trusted `/P -44` from object `5 0 obj << ... >> 6 0 R endobj` and reported `copy_extract_allowed_after_decryption`.
- Focused verification after this patch:
  - `php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionObjectBodyTrailingOperandCurrentBaseTest.php` => 1 test file / 90 assertions / 0 failures
  - `php tools/run-tests.php $(find lanes/markerpdf/tests -maxdepth 1 -type f -name 'PdfEncryptedPermission*CurrentBaseTest.php' | sort)` => 64 test files / 6045 assertions / 0 failures
  - `php lanes/markerpdf/examples/wordpress-pdf-encrypted-object-body-tail-currentbase.php` => exits 0 with `conversion_stage=encrypted-pdf-preflight`, `pipeline_calls=0`, `raw_material_exposed=false`
  - `git diff --check -- lanes/markerpdf` => no whitespace errors

## Non-Overlap

This does not repeat prior malformed trailer `/Encrypt` operand coverage. That accepted boundary catches a multi-token value inside the trailer dictionary itself. This slice catches the next boundary: the trailer `/Encrypt` value is a single indirect reference, but the referenced object body has a trailing top-level operand after the dictionary.

## Dependency Closure

No new support component is needed. The patch reuses the native PDF dictionary/value readers and existing `PdfSecurityPreflight` encrypted import policy.
