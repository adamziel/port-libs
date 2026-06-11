# Shared ZIP/OPC raw Unix owner policy preflight

## Slice

- Hook: `plib-059mr` / Pandoc shared ZIP/OPC package core blocker.
- Target: `lanes/pandoc` only.
- Base target recorded for submit: `origin/main` at `0c646c4736ec5f7580aafa48e454889c9c0b1084`.

## Implementation

- Added `ZipPackage::unixOwnerPolicyPreflight()` to scan central-directory and local-header Info-ZIP Unix UID/GID extra fields before package construction.
- Added structured owner metadata entries, central/local counts, mismatch records, invalid-payload records, unavailable-local-header records, and bounded-reader issue codes.
- Wired the raw strict import preflight so owner metadata diagnostics are still visible when another ZIP policy, such as unsupported general-purpose flags, prevents `ZipPackage::fromString()` from instantiating.

## Non-overlap

- This extends the existing instantiated Unix owner metadata preflight into the raw package-preflight path.
- It does not repeat the prior raw extra-field ID policy, Unicode path/comment extra-field policy, ZIP64 policy, creator-host/platform metadata, permission/symlink policies, encryption policy, or timestamp policy.
- No Pandoc, Word, LibreOffice, office suite, `zip`, `unzip`, `ZipArchive`, browser renderer, external validator, online service, live provider test, or live-service provider test is invoked.

## Evidence

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php` -> `1 test files, 3170 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests` -> `44 test files, 63841 assertions, 0 failures`

## Accounting

- `phpPass` remains `3067` after rebasing over mainline PDF/Typst system font boundary coverage; the existing `ZipPackageTest` PASS case was extended rather than adding a new named PASS case.
- Mapped denominator moves `3193 -> 3194`.
- Added `mappedZipRawUnixOwnerPolicyCases: 1`.
- Added `zipRawUnixOwnerPolicyAssertions: 24`.
