# Pandoc Charset Unicode Width Core - CP437 Source Bytes

Slice: `pandoc-charset-unicode-width-core-current-base-20260608T093818Z`
Base accepted HEAD: `f7295ca99962e4a2fb64b25159ca465a0c1f3909`

## Behavior

- Added bounded native CP437/IBM437 source-byte decoding in `UnicodeText::decodeBytes()`.
- Normalizes `cp437`, `ibm437`, `dos437`, `oem437`, `cspc8codepage437`, and related aliases to canonical `ibm437`.
- Decodes DOS box-drawing bytes, accented Latin bytes, Greek/math symbols, and CP437 no-break-space before MarkdownReader and WordPressBlockWriter handoff.
- Extends the WordPress charset audit example with an `IBM437 source` row: `Box ╔═╗║╠; résumé; αß °±.` with narrow/wide widths `25/36`.

## Source Truth

The mapping follows the lane's existing bounded CP437 support used for ZIP/package names and comments, expressed as codepoint integers for source byte decoding. No Pandoc runner, Haskell runner, Cabal build/test, external charset converter, browser renderer, online service, live provider test, or live-service provider test was executed.

## Red-First Evidence

- Baseline: `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - `1 test files, 804 assertions, 0 failures`
- Red-first after adding the focused test, before implementation:
  - `1 test files, 805 assertions, 1 failures`
  - Expected canonical `ibm437`; actual path was `utf-8-repaired` with replacement characters.
- Final focused test:
  - `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - `1 test files, 816 assertions, 0 failures`

## Verification

- `php -l lanes/pandoc/src/UnicodeText.php`
- `php -l lanes/pandoc/tests/UnicodeTextTest.php`
- `php -l lanes/pandoc/examples/wordpress-charset-unicode-handoff.php`
- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
- `php lanes/pandoc/examples/wordpress-charset-unicode-handoff.php --self-test`
- `php -r 'foreach (["lanes/pandoc/lane-status.json","lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " json ok\n"; }'`
- `git diff --check -- lanes/pandoc`

Final results: PHP lint passed, focused UnicodeText test passed at `1 test files, 816 assertions, 0 failures`, charset example smoke printed `charset unicode handoff self-test ok`, JSON validation passed, and diff whitespace check passed.

## Dependency Closure

No new support component is needed. This slice reuses `UnicodeText`, `MarkdownReader`, `WordPressBlockWriter`, the existing charset example, and focused lane tests.

## Non-Overlap

This slice is distinct from accepted IBM866, ISO-8859, Windows code page, KOI8, Japanese, Chinese, Korean, emoji/display-width, and ZIP CP437 entry-name/comment slices. It specifically covers CP437/IBM437 Markdown source byte decoding and WordPress charset audit handoff.
