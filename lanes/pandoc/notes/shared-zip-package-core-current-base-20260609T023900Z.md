# Pandoc ZIP Package Core Current-Base Slice - 2026-06-09T023900Z

## Behavior

- Added `ZipPackage::externalAttributePolicyPreflight()` as a raw central-directory scan that runs before package instantiation.
- The summary reports blocked external-attribute policy cases for:
  - Unix symlink entries.
  - Unix FIFO/character/block/socket/unknown special-file entries.
  - DOS directory attributes on names that are not directory entries.
  - Unix file-type metadata that disagrees with the trailing-slash entry-name shape.
- `ZipPackage::rawStrictImportPreflight()` now includes the `externalAttributes` summary and adds those issue keys to aggregate diagnostics before falling back to the existing constructor failure.
- Non-Unix creator hosts keep high-word Unix-looking attribute bits as metadata, matching the existing reader behavior.

## Verification

- Baseline before edits: `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - `1 test files, 2131 assertions, 0 failures`
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - `1 test files, 2189 assertions, 0 failures`
  - Assertion delta: `+58`
  - PASS-line delta: `+1`
- Example smoke: `php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test`
  - `zip package writer preflight self-test passed`
- PHP syntax:
  - `php -l lanes/pandoc/src/ZipPackage.php`
  - `php -l lanes/pandoc/tests/ZipPackageTest.php`
  - `php -l lanes/pandoc/examples/wordpress-zip-package-preflight.php`
- Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the native PHP EOCD and central-directory scanners already used by ZIP raw strict import preflight and does not call Pandoc, Cabal/Haskell runners, Word, LibreOffice, `zip`, `unzip`, ZipArchive, external converters, template engines, TeX/PDF engines, browser renderers, validators, online services, or live providers.

## Non-Overlap

This does not change the existing constructor enforcement for symlinks, Unix special files, or directory/file-type mismatch entries, and it does not duplicate the accepted DOS hidden/system/volume-label strict-import policy. It only makes constructor-blocking external-attribute issues visible in raw strict preflight before package instantiation fails.

## Next

A non-overlapping ZIP package follow-up could add bounded central-directory recovery metadata, unsupported creator-host edge policy detail, or additional ZIP64 compatibility reporting while keeping the implementation native PHP and external-tool free.
