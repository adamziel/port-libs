# Pandoc Syntax Highlighting Core Current Base

Slice: `pandoc-syntax-highlighting-core-current-base-20260606T225500Z`
Base: `72b74d8bf978910fedcbf4b3ed6fbaee9456d76b`

## Behavior

Native `SyntaxHighlighter` now treats PHPDoc blocks inside PHP code fences as structured review tokens instead of one opaque comment. It preserves delimiters and prose as comments, highlights PHPDoc annotations such as `@template`, `@param`, `@return`, `@throws`, and `@var` as annotation tokens, highlights bounded PHPDoc type expressions as datatypes/operators, and highlights documented variables as variables.

The fixture adds a WordPress migration-review PHP snippet with typed PHPDoc metadata. The example smoke now verifies the highlighted HTML and WordPress raw HTML handoff for that snippet.

## Evidence

- Baseline focused command before edits: `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php`
  - Result: `1 test files, 1239 assertions, 0 failures`.
- Red probe: direct PHPDoc highlighting rendered the whole `/** ... */` block as one comment span and produced no annotation/type token spans.
- Final focused command: `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php`
  - Result: `1 test files, 1260 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php --self-test`
  - Result: `syntax highlighting handoff self-test ok`.

Status delta:

- `phpPass`: `1410 -> 1411`
- mapped denominator: `1823 -> 1824`
- focused assertions: `1239 -> 1260` (`+21`)

## Dependency Closure

No new support component is needed. This slice reuses native PHP `SyntaxHighlighter` token scanning, `MarkdownReader` fenced-code attributes, `WordPressBlockWriter` raw HTML handoff, and the existing syntax-highlighting example. Full Pandoc runner parity, Skylighting/Haskell runner parity, external highlighters, PHP snippet execution, browser renderers, JavaScript runtimes, online services, live provider tests, and live-service provider tests remain separate bounded follow-up work.

## Non-Overlap

This does not repeat accepted PHP 8 attributes/enums, PHP heredoc/nowdoc, HTML PHP islands, CSS, Rust, AsciiDoc, GraphQL, Bash, JSON, YAML, SQL, or XML syntax-highlighting support. The owned behavior is PHPDoc annotation/type token handoff inside PHP code blocks only.

## Exclusions

No Pandoc, Cabal/Haskell runner, Skylighting runtime, external highlighter, PHP snippet execution, browser renderer, JavaScript runtime, online sanitizer, online service, live provider test, or live-service provider test was run.
