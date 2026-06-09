# Pandoc syntax highlighting current-base sed print-command handoff

Slice: `pandoc-syntax-highlighting-core-current-base-20260609T041826Z`
Base accepted HEAD: `8545b79dd7a73e9ae0947d693d1f23920ee07f78`

## Scope

Implemented one bounded syntax-highlighting support cluster for sed print-command disambiguation:

- `SyntaxHighlighter` now recognizes bare sed `p` and `P` print commands after optional numeric or regex addresses and optional `!` negation.
- The recognized print command is emitted as a keyword token, so fixture-backed WordPress review packets no longer surface a standalone final `p` command as a substitution flag.
- Substitution flags such as `s/foo/bar/p` remain attribute tokens, preserving the existing accepted substitution handoff.
- The WordPress syntax-highlighting example self-test now asserts the fixture print command token handoff.

Source-truth note: this maps the bounded shape of upstream Skylighting sed syntax, where command contexts distinguish print commands from substitution flags. I used static source inspection only; no Pandoc, Skylighting, sed, Haskell, or external converter runner was executed.

## Baseline and Non-overlap

- Baseline focused test before this patch: `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php` passed with `1 test files, 2520 assertions, 0 failures`.
- This directly follows the prior accepted sed append/change/insert text-payload slice, which recorded bare `p` command tokenization as the next non-overlapping edge.
- This avoids prior accepted syntax clusters for Groovy/Gradle/Jenkins, Fish, Pascal/Delphi, Common Lisp, D, Fortran, Tcl, Protobuf, Meson/Just, Fennel, Raku, Objective-C, Erlang, CSV/TSV, Scheme/Racket, Vimscript, BibTeX, shell, custom theme metadata, token-title metadata, line-highlight metadata, unsupported-language fallback behavior, and sed append/change/insert text payload preservation.

## Verification

- `php -l lanes/pandoc/src/SyntaxHighlighter.php` - no syntax errors.
- `php -l lanes/pandoc/tests/SyntaxHighlighterTest.php` - no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php` - no syntax errors.
- `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'` - `pandoc json ok`.
- `php lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php --self-test` - `syntax highlighting handoff self-test ok`.
- `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php` - `1 test files, 2528 assertions, 0 failures`.
- `git diff --check -- lanes/pandoc` - no output.

Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `2286` -> `2287`.
- Mapped denominator: `2687` -> `2688`.
- Focused assertion delta: `+8` in `SyntaxHighlighterTest.php`.
- Added `mappedSyntaxHighlightingSedPrintCommandCases: 1` and `syntaxHighlightingSedPrintCommandAssertions: 8`.

## Dependency Closure

No new support component is needed. The slice reuses native PHP `SyntaxHighlighter`, `MarkdownReader` code-block attributes, the existing WordPress syntax fixture/example, and focused `SyntaxHighlighterTest.php` coverage. Full upstream Pandoc/Skylighting runner parity remains an upstream-runner dependency task requiring a hydrated pinned checkout and Haskell test executables.

## Follow-up

Next syntax-highlighting work should choose shell-session transcript prompt/output highlighting, another uncovered Skylighting alias family, or deeper sed delimiter/address parsing with fixture-backed PHP tests.
