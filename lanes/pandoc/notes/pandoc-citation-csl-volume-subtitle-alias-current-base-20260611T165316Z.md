# Pandoc CSL/BibLaTeX Volume Subtitle Alias Slice

Bead: `plib-ia2m1`
Base: `a886765f4`
Scope: `lanes/pandoc`

Implemented a bounded CSL/BibLaTeX parser slice for volume subtitle aliases. `BibtexCslParser` now composes `volumetitle` with `volumesubtitle` and `volume-title` with `volume-subtitle` into CSL `volume-title`, matching the established title-family composition behavior while preserving the raw BibLaTeX subtitle fields in `rawBibtex`.

Focused coverage: `CitationCslProcessorTest.php` now has an explicit PASS case for compact and hyphenated volume subtitle aliases, plus existing title-family/review-volume cases assert the composed volume titles and raw subtitle provenance.

Verification on current main `a886765f4`:

- `php -l lanes/pandoc/src/BibtexCslParser.php`
- `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php` - 1 file, 4731 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests` - 44 files, 65429 assertions, 0 failures
