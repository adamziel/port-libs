# Pandoc Markdown Reader Inline Extension Profiles - 2026-06-28

Bead: `plib-wfa`

Scope:
- Gated `~~strikeout~~` parsing through Markdown format profile defaults and
  explicit `+strikeout`/`-strikeout` overrides.
- Gated bare URI autolink parsing through profile defaults and explicit
  `+bare_uri_autolinks`/`-bare_uri_autolinks` overrides.
- Added `www.example.test`-style bare URI recognition with `http://`
  destination normalization.
- Gated superscript, subscript, citations, dollar math, wikilinks,
  bracketed spans, raw inline attributes, and inline code attributes by their
  Markdown flavor defaults and explicit extension overrides.
- Parsed chained Markdown format suffix disables such as
  `markdown-emoji-strikeout-subscript-raw_tex` as separate extension flags.

Accounting:
- `phpPass`: `463 -> 464`
- `UPSTREAM_TEST_MANIFEST.json`
  `mappedMarkdownReaderStrikeoutBareUriProfileCases`: `0 -> 26`.
- `UPSTREAM_TEST_MANIFEST.json`
  `mappedMarkdownReaderFlavorExtensionProfileCases`: `0 -> 16`.

Validation:
- `jq empty lanes/pandoc/lane-status.json lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
- `php -l lanes/pandoc/src/MarkdownReader.php`
- `php -l lanes/pandoc/src/MarkdownFormatProfile.php`
- `php -l lanes/pandoc/tests/MarkdownReaderStrikeoutBareUriProfileCompletionTest.php`
- `php -l lanes/pandoc/tests/MarkdownReaderFlavorExtensionProfileCompletionTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderStrikeoutBareUriProfileCompletionTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderFlavorExtensionProfileCompletionTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderFlavorProfileSurgeTest.php`
