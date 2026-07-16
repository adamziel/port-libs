# Pandoc ZIP Package Core Current Base - Name Hygiene

Slice: `pandoc-shared-zip-package-core-current-base-20260608T095026Z`
Base accepted HEAD: `2d73d97058438bddda69f12958834d59c4b7c86c`

## Behavior

Implemented a strict ZIP package entry-name hygiene preflight for package handoff:

- `ZipPackage::nameHygienePreflight()` reports entry path segments with leading/trailing whitespace or trailing-dot segments.
- `ZipPackage::assertNoNameHygieneReviewEntries()` rejects those names before strict import.
- `ZipPackage::strictImportPreflight()` now emits `name-hygiene-review-entries` and includes the `nameHygiene` summary.

Raw ZIP reading remains permissive for syntactically valid ZIP names, including ordinary internal spaces such as `word/media/review image.png`. The new policy only blocks strict native Office/EPUB/ODT-style media handoff when names are likely to produce ambiguous package part semantics.

## Non-Overlap

This does not repeat accepted ZIP central-directory signature provenance, archive extra data records, trailing-deflate integrity, Unicode/raw-name collision handling, DOS timestamp policy, ZIP64 handling, or OPC Pack URI validation. OPC part-name validation for raw whitespace/control and trailing-dot segments already lives in OPC package semantics; this slice adds the ZIP-level strict-import review needed before package readers hand raw archive names to those higher-level package contracts.

## Verification

Baseline before edits:

```sh
php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php
```

Result: `1 test files, 1391 assertions, 0 failures`.

Final focused verification:

```sh
php -l lanes/pandoc/src/ZipPackage.php
php -l lanes/pandoc/tests/ZipPackageTest.php
php -l lanes/pandoc/examples/wordpress-zip-package-preflight.php
php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php
php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test
```

Results:

- `No syntax errors detected` for all changed PHP files.
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`: `1 test files, 1426 assertions, 0 failures`.
- `php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test`: `zip package writer preflight self-test passed`.
- `php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $path) { ... }'`: both JSON files parsed successfully.
- `git diff --check -- lanes/pandoc`: passed with no output.

## Dependency Closure

No new support component is needed. This slice reuses the existing native `ZipPackage` parser/writer, strict import preflight, focused TestRunner coverage, and WordPress ZIP package preflight example. Pandoc, Word, LibreOffice, `zip`/`unzip`, `ZipArchive`, external archive tools, Cabal/Haskell runners, online services, live provider tests, and live-service provider tests were not executed.
