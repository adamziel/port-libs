# EPUB XHTML image input package-resource handoff

Slice: `pandoc-epub3-xhtml-image-input-resource-20260610T1701Z`

This slice extends the native EPUB3 package reader so XHTML `input` elements with
`src` attributes are included in package resource scanning. Image submit controls
now preserve their package-local source image as an `input-image`
`form-image-control` embedded resource while keeping existing inert form
side-effect metadata for reviewer handoff.

No new support component is needed. The implementation reuses `ZipPackage`,
the existing XHTML content resource scanner, package-reference resolution, and
the in-memory EPUB fixture builder.

Focused evidence:

```text
php -l lanes/pandoc/src/EpubReader.php
No syntax errors detected in lanes/pandoc/src/EpubReader.php

php -l lanes/pandoc/tests/EpubReaderTest.php
No syntax errors detected in lanes/pandoc/tests/EpubReaderTest.php

php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php
1 test files, 4014 assertions, 0 failures

php tools/run-tests.php lanes/pandoc/tests
44 test files, 60844 assertions, 0 failures
```

Accounting:

- `lane-status.json` `phpPass`: `2991 -> 2992`
- `lane-status.json` `phpFail`: `0`
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `3148 -> 3149`
- Added 1 focused EPUB XHTML image-input resource case.

No Pandoc, Cabal/Haskell runner, EPUBCheck, `zip`/`unzip`, browser renderer,
external validator, online service, live provider test, or live-service provider
test was executed.
