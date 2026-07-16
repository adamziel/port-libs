# Pandoc Syntax Highlighting Core Current Base

Slice: `pandoc-syntax-highlighting-core-current-base-20260607T020546Z`
Base: `beafb5b9ebe55f9aec9402f03ec049292424d83f`

## Behavior

Native `SyntaxHighlighter` now supports a bounded Liquid/Shopify template
handoff for WordPress migration review packets. It normalizes `liquid`,
`shopify`, `liquid-html`, and `html-liquid` aliases; highlights Liquid comment
blocks, tag delimiters, control tags, variables, filters, keyword operators,
named render arguments, strings, numbers, and surrounding HTML wrapper tokens;
and preserves Pandoc line numbering plus WordPress raw HTML style metadata.

The fixture adds a Shopify product-card migration snippet that exercises
`assign`, `if`/`else`/`endif`, filters such as `default`, `escape`,
`strip_html`, and `truncatewords`, plus a `render` call with named arguments.

## Evidence

- Baseline focused command before edits: `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php`
  - Result: `1 test files, 1295 assertions, 0 failures`.
- Red-first focused run after adding the fixture/test initially failed because
  `shopify`/`liquid-html` aliases were unsupported and no Liquid tag/filter
  tokenizer existed.
- Final focused command: `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php`
  - Result: `1 test files, 1318 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php --self-test`
  - Result: `syntax highlighting handoff self-test ok`.

Status delta:

- `phpPass`: `1433 -> 1434`
- mapped denominator: `1850 -> 1851`
- focused assertions: `1295 -> 1318` (`+23`)

## Dependency Closure

No new support component is needed. This slice reuses native PHP
`SyntaxHighlighter` scanning, `MarkdownReader` fenced-code attributes,
`AstNode` code-block handoff, `WordPressBlockWriter` raw HTML handoff, the
existing syntax-highlighting fixture, and the WordPress syntax-highlighting
example.

Full Pandoc/Skylighting runner parity remains gated on hydrating the pinned
upstream checkout at `0640c4c9859aa5a3ede082c190fcd5883c24ac83` and explicitly
authorizing Haskell/Cabal runner work.

## Non-Overlap

This does not repeat accepted syntax-highlighting coverage for CSS, Rust,
AsciiDoc, Mustache/Handlebars, Twig/Timber, HCL/Terraform, GraphQL, HTML PHP
islands, PHPDoc, PHP attributes/enums, SQL/PostgreSQL, Apache, Nginx, CMake,
Mermaid, or the existing base language/style/token wrappers. The owned behavior
is bounded Liquid/Shopify template alias and token handoff only.

## Exclusions

No Pandoc, Cabal/Haskell runner, Skylighting runtime, Shopify/Liquid engine,
external highlighter, browser renderer, JavaScript runtime, online sanitizer,
online service, live provider test, or live-service provider test was run.
