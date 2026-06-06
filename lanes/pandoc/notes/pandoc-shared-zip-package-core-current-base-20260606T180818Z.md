# pandoc-shared-zip-package-core-current-base-20260606T180818Z

Base accepted HEAD: `11a2e57d1384f7898502502ab620e40838291fb1`

## Behavior

This slice extends the native PHP ZIP package strict-import boundary for central-directory digital signatures.

Existing support already parses the optional central-directory digital signature record and exposes reviewer provenance with `cryptographicVerification=not-performed-native-bounded-reader`. This patch keeps that inspectable/readable behavior, but `ZipPackage::strictImportPreflight()` now marks signed central directories invalid with `central-directory-signature-unverified`, and `assertStrictImportable()` rejects them before DOCX/ODT/EPUB media handoff.

## Non-overlap

This does not repeat the earlier central-directory signature parser/provenance slice or the Unicode normalized case-insensitive collision preflight slice. It only adds the strict native import policy for signatures this bounded reader does not cryptographically verify.

## Evidence

- Baseline: `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php` passed with `1 test files, 873 assertions, 0 failures`.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php` passed with `1 test files, 880 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test` passed with `zip package writer preflight self-test passed`.
- PHP lint passed for:
  - `lanes/pandoc/src/ZipPackage.php`
  - `lanes/pandoc/tests/ZipPackageTest.php`
  - `lanes/pandoc/examples/wordpress-zip-package-preflight.php`
- `git diff --check -- lanes/pandoc` passed with no output.

## Status Delta

- `lane-status.json` `phpPass`: `1381 -> 1382`
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `1794 -> 1795`

## Dependency Closure

No new support component is needed. This reuses the native PHP ZIP central-directory parser, central-directory signature provenance reader, strict import preflight aggregator, bounded read-integrity path, focused TestRunner, and the existing WordPress ZIP preflight example.

Cryptographic verification of ZIP central-directory signatures, OPC XMLDSig/package-signature validation, external archive validation, and full Pandoc/Haskell runner parity remain out of scope for this bounded support-library slice.

## Next

Continue ZIP/OPC support with non-overlapping native behavior such as OPC signature-origin relationship handoff, package relationship/content-type policy, or archive edge preflights needed by DOCX/ODT/EPUB imports. Keep Pandoc, Cabal/Haskell runners, zip/unzip, ZipArchive, Word, LibreOffice, external archive validators, online services, live provider tests, and live-service provider tests out unless explicitly authorized.
