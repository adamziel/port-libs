# Pandoc Syntax Highlighting Sed Current-Base Slice

Micro-slice: `pandoc-syntax-highlighting-core-current-base-20260608T205847Z`
Base accepted HEAD: `5d4304c18bb1f0b3ffb02f52a119f3462fac3ca7`

## Behavior

- Added native `sed` syntax-highlighting alias handoff for `sed`, `gsed`, `gnu-sed`, and `stream-editor`.
- Added a fixture-backed WordPress cleanup script fenced as `{.sed #sed-review .numberLines startFrom=1020}`.
- Token coverage is bounded to Sed comments, labels, numeric addresses, slash-delimited regex addresses, hash/slash-delimited substitution bodies, command letters, substitution flags, and punctuation/operators.
- Extended the WordPress syntax-highlighting example smoke to render the Sed block with Tango style metadata and numbered-line handoff.

## Evidence

- No `port-pandoc-*.needs-lane-rework.md` notes existed before editing.
- Baseline before this slice: `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php` passed with `1 test files, 1889 assertions, 0 failures`.
- Red-first after adding Sed fixture assertions: `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php` failed as expected with `1692 assertions, 2 failures` because Sed aliases were not mapped and Sed blocks were unsupported.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php` passed with `1 test files, 1914 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php --self-test` passed with `syntax highlighting handoff self-test ok`.
- Required final checks run for handoff: PHP lint for changed PHP files, JSON validation for lane metadata, and `git diff --check -- lanes/pandoc`.
- Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses the native `MarkdownReader` fenced-code metadata parser, `SyntaxHighlighter` scan/render pipeline, style CSS renderer, line-number renderer, and WordPress HTML-block handoff. It intentionally did not run Pandoc, Cabal/Haskell binaries, Skylighting, `sed`, external highlighters, browser renderers, JavaScript runtimes, online services, live provider tests, or live-service provider tests.

## Non-Overlap

This slice avoids the just-ready Fish syntax-highlighting case and prior accepted language clusters. Remaining non-overlapping syntax follow-ups include Erlang, Raku, Scheme/Racket, Objective-C, or deeper Sed edge cases only if backed by distinct fixture assertions.
