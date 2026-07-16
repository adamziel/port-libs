# Markdown Reader Tab Marker Heading Completion

Scope: maps four upstream-style Markdown/CommonMark reader fixtures where a tab immediately after a bullet or ordered list marker starts list item content and can introduce an ATX heading either as the first child or after paragraph text.

Implementation: no parser code change was required. The existing MarkdownReader behavior now has focused coverage in `MarkdownReaderTabMarkerHeadingCompletionTest.php`, including WordPress list handoff assertions for heading-first and paragraph-then-heading list item children.

Accounting: `phpPass` moves 16852 -> 16856 and `phpFail` remains 0. `UPSTREAM_TEST_MANIFEST.json` root and upstream mapped counters move 16405 -> 16409, and `benchmarkDenominator.mapped` moves 3543 -> 3547. The slice adds `mappedMarkdownReaderTabMarkerHeadingCompletionCases = 4` and `markdownReaderTabMarkerHeadingCompletionAssertions = 51`.

Verification after rebase onto `origin/main` at `ec58db9363`:
- `php -l lanes/pandoc/tests/MarkdownReaderTabMarkerHeadingCompletionTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTabMarkerHeadingCompletionTest.php` (1 file, 51 assertions, 0 failures)
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTabMarkerHeadingCompletionTest.php lanes/pandoc/tests/MarkdownReaderBlocksSurgeTest.php lanes/pandoc/tests/MarkdownCommonMarkSurgeTest.php` (3 files, 5224 assertions, 0 failures)
- `php tools/run-tests.php lanes/pandoc/tests` (224 files, 171884 assertions, 0 failures)

No Pandoc, cmark/commonmark runners, Cabal/Haskell runners, browser renderers, Node tooling, external validators, online services, live provider tests, or live-service provider tests were invoked.
