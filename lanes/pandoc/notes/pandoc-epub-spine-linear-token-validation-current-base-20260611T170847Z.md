# Pandoc EPUB Spine Linear Token Validation Current Base 20260611T170847Z

## Scope

- Bead: plib-swo73, Pandoc EPUB3 package ingestion core blocker slice.
- Current base: 392b11a2e.
- Change: compact `EpubPackage` spine itemrefs now preserve raw `linear`
  token provenance as `linearRaw`, `linearSpecified`, and `linearValid`.
- Handoff behavior: invalid OPF `linear` values are still treated as primary
  reading-order entries, but `invalid-spine-linear-value` diagnostics are
  attached to the spine item and surfaced through package validation and
  WordPress review metadata.

## Verification

- `php -l lanes/pandoc/src/EpubPackage.php`
- `php -l lanes/pandoc/tests/EpubPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageTest.php`
  passed 1 file / 1246 assertions / 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests` passed 44 files / 64298
  assertions / 0 failures.

No Pandoc binary, EPUBCheck, Cabal/Haskell runner, zip/unzip, browser renderer,
external validator, online service, live provider test, or live-service
provider test was executed.
