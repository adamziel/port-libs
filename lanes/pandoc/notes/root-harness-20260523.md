# pandoc Root Harness Gate - 2026-05-23

- Focused lane lint passed:
  - `php -l lanes/pandoc/src/MarkdownReader.php`
  - `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
- Focused lane tests passed:
  - `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: 1 test file, 2,167 assertions, 0 failures
- Root harness was not started because the required duplicate-root gate found
  active root harness processes:
  - PID `2552688`, user `claude`, PPID `2539412`, elapsed `00:26`, state `Rs`,
    command `php tools/run-tests.php`
  - PID `2552780`, user `claude`, PPID `2509052`, elapsed `00:25`, state `Ss`,
    command `php tools/run-tests.php`

- A later duplicate-root gate was clear, so this lane worker ran the root
  harness:
  - `php tools/run-tests.php`
  - Result: 200 test files, 22,731 assertions, 0 failures

## Nested-Dollar Inline Math Slice

- Focused lane lint passed:
  - `php -l lanes/pandoc/src/MarkdownReader.php`
  - `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
- Focused lane tests passed:
  - `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: 1 test file, 2,176 assertions, 0 failures
- Root harness was not started because the required duplicate-root gate found
  an active root harness process:
  - PID `2613382`, user `claude`, PPID `2613380`, elapsed `00:19`, state `R`,
    command `php tools/run-tests.php`

## Raw HTML Before Header And Commented List Slice

- Focused lane lint passed:
  - `php -l lanes/pandoc/src/MarkdownReader.php`
  - `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
- Focused lane tests passed:
  - `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: 1 test file, 2,199 assertions, 0 failures
- Required duplicate-root gate was clear:
  - `pgrep -af '^php tools/run-tests\.php( |$)'`
  - Result: no active root harness processes
- Root harness was run once and failed outside this lane:
  - `php tools/run-tests.php`
  - Result: 202 test files, 23,114 assertions, 2 failures
  - Visible non-pandoc failure: `lanes/readability/tests/ArticleExtractorTest.php`
    test `maps Mozilla firefox-nightly-blog fixture with article-header rel
    author byline` expected `Mike Conley` and got `NULL`.
  - Pandoc tests passed inside the root run.
- Post-run duplicate-root sample found another active root harness:
  - PID `2656523`, user `claude`, PPID `2627884`, elapsed `00:50`, state `R`,
    command `php tools/run-tests.php`
  - No additional root run was started.

## Case-Insensitive Reference, Curly Quote, And Consecutive List Slice

- Focused lane lint passed:
  - `php -l lanes/pandoc/src/MarkdownReader.php`
  - `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
- Focused lane tests passed:
  - `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: 1 test file, 2,232 assertions, 0 failures
- Root harness was not started because the required duplicate-root gate found
  an active root harness process:
  - PID `2694170`, user `claude`, PPID `2667629`, elapsed `00:12`, state `Rs`,
    command `php tools/run-tests.php`

## Task-List Markdown And LaTeX Writer Slice

- Focused lane lint passed:
  - `php -l lanes/pandoc/src/MarkdownReader.php`
  - `php -l lanes/pandoc/src/WordPressBlockWriter.php`
  - `php -l lanes/pandoc/src/MarkdownWriter.php`
  - `php -l lanes/pandoc/src/LatexWriter.php`
  - `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
- Focused lane tests passed:
  - `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: 1 test file, 2,234 assertions, 0 failures
- Required duplicate-root gate was clear:
  - `pgrep -af '^php tools/run-tests\.php( |$)'`
  - Result: no active root harness processes
- Final root harness run passed:
  - `php tools/run-tests.php`
  - Result: 204 test files, 23,553 assertions, 0 failures

## Markdown Writer Fancy Ordered-List Marker Slice

- Focused lane lint passed:
  - `php -l lanes/pandoc/src/MarkdownWriter.php`
  - `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
- Focused lane tests passed:
  - `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: 1 test file, 2,238 assertions, 0 failures
- Required duplicate-root gate:
  - `pgrep -af '^php tools/run-tests\.php( |$)'`
  - First sample found focused lane PID `2835754`, command `php tools/run-tests.php lanes/syncthing/tests`.
  - Immediate exact-root sample found PID `2836374`, owner `claude`, PPID `2836373`, elapsed `00:07`, state `R`, command `php tools/run-tests.php`.
  - Later sample found exact-root PID `2858105`, owner `claude`, PPID `2833947`, elapsed `00:24`, state `R`, command `php tools/run-tests.php`.
- This lane did not start a duplicate root harness while those processes were active.
- A final duplicate-root gate was clear, so this lane worker ran the root harness once:
  - `php tools/run-tests.php`
  - Result: 205 test files, 23,807 assertions, 0 failures
