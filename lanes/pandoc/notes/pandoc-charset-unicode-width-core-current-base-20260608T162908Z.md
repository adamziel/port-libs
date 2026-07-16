# pandoc-charset-unicode-width-core-current-base-20260608T162908Z

## Scope

Lane: `pandoc`

Accepted base: `f7bb0ce56c95f19eaed5b64a386c252d4eb5269a`

This slice extends the native charset/Unicode width helper for environments
where PHP `IntlChar` is unavailable. `UnicodeText` now treats Telugu, Kannada,
Malayalam, Sinhala, and Lao vowel/diacritic marks as bounded zero-width cluster
extenders in the fallback mark table. The existing Thai/Lao SARA AM spacing
path remains unchanged, so those clusters keep their accepted two-column
display behavior.

## Source Truth

The behavior follows the existing Pandoc-style display-column contract already
implemented in this lane: combining and spacing vowel signs used to compose
visible South and Southeast Asian syllables should not add extra columns or be
cut away from their base character during split/wrap operations. This patch
reuses the accepted `UnicodeText` bounded fallback design instead of adding a
new parser or external dependency.

## Evidence

- `php -l lanes/pandoc/src/UnicodeText.php`
- `php -l lanes/pandoc/tests/UnicodeTextTest.php`
- `php -l lanes/pandoc/examples/wordpress-charset-unicode-handoff.php`
- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 895 assertions, 0 failures`
- `php lanes/pandoc/examples/wordpress-charset-unicode-handoff.php --self-test`
  - Result: `charset unicode handoff self-test ok`
- `php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " json ok\n"; }'`
  - Result: both lane-local JSON files decoded successfully
- `git diff --check -- lanes/pandoc`
  - Result: passed

Root harness was not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. The slice reuses `UnicodeText`,
`MarkdownReader`, `MarkdownWriter`, and `WordPressBlockWriter`. No Pandoc,
Cabal/Haskell runner, external charset converter, browser renderer, online
service, live provider test, or live-service provider test was executed.

## Non-Overlap

This does not repeat accepted byte-decoder slices, emoji/variation/ZWJ/tag
width handling, East Asian wide and ambiguous width slices, Hangul Jamo, Indic
virama, Myanmar/Khmer conjunct, Thai/Lao SARA AM, Tibetan tsheg wrapping, or
format/default-ignorable control handling. The new implementation is limited to
fallback Unicode mark ranges for Telugu, Kannada, Malayalam, Sinhala, and Lao
vowel/diacritic clusters plus the matching focused test and WordPress audit row.

## Follow-Up

Next charset/Unicode work should choose a distinct width or byte-decoding gap,
such as additional fallback mark ranges, residual malformed-byte repair, or
writer alignment behavior.
