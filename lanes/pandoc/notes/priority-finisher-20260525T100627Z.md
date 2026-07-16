# Pandoc Priority Finisher - 2026-05-25 10:06 UTC

## Rework Target

This isolated lane started from accepted HEAD
`fcf9904d3550fb443193e37cc57858aa92844701` and found three Pandoc lane rework
markers in the main handoff directory:

- `port-pandoc-20260525T071643Z.needs-lane-rework.md`
- `port-pandoc-rework-20260525T083948Z.needs-lane-rework.md`
- `port-pandoc-finisher-20260525T092827Z.needs-lane-rework.md`

The first two markers describe the stale Markdown writer `Space`,
`SoftBreak`, and `LineBreak` patch conflict. The third marker describes a
newer stale finisher patch that only failed against `lane-status.json`.
Current accepted HEAD already contains the Space/SoftBreak/LineBreak
implementation, the focused tests, and a later table-span Markdown writer
slice. This rework therefore stays additive: it refreshes lane-local evidence
without replaying obsolete manifest or status text over the accepted table-span
metadata.

## Behavior Preserved

The native `MarkdownWriter` still maps the bounded upstream
`Text.Pandoc.Writers.Markdown.Inline` behavior:

- `space` emits one literal Markdown source space.
- `softbreak` emits a physical Markdown newline.
- `linebreak` emits Pandoc's hard-break marker, a backslash followed by a
  newline.

The accepted table span degradation slice is also preserved. Pipe-table output
cannot represent `rowspan` or `colspan` structurally, so the writer expands
spanned cells into rectangular rows with empty covered-cell placeholders while
keeping the source content in the first covered cell.

## WordPress Smoke

The existing `wordpress-markdown-review-handoff.php` example remains the
WordPress-visible path for this behavior. Its reviewer spacing packet includes
an explicit `space`, a `softbreak`, and a `linebreak`, which allows Data
Liberation review Markdown to keep intentional source boundaries without an
external converter.

## Dependency Closure

No new support component is needed. This rework reuses the existing Markdown
inline renderer, blockquote/list newline handling, table AST, pipe-table
width/alignment logic, caption renderer, and table-cell escaping. It does not
activate DOCX/OpenXML, legacy DOC/CFB, PDF, EPUB/ODT, citation, math,
YAML/JSON metadata, archive, compression, Unicode, or charset support rows.

## Verification

Focused verification for this worktree:

- `php -l lanes/pandoc/src/MarkdownWriter.php` - passed.
- `php -l lanes/pandoc/tests/MarkdownReaderTest.php` - passed.
- `php -l lanes/pandoc/examples/wordpress-markdown-review-handoff.php` -
  passed.
- `php -r 'json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
  - passed.
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` - passed,
  1 test file, 2,293 assertions, 0 failures.
- `php lanes/pandoc/examples/wordpress-markdown-review-handoff.php | rg -n "Reviewer spacing packet|hard boundary follows|next reviewer line"`
  - passed; emitted the explicit-space reviewer packet, soft newline, and
  hard-break marker.
- `git diff --check -- lanes/pandoc` - passed.

Root harness status: not run - isolated micro-slice.

## Next Task

Map another bounded Markdown writer branch after table span degradation, such
as multi-block table-cell fallback or additional raw block format variants with
native upstream fixture parity.
