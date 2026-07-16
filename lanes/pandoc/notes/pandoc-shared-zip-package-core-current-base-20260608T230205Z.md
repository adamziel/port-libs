# Pandoc Shared ZIP Package Core Current Base: Generated Creator Host Policy

## Summary

- Slice: `pandoc-shared-zip-package-core-current-base-20260608T230205Z`
- Accepted base: `d8ca989a03aa98e6028adc24e3edc39bb34ec9a6`
- Behavior: `ZipPackage::build()` now accepts explicit known `creatorHostSystem` metadata for generated package entries, writes it into central-directory `versionMadeBy` host bytes, keeps version-needed at 20, and rejects unknown or non-integer generated host systems before package bytes are emitted.
- WordPress path: `wordpress-zip-package-preflight.php --self-test` now covers generated packages with `windows-ntfs` and `ms-dos-fat` creator hosts, raw creator-host policy acceptance, strict import acceptance, and fail-closed unknown generated host metadata.

## Evidence

- Rework-note check: `ls -1 /home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md 2>/dev/null` returned no current lane rework notes.
- Baseline focused test before implementation: `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php` passed with `1 test files, 1977 assertions, 0 failures`.
- Red-first focused test with only the new test: `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php` failed with `1 test files, 1978 assertions, 1 failures`; the generated entry still defaulted to Unix creator host `3` instead of the requested Windows host `10`.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php` passed with `1 test files, 1999 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test` passed with `zip package writer preflight self-test passed`.
- Syntax checks:
  - `php -l lanes/pandoc/src/ZipPackage.php` passed.
  - `php -l lanes/pandoc/tests/ZipPackageTest.php` passed.
  - `php -l lanes/pandoc/examples/wordpress-zip-package-preflight.php` passed.
- Whitespace check: `git diff --check -- lanes/pandoc` passed.
- Root harness: not run - isolated micro-slice.

## Status Delta

- `lanes/pandoc/lane-status.json` `phpPass`: `1950` to `1951`.
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2370` to `2371`.
- `mappedZipPackageCoreSupportCases`: `22` to `23`.
- `zipPackageCoreAssertions`: `161` to `183`.
- Focused ZIP package assertions: `1977` to `1999`.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP ZIP writer, central-directory parser, creator-host naming policy, strict import preflight, and WordPress ZIP package preflight smoke. It did not run Pandoc, Cabal solver/build/test commands, Haskell runners, `zip`/`unzip`, `ZipArchive`, Word, LibreOffice, external archive tools, online services, live provider tests, or live-service provider tests.

## Non-Overlap

This is writer-side generated creator-host metadata. It does not repeat the accepted raw creator-host policy preflight, central-directory signature, invalid DOS timestamp, Unicode-name collision, trailing-deflate payload-integrity, compression-method, encryption, archive-extra, ZIP64 preflight, local-header mismatch, or raw-name provenance ZIP package slices.

## Next

A non-overlapping ZIP package follow-up can cover bounded ZIP64 entry upgrade planning or raw-name provenance diagnostics not already covered by local-header, timestamp, encryption, archive-extra, compression-method, raw creator-host policy, and generated creator-host preflights.
