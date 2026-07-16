# Pandoc Priority Finisher - 2026-05-25

## Rework Target

This isolated lane started from accepted HEAD
`9828ac923ce0cc62487799769d3aa6859e3669b1` after two stale Pandoc handoffs
were marked for lane rework:

- `port-pandoc-20260525T071643Z.needs-lane-rework.md`
- `port-pandoc-rework-20260525T083948Z.needs-lane-rework.md`

Both markers point to the same conflicted behavior slice: Markdown writer
`Space`, `SoftBreak`, and `LineBreak` inline emission. The current accepted base
already contains the rebased implementation and focused tests, so this finisher
handoff preserves the accepted manifest/status evidence and refreshes the
lane-local note/status on top of the current base instead of replaying the stale
patch.

## Behavior Preserved

The native `MarkdownWriter` keeps the bounded upstream writer behavior from
`Text.Pandoc.Writers.Markdown.Inline`:

- `space` emits one literal source space.
- `softbreak` emits a physical Markdown newline.
- `linebreak` emits Pandoc's hard-break marker, a backslash followed by a
  newline.

Focused tests cover the constructors at top-level paragraph scope, inside
recursive emphasis/strong inline rendering, and after blockquote line prefixing.
The WordPress Markdown reviewer handoff example also emits an explicit spacing
packet with the soft newline and hard-break marker.

## Dependency Closure

No new support component is needed. This finisher reuses the existing Markdown
inline renderer, blockquote renderer, delimiter helpers, block writer newline
handling, and WordPress Markdown review handoff example. No DOCX/OpenXML,
legacy DOC/CFB, PDF, EPUB/ODT, citation, math, YAML/JSON metadata, archive,
compression, Unicode, or charset support row is activated.

## Focused Verification

Focused verification was rerun from the current accepted base:

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

Root harness status: not run - isolated micro-slice.

## Next Task

Map another bounded Markdown writer branch after the accepted
Space/SoftBreak/LineBreak and line-block evidence, such as multi-block
table-cell fallback, table span degradation policy, or additional raw block
format variants with native upstream fixture parity.
