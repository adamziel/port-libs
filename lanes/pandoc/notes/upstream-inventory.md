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
- `test/testsuite.native` `BulletList`/`OrderedList` nodes in the full rendered
  suite: 36
- `test/testsuite.txt` Lists section slice inspected in this run: 163 Markdown
  lines from `# Lists` through the start of `# Definition Lists`
- `test/testsuite.txt` Definition Lists section slice inspected in this run: 93
  Markdown lines through the start of `# HTML Blocks`
- `test/testsuite.native` Definition Lists rendered native AST slice inspected
  in this run: 163 lines
- `test/testsuite.txt` HTML Blocks section slice inspected in this run: 102
  Markdown lines through the start of `# Inline Markup`
- `test/testsuite.native` HTML Blocks rendered native AST slice inspected in
  this run: 161 lines, including 22 `RawBlock` markers, 9 `Div` markers, and 4
  `CodeBlock` markers
- `test/testsuite.txt` Inline Markup section slice inspected in this run: 30
  Markdown lines through the start of `# Smart quotes, ellipses, dashes`
- `test/testsuite.native` Inline Markup rendered native AST slice inspected in
  this run: 168 lines, including 9 `Emph` markers, 6 `Strong` markers,
  1 `Strikeout` marker, 3 `Superscript` markers, and 3 `Subscript` markers
- `test/testsuite.txt` Smart quotes, ellipses, dashes section slice inspected
  in this run: 21 Markdown lines through the start of `# LaTeX`
- `test/testsuite.native` Smart quotes, ellipses, dashes rendered native AST
  slice inspected in this run: 154 lines, including 14 `Quoted` markers plus
  smart apostrophe, em-dash, en-dash, and ellipsis code points in `Str` nodes
- `Tests.Readers.Markdown` definition-list cases: 8, all of which are now
  mapped by focused PHP tests
- Focused `# Lists` fancy-marker mappings from `test/testsuite.txt`: 4 local
  checks covering parenthesized decimal starts, lower/upper roman numerals,
  upper/lower alphabetic markers, and Pandoc autonumbering
- Markdown fixture files under `test/`: 1,096
- Office/archive fixtures (`docx`, `odt`, `epub`, `pptx`, `xlsx`, `rtf`): 309
- HTML/XML/JATS fixtures: 29
- `pandoc-lua-engine/test/**/*.hs` modules: 5
- `pandoc-lua-engine/test/` artifacts: 54
- `benchmark/` files: 1
- `data/` files: 247

The dashboard denominator is now 2,028 inspected upstream test files/artifacts:
1,974 under `test/` plus all 54 tracked artifacts under
`pandoc-lua-engine/test/`. This replaces the earlier 1,979 count that only
included the five Lua-engine Haskell test modules.

## Runner Blocker

The full upstream suite was not executed in this run. Pandoc's `test-pandoc` and
`test-pandoc-lua-engine` suites must be built as Haskell Tasty executables from
a full checkout before they can run command, golden, HUnit, QuickCheck, and Lua
tests. The current cache is intentionally blob-filtered and not checked out to
keep network and disk use modest, and `ghc`, `cabal`, and `stack` are not on
PATH; installing those tools alone would still leave a broad dependency build
outside this bounded lane run. The defensible denominator used for this lane is
therefore the cloned static `git ls-tree` inventory plus targeted `git show`
reads from the upstream object database, not upstream runner parity.

## Native PHP Mapping Added

The current PHP slice maps a narrow part of `Tests.Readers.Markdown` semantics:

- ATX headings
- Paragraph joining
- Bullet and ordered list blocks
- Inline emphasis with `*text*`
- Inline strong with `**text**`
- Inline underscore emphasis/strong from the `# Inline Markup` section of
  `test/testsuite.txt`, cross-checked against `test/testsuite.native`: `_is
  this_` maps to `Emph`, `__is this__` maps to `Strong`, and triple `***`/`___`
  delimiters map to Pandoc's `Strong [Emph [...]]` shape.
- Inline strikeout, superscript, and subscript from the same `# Inline Markup`
  slice: `~~This is *strikeout*.~~` maps to a `Strikeout` node containing
  nested emphasis, `a^bc^d`/`a^*hello*^`/`a^hello\ there^` map to
  superscripts with escaped spaces normalized to non-breaking spaces,
  `H~2~O`/`H~23~O`/`H~many\ of\ them~O` map to subscripts, and the upstream
  unescaped-space examples remain plain text rather than script spans.
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
- Horizontal rules from the `# Code Blocks` and `# Lists` sections of
  `test/testsuite.txt`, cross-checked against `test/testsuite.native`: the
  underscore divider before the Lists section and the indented spaced-asterisk
  divider after `B. Williams` both become `HorizontalRule` nodes instead of
  paragraphs or bullet lists.
- Tight/loose list item shape and continuation paragraphs from the
  `# Lists` section of `test/testsuite.txt`, cross-checked against
  `test/testsuite.native`: blank lines between list items mark the list loose
  and turn item text into paragraph blocks, tab/space-indented continuation
  lines remain inside the current list item, multi-paragraph ordered items keep
  both paragraphs under the same item, and loose nested lists keep the parent
  item paragraph before the nested `BulletList`.
- Fancy ordered-list markers from the same `# Lists` section: `(2)` and `(3)`
  produce a decimal `OrderedList` starting at 2 with a two-parentheses
  delimiter, `iv.`/`v.` produce a lower-roman nested list starting at 4,
  `(A)`/`(B)` produce an upper-alpha nested list, `A.`/`I.`/`(6)`/`c)` produce
  the nested upper-alpha, upper-roman, decimal, and lower-alpha shape shown in
  `test/testsuite.native`, and `#.` produces Pandoc-style autonumbered lists.
- Definition-list cases from `Tests.Readers.Markdown`: no blank space,
  blank space before the first definition, lazy continuation lines, indented
  continuation paragraphs, blank space before the second definition, first-line
  marker at column zero, a list inside a definition, and the definition list
  nested inside an HTML div.
- Definition-list cases from the `# Definition Lists` section of
  `test/testsuite.txt`, cross-checked against `test/testsuite.native`: terms can
  contain emphasis, indented continuation blocks can add additional paragraphs,
  eight-space-indented continuation lines become code blocks, continuation
  block quotes remain block quotes inside the definition body, alternate `~`
  markers are accepted after a blank term line, and an indented ordered list
  stays nested inside the `orange` definition body.
- HTML-block cases from the `# HTML Blocks` section of `test/testsuite.txt`,
  cross-checked against `test/testsuite.native`: one-line and nested `<div>`
  containers become `div` AST nodes, raw `<table>` blocks preserve their HTML
  boundary while interpreting Markdown in cell text, `<script>` bodies are kept
  raw without interpreting Markdown, HTML comments become raw HTML blocks with
  tabs expanded as Pandoc does, trailing spaces are trimmed from raw comments and
  `<hr>` tags, and tab-indented HTML remains an indented code block.
- Smart-punctuation cases from the `# Smart quotes, ellipses, dashes` section
  of `test/testsuite.txt`, cross-checked against `test/testsuite.native`: nested
  single and double quotes become `quoted` AST nodes, apostrophes inside words
  normalize to Pandoc's right single quotation mark, quoted code spans remain
  code, quoted one-line reference links resolve through collected definitions,
  `---` becomes an em dash, numeric `--` ranges become en dashes, and `...`
  becomes an ellipsis while preserving a fourth trailing dot.

The WordPress writer emits block comments and escaped HTML for the same AST
without calling the upstream `pandoc` binary.

Focused local verification on 2026-05-22: the pandoc-local test file passed with
56 tests, 301 assertions, and 0 failures. The required repo-wide
`php tools/run-tests.php` suite passed with 85 test files, 5,757 assertions, and
0 failures after this smart-punctuation lane batch in the shared dirty
worktree.
