2026-06-10 UTC shared ZIP/OPC raw comment preflight slice.

- Scope: `ZipPackage::rawCommentPreflight()` and `rawStrictImportPreflight()` expose raw package comments and central-directory entry comments before package construction succeeds.
- Fixture: duplicate local header offsets block `ZipPackage::fromString()`, while raw preflight still reports package comment control bytes, entry comment control bytes, Unicode bidi comment metadata, and `rawComments` diagnostics for reviewer handoff.
- Verification: `php -l lanes/pandoc/src/ZipPackage.php`; `php -l lanes/pandoc/tests/ZipPackageTest.php`; `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php` -> 1 file / 2968 assertions / 0 failures; `php tools/run-tests.php lanes/pandoc/tests` -> 44 files / 60351 assertions / 0 failures.
- External tools not run: Pandoc, office suites, `zip`/`unzip`, Cabal/Haskell runners, TeX/PDF engines, browser renderers, external validators, online services, live provider tests, and live-service provider tests.
