# Pandoc CommonMark Raw Pre Boundary: Superseded MR

## Scope

- `plib-5xy2` proposed preserving non-code CommonMark `<pre>` blocks until their closing `</pre>` tag.
- Current `main` already contains that behavior and a broader focused test from `plib-3yj8`.
- The retained coverage keeps `<pre><code>` on the structured code-block path while non-code `<pre>` remains raw HTML through internal blank lines.

## Verification

- `php -l lanes/pandoc/src/MarkdownReader.php`
- `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 6398 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `42 test files, 58236 assertions, 0 failures`

## Decision

The worker test was not reapplied because it used the same PHP array key as the accepted test already on `main`, which would risk shadowing coverage rather than adding a distinct case.
