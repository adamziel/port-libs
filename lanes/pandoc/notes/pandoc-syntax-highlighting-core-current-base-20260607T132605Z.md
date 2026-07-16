# Pandoc Syntax Highlighting Typst Source Slice

Micro-slice: `pandoc-syntax-highlighting-core-current-base-20260607T132605Z`
Base accepted HEAD: `8b3e49d3b94f0a6f9c5a54975fd3ee75dce694ef`

## Behavior

- Added bounded native Typst source-code highlighting to `SyntaxHighlighter`.
- Added `typ`, `typst`, and `typst-source` aliases.
- Preserves representative Typst markup comments, `#set` / `#let` / `#show` keywords, hash functions and variables, heading markers, attributes, string literals, numeric units, and operators.
- Added a WordPress import review fixture block with Pandoc-style numbered line metadata and a focused WordPress HTML handoff smoke.

## Source Truth

This slice follows the lane contract for `pandoc-syntax-highlighting-core-*`: fixture-backed language alias/style/token handoff in native PHP. It ports the format contract for Typst source highlighting only. It does not implement or execute Typst rendering, PDF generation, Pandoc, Skylighting, Cabal/Haskell runners, browser renderers, JavaScript runtimes, online sanitizers, online services, live provider tests, or live-service provider tests.

## Evidence

- Baseline focused test before implementation: `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php` passed with `1 test files, 1413 assertions, 0 failures`.
- Red-first direct probe before implementation: `normalizeLanguage('typst')` returned `null`, and `highlight(..., 'typst')` reported `unsupported-language`.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php` passed with `1 test files, 1443 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php --self-test` passed with `syntax highlighting handoff self-test ok`.

## Dependency Closure

No new support component is needed. The slice reuses the native `SyntaxHighlighter`, `MarkdownReader`, `AstNode` code-block metadata, `WordPressBlockWriter`, existing fixture path, and focused PHP test harness.

## Non-Overlap

This does not overlap accepted CSS, Rust, AsciiDoc, HCL/Terraform, JSONC/JSON5, Liquid, Elm, Mustache/Handlebars, GraphQL, PHPDoc, embedded HTML/PHP, CMake, or LESS syntax-highlighting slices. It also does not overlap PDF-engine Typst handoff planning because no Typst renderer, PDF engine, or output artifact planning is executed.

## Next

Continue syntax highlighting with another non-overlapping fixture-backed language family or writer-level code-block policy metadata. Keep external renderer and upstream runner execution out of scope unless the supervisor explicitly assigns that audit.
