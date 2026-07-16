# MarkerPDF Encrypted Permissions Preflight Current Base

Slice: `markerpdf-encrypted-permissions-preflight-current-base-20260606T143921Z`

Base accepted HEAD: `c225160401688bd1c3ca993be227a17e71dcecc4`

## Behavior

Revision-2 Standard security handlers do not define the later accessibility extraction permission bit. The encrypted permission preflight now reports `extract_for_accessibility` as not applicable in the convenience review booleans, returning `accessibility_extract_allowed: null` instead of treating the absent permission as a denied `false` value.

Applicable denied bits still report `false`; this change only affects permission names outside the decoded Standard security-handler revision.

## Source Truth And Non-Overlap

The slice stays inside native no-GPU markerPDF security preflight behavior. It does not decrypt document content, enforce passwords, launch OCR/model code, run Python, or call external PDF tools.

Non-overlap checked before editing:

- Existing duplicate `/Perms` authorization material coverage remains untouched.
- Existing duplicate `EncryptMetadata` boundary coverage remains untouched.
- Existing top-level direct-object and comment-decoy encryption dictionary boundaries remain untouched.
- Crypt-filter role preflight and public-key recipient permission surfaces remain untouched.

## Focused Evidence

Red-first probe before the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionRevisionBitCurrentBaseTest.php
```

Result: `1 test files, 82 assertions, 1 failures`; the new revision-2 probe expected `accessibility_extract_allowed` to be `NULL` and got `false`.

After the source, test, and smoke edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionRevisionBitCurrentBaseTest.php
```

Result: `1 test files, 90 assertions, 0 failures`.

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfSecurityPreflightTest.php
```

Result: `1 test files, 494 assertions, 0 failures`.

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermission*Test.php
```

Result: `45 test files, 3942 assertions, 0 failures`.

```text
php lanes/markerpdf/examples/wordpress-pdf-encrypted-permission-bit-preflight-currentbase.php
```

Result: exits `0`; the WordPress review comments include `accessibility_extract_allowed: null`, `extract_for_accessibility` in `not_applicable_permission_names`, encrypted text blocked from paragraph output, and all decryption/model/external-tool execution flags false.

## Dependency Closure

No new support component is needed. The patch reuses the existing native PHP `PdfSecurityPreflight` permission decoder and the existing WordPress encrypted-permission smoke fixture path.

Root harness status: not run - isolated micro-slice.

Next task: continue non-overlapping markerPDF native parser work around searchable-PDF fidelity, especially fonts, CMaps, stream filters, xref repair, metadata, annotations, forms, page geometry, and image/filter metadata.
