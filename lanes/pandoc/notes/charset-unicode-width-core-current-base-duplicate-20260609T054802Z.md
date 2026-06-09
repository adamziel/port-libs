# KOI8-RU charset/unicode-width handoff

Slice: `pandoc-charset-unicode-width-core-current-base-duplicate-20260609T054802Z`
Base accepted HEAD: `2c84ca27878846c6b3725d422a6af783d4bbe9c7`

## Behavior

- `UnicodeText::decodeBytes()` now recognizes `koi8-ru` plus the `cskoi8ru`/`koi8russianukrainian` labels.
- The KOI8-RU overlay maps the Ukrainian and Belarusian byte positions from the local Tcl table at `/usr/share/tcl9.0/encoding/koi8-ru.enc`, including `0xAE => U+045E` and `0xBE => U+040E`.
- The focused test keeps KOI8-RU distinct from accepted KOI8-U behavior by checking that KOI8-U still decodes `0xAE/0xBE` as box-drawing glyphs.
- The WordPress charset handoff example now includes a KOI8-RU audit row with source-encoding metadata and narrow/wide display-width counts.

## Evidence

- Baseline before this patch: `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php` passed with `1 test files, 1495 assertions, 0 failures`.
- Red-first after adding the focused test: `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php` failed with `1 test files, 1496 assertions, 1 failures` because `koi8-ru` fell back to `utf-8-repaired`.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php` passed with `1 test files, 1507 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-charset-unicode-handoff.php --self-test` passed with `charset unicode handoff self-test ok`.

Root harness: not run - isolated micro-slice.

## Dependency closure

No new support component is needed. This slice reuses `UnicodeText`, `MarkdownReader`, and `WordPressBlockWriter`, and ports the bounded KOI8-RU single-byte overlay directly into the existing native charset decoder. No Pandoc runner, Cabal/Haskell runner, office tool, zip/unzip, external converter, TeX/PDF engine, browser renderer, online service, live provider test, or live-service provider test was used.

## Non-overlap

This does not repeat accepted KOI8-R, KOI8-U, KOI8-T, Mac Cyrillic/Ukrainian, ISO-2022, GB, Big5, EUC-JP, EUC-KR, Windows codepage, or Unicode display-width slices. The new behavior is only the KOI8-RU Belarusian/Ukrainian overlay and its WordPress charset handoff audit.

## Follow-up

Next charset/unicode-width work should target a non-overlapping direct native mapping source, such as Big5-HKSCS or EUC-TW only if a usable Unicode table is available locally, or a distinct display-width edge not already covered.
