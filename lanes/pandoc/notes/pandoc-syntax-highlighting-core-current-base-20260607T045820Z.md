# Pandoc Syntax Highlighting Core Current Base

Micro-slice: `pandoc-syntax-highlighting-core-current-base-20260607T045820Z`

Accepted base: `30b5b473eebd1a022d75d5de890cf10648aded26`

## Behavior

- Added bounded native `jsonc` syntax-highlighting support for JSON-with-comments review snippets.
- `jsonc`, `json5`, `json-with-comments`, and `json.comments` aliases now normalize to the `jsonc` tokenizer.
- The tokenizer preserves line comments, block comments, quoted and unquoted keys, strings, booleans/null, JSON5-style `NaN`/`Infinity` constants, signed/decimal/hex numbers, punctuation, numbered source wrappers, and WordPress raw HTML style metadata.
- Added a WordPress import review JSONC fixture block with comments, unquoted keys, trailing commas, constants, and numbered line metadata.

## Evidence

- Baseline: `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php` passed with `1 test files, 1351 assertions, 0 failures`.
- Red-first probe before the source change: `jsonc => <empty> unsupported-language`; `json5 => <empty> unsupported-language`.
- Final: `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php` passed with `1 test files, 1384 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php --self-test` printed `syntax highlighting handoff self-test ok`.

## Dependency Closure

No new support component is needed. This reuses native PHP `SyntaxHighlighter` scanning, Markdown fenced-code metadata, `AstNode` code blocks, `WordPressBlockWriter` raw HTML handoff, existing style CSS generation, and the focused lane PHP harness.

Full Skylighting/Pandoc syntax-definition parity and full JSON5 parsing remain out of scope for this bounded comment-aware JSON review highlighter.

## Non-Overlap

This avoids the accepted syntax-highlighting CSS, Rust, AsciiDoc, HCL/Terraform, Liquid, and Elm slices. No Pandoc, Cabal, Haskell runner, Skylighting, external highlighter, browser renderer, JavaScript runtime, online sanitizer, online service, live provider test, or live-service provider test was executed.
