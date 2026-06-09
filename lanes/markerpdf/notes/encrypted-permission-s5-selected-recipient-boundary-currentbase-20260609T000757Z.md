# Encrypted Permission S5 Selected Recipient Boundary Current Base

- Slice: `markerpdf-encrypted-permissions-preflight-current-base-20260609T000757Z`
- Base accepted HEAD: `35d557737dc1b88c45279aeb585788c53834812d`
- Scope: native PDF security preflight only; no decryption, CMS parsing, OCR/models, Python workers, or external PDF tools.

## Source-Truth Behavior

For public-key security handlers using `adbe.pkcs7.s5`, permissions are carried by crypt-filter recipient envelopes. Legacy top-level `/Recipients` can still appear in the encryption dictionary but are not the selected permission source when S5 crypt-filter recipients are present. The native preflight must keep malformed unselected legacy recipients visible as aggregate diagnostics without letting them override selected crypt-filter recipient permission review.

## Patch

- `PdfMetadataExtractor` now computes selected-recipient declaration failure, selected recipient entry statuses, and selected trailing operand counts after combining S5 crypt-filter recipients with any selected top-level recipient source.
- `PdfSecurityPreflight` now drives public-key recipient declaration failure policy from selected-recipient declaration failure, while preserving aggregate `recipient_declaration_fail_closed` diagnostics for malformed decoys.
- Added a focused S5 fixture where a selected crypt-filter recipient is valid but an unselected legacy top-level `/Recipients` entry has a trailing operand.
- Added a WordPress smoke for the same boundary.

## Red-First / Verification Evidence

Red-first:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionPublicKeyS5SelectedRecipientBoundaryCurrentBaseTest.php
FAIL ... Expected public_key_recipient_permissions_undecoded, actual public_key_recipient_declaration_malformed ...
1 test files, 3 assertions, 1 failures
```

After patch:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionPublicKeyS5SelectedRecipientBoundaryCurrentBaseTest.php
PASS keeps s5 crypt-filter recipient selection authoritative when legacy top-level recipients are malformed decoys
1 test files, 53 assertions, 0 failures
```

Additional focused verification:

```text
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg 'Pdf(EncryptedPermissionPublicKey|SecurityPublicKey).*CurrentBaseTest.php$' | sort)
9 test files, 592 assertions, 0 failures

php tools/run-tests.php lanes/markerpdf/tests/PdfSecurityPreflightTest.php
1 test files, 494 assertions, 0 failures

php lanes/markerpdf/examples/wordpress-pdf-encrypted-public-key-s5-selected-recipient-boundary-currentbase.php
exits 0; selected_recipient_declaration_fail_closed=false, recipient_declaration_fail_closed=true, executes_decryption=false

php -l lanes/markerpdf/src/PdfMetadataExtractor.php
php -l lanes/markerpdf/src/PdfSecurityPreflight.php
php -l lanes/markerpdf/tests/PdfEncryptedPermissionPublicKeyS5SelectedRecipientBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-encrypted-public-key-s5-selected-recipient-boundary-currentbase.php
no syntax errors

php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'
lane-status json ok

git diff --check -- lanes/markerpdf
passed
```

## Non-Overlap

This does not overlap recent image/XObject, xref repair, stream filter, metadata, OCR/model, or Standard-handler permission-bit slices. It narrows the public-key S5 recipient selection boundary only.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PDF dictionary parser, crypt-filter recipient inventory, and security-preflight review data. CMS/private-key recipient parsing remains intentionally out of scope and fail-closed under the current no-live-service/no-model lane rules.
