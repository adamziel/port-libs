# Markdown writer inline-note citation fixture

Slice: `markdown-writer-inline-note-citation-fixture-20260701`

## Summary

This slice adds writer-side coverage for the existing upstream-mapped
`upstream-markdown-inline-note-citations.md` fixture.

- Adds three focused `MarkdownWriter` cases that mirror the fixture lines for
  citations, links with titles, author-in-text citation suffixes, code spans,
  and citation groups inside note bodies.
- Confirms the writer's current Markdown note form as footnote definitions, then
  round-trips those definitions back through `MarkdownReader` and verifies the
  note-body inline node structure.
- Updates `UPSTREAM_TEST_MANIFEST.json` mapped accounting:
  `mappedMarkdownWriterInlineNoteCitationFixtureCases: 3`,
  `markdownWriterInlineNoteCitationFixtureAssertions: 25`, and mapped
  denominator `2883 -> 2886`.

No Markdown writer behavior changed in this slice.

## Verification

- `php -l lanes/pandoc/tests/MarkdownWriterInlineNoteCitationFixtureCompletionTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownWriterInlineNoteCitationFixtureCompletionTest.php`
  passed: 1 file, 25 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownWriterInlineNoteCitationFixtureCompletionTest.php lanes/pandoc/tests/MarkdownReaderInlineNoteCitationFixtureTest.php lanes/pandoc/tests/MarkdownWriterCitationSpanBoundaryFixtureCompletionTest.php`
  passed: 3 files, 97 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests` was run for the full Pandoc
  lane gate and remains red outside this slice: 535 files, 142319 assertions,
  8912 failures. The new inline-note citation writer fixture cases passed in
  that run.

No Pandoc executable, upstream Haskell runner, Cabal build/test command, browser
renderer, office suite, TeX engine, external validator, online service, Node
tooling, or zip/unzip command was used for this slice.
