# Pandoc LaTeX Note Anchors And Endnote Grouping - 2026-06-25

Hook bead: `plib-gq8fu`.

Scope: close the native PHP LaTeX note follow-up for stable note/backlink
anchors, duplicate label de-duplication, generated fallback anchors, and bounded
grouped endnotes. This work does not invoke Pandoc, TeX engines, browsers, Node
tooling, live providers, or external validators.

## Implementation

`LatexWriter` now assigns stable `fn-*` and `fnref-*` anchors to generated and
labelled notes. Duplicate source labels are de-duplicated in source order and
reported as LaTeX comments at the end of the rendered document, preserving the
source label relationship without emitting conflicting anchors.

When the `groupEndnotes` option is enabled, notes marked as endnotes through
`noteClass`, `sourceType`, `noteType`, or `cslNoteType` render as grouped
`\endnote{...}` entries with the same anchor handling. Documents that emit
grouped endnotes append `\theendnotes` once. Existing styled-inline note
splitting behavior is preserved, with Markdown-derived tests updated for the
new anchored LaTeX output.

## Verification

- `php -l lanes/pandoc/src/LatexWriter.php`
- `php -l lanes/pandoc/tests/LatexWriterTest.php`
- `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php lanes/pandoc/tests/JsonReaderWriterTest.php lanes/pandoc/tests/LatexWriterTest.php lanes/pandoc/tests/MediaBagTest.php`
  - 4 files, 4,870 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  - 15 files, 22,873 assertions, 0 failures.
