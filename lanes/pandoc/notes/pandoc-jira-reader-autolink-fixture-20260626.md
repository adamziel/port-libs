# Pandoc Jira Reader Autolink Fixture Slice

2026-06-26 UTC

## Source Truth

- Pinned upstream commit: `912bfa5e2e3f5c74eb125dfc19404f67c61ca58b`.
- Upstream source: `src/Text/Pandoc/Readers/Jira.hs`.
- Upstream fixture: `test/jira-reader.jira`.
- The larger fixture includes bare `https`, `http`, and `mailto` autolinks in
  paragraphs, lists, and block quotes, plus a code-block example where the same
  URL-shaped text remains literal.
- `Text.Pandoc.Readers.Jira` maps `Jira.AutoLink` to a Pandoc `Link` whose
  visible text is the URI.

## Native PHP Status

- `PortLibs\Pandoc\JiraReader` now recognizes bare explicit-scheme autolinks at
  inline boundaries and preserves their URI as both link target and visible
  text.
- Terminal sentence punctuation is left outside the target.
- Explicit bracketed Jira link labels keep their label text; the autolink pass
  is disabled while parsing those labels to avoid nested links.
- Code blocks continue to bypass inline parsing, so fixture URL-shaped code text
  remains literal.
- Jira remains a partial reader; this slice does not claim full
  `jira-reader.jira/native` parity or move registry denominators.

## Verification

- `php -l lanes/pandoc/src/JiraReader.php`
- `php -l lanes/pandoc/tests/JiraReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/JiraReaderTest.php`
  - 1 test file, 79 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/JiraReaderTest.php lanes/pandoc/tests/PandocFormatRegistryTest.php lanes/pandoc/tests/PandocConverterTest.php`
  - 3 test files, 380 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 287 test files, 115348 assertions, 9848 failures
  - Full lane remains red outside the touched Jira slice, including existing
    DocBook reader, HTML writer, LaTeX writer, Markdown surge, Unicode text,
    and YAML metadata provenance failures.
