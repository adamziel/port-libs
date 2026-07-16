# ODT Compact Script Package Byte Policy

Bead: `plib-p5xnm`
Base: `b3f2aa2a05`
Date: 2026-06-12 UTC

This slice keeps compact `OpenDocumentPackage` package-ingestion provenance aligned
with the safer ODF reader path for macro/script sidecars. Manifest-declared and
ZIP-present `Basic/` and `Scripts/` package parts are now classified as
`script-package` review items, counted in package inventory, and blocked from
byte exposure with `script-package-bytes-blocked` unless an earlier encrypted or
directory policy applies. Manifest review items also carry the existing CRC
provenance needed to audit blocked script payloads without exposing script bytes.

The focused fixture covers declared Basic modules, JavaScript sidecars, script
directories, missing script parts, encrypted script parts, and undeclared script
package entries. Script payloads remain outside document media handoff and are
reported as metadata-only package review provenance.

Verification:

- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/tests/OpenDocumentPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OpenDocumentPackageTest.php`
  - 1 test file, 609 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 test files, 68291 assertions, 0 failures

Accounting:

- `phpPass`: 3159 -> 3160
- mapped denominator: 3225 -> 3226
- `mappedOdtCompactPackageScriptCases`: 1
- `odtCompactPackageScriptAssertions`: 41

No Pandoc, office suites, zip/unzip, browser renderers, external validators,
online services, live provider tests, or live-service provider tests were run.
