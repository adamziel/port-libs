# Pandoc Priority Keeper Rework - 2026-05-25 09:23 UTC

## Rework Target

This isolated lane started from accepted HEAD
`a3fa3df0175bb39daa4296f083898ddc9f5f4f5a` and rechecked the two Pandoc
handoff markers that still need lane-owner rework:

- `port-pandoc-20260525T071643Z.needs-lane-rework.md`
- `port-pandoc-rework-20260525T083948Z.needs-lane-rework.md`

Both markers describe stale patch application for the same Markdown writer
`Space`, `SoftBreak`, and `LineBreak` inline emission slice after newer Pandoc
manifest, status, and `MarkdownReaderTest.php` evidence landed. The accepted
worktree already contains the behavior and tests, so this rework is deliberately
additive: preserve the implementation and refresh lane-local evidence on the
current accepted base.

## Behavior Preserved

The native `MarkdownWriter` maps the bounded upstream
`Text.Pandoc.Writers.Markdown.Inline` behavior:

- `space` emits one literal Markdown source space.
- `softbreak` emits a physical Markdown newline.
- `linebreak` emits Pandoc's hard-break marker, a backslash followed by a
  newline.

Focused tests cover direct paragraph emission, recursive emphasis/strong
emission, and blockquote prefix handling. The WordPress Markdown review handoff
example includes the same explicit spacing packet for a reviewer-visible Data
Liberation path.

## Dependency Closure

No new support component is needed. This rework reuses the existing Markdown
inline renderer, blockquote renderer, delimiter helpers, block writer newline
handling, and WordPress Markdown review handoff example. It does not activate
DOCX/OpenXML, legacy DOC/CFB, PDF, EPUB/ODT, citation, math, YAML/JSON
metadata, archive, compression, Unicode, or charset support rows.

## Verification

Focused verification for this worktree:

- `php -l lanes/pandoc/src/MarkdownWriter.php` - passed.
- `php -l lanes/pandoc/tests/MarkdownReaderTest.php` - passed.
- `php -l lanes/pandoc/examples/wordpress-markdown-review-handoff.php` -
  passed.
- `php -r 'json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
  - passed.
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` - passed,
  1 test file, 2,291 assertions, 0 failures.
- `php lanes/pandoc/examples/wordpress-markdown-review-handoff.php | rg -n "Reviewer spacing packet|hard boundary follows|next reviewer line"`
  - passed; emitted the explicit-space reviewer packet, soft newline, and
  hard-break marker.
- `git diff --check -- lanes/pandoc` - passed.

Root verification was not run for this isolated micro-slice.

## Next Task

Map another bounded Markdown writer branch after the accepted
Space/SoftBreak/LineBreak and line-block evidence, such as multi-block
table-cell fallback, table span degradation policy, or additional raw block
format variants with native upstream fixture parity.
