# Pandoc Syntax Highlighting Apache Htaccess Slice

## Scope

- Micro-slice: `pandoc-syntax-highlighting-core-current-base-20260605T203958Z`
- Accepted base: `573d6fae38c151ba4ce645385f7be4f06788579c`
- Ownership: bounded syntax-highlighting language alias/style/token handoff under `lanes/pandoc/**`.

## Behavior Added

- Added Apache/httpd/.htaccess aliases to the bounded syntax highlighter:
  `apache`, `apacheconf`, `apache-config`, `apache2`, `apache-conf`,
  `httpd`, `httpd-conf`, and `htaccess` normalize to canonical `apache`.
- Added native Apache configuration token rules for WordPress permalink review
  snippets, including section tags, comments, quoted strings, variables,
  module datatype names, directives, constants, paths, options, and flags.
- Added a fixture-backed `.htaccess` code block with line numbering and a
  WordPress example self-test that hands the highlighted Apache review packet
  to a raw HTML block with style metadata.

## Source Truth

- Pandoc `Text.Pandoc.Highlighting` at pinned
  `0640c4c9859aa5a3ede082c190fcd5883c24ac83` carries code-block classes,
  `startFrom`, `numberLines`, source code classes, and style selection through
  the highlighting formatter contract.
- Pandoc delegates syntax lookup/tokenization to Skylighting; Apache
  configuration and `.htaccess` snippets are a bounded alias/token handoff
  here, not a full KDE XML syntax engine or Apache expression parser.
- No Pandoc binary, Cabal solver/build/test command, Haskell runner,
  Skylighting runtime, Apache/httpd, external highlighter, browser renderer,
  JavaScript runtime, online sanitizer, or online conversion service was
  executed.

## Verification

- Baseline before behavior test:
  - `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php`
  - Result: `1 test files, 850 assertions, 0 failures`
- Focused behavior after implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php`
  - Result: `1 test files, 877 assertions, 0 failures`
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

- Focused SyntaxHighlighter coverage: `850 -> 877` assertions.
- Focused PASS cases: `42 -> 43`.
- Lane `phpPass`: `1070 -> 1071`.
- Manifest mapped denominator: `1522 -> 1523`.

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
PowerShell, Graphviz DOT, JavaScript, C#, SQL-family MySQL/SQLite aliases,
generic SQL migration snippets, or PostgreSQL dollar-quoted strings.

It owns only bounded Apache/httpd/.htaccess alias and directive-token handoff
for WordPress permalink and header review snippets.

## Follow-Up

Keep nginx configuration highlighting, JSON-with-comments, parser-state-aware
embedded language highlighting, richer Apache expression parsing, writer-wide
default highlighting policy, and full KDE/Skylighting XML syntax-definition
parity as separate bounded slices.
