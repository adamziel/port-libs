# pandoc-shared-zip-package-core-current-base-20260608T051915Z

Base accepted HEAD: `c162e5af21915b05e444923d010d6e56dffee14f`

## Behavior

This slice adds bounded native ZIP extraction-version floor policy for package
imports used by DOCX/ODT/EPUB and WordPress review handoff paths.

- `ZipPackage::fromString()` now rejects deflated entries that declare
  `version needed to extract` below `20`.
- Stored entries with a data descriptor also require `version needed to
  extract >= 20`, because streamed CRC/size metadata changes how the payload is
  interpreted.
- Ordinary stored entries remain compatible with `version needed to extract`
  `10`.
- `ZipPackage::compressionMethodPolicyPreflight()` now reports understated
  version metadata separately from too-new version metadata, including
  `minimumVersionNeededToExtract`, local/central too-low booleans, and
  `understatedVersionEntries`.
- The WordPress ZIP package preflight example now exposes the understated
  version policy and minimum-version diagnostic.

## Evidence

Baseline focused run before edits:

`php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`

Result: `1 test files, 1315 assertions, 0 failures`

Final focused run after implementation:

`php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`

Result: `1 test files, 1355 assertions, 0 failures`

Focused delta: `+1` PHP PASS case, `+40` focused assertions.

Example smoke:

`php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test`

Result: `zip package writer preflight self-test passed`

PHP lint:

- `php -l lanes/pandoc/src/ZipPackage.php` -> no syntax errors
- `php -l lanes/pandoc/tests/ZipPackageTest.php` -> no syntax errors
- `php -l lanes/pandoc/examples/wordpress-zip-package-preflight.php` -> no syntax errors

Whitespace check:

`git diff --check -- lanes/pandoc`

Result: passed with no output.

## Status Delta

- `lane-status.json` `phpPass`: `1542 -> 1543`
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `1963 -> 1964`
- `zipPackageCoreSupportCases`: `22 -> 23`
- `mappedZipPackageCoreSupportCases`: `22 -> 23`
- `zipPackageCoreAssertions`: `161 -> 201`

## Dependency Closure

No new support component is needed. This slice reuses native PHP
`ZipPackage` central/local metadata parsing, the existing compression-method
policy scanner, in-memory ZIP fixtures, the focused PHP test harness, and the
WordPress ZIP package preflight example.

No Pandoc, Cabal/Haskell runner, Word, LibreOffice, zip/unzip, ZipArchive,
external archive tool, online service, live provider test, or live-service
provider test was executed.

## Non-overlap

This does not repeat accepted ZIP max-version rejection, local version mismatch
rejection, compression-method support preflight, deflate option flag policy,
data descriptor integrity, ZIP64 EOCD/extra-field/data-descriptor rejection,
split archive handling, archive extra data record policy, invalid DOS
timestamp preflight, central-directory signature provenance, raw deflate
trailing-byte rejection, Unicode-name collision handling, symlink/special-file
rejection, or DOS/Unix attribute policy.

## Follow-up

Next ZIP/OPC package work should stay bounded to non-overlapping native package
preflight, such as additional central/local metadata provenance needed by
DOCX/ODT/EPUB readers, ZIP64 accounting diagnostics, or OPC integration.
