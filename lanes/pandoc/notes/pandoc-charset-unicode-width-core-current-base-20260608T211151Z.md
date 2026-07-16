# pandoc-charset-unicode-width-core-current-base-20260608T211151Z

Slice: `pandoc-charset-unicode-width-core-current-base-20260608T211151Z`
Lane: `pandoc`
Base accepted HEAD: `26bbd2b7e4199c593e970e19e2909436056056d0`

## Behavior

- Added bounded IBM869/CP869 DOS Greek byte decoding to `UnicodeText`.
- Added `cp869`, `csibm869`, `dos869`, `oem869`, `xcp869`, and `cpgr`
  aliases to the native charset label normalizer.
- Preserved official CP869 undefined high bytes as replacement-character
  repairs instead of silently inheriting another DOS code page.
- Extended the WordPress charset handoff example with an IBM869 audit row that
  records source encoding and display-width values.

Source truth: Unicode public vendor mapping
`https://www.unicode.org/Public/MAPPINGS/VENDORS/MICSFT/PC/CP869.TXT`
(`cp869_DOSGreek2 to Unicode table`, table version 2.00, dated 1996-04-24).

## Non-Overlap

This does not repeat the existing ISO-8859-7 Greek, Windows-1253 Greek,
Mac Greek, IBM866 Cyrillic, IBM860 Portuguese, IBM437 box drawing, IBM850,
IBM852, IBM863, or IBM865 slices. It adds a separate DOS Greek 2 code page with
undefined-byte repair accounting and WordPress audit-row coverage.

No Pandoc, Cabal solver/build/test command, Haskell runner, external charset
converter, browser renderer, online service, live provider test, or
live-service provider test was executed.

## Red-First

- Baseline: `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  passed with `1 test files, 1038 assertions, 0 failures`.
- After adding the CP869 focused test before implementation:
  `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php` failed with
  `1 test files, 1039 assertions, 1 failures` because `cp869` fell back to
  `utf-8-repaired`.

## Verification

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 1053 assertions, 0 failures`.
- `php lanes/pandoc/examples/wordpress-charset-unicode-handoff.php --self-test`
  - Result: `charset unicode handoff self-test ok`.
- `php -l lanes/pandoc/src/UnicodeText.php`
  - Result: `No syntax errors detected in lanes/pandoc/src/UnicodeText.php`.
- `php -l lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `No syntax errors detected in lanes/pandoc/tests/UnicodeTextTest.php`.
- `php -l lanes/pandoc/examples/wordpress-charset-unicode-handoff.php`
  - Result: `No syntax errors detected in lanes/pandoc/examples/wordpress-charset-unicode-handoff.php`.
- `php -r 'json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
  - Result: `pandoc json ok`.
- `git diff --check -- lanes/pandoc`
  - Result: no whitespace errors.

## Dependency Closure

No new support component is needed. The slice reuses `UnicodeText`,
`MarkdownReader`, `WordPressBlockWriter`, and the existing focused charset
tests/example.

Full upstream Pandoc runner parity remains outside this isolated micro-slice.
Next charset/Unicode work should choose a non-overlapping byte-decoding or
display-width gap, such as another bounded legacy DOS/Mac label,
charset-sniffing handoff, or Unicode width cluster not already covered by the
existing charset slices.
