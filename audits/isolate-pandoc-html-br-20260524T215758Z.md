# Pandoc HTML `<br>` Slice Isolation - 2026-05-24

## Worktree

- Main checkout: `/home/claude/port-libs`
- Detached clean worktree: `/tmp/port-isolate-pandoc-html-br-20260524T215758Z`
- Base commit: `30f1c09c9059e8b6ac4fbf5b295cda3060ec298c` (`Refresh independent audit status`)
- Patch artifact: `/home/claude/port-libs/.tmux-team/tmp/isolate-pandoc-html-br-20260524T215758Z.patch`

## Scope

Rebuilt only the standalone HTML `<br>` / Pandoc `LineBreak` reader slice from a clean detached `HEAD` worktree.

Touched files in the patch:

- `lanes/pandoc/src/MarkdownReader.php`
- `lanes/pandoc/tests/MarkdownReaderTest.php`
- `lanes/pandoc/fixtures/upstream-html-standalone-linebreak.html`
- `lanes/pandoc/examples/wordpress-native-html-standalone-linebreak-handoff.php`

Behavior isolated:

- Top-level standalone `<br>`, `<br/>`, and attributed `<br ...>` lines are routed through the existing DOM-backed HTML inline parser instead of becoming literal markdown text.
- DOM `br` nodes continue to map to existing `AstNode('linebreak')` inline nodes.
- HTML inline paragraph flushing now preserves a paragraph that contains only a `linebreak`, so standalone `<br/>` is not dropped as an empty paragraph.
- Focused test covers direct fragments, fixture input, AST linebreak nodes, and WordPress paragraph output with `<br/>`.

## Excluded Dirty-Main Changes

The isolation patch deliberately excludes dirty-main Pandoc changes outside this slice, including:

- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
- `lanes/pandoc/lane-status.json`
- `lanes/pandoc/notes/root-harness-20260523.md`
- `lanes/pandoc/notes/upstream-inventory.md`
- `lanes/pandoc/notes/wordpress-scenarios.md`
- `lanes/pandoc/src/LatexWriter.php`
- `lanes/pandoc/src/MarkdownWriter.php`
- `lanes/pandoc/src/WordPressBlockWriter.php`
- `lanes/pandoc/examples/wordpress-markdown-review-handoff.php`
- Broad dirty-main untracked Pandoc readers/writers/examples/fixtures unrelated to standalone HTML `<br>` parsing, including native writer/status/fixture/example work not required by this isolated slice.

No support-library rows were activated or edited. This remains lane-local HTML parsing.

## Commands

- `git worktree add --detach /tmp/port-isolate-pandoc-html-br-20260524T215758Z HEAD` from `/home/claude/port-libs`: exit `0`.
- `git rev-parse HEAD` from the detached worktree: exit `0`, output `30f1c09c9059e8b6ac4fbf5b295cda3060ec298c`.
- `php -l lanes/pandoc/src/MarkdownReader.php`: exit `0`.
- `php -l lanes/pandoc/tests/MarkdownReaderTest.php`: exit `0`.
- `php -l lanes/pandoc/examples/wordpress-native-html-standalone-linebreak-handoff.php`: exit `0`.
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`: exit `0`, result `1 test files, 2275 assertions, 0 failures`.
- `git diff --check -- lanes/pandoc/src/MarkdownReader.php lanes/pandoc/tests/MarkdownReaderTest.php lanes/pandoc/fixtures/upstream-html-standalone-linebreak.html lanes/pandoc/examples/wordpress-native-html-standalone-linebreak-handoff.php`: exit `0`.
- `php lanes/pandoc/examples/wordpress-native-html-standalone-linebreak-handoff.php`: exit `0`, emitted two WordPress paragraph blocks containing `<br/>`.
- `git add -N lanes/pandoc/examples/wordpress-native-html-standalone-linebreak-handoff.php lanes/pandoc/fixtures/upstream-html-standalone-linebreak.html` in the detached worktree only: exit `0`; used only so `git diff --binary` included new files.
- `git diff --binary -- lanes/pandoc/src/MarkdownReader.php lanes/pandoc/tests/MarkdownReaderTest.php lanes/pandoc/fixtures/upstream-html-standalone-linebreak.html lanes/pandoc/examples/wordpress-native-html-standalone-linebreak-handoff.php > /home/claude/port-libs/.tmux-team/tmp/isolate-pandoc-html-br-20260524T215758Z.patch`: exit `0`; patch size `6754` bytes.

## Result

- Verification: passed.
- Ready marker: created at `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-isolate-pandoc-html-br.ready`.
- Recommendation: integrator should accept this isolated patch as the standalone Pandoc HTML `<br>` / `LineBreak` slice.
