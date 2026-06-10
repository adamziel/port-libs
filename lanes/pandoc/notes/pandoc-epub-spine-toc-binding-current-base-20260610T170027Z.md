# Pandoc EPUB Spine TOC Binding Slice - 2026-06-10T17:00:27Z

## Scope

- Bead: `plib-l1vk`
- Area: EPUB3 package ingestion core blocker
- Boundary: OPF package spine `toc` binding to legacy NCX navigation handoff

## Change

- `EpubPackageReader` now preserves `spine@toc` as `epub.spineTocId`.
- NCX selection now honors the OPF spine TOC binding when it points to an NCX manifest item.
- Added a two-NCX EPUB fixture proving the reader selects the bound NCX instead of the first manifest NCX.

## Evidence

- Red before implementation: `php tools/run-tests.php lanes/pandoc/tests/EpubPackageReaderTest.php` failed on missing `spineTocId`.
- `php -l lanes/pandoc/src/EpubPackageReader.php`
- `php -l lanes/pandoc/tests/EpubPackageReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageReaderTest.php lanes/pandoc/tests/EpubPackageTest.php lanes/pandoc/tests/EpubReaderTest.php` passed 3 files / 4883 assertions / 0 failures.
- Pre-rebase `php tools/run-tests.php lanes/pandoc/tests` passed 44 files / 60768 assertions / 0 failures.
- Post-rebase `php tools/run-tests.php lanes/pandoc/tests` passed 44 files / 60816 assertions / 0 failures.

No Pandoc binary, Cabal/Haskell runner, office suite, zip/unzip, browser renderer, EPUBCheck/external validator, online service, live provider, or live-service provider test was executed.
