# Pandoc Syntax Highlighting Lua Long Bracket Slice

## Scope

- Micro-slice: `pandoc-syntax-highlighting-core-current-base-20260605T214034Z`
- Accepted base: `500cc7848dadcf67518c357fc6134f1f2012fec1`
- Ownership: bounded syntax-highlighting alias/style/token handoff under `lanes/pandoc/**`.

## Behavior Added

- Extended the bounded native Lua/Pandoc-Lua tokenizer so equal-delimited Lua
  long comments and strings are emitted as single comment/string token spans:
  `--[=[...]=]`, `[=[...]=]`, and longer `=` delimiter levels.
- Added a fixture-backed `pandoc-lua` WordPress review packet where a Lua
  filter stores raw WordPress block HTML inside a long string and returns
  `pandoc.RawBlock("html", rawBlock)`.
- Updated the WordPress syntax-highlighting example self-test to verify the
  long comment, long-string body, RawBlock call, line numbering, and style
  metadata handoff without invoking external highlighters.

## Source Truth

- Existing accepted syntax-highlighting slices established the local
  Pandoc/Skylighting handoff contract: normalize code-block language aliases,
  use Pandoc built-in style names, emit `sourceCode` / `numberSource` wrappers,
  and preserve short token classes such as `kw`, `dt`, `fu`, `va`, `st`,
  `co`, and `op`.
- The accepted Pandoc manifest keeps `pandoc-lua-engine/test/` in the pinned
  upstream inventory and records the full Haskell runner as blocked on a
  hydrated checkout plus Cabal project/package files. This slice ports only the
  bounded PHP source-code review handoff for Lua filter snippets, not the Lua
  engine, Skylighting runtime, or full KDE XML syntax definitions.
- This isolated worktree has no local `/home/claude/port-libs/.upstream-cache/pandoc`
  checkout, so no upstream files, Pandoc binary, Cabal solver/build/test
  command, Haskell runner, Lua runtime, Skylighting runtime, external
  highlighter, browser renderer, online sanitizer, or online service was run.

## Verification

- Baseline before new assertions:
  - `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php`
  - Result: `1 test files, 877 assertions, 0 failures`
- Red-first after adding the fixture-backed assertion:
  - `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php`
  - Result: `1 test files, 885 assertions, 1 failures`
  - Failure: the Lua long-bracket string opener/body/closer were tokenized as
    operator/variable/comment fragments instead of string spans.
- Focused behavior after implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php`
  - Result: `1 test files, 894 assertions, 0 failures`
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

Root harness not run - isolated micro-slice.

## Status Delta

- Focused SyntaxHighlighter coverage: `877 -> 894` assertions.
- Focused PASS cases: `43 -> 44`.
- Lane `phpPass`: `1081 -> 1082`.
- Manifest mapped denominator: `1533 -> 1534`.

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
diff, Markdown, Ruby, Lua short strings and plain `[[...]]` strings,
TypeScript, JSX, R, Python, C/C++, Dockerfile, Makefile, INI, TOML, Perl,
Java, XML/XSLT, Bash heredocs, token-title attributes, custom Pandoc theme
JSON, CSS, Rust, Nix, SCSS/Sass, Go, PowerShell, Graphviz DOT, JavaScript, C#,
SQL-family MySQL/SQLite aliases, generic SQL migration snippets, PostgreSQL
dollar-quoted strings, or Apache/.htaccess handoffs.

It owns only bounded Pandoc Lua equal-delimited long comment/string token
handoff for WordPress raw HTML filter review snippets.

## Follow-Up

Keep Lua nested/unterminated long-bracket diagnostics, parser-state-aware
embedded-language highlighting, richer Lua operator/standard-library
categories, writer-wide default highlighting policy, additional language
grammars, and full KDE/Skylighting XML syntax-definition parity as separate
bounded slices.
