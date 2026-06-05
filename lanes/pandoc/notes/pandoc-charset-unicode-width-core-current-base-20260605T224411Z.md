# Pandoc Charset/Unicode Width Core - Myanmar and Khmer Conjuncts

Slice: `pandoc-charset-unicode-width-core-current-base-20260605T224411Z`
Base: `ee26489bdb651a4b12ce158e3b8859ff31df6834`

## Change

- Extended the bounded `UnicodeText` grapheme/linker tables so Myanmar U+1039
  virama and Khmer U+17D2 coeng consonant stacks stay intact during display
  slicing.
- Counted the bounded Myanmar and Khmer consonant stacks as one display cell
  for Markdown table padding and WordPress review wrapping.
- Added the behavior to the WordPress charset handoff smoke as a
  `Myanmar/Khmer conjuncts` audit row.
- Updated the pandoc manifest/status counters for one mapped native
  charset/Unicode display-width case.

## Source Truth

The bounded source truth is Unicode text segmentation for extended grapheme
clusters: UAX #29 GB9c keeps certain `Indic_Conjunct_Break` consonant-linker-
consonant combinations inside one grapheme cluster. UAX #11 also records that
display-width decisions for fixed-pitch layout need character-width tailoring
and should not treat combining marks independently from their base characters.

- https://unicode.org/reports/tr29/
- https://www.unicode.org/reports/tr11/

No current-base pandoc rework note was present. No Pandoc, Cabal solver/build/
test command, Haskell runner, external charset converter, browser renderer,
online sanitizer, online service, or live provider test was executed.

## Red-First Evidence

Before implementation:

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 462 assertions, 1 failures`
  - Failure: the new Myanmar virama stack assertion expected display width `1`
    but received `2`.

## Verification

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 471 assertions, 0 failures`
  - Delta: `+1` focused PASS case / `+10` assertions.
- `php lanes/pandoc/examples/wordpress-charset-unicode-handoff.php --self-test`
  - Result: `charset unicode handoff self-test ok`
- `php -l lanes/pandoc/src/UnicodeText.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-charset-unicode-handoff.php`
  - Result: no syntax errors.
- `php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " json ok\n"; }'`
  - Result: both pandoc JSON files decoded successfully.
- `git diff --check -- lanes/pandoc`
  - Result: passed with no output.

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses the existing native PHP
`UnicodeText` grapheme, display-width, padding, wrapping, `MarkdownWriter`, and
WordPress charset handoff paths. Full Unicode property-table ingestion,
broader `Indic_Conjunct_Break` coverage, HTML/XML charset sniffing,
terminal-profile-specific emoji width variants, and full upstream Pandoc
Haskell runner parity remain separate bounded follow-up work.

## Non-Overlap

This does not overlap accepted UTF-8 repair, UTF-16/UTF-32 BOM handling,
single-byte legacy encodings, Shift_JIS/Windows-31J, EUC-JP, ISO-2022-JP,
Big5, GBK, EUC-KR, HZ-GB-2312, Unicode normalization, emoji presentation and
tag/ZWJ clusters, supplementary East Asian wide ranges, ambiguous-width policy,
Unicode separator wrapping, default-ignorable controls, prepended
format-control zero-width accounting, Devanagari/Bengali/Tamil virama
clusters, Markdown/HTML reader behavior, table geometry, DOCX/ODF/EPUB/PDF,
syntax-highlighting, CSL/BibTeX, YAML, doctemplate, ZIP/OPC, or
upstream-runner dependency audit slices.
