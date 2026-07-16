# Pandoc Syntax Highlighting Core Current Base

Slice: `pandoc-syntax-highlighting-core-current-base-20260607T121827Z`

Accepted base: `8e9f32e829402726c458eef0af30498c4e6ff1de`

## Behavior

- Added bounded native `less` syntax-highlighting support for WordPress block-theme review snippets.
- `less`, `less-css`, `lesscss`, and `language-lesscss` aliases now normalize to the canonical `less` tokenizer.
- The tokenizer preserves line/block comments, LESS variables, interpolation, guard keywords, color functions, custom properties, selector/mixin heads, pseudo selectors, dimensions, colors, media queries, numbered source wrappers, and WordPress raw HTML style metadata.
- The WordPress syntax-highlighting fixture and handoff example now include a numbered LESS review block.

## Source Truth

- Pandoc delegates code-block highlighting through its syntax-highlighting layer and preserves source-code language classes, styles, and line-numbering attributes for writers.
- Skylighting has a LESS/CSS-preprocessor syntax definition with variables, mixins, guards, selectors, CSS values, and nested media-query contexts. This slice ports only the bounded PHP handoff needed by the local Markdown and WordPress syntax-highlighting path.
- No Pandoc, Cabal solver/build/test command, Haskell runner, Skylighting runtime, LESS compiler, external highlighter, browser renderer, JavaScript runtime, online sanitizer, online service, live provider test, or live-service provider test was executed.

## Evidence

- Latest prior syntax note: `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php` passed with `1 test files, 1384 assertions, 0 failures`.
- First focused run after adding the fixture exposed a scanner-order issue: the LESS `when` guard keyword was tokenized by the generic function pattern. The final implementation moves bounded guard keywords ahead of the fallback function rule.
- Final focused test:
  - `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php`
  - Result: `1 test files, 1413 assertions, 0 failures`.
- Example smoke:
  - `php lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php --self-test`
  - Result: `syntax highlighting handoff self-test ok`.

## Dependency Closure

No new support component is needed. This reuses native PHP `SyntaxHighlighter` scanning, Markdown fenced-code metadata, `AstNode` code blocks, `WordPressBlockWriter` raw HTML handoff, existing style CSS generation, the syntax fixture, and the focused lane PHP harness.

Full Skylighting/Pandoc syntax-definition parity, LESS compilation, source maps, CSS module analysis, browser rendering, and writer-wide default highlighting policy remain separate bounded follow-ups.

## Non-Overlap

This does not repeat accepted CSS, SCSS/Sass, HCL/Terraform, AsciiDoc, JSONC/JSON5, Mustache/Handlebars, Liquid, embedded HTML/PHP, GraphQL, PHPDoc, Elm, Rust, or CMake syntax-highlighting clusters. It owns only bounded LESS alias/token handoff.
