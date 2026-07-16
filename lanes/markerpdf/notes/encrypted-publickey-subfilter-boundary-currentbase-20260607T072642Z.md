# Encrypted Public-Key SubFilter Boundary Current Base

Slice: `markerpdf-encrypted-permissions-preflight-current-base-20260607T072642Z`  
Base accepted HEAD: `2ad8162f5a3428f4ace3f2bf0a83927be18816b0`

## Source Truth

- Upstream `sddai/markerPDF` behavior keeps encrypted PDFs out of native text extraction unless decryption succeeds. Under this lane's no-GPU/no-model scope, the PHP port must preflight encrypted security metadata without attempting passwords, CMS parsing, private-key operations, OCR, or external PDF tools.
- PDF public-key handlers route recipient permission envelopes differently by `/SubFilter`: legacy `adbe.pkcs7.s3`/`adbe.pkcs7.s4` use top-level `/Recipients`, while `adbe.pkcs7.s5` uses crypt-filter recipients. Duplicate or malformed `/SubFilter` declarations can change which envelope source is trusted, so the native importer now fails closed before selecting a recipient permission source.

## Behavior Added

- Added `security_handler_subfilter_declaration_review` to encrypted metadata for duplicate and malformed top-level `/SubFilter` declarations.
- Public-key recipient review still inventories top-level and crypt-filter recipient envelopes as hashes and counts, but suppresses selected recipient permission envelopes when `/SubFilter` is ambiguous or non-name.
- Permission preflight now reports:
  - `source=security_handler_subfilter_declaration_malformed`
  - `policy=permissions_malformed_blocked_without_decryption`
  - `content_extraction_boundary=blocked_encrypted_security_handler_subfilter_malformed`
  - `selected_public_key_recipient_count=0`

## Red-First Evidence

Before source edits, a duplicate public-key `/SubFilter /adbe.pkcs7.s3 /SubFilter /adbe.pkcs7.s5` fixture selected the last subfilter and reported:

```json
{
  "policy": "public_key_recipient_permissions_blocked_without_private_key",
  "boundary": "blocked_encrypted_public_key_recipient_permissions",
  "source": "public_key_recipient_permissions",
  "recipient_source": "crypt_filter_recipients_with_legacy_encryption_dictionary_recipients",
  "selected_count": 1
}
```

After the patch, duplicate and composite `/SubFilter` operands fail closed as malformed security-handler subfilter declarations and select zero recipient permission envelopes.

## Verification

- `php -l lanes/markerpdf/src/PdfMetadataExtractor.php`  
  Result: no syntax errors.
- `php -l lanes/markerpdf/src/PdfSecurityPreflight.php`  
  Result: no syntax errors.
- `php -l lanes/markerpdf/tests/PdfEncryptedPermissionPublicKeySubfilterBoundaryCurrentBaseTest.php`  
  Result: no syntax errors.
- `php -l lanes/markerpdf/examples/wordpress-pdf-publickey-subfilter-boundary-currentbase.php`  
  Result: no syntax errors.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionPublicKeySubfilterBoundaryCurrentBaseTest.php`  
  Result: `1 test files, 79 assertions, 0 failures`.
- Adjacent encrypted-permission subset:
  `php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionPublicKeyDefaultCryptFilterCurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityPublicKeyPermissionCurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityPublicKeyDssPermissionBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionDuplicateFilterHandlerCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionFilterOperandCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionsPreflightCurrentBaseTest.php`  
  Result: `6 test files, 723 assertions, 0 failures`.
- WordPress smoke:
  `php lanes/markerpdf/examples/wordpress-pdf-publickey-subfilter-boundary-currentbase.php`  
  Result: exits 0; emitted `selected_recipient_count=0`, `subfilter_declaration_status=duplicate_security_handler_subfilter_entries_review`, `raw_recipient_material_exposed=false`, and all no-execution flags false.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This slice does not repeat the accepted Standard `/P` malformed/duplicate permission-word work, Standard `/Filter` duplicate/malformed handler work, public-key default EFF recipient selection, public-key DSS review, crypt-filter role/default/method boundaries, or encrypted attachment EFF payload preflight. It only adds the public-key `/SubFilter` declaration boundary before recipient permission source selection.

## Dependency Closure

No new support component is needed. The patch reuses the existing native PHP PDF dictionary scanner, object resolver, public-key recipient inventory, and security preflight reporting. CMS/PKCS#7 recipient parsing, private-key use, decryption, OCR/model execution, and external PDF tools remain intentionally out of scope for this lane.
