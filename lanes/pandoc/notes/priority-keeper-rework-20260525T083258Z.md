# Pandoc Priority Keeper Rework - 2026-05-25

## Rework Target

The deferred `port-pandoc-20260525T071643Z` handoff conflicted with newer
accepted Markdown writer evidence in:

- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
- `lanes/pandoc/lane-status.json`
- `lanes/pandoc/tests/MarkdownReaderTest.php`

This worktree is based on accepted HEAD
`29038ae5b44947b12926e29fb9b6c58eb47fefc7`. That base already contains the
rebased Space/SoftBreak/LineBreak Markdown writer behavior after the accepted
line-block/raw-block/inline-attribute slices, so this rework preserves the
current behavior and refreshes lane-local evidence without rewriting the
conflict-prone test block.

## Behavior Preserved

`MarkdownWriter` now maps Pandoc inline break nodes as native Markdown writer
output:

- `space` emits one literal source space.
- `softbreak` emits a physical Markdown newline inside the paragraph.
- `linebreak` emits Pandoc's hard-break marker, a backslash followed by a
  newline.

Focused tests cover direct paragraph output, nested emphasis/strong output, and
blockquote prefix rendering. The WordPress reviewer handoff example also emits
an explicit-space packet containing a soft newline and hard-break marker.

## Dependency Closure

No new support component is needed. This rework reuses the existing inline
renderer, blockquote renderer, block writer newline handling, and Markdown
review handoff example. No DOCX/OpenXML, legacy DOC/CFB, PDF, EPUB, ODT,
citation, math, YAML/JSON metadata, archive, compression, Unicode, or charset
support row is activated by this slice.

## Focused Verification

Completed focused verification for the fresh handoff:

- `php -l lanes/pandoc/src/MarkdownWriter.php` - passed.
- `php -l lanes/pandoc/tests/MarkdownReaderTest.php` - passed.
- `php -l lanes/pandoc/examples/wordpress-markdown-review-handoff.php` -
  passed.
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` - passed,
  1 test file, 2,291 assertions, 0 failures.
- `php lanes/pandoc/examples/wordpress-markdown-review-handoff.php` - passed;
  output includes the explicit-space reviewer packet, soft newline, and Pandoc
  hard-break marker.
- `php -r 'json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
  - passed.
- `git diff --check -- lanes/pandoc` - passed.

Root harness status: not run - isolated micro-slice.

## Next Task

Map another bounded Markdown writer branch after Space/SoftBreak/LineBreak
inline emission and line-block emission, such as multi-block table-cell
fallback, table span degradation policy, or additional raw block format
variants with native upstream fixture parity.
