# Encrypted Permissions Crypt Filter Length Current Base

Slice: `markerpdf-encrypted-permissions-preflight-current-base-20260605T040316Z`
Base: `f204ed84afa8d0ea66ac2f826529215f6be9f6fa`

## Behavior

PDF crypt-filter dictionaries declare `/CFM` and optional `/Length` entries for encrypted content roles. This slice keeps markerPDF's no-decryption boundary but now fails closed when document stream/string crypt filters explicitly declare unsupported key lengths:

- `/CFM /V2` and `/CFM /AESV2` must be within the 5-16 byte review range.
- `/CFM /AESV3` must be 32 bytes.
- `/CFM /Identity` and `/CFM /None` remain unencrypted review metadata; their `/Length` values are ignored and do not authorize payload import.

The preflight reports `invalid_crypt_filter_key_length_fail_closed`, `blocked_by_invalid_document_crypt_filter_key_length`, invalid role/filter names, and per-role key-length review rows. It still does not decrypt, validate passwords, enforce permissions, execute PDF actions, run Python/models, or invoke external PDF tools.

## Evidence

Red-before-change:

- `php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionCryptFilterLengthCurrentBaseTest.php`
- Failed after 3 assertions because the document was reported as `copy_or_extract_allowed_but_decryption_required` instead of failing closed on invalid crypt-filter key lengths.

Passing focused checks:

- `php -l lanes/markerpdf/src/PdfSecurityPreflight.php`
- `php -l lanes/markerpdf/tests/PdfEncryptedPermissionCryptFilterLengthCurrentBaseTest.php`
- `php -l lanes/markerpdf/examples/wordpress-pdf-encrypted-crypt-filter-length-currentbase.php`
- `php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionCryptFilterLengthCurrentBaseTest.php`
  - `1 test files, 63 assertions, 0 failures`
- `php tools/run-tests.php lanes/markerpdf/tests/PdfSecurityPreflightTest.php lanes/markerpdf/tests/PdfEncryptedPermissionCryptFilterPreflightCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionDefaultCryptFilterCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionUnsupportedCryptFilterCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionAuthEventCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionCryptFilterNoneCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionPublicKeyDefaultCryptFilterCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionIndirectOperandsCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionsPreflightCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionUnsignedWordCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionWordRangeCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionRevisionBitCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionCryptFilterLengthCurrentBaseTest.php`
  - `13 test files, 1350 assertions, 0 failures`
- `php lanes/markerpdf/examples/wordpress-pdf-encrypted-crypt-filter-length-currentbase.php`
  - exits 0 and emits the WordPress smoke comments for the invalid key-length boundary.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PDF dictionary/encryption metadata parser and the existing `PdfSecurityPreflight` review pipeline. GPU/model OCR, decryption, CMS parsing, signature validation, and external PDF tools remain intentionally out of scope for this markerPDF lane.

## Non-Overlap

This does not repeat accepted encrypted-permission slices for Standard permission bits, unsigned/out-of-range `/P`, duplicate `/P`, Standard R4/R6 authentication material, public-key recipient selection, default EFF inheritance, CFM None/Identity payload review, unsupported crypt-filter methods, or AuthEvent mismatch. It only adds explicit crypt-filter key-length validation before encrypted WordPress text import.
