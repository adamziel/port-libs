# Pandoc Markdown Reader Emoji Extension Profile - 2026-06-28

Bead: `plib-q014`

Scope:
- Gated Markdown emoji shortcode parsing through the Markdown format profile.
- Enabled emoji for default `markdown`/`pandoc`, `commonmark_x`, `gfm`, and
  explicit `+emoji` overrides.
- Kept `commonmark`, strict, PHP Extra, MultiMarkdown, and `-emoji` profiles
  literal.
- Added the `:rocket:` alias used by the flavor profile probe.

Accounting:
- `phpPass`: `462 -> 463`
- `UPSTREAM_TEST_MANIFEST.json`
  `mappedMarkdownReaderEmojiExtensionProfileCases`: `0 -> 16`.

Validation:
- `jq empty lanes/pandoc/lane-status.json lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
- `php -l lanes/pandoc/src/MarkdownReader.php`
- `php -l lanes/pandoc/tests/MarkdownReaderEmojiExtensionProfileCompletionTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderEmojiExtensionProfileCompletionTest.php`
  - 1 file, 53 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderFlavorProfileSurgeTest.php`
  - still baseline-red on bare URI default/GFM profile gating and strikeout
    profile gating, with the previous emoji failures cleared.
