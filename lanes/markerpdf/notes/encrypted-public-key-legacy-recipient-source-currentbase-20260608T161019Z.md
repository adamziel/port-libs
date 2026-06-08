# markerPDF encrypted public-key legacy recipient source current-base

Micro-slice: `markerpdf-encrypted-permissions-preflight-current-base-20260608T161019Z`

Base accepted HEAD: `5f385153306ae68f081cbb8d67375beb9645b190`

## Source Truth

- Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` blocks encrypted PDFs unless the security handler can authorize and decrypt them.
- PDF public-key security-handler preflight is metadata-only in this no-GPU/no-model lane. It inventories recipient envelopes, but does not parse CMS, match private keys, decrypt streams or strings, enforce permissions, or expose recipient bytes.
- Existing lane behavior already selected top-level `/Recipients` for legacy `adbe.pkcs7.s3` and `adbe.pkcs7.s4`, and selected crypt-filter `/Recipients` for `adbe.pkcs7.s5`. This slice covers the mixed-source boundary where a valid legacy subfilter also carries crypt-filter recipient arrays.

## Implemented

- `PdfMetadataExtractor::publicKeyRecipientCryptFilterSelection()` now suppresses selected recipient-envelope counts for crypt-filter `/Recipients` when `/SubFilter` is `adbe.pkcs7.s3` or `adbe.pkcs7.s4`.
- The crypt-filter recipient arrays remain inventoried as review-only metadata via `crypt_filter_recipient_filter_names`, `unselected_crypt_filter_recipient_filter_names`, suppressed count/byte fields, and `legacy_public_key_subfilter_uses_top_level_recipients`.
- The selected permission source for legacy public-key preflight stays `encryption_dictionary_recipients`; encrypted text remains blocked until real private-key recipient decoding and decryption exist.
- Added focused test coverage for both `adbe.pkcs7.s4` and `adbe.pkcs7.s3`, plus a WordPress smoke example for the S4 import boundary.

## Non-Overlap

This does not repeat accepted S5 default crypt-filter recipient selection, malformed/duplicate public-key `/SubFilter` fail-closed handling, malformed crypt-filter role declarations, top-level legacy recipient selection without crypt-filter decoys, Standard handler permission/authentication review, crypt-filter content-role preflight, or public-key DSS permission-boundary review.

The bounded new behavior is valid legacy public-key subfilter source precedence: top-level `/Recipients` are selected, crypt-filter `/Recipients` are review-only decoys, raw recipient bytes stay redacted, and no CMS/decryption/permission-enforcement work runs.

## Verification

- Red-first: `php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionPublicKeyLegacyCryptFilterDecoyCurrentBaseTest.php` - failed before the source patch with `1 test files, 14 assertions, 2 failures`; both valid legacy subfilters selected 2 recipients instead of the expected 1 top-level recipient.
- `php -l lanes/markerpdf/src/PdfMetadataExtractor.php` - passed.
- `php -l lanes/markerpdf/tests/PdfEncryptedPermissionPublicKeyLegacyCryptFilterDecoyCurrentBaseTest.php` - passed.
- `php -l lanes/markerpdf/examples/wordpress-pdf-encrypted-public-key-legacy-recipient-source-currentbase.php` - passed.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionPublicKeyLegacyCryptFilterDecoyCurrentBaseTest.php` - passed, `1 test files, 98 assertions, 0 failures`.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionPublicKeyLegacyCryptFilterDecoyCurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityPublicKeyPermissionCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionPublicKeyDefaultCryptFilterCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionPublicKeySubfilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionPublicKeyCryptFilterRoleBoundaryCurrentBaseTest.php` - passed, `5 test files, 330 assertions, 0 failures`.
- `php lanes/markerpdf/examples/wordpress-pdf-encrypted-public-key-legacy-recipient-source-currentbase.php` - emitted `encrypted_text_blocked=true`, `permission_policy=public_key_recipient_permissions_blocked_without_private_key`, `selected_recipient_source_policy=encryption_dictionary_recipients`, `selected_recipient_count=1`, `selected_crypt_filter_recipient_count=0`, `unselected_crypt_filter_recipients=["LegacyDecoyFilter"]`, `crypt_filter_selection_suppressed_by_legacy_subfilter=true`, `raw_recipient_material_exposed=false`, and all decryption/permission-enforcement/Python/model/external-tool execution flags false.

## Status Delta

- Focused markerPDF behavior tests move `3278 -> 3280` pass / `0` fail.
- WordPress scenario coverage moves `2673 -> 2674`.
- Mapped upstream inventory is unchanged; this is a current-base native encrypted-permission source-precedence boundary over already mapped public-key recipient primitives.

## Dependency Closure

No new support component is needed. This reuses native PDF dictionary/object parsing, crypt-filter metadata extraction, public-key recipient inventory, encrypted-text fail-closed preflight, and WordPress smoke output.

Full CMS/PKCS#7 parsing, private-key matching, public-key recipient permission decoding, Standard/public-key decryption, password attempts, permission enforcement, OCR/model execution, raster rendering, and external PDF tools remain out of scope. Activating those requires a bounded native cryptographic/decryption component with valid/invalid recipient envelope, private-key, decrypted text/stream, and permission-bit fixtures.
