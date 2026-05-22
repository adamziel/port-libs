# pandoc Upstream Test Inventory

Inventory source: blob-filtered shallow clone at `.upstream-cache/pandoc`.

- Upstream commit: `0640c4c9859aa5a3ede082c190fcd5883c24ac83`
- Main suite declared by `pandoc.cabal`: `test-suite test-pandoc`
- Runner shape: Haskell Tasty executable `test/test-pandoc.hs`
- License: GPL-2.0-or-later, with GPL-compatible exceptions documented in
  `COPYRIGHT`

## Counted Static Denominator

- `test/` files/artifacts: 1,974
- `test/Tests/**/*.hs` Haskell test modules: 62
- Reader test modules: 34
- Writer test modules: 22
- Shared/support test modules: 6
- `test/command` artifacts: 1,155
- `test/command/*.md` command fixture files: 1,064
- `.native` expected artifacts under `test/`: 252
- `test/testsuite.txt` top-level Markdown sections: 14
- `test/testsuite.native` rendered native AST lines: 2,238
- `test/testsuite.native` `BlockQuote` nodes in the full rendered suite: 7
- `test/testsuite.native` `CodeBlock` nodes in the full rendered suite: 11
- `Tests.Readers.Markdown` definition-list cases: 8, all of which are now
  mapped by focused PHP tests
- Markdown fixture files under `test/`: 1,096
- Office/archive fixtures (`docx`, `odt`, `epub`, `pptx`, `xlsx`, `rtf`): 309
- HTML/XML/JATS fixtures: 29
- `pandoc-lua-engine/test/**/*.hs` modules: 5
- `benchmark/` files: 1
- `data/` files: 247

The dashboard denominator is 1,979 inspected upstream test files/artifacts:
1,974 under `test/` plus 5 `pandoc-lua-engine` test modules.

## Runner Blocker

The full upstream suite was not executed in this run. This environment does not
have `ghc`, `cabal`, or `stack`, Pandoc's `test-pandoc` suite must be built as a
Haskell Tasty executable before it can run command, golden, HUnit, and
QuickCheck tests, and the upstream cache is intentionally blob-filtered and not
checked out to keep network and disk use modest. The defensible denominator used
for this lane is therefore the cloned static `git ls-tree` inventory plus
targeted `git show` reads from the upstream object database, not upstream runner
parity.

## Native PHP Mapping Added

The current PHP slice maps a narrow part of `Tests.Readers.Markdown` semantics:

- ATX headings
- Paragraph joining
- Bullet and ordered list blocks
- Inline emphasis with `*text*`
- Inline strong with `**text**`
- Inline code spans
- Inline links with `[label](url)`
- Indented fenced code blocks from `test/command/indented-fences.md`, including
  Pandoc's opening-fence indentation stripping and both bare language and
  `{.class}` info strings
- Indented code blocks from the `# Code Blocks` section of `test/testsuite.txt`,
  cross-checked against `test/testsuite.native`: blank lines stay inside the
  block, a one-tab indent starts a block with no remaining indent, two leading
  tabs leave one expanded four-space indent in the code text, and literal
  backslashes are preserved rather than unescaped.
- Block quote cases from the `# Block Quotes` section of `test/testsuite.txt`,
  cross-checked against `test/testsuite.native`: simple quoted paragraphs,
  quote-contained indented code, ordered lists, nested block quotes, and the
  lazy-continuation case where `> 1.` stays inside a paragraph instead of
  starting a quote.
- Definition-list cases from `Tests.Readers.Markdown`: no blank space,
  blank space before the first definition, lazy continuation lines, indented
  continuation paragraphs, blank space before the second definition, first-line
  marker at column zero, a list inside a definition, and the definition list
  nested inside an HTML div.

The WordPress writer emits block comments and escaped HTML for the same AST
without calling the upstream `pandoc` binary.

Focused local verification on 2026-05-22: the pandoc-local test file passed with
28 tests, 114 assertions, and 0 failures. The required repo-wide
`php tools/run-tests.php` run passed with 58 test files, 3,187 assertions, and 0
failures in the shared worktree.
