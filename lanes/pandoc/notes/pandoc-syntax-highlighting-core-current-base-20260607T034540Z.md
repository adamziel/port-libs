# Pandoc Syntax Highlighting Core Current Base

Slice: `pandoc-syntax-highlighting-core-current-base-20260607T034540Z`

Accepted base: `b6751e8d16a369b3cb6f380d161ef10027ea4635`

Implemented one bounded native syntax-highlighting cluster for Elm code fences:

- `SyntaxHighlighter::normalizeLanguage()` now maps `elm`, `elm-module`, and `elm-source` aliases to canonical `elm`.
- The native tokenizer covers Elm block/line comments, module/import/type declarations, constructors, qualified `Decode`/`Html`/`Attr` functions, datatypes, booleans, strings, numbers, variables, and operators.
- `fixtures/wordpress-syntax-highlight.md` adds one numbered Elm review block, and the WordPress handoff example now self-tests the highlighted Elm HTML block.

Focused evidence:

- Baseline: `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php` passed with `1 test files, 1318 assertions, 0 failures`.
- Final: `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php` passed with `1 test files, 1351 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php --self-test` passed with `syntax highlighting handoff self-test ok`.
- PHP lint passed for `lanes/pandoc/src/SyntaxHighlighter.php`, `lanes/pandoc/tests/SyntaxHighlighterTest.php`, and `lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php`.
- `git diff --check -- lanes/pandoc` passed with no output.

Dependency closure:

No new support component is needed. This reuses the native PHP syntax highlighter, Markdown fixture parser, WordPress block writer, and focused lane test harness. No Pandoc, Cabal, Haskell runner, Skylighting, Elm compiler, external highlighter, browser renderer, JavaScript runtime, online sanitizer, online service, live provider test, or live-service provider test was executed.

Non-overlap:

This does not touch the accepted CSS, Rust, AsciiDoc, Mustache/Handlebars, HCL/Terraform, Liquid, embedded HTML/PHP, GraphQL, or PHPDoc syntax-highlighting clusters. It adds one Elm-specific alias/token fixture and one mapped manifest case.
