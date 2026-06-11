# CSL Direct Short Volume Title Aliases Slice

Bead: `plib-t2zy1`
Date: 2026-06-11 UTC
Base: origin/main 8086676050

Implemented a bounded native PHP CSL citation/bibliography slice in
`CitationCslProcessor`:

- Direct CSL JSON now normalizes `shortvolumetitle`, `short-volume-title`, and
  `shortVolumeTitle` into canonical `volumeTitleShort` metadata.
- CSL text variable rendering now accepts `short-volume-title` and
  `shortvolumetitle` aliases alongside `volume-title-short`.
- Canonical `volume-title` `form="short"` rendering remains backed by
  `volumeTitleShort`.
- Raw direct CSL JSON alias provenance remains available on normalized items.

Verification:

- `php -l lanes/pandoc/src/CitationCslProcessor.php`
- `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php` -> 1 test file, 4942 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests` -> 44 test files, 67342 assertions, 0 failures

Accounting:

- `phpPass` 3145 -> 3146
- mapped denominator 3221 -> 3222
- `mappedDirectCslShortVolumeTitleAliasCases`: 1
- `directCslShortVolumeTitleAliasAssertions`: 16

No Pandoc, citeproc, BibTeX, Biber, bibliography manager, browser renderer,
external validator, online service, live provider test, or live-service
provider test was invoked.
