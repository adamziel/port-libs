# Pandoc Syntax Highlighting PostgreSQL Dollar Quote Slice

## Scope

- Micro-slice: `pandoc-syntax-highlighting-core-current-base-20260605T200740Z`
- Accepted base: `b04f57c7230c881432b7183ac804ada5839368dd`
- Ownership: bounded syntax-highlighting language alias/style/token handoff under `lanes/pandoc/**`.

## Behavior Added

- Added `pgsql` and `plpgsql` aliases to the bounded SQL highlighter.
- Added native SQL token rules for PostgreSQL tagged and plain dollar-quoted
  strings, preserving each dollar-quoted body as `StringTok`/`st` across
  numbered source lines.
- Extended bounded SQL keyword/datatype coverage for PostgreSQL function and
  trigger review snippets (`CREATE OR REPLACE FUNCTION`, `RETURNS trigger`,
  `LANGUAGE plpgsql`, `CREATE TRIGGER`, `FOR EACH ROW EXECUTE FUNCTION`).
- Added a fixture-backed WordPress PostgreSQL trigger review block and example
  self-test coverage that hands the highlighted SQL to a WordPress HTML block
  with style metadata.

## Source Truth

- Pandoc `Text.Pandoc.Highlighting` at pinned
  `0640c4c9859aa5a3ede082c190fcd5883c24ac83` carries code-block classes,
  `startFrom`, `numberLines`, source code classes, and style selection through
  the highlighting formatter contract.
- Pandoc delegates syntax lookup/tokenization to Skylighting; PostgreSQL SQL
  snippets are a bounded alias/token handoff here, not a full KDE XML syntax
  engine or embedded PL/pgSQL parser.
- No Pandoc binary, Cabal solver/build/test command, Haskell runner,
  Skylighting runtime, PostgreSQL/`psql`, external highlighter, browser
  renderer, JavaScript runtime, online sanitizer, or online conversion service
  was executed.

## Verification

- Baseline before behavior test:
  - `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php`
  - Result: `1 test files, 824 assertions, 0 failures`
- Red-first focused run after adding the fixture-backed test, before
  implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php`
  - Result: `1 test files, 826 assertions, 1 failures`
  - Failure: `pgsql` normalized to `NULL`.
- Focused behavior after implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php`
  - Result: `1 test files, 850 assertions, 0 failures`
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
  - Result: both JSON files decoded successfully.
- Final whitespace check:
  - `git diff --check -- lanes/pandoc`
  - Result: clean.

## Status Delta

- Focused SyntaxHighlighter coverage: `824 -> 850` assertions.
- Focused PASS cases: `41 -> 42`.
- Lane `phpPass`: `1060 -> 1061`.
- Manifest mapped denominator: `1513 -> 1514`.

## Dependency Closure

No new support component is needed. This slice reuses native PHP `AstNode`,
`MarkdownReader`, `SyntaxHighlighter`, and `WordPressBlockWriter`.

Upstream runner dependency closure remains gated on hydrating a local Pandoc
checkout at `0640c4c9859aa5a3ede082c190fcd5883c24ac83` with `cabal.project`,
`pandoc.cabal`, `pandoc-lua-engine/pandoc-lua-engine.cabal`,
`test/test-pandoc.hs`, and
`pandoc-lua-engine/test/test-pandoc-lua-engine.hs` present before any
non-mutating Cabal solver/build plan.

## Non-Overlap

This patch does not repeat accepted syntax-highlighting coverage for base
language/style wrappers, line anchors, WordPress writer opt-in, Haskell, TeX,
diff, Markdown, Ruby, Lua, TypeScript, JSX, R, Python, C/C++, Dockerfile,
Makefile, INI, TOML, Perl, Java, XML/XSLT, Bash heredocs, token-title
attributes, custom Pandoc theme JSON, CSS, Rust, Nix, SCSS/Sass, Go,
PowerShell, Graphviz DOT, JavaScript, C#, SQL-family MySQL/SQLite aliases, or
generic SQL migration snippets.

It owns only bounded PostgreSQL SQL alias and dollar-quoted string handoff for
WordPress migration review snippets.

## Follow-Up

Keep embedded PL/pgSQL tokenization inside dollar-quoted strings,
PostgreSQL-specific edge diagnostics, embedded highlighting inside HTML
`script`/`style` and Markdown fenced-code blocks, writer-wide default
highlighting policy, and full KDE/Skylighting XML syntax-definition parity as
separate bounded slices.
