# markerpdf encrypted permission trust boundary current-base

Slice: `markerpdf-encrypted-permissions-preflight-current-base-20260608T052518Z`
Base: `c162e5af21915b05e444923d010d6e56dffee14f`

## Behavior

This patch keeps the existing Standard security-handler authentication material
review intact, but adds a separate permission-trust boundary for decoded `/P`
bits. Complete raw `/O`, `/U`, `/OE`, `/UE`, and `/Perms` material can now be
reported as ready for a future password attempt without implying that the
decoded permission word is usable for import permission trust.

The new review fields are additive:

- `authentication_material_usable_for_permission_trust`
- `permission_trust_requires_password_validation`
- `permission_trust_blocker`
- mirrored top-level `permission_bits_authentication_material_usable`,
  `permission_bits_trust_requires_password_validation`, and
  `permission_bits_trust_blocker`

Malformed Standard handler parameters, including a `/V 4` plus `/R 6`
version/revision mismatch, keep `standard_authentication_ready_for_password_attempt`
true when the raw material is complete, but set permission trust usability to
false with `decoded_permission_bits_not_syntactically_reliable`.

## Source Truth

The PDF security handler preflight remains review-only. It does not validate
passwords, decrypt bytes, expose owner/user keys, enforce permissions, invoke
Python/OCR/model code, or run external PDF tools. Permission bits are treated as
syntactically decoded metadata until a future authenticated decryption path can
validate them.

Non-overlap: this slice does not repeat revision-bit permission gating,
reserved-bit handling, AES-256 `/Perms` readiness, crypt-filter method or
generation compatibility, missing `/P`, malformed `/P` operands, public-key
recipient review, associated-file redaction, or xref `/Encrypt` selection.

## Verification

Focused test added:

`php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionTrustBoundaryCurrentBaseTest.php`

Result:

`1 test files, 80 assertions, 0 failures`

Adjacent encrypted-permission regression check:

`php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionVersionRevisionCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionAuthenticationTrustCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionOperationAuthReadinessCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionAes256LengthCurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityPreflightTest.php`

Result:

`5 test files, 884 assertions, 0 failures`

The WordPress smoke is:

`php lanes/markerpdf/examples/wordpress-pdf-encrypted-permission-trust-boundary-currentbase.php`

It exits 0 with encrypted text blocked, raw Standard auth material ready,
permission trust material unusable, no raw material exposure, and no Python,
model, OCR, decryption, permission enforcement, or external PDF tool execution.

Changed PHP lint passed:

- `php -l lanes/markerpdf/src/PdfSecurityPreflight.php`
- `php -l lanes/markerpdf/tests/PdfEncryptedPermissionTrustBoundaryCurrentBaseTest.php`
- `php -l lanes/markerpdf/examples/wordpress-pdf-encrypted-permission-trust-boundary-currentbase.php`

Lane diff check passed:

`git diff --check -- lanes/markerpdf`

## Dependency Closure

No new support component is needed. The patch reuses the existing native PHP
encrypted-document dictionary parser, Standard handler parameter review, and
security preflight surfaces. GPU/model/OCR execution remains intentionally out
of scope for this no-GPU markerPDF lane.

## Next Task

Continue non-overlapping native PDF parser work in fonts, CMaps, stream
filters, metadata, annotations, forms, page geometry, xref repair, or
image/filter metadata. Do not expand this slice into password validation or
decryption; those require a separate authenticated decryption implementation.
