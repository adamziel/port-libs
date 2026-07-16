# Pandoc Syntax Highlighting Current-Base Objective-C Slice

- Micro-slice: `pandoc-syntax-highlighting-core-current-base-20260608T225655Z`
- Accepted base: `79f9f98965689b71a99ad50e1ab3f41478685bb2`
- Scope: bounded native PHP Objective-C syntax-highlighting handoff for WordPress review code blocks.

## Behavior

Added canonical `objectivec` alias normalization for `objc`, `obj-c`, `objective-c`, `objective-c++`, `objectivecpp`, and `mm`, while preserving existing `m` handling for MATLAB/Octave snippets. Added a native Objective-C scanner for comments, `#import`/preprocessor lines, `@interface`, `@implementation`, `@property`, `@autoreleasepool`, `@end`, property attributes, Objective-C string literals, class datatypes, constants, functions, numbers, variables, and operators.

The fixture-backed WordPress review block exercises numbered source wrappers, Objective-C aliases, directive/property tokens, Objective-C `@"..."` strings, `NSLog(...)` function calls, and WordPress raw HTML style handoff.

## Evidence

- Rework notes: none found at `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md`.
- Baseline: `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php` passed with `1 test files, 2061 assertions, 0 failures`.
- Red-first: `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php` failed with `1 test files, 2063 assertions, 1 failures` because Objective-C aliases normalized to `NULL`.
- Final: `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php` passed with `1 test files, 2090 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php --self-test` passed.
- PHP lint passed for changed PHP files.

## Dependency Closure

No new support component is needed. This slice reuses native `SyntaxHighlighter` alias normalization/scanning, `MarkdownReader` fenced-code metadata, `AstNode` code blocks, and WordPress raw HTML style handoff. Pandoc, Cabal solver/build/test commands, Haskell runners, Skylighting, Objective-C/Clang compilers, external highlighters, browser renderers, online services, live provider tests, and live-service provider tests were not run.

## Non-Overlap

This slice does not repeat recent syntax-highlighting clusters for CSS, Rust, AsciiDoc, HCL/Terraform, Typst, or Erlang. A useful follow-up is a separate fixture-backed native alias/token handoff such as Raku.
