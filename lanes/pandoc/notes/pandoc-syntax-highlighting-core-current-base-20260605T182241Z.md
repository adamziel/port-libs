# Pandoc Syntax Highlighting C# Core Slice

## Scope

- Micro-slice: `pandoc-syntax-highlighting-core-current-base-20260605T182241Z`
- Accepted base: `141de3966cbe05d9889ff6391139ca72c205c74e`
- Ownership: bounded syntax-highlighting language alias/style/token handoff under `lanes/pandoc/**`.

## Behavior Added

- Added bounded C# alias normalization for `cs`, `csharp`, `C#`, `csx`, and `language-cs`.
- Added a native C# tokenizer for review-packet handoff covering comments, preprocessor lines, strings including interpolated strings, targeted attributes, keywords, constants, primitive/common datatypes, numbers, generic method calls, variables, and C# operators including `?.` and `??`.
- Added a fixture-backed ASP.NET legacy import review snippet with numbered source lines and WordPress raw HTML style metadata.
- Extended the WordPress syntax-highlighting example smoke to exercise the C# review handoff.

## Source Truth

- Pandoc `Text.Pandoc.Highlighting` at pinned `0640c4c9859aa5a3ede082c190fcd5883c24ac83` carries code-block classes, `startFrom`, `numberLines`, source code classes, and style selection through the highlighting formatter contract.
- Skylighting documents its Haskell highlighter as generated from KDE XML syntax definitions and intended as Pandoc's highlighting backend; published package metadata records `cs`/C# syntax definition support in the Skylighting core syntax inventory.
- This ports a bounded native PHP token handoff for WordPress reviewer packets, not the full KDE/Skylighting parser-state engine.
- No Pandoc binary, Cabal solver/build/test command, Haskell runner, Skylighting runtime, .NET runtime, external highlighter, browser renderer, JavaScript runtime, online sanitizer, or online conversion service was executed.

## Verification

- Baseline before implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php`
  - Result: `1 test files, 771 assertions, 0 failures`
- Focused behavior after implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php`
  - Result: `1 test files, 800 assertions, 0 failures`
- WordPress example smoke:
  - `php lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php --self-test`
  - Result: `syntax highlighting handoff self-test ok`
- PHP lint:
  - `php -l lanes/pandoc/src/SyntaxHighlighter.php`
  - `php -l lanes/pandoc/tests/SyntaxHighlighterTest.php`
  - `php -l lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php`
  - Result: no syntax errors.

## Status Delta

- Focused SyntaxHighlighter coverage: `771 -> 800` assertions.
- Focused PASS cases: `39 -> 40`.
- Lane `phpPass`: `1034 -> 1035`.
- Manifest mapped denominator: `1486 -> 1487`.

## Dependency Closure

No new support component is needed. This slice reuses native PHP `AstNode`, `MarkdownReader`, `SyntaxHighlighter`, and `WordPressBlockWriter`.

Upstream runner dependency closure remains gated on hydrating a local Pandoc checkout at `0640c4c9859aa5a3ede082c190fcd5883c24ac83` with `cabal.project`, `pandoc.cabal`, `pandoc-lua-engine/pandoc-lua-engine.cabal`, `test/test-pandoc.hs`, and `pandoc-lua-engine/test/test-pandoc-lua-engine.hs` present before any non-mutating Cabal solver/build plan.

## Non-Overlap

This patch does not repeat accepted syntax-highlighting coverage for base language/style wrappers, line anchors, WordPress writer opt-in, Haskell, TeX, diff, Markdown, Ruby, Lua, TypeScript, JSX, R, Python, C/C++, Dockerfile, Makefile, INI, TOML, Perl, Java, XML/XSLT, Bash heredocs, token-title attributes, custom Pandoc theme JSON, CSS, Rust, Nix, SCSS/Sass, Go, PowerShell, Graphviz DOT, or JavaScript handoffs.

It owns only bounded C# / `cs` alias and token handoff for ASP.NET-to-WordPress review snippets.

## Follow-Up

Keep parser-state-aware C# string interpolation internals, broader .NET language aliases, embedded highlighting inside HTML `script`/`style` and Markdown fenced-code blocks, full KDE/Skylighting XML syntax-definition parity, and writer-wide default-highlighting policy as separate bounded slices.
