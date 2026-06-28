# Pandoc Markdown Reader Mark Extension Profile - 2026-06-28

Scope: bounded native PHP MarkdownReader profile parity for the Pandoc
Markdown `mark` extension.

This slice parses `==marked==` into a `span` with class `mark` when the active
Markdown profile enables `mark`. Default Markdown/Pandoc and CommonMark-X
profiles enable it; CommonMark, GFM, strict, and explicit `-mark` profiles keep
the delimiters literal unless `+mark` or an enabled extension-array/map option is
provided.

It does not change the separate writer fallback policy for mark spans, nor the
remaining emoji and strikeout profile-gating backlog in
`MarkdownReaderFlavorProfileSurgeTest.php`.

Status movement:

- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
  `mappedMarkdownReaderMarkExtensionProfileCases`: `0 -> 13`.
- `lanes/pandoc/lane-status.json` `phpPass`: `460 -> 461`.

Validation:

- `php -l lanes/pandoc/src/MarkdownReader.php`
- `php -l lanes/pandoc/tests/MarkdownReaderMarkExtensionProfileCompletionTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderMarkExtensionProfileCompletionTest.php`
  - 1 file, 43 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderFlavorProfileSurgeTest.php`
  - still baseline-red on emoji and strikeout profile gates, with the previous
    mark failures gone
