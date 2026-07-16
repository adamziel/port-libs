# Pandoc Syntax Highlighting PHP Heredoc/Nowdoc Slice

## Scope

- Micro-slice: `pandoc-syntax-highlighting-core-current-base-20260605T224310Z`
- Accepted base: `ee26489bdb651a4b12ce158e3b8859ff31df6834`
- Ownership: bounded syntax-highlighting language alias/style/token handoff under `lanes/pandoc/**`.

## Behavior Added

- Extended the bounded native PHP tokenizer so `<<<HTML` heredoc and
  `<<<'NOWDOC'` nowdoc regions are emitted as string tokens from opener through
  closing delimiter.
- Added a fixture-backed WordPress review packet where PHP code stores
  WordPress block HTML in heredoc and nowdoc literals.
- Updated the WordPress syntax-highlighting example self-test to verify the
  heredoc opener, nowdoc opener, block payload lines, line numbering, and
  style metadata handoff without executing the highlighted PHP.

## Source Truth

- Existing accepted syntax-highlighting slices established the local
  Pandoc/Skylighting handoff contract: normalize code-block language aliases,
  use Pandoc built-in style names, emit `sourceCode` / `numberSource` wrappers,
  and preserve short token classes such as `kw`, `pp`, `va`, `st`, and `op`.
- Pandoc delegates highlighting to Skylighting syntax lookup and formatting;
  this slice ports only a bounded PHP string-state handoff for review packets,
  not the full KDE/Skylighting PHP XML state machine.
- This isolated worktree has no local `.upstream-cache/pandoc` checkout, so no
  upstream files, Pandoc binary, Cabal solver/build/test command, Haskell
  runner, PHP execution of highlighted snippets, Skylighting runtime, external
  highlighter, browser renderer, JavaScript, online sanitizer, or online
  service was run.

## Verification

- Baseline before new assertions:
  - `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php`
  - Result: `1 test files, 894 assertions, 0 failures`
- Red-first after adding the fixture-backed assertion:
  - `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php`
  - Result: `1 test files, 903 assertions, 1 failures`
  - Failure: PHP heredoc and nowdoc payloads were tokenized as operators/text
    instead of string spans.
- Focused behavior after implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php`
  - Result: `1 test files, 917 assertions, 0 failures`
- WordPress example smoke:
  - `php lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php --self-test`
  - Result: `syntax highlighting handoff self-test ok`
- PHP syntax checks:
  - `php -l lanes/pandoc/src/SyntaxHighlighter.php`
  - Result: no syntax errors.
  - `php -l lanes/pandoc/tests/SyntaxHighlighterTest.php`
  - Result: no syntax errors.
  - `php -l lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php`
  - Result: no syntax errors.
- JSON validation:
  - `php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " json ok\n"; }'`
  - Result: both lane JSON files decoded successfully.
- Diff hygiene:
  - `git diff --check -- lanes/pandoc`
  - Result: no whitespace errors.

Root harness not run - isolated micro-slice.

## Status Delta

- Focused SyntaxHighlighter coverage: `894 -> 917` assertions.
- Focused PASS cases: `44 -> 45`.
- Lane `phpPass`: `1100 -> 1101`.
- Manifest mapped denominator: `1552 -> 1553`.

## Dependency Closure

No new support component is needed. This slice reuses native PHP `AstNode`,
`MarkdownReader`, `SyntaxHighlighter`, `WordPressBlockWriter`, the existing
syntax-highlighting fixture, and focused PHP tests.

Upstream runner dependency closure remains gated on hydrating a local Pandoc
checkout at `0640c4c9859aa5a3ede082c190fcd5883c24ac83` with `cabal.project`,
`pandoc.cabal`, `pandoc-lua-engine/pandoc-lua-engine.cabal`,
`test/test-pandoc.hs`, and
`pandoc-lua-engine/test/test-pandoc-lua-engine.hs` present before any
non-mutating Cabal solver/build plan.

## Non-Overlap

This patch does not repeat accepted syntax-highlighting coverage for base
language/style wrappers, line anchors, WordPress writer opt-in, Haskell, TeX,
diff, Markdown, Ruby, Lua short strings, Lua long-bracket strings/comments,
TypeScript, JSX, R, Python, C/C++, Dockerfile, Makefile, INI, TOML, Perl,
Java, XML/XSLT, Bash heredocs, token-title attributes, custom Pandoc theme
JSON, CSS, Rust, Nix, SCSS/Sass, Go, PowerShell, Graphviz DOT, JavaScript,
C#, SQL-family MySQL/SQLite aliases, PostgreSQL dollar-quoted strings, or
Apache/.htaccess handoffs.

It owns only bounded PHP heredoc/nowdoc string token handoff for WordPress
block review snippets.

## Follow-Up

Keep PHP variable interpolation inside heredoc bodies, parser-state-aware
embedded-language highlighting, full KDE/Skylighting XML syntax-definition
parity, writer-wide default highlighting policy, and additional language
grammars as separate bounded slices.
