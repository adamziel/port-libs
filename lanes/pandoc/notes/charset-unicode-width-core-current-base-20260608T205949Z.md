# Charset Unicode Width Core Current Base - IBM865/CP865

Slice: `pandoc-charset-unicode-width-core-current-base-20260608T205949Z`
Base accepted HEAD: `5d4304c18bb1f0b3ffb02f52a119f3462fac3ca7`

## Behavior

- Added native bounded IBM865/CP865/DOS865 byte decoding labels to `UnicodeText`.
- Reused the existing IBM850 DOS table for shared Nordic bytes and overrode the IBM865-specific `0xAF` byte to `¤`, preserving CP850 `0xAF` as `»`.
- Added a WordPress charset handoff row covering Danish, Norwegian, and Icelandic DOS Nordic text plus display-width accounting.

## Red-First Evidence

- Baseline before edits:
  - `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 1026 assertions, 0 failures`
- Red-first after adding the IBM865 focused test before implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 1027 assertions, 1 failures`
  - Failure was the expected fallback to `utf-8-repaired` instead of `ibm865`.

## Final Verification

- `php -l lanes/pandoc/src/UnicodeText.php`
  - `No syntax errors detected in lanes/pandoc/src/UnicodeText.php`
- `php -l lanes/pandoc/tests/UnicodeTextTest.php`
  - `No syntax errors detected in lanes/pandoc/tests/UnicodeTextTest.php`
- `php -l lanes/pandoc/examples/wordpress-charset-unicode-handoff.php`
  - `No syntax errors detected in lanes/pandoc/examples/wordpress-charset-unicode-handoff.php`
- `php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $f) { json_decode(file_get_contents($f), true, flags: JSON_THROW_ON_ERROR); echo $f, ": valid\n"; }'`
  - `lanes/pandoc/lane-status.json: valid`
  - `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json: valid`
- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - `1 test files, 1038 assertions, 0 failures`
- `php lanes/pandoc/examples/wordpress-charset-unicode-handoff.php --self-test`
  - `charset unicode handoff self-test ok`
- `git diff --check -- lanes/pandoc`
  - passed with no output

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. The slice reuses native PHP `UnicodeText` decoding, `MarkdownReader`, `MarkdownWriter`, and `WordPressBlockWriter` paths. No Pandoc, Cabal solver/build/test command, Haskell runner, external charset converter, browser renderer, online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This slice does not repeat accepted Shift_JIS, HZ-GB-2312, ISO-8859-3/7/8/9, Windows-1256/1258, IBM860, Indic/Myanmar/Khmer display-width, or Unicode GLOB/libsqlite work. It is limited to IBM865/CP865 source-byte decoding and WordPress charset audit coverage under `lanes/pandoc/**`.

## Next

Pick a non-overlapping charset or width gap, such as IBM861 Icelandic, IBM775 Baltic, CP862 Hebrew, or another display-width edge not already covered by current charset slices.
