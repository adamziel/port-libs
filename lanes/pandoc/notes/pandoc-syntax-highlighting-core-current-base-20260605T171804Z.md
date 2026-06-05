# Pandoc Syntax Highlighting JavaScript Core Slice

## Scope

- Micro-slice: `pandoc-syntax-highlighting-core-current-base-20260605T171804Z`
- Accepted base: `a02e517ff4cebff34472ef9141087e7aada78c2f`
- Ownership: bounded syntax-highlighting language alias/style/token handoff under `lanes/pandoc/**`.

## Behavior Added

- Added `mjs`, `cjs`, `node`, `nodejs`, `ecmascript`, and `es6` aliases to the existing canonical JavaScript highlighter.
- Extended bounded JavaScript token handoff for Gutenberg ES-module review snippets:
  imports/exports, variables, object keys, built-in `JSON`/`console`, regex literals, bigint-like numeric literals, async/await, function calls, optional/nullish operators, and numbered source wrappers.
- Updated the WordPress syntax-highlighting fixture and example smoke with a Gutenberg `registerBlockType` module review block.

## Source Truth

- Pandoc `Text.Pandoc.Highlighting` at pinned `0640c4c9859aa5a3ede082c190fcd5883c24ac83` delegates language lookup to Skylighting code-block classes and carries `startFrom`, `numberLines`, `lineAnchors`, source code classes, and built-in styles through formatter options:
  `https://raw.githubusercontent.com/jgm/pandoc/0640c4c9859aa5a3ede082c190fcd5883c24ac83/src/Text/Pandoc/Highlighting.hs`
- Skylighting JavaScript syntax data includes JavaScript keywords, built-ins such as `JSON`/`console`, DOM/Node globals, methods, and standard literals:
  `https://raw.githubusercontent.com/jgm/skylighting/master/skylighting-core/xml/javascript.xml`
- This ports a bounded token handoff for WordPress reviewer packets, not the full KDE/Skylighting parser-state engine.
- No Pandoc binary, Cabal solver/build/test command, Haskell runner, Skylighting runtime, JavaScript runtime, Node, external highlighter, browser renderer, online sanitizer, or online conversion service was executed.

## Verification

- Baseline before implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php`
  - Result: `1 test files, 738 assertions, 0 failures`
- Focused behavior after implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php`
  - Result: `1 test files, 771 assertions, 0 failures`
- WordPress example smoke:
  - `php lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php --self-test`
  - Result: `syntax highlighting handoff self-test ok`

## Status Delta

- Focused SyntaxHighlighter coverage: `738 -> 771` assertions.
- Focused PASS cases: `38 -> 39`.
- Lane `phpPass`: `1017 -> 1018`.
- Manifest mapped denominator: `1471 -> 1472`.

## Dependency Closure

No new support component is needed. This slice reuses native PHP `AstNode`, `MarkdownReader`, `SyntaxHighlighter`, `WordPressBlockWriter`, and the existing WordPress syntax-highlighting handoff smoke.

Upstream runner dependency closure remains gated on hydrating a local Pandoc checkout at `0640c4c9859aa5a3ede082c190fcd5883c24ac83` with `cabal.project`, `pandoc.cabal`, `pandoc-lua-engine/pandoc-lua-engine.cabal`, `test/test-pandoc.hs`, and `pandoc-lua-engine/test/test-pandoc-lua-engine.hs` present before any non-mutating Cabal solver/build plan.

## Non-Overlap

This patch does not repeat accepted syntax-highlighting coverage for base language/style wrappers, line anchors, WordPress writer opt-in, Haskell, TeX, diff, Markdown, Ruby, Lua, TypeScript, JSX, R, Python, C/C++, Dockerfile, Makefile, INI, TOML, Perl, Java, XML/XSLT, Bash heredocs, token-title attributes, custom Pandoc theme JSON, CSS, Rust, Nix, SCSS/Sass, Go, PowerShell, or Graphviz DOT handoffs.

It owns only bounded JavaScript ES-module alias and token handoff for Gutenberg review snippets.

## Follow-Up

Keep JavaScript regexp/division disambiguation, nested template-string interpolation tokenization, parser-state-aware embedded language highlighting inside HTML `script`/`style`, Markdown fenced-code embedded highlighting, shell/Perl here-doc parity, writer-wide default highlighting policy, and full KDE/Skylighting XML syntax-definition parity as separate bounded slices.
