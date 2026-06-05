# Pandoc Syntax Highlighting SQL Core Slice

## Scope

- Micro-slice: `pandoc-syntax-highlighting-core-current-base-20260605T193120Z`
- Accepted base: `8136e31e3cbc131cb905067bff7696d833252432`
- Ownership: bounded syntax-highlighting language alias/style/token handoff under `lanes/pandoc/**`.

## Behavior Added

- Added SQL-family alias normalization for `mysql`, `mariadb`, `sqlite`, and `sqlite3`.
- Extended bounded SQL token handoff for WordPress migration review snippets:
  MySQL backtick identifiers, SQL double-quoted identifiers, datatypes, constants, DDL/DML and transaction keywords, MySQL duplicate-key updates, functions, named bind parameters, and numbered source wrappers.
- Added a fixture-backed WordPress SQL migration review block and example smoke coverage that hands the highlighted SQL to a WordPress raw HTML block with style metadata.

## Source Truth

- Pandoc `Text.Pandoc.Highlighting` at pinned `0640c4c9859aa5a3ede082c190fcd5883c24ac83` carries code-block classes, `startFrom`, `numberLines`, source code classes, and style selection through the highlighting formatter contract.
- Skylighting is Pandoc's syntax-highlighting backend and provides SQL-family syntax definitions and code-block alias lookup; this slice ports a bounded native PHP token handoff for review packets, not the full KDE/Skylighting parser-state engine.
- No Pandoc binary, Cabal solver/build/test command, Haskell runner, Skylighting runtime, database engine, `mysql`, `sqlite3`, external highlighter, browser renderer, JavaScript runtime, online sanitizer, or online conversion service was executed.

## Verification

- Baseline before implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php`
  - Result: `1 test files, 800 assertions, 0 failures`
- Focused behavior during implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php`
  - Result: `1 test files, 816 assertions, 1 failures`
  - Failure: exact expected SQL operator span did not match the scanner's existing adjacent-operator token merge.
- Focused behavior after implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php`
  - Result: `1 test files, 824 assertions, 0 failures`
- WordPress example smoke:
  - `php lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php --self-test`
  - Result: `syntax highlighting handoff self-test ok`

## Status Delta

- Focused SyntaxHighlighter coverage: `800 -> 824` assertions.
- Focused PASS cases: `40 -> 41`.
- Lane `phpPass`: `1049 -> 1050`.
- Manifest mapped denominator: `1502 -> 1503`.

## Dependency Closure

No new support component is needed. This slice reuses native PHP `AstNode`, `MarkdownReader`, `SyntaxHighlighter`, `WordPressBlockWriter`, and the existing WordPress syntax-highlighting handoff smoke.

Upstream runner dependency closure remains gated on hydrating a local Pandoc checkout at `0640c4c9859aa5a3ede082c190fcd5883c24ac83` with `cabal.project`, `pandoc.cabal`, `pandoc-lua-engine/pandoc-lua-engine.cabal`, `test/test-pandoc.hs`, and `pandoc-lua-engine/test/test-pandoc-lua-engine.hs` present before any non-mutating Cabal solver/build plan.

## Non-Overlap

This patch does not repeat accepted syntax-highlighting coverage for base language/style wrappers, line anchors, WordPress writer opt-in, Haskell, TeX, diff, Markdown, Ruby, Lua, TypeScript, JSX, R, Python, C/C++, Dockerfile, Makefile, INI, TOML, Perl, Java, XML/XSLT, Bash heredocs, token-title attributes, custom Pandoc theme JSON, CSS, Rust, Nix, SCSS/Sass, Go, PowerShell, Graphviz DOT, JavaScript, or C# handoffs.

It owns only bounded SQL-family alias and token handoff for WordPress migration review snippets.

## Follow-Up

Keep parser-state-aware SQL dialect details, PostgreSQL dollar-quoted strings, embedded highlighting inside HTML `script`/`style` and Markdown fenced-code blocks, full KDE/Skylighting XML syntax-definition parity, and writer-wide default-highlighting policy as separate bounded slices.
