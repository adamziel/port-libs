# Pandoc Syntax Highlighting Core Current Base

Slice: `pandoc-syntax-highlighting-core-current-base-20260606T105616Z`

Base accepted HEAD: `acaa655f41a326695b1b8edaa14a30da83e3ddae`

## Behavior Added

- Extended bounded native `SyntaxHighlighter` PHP handoff for modern plugin and
  block review snippets:
  - classifies PHP 8 `#[...]` attributes as `OtherTok`/`ot` before the PHP
    `#` comment rule can consume them;
  - recognizes bounded `enum`, `fn`, backed scalar types, class-like
    identifiers, enum cases, readonly classes, and bare property names around
    `::` and `->` handoff tokens;
  - preserves Pandoc numbered-source wrappers and WordPress raw HTML style
    metadata for PHP attribute review packets.
- Added a fixture-backed WordPress PHP block-variation review snippet covering
  attributes, readonly class constructor promotion, backed enums, enum-case
  access, and closure return types.

## Source Truth

- Pandoc `Text.Pandoc.Highlighting` at
  `0640c4c9859aa5a3ede082c190fcd5883c24ac83` delegates code-block syntax
  lookup to Skylighting by code-block classes while preserving source classes,
  `startFrom`, numbered lines, line anchors, and built-in style metadata.
- Skylighting's PHP syntax treats attributes as code metadata rather than
  comments and supports current PHP keywords such as enums and arrow functions.
  This slice ports a bounded token handoff, not the full KDE XML syntax engine
  or a PHP parser.
- No Pandoc binary, Cabal solver/build/test command, Haskell runner,
  Skylighting runtime, PHP execution of highlighted snippets, external
  highlighter, browser renderer, JavaScript, online sanitizer, online service,
  live provider test, or live-service provider test was executed.

## Verification

- Baseline before implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php`
  - Result: `1 test files, 1186 assertions, 0 failures`
- Red-first focused probe before source implementation:
  - Direct PHP attribute highlight probe rendered
    `#[BlockVariation(name: "legacy/import")]` as `<span class="co">...`
    comment output.
- Focused behavior after implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php`
  - Result: `1 test files, 1208 assertions, 0 failures`
- WordPress example smoke:
  - `php lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php --self-test`
  - Result: `syntax highlighting handoff self-test ok`

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the existing `AstNode`,
`MarkdownReader`, `WordPressBlockWriter`, and bounded native
`SyntaxHighlighter` support row. Full upstream runner parity remains gated on
hydrating the Pandoc checkout at `0640c4c9859aa5a3ede082c190fcd5883c24ac83`
and producing a Cabal solver/build plan for `test-pandoc` and
`test-pandoc-lua-engine`.

## Non-Overlap

This patch does not repeat accepted syntax-highlighting coverage for base
language/style/token support, line anchors, token-title attributes, WordPress
writer opt-in, Haskell, TeX/LaTeX, diff/patch, Markdown-family, Ruby/Rake,
Lua/Pandoc-Lua, TypeScript, Python, C/C++, Dockerfile/Containerfile, Makefile,
JSX/React, R, INI/config, TOML/Cargo.lock, Perl, Java, XML/XSLT, Bash/sh
heredoc state, CSS at-rules/selectors, Rust aliases/tokens, Nix, SCSS, Go,
PowerShell, Graphviz DOT, JavaScript, C#, SQL/PostgreSQL/Apache, RST, TSX,
CMake, Nginx, Twig, Handlebars, Mermaid, embedded HTML CSS/JavaScript/PHP, or
GraphQL. It owns only bounded PHP 8 attribute, enum, backed-type, and closure
token handoff.

## Follow-Up

Keep full PHP parser parity, nested attribute argument arrays, DocBlock
annotations, token-title expansion for attributes, embedded-language
tokenization inside PHP strings, full KDE/Skylighting XML syntax-definition
parity, and writer-wide default highlighting policy as separate bounded slices.
