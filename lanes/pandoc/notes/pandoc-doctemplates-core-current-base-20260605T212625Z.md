# Pandoc doctemplates core current-base 2026-06-05T21:26:25Z

## Slice

- Added bounded parser support for trailing join separators after applied
  partial pipe chains.
- `PortLibs\Pandoc\DocTemplate` now accepts directives such as
  `${ items:row()/uppercase[ | ] }`, applying the partial pipe to each rendered
  partial before joining rendered items with the bracketed separator.
- The accepted separator-before-pipe form remains supported, and ambiguous
  double separators such as `row()[, ]/uppercase[ | ]` are rejected.
- Updated the WordPress doctemplate review-packet smoke with a reviewer warning
  rollup that uses applied partial rebinding, partial pipe application, and a
  trailing separator in one directive.

## Source Truth

- Hackage `doctemplates-0.11.0.1` documents pipes as transformations on
  variables and partials, and the prior lane notes cite
  `Text.DocTemplates.Parser` / `Text.DocTemplates.Internal` as the upstream
  parser/rendering source for Pandoc's template engine:
  `https://hackage.haskell.org/package/doctemplates`
  `https://raw.githubusercontent.com/jgm/doctemplates/master/src/Text/DocTemplates/Parser.hs`
  `https://raw.githubusercontent.com/jgm/doctemplates/master/src/Text/DocTemplates/Internal.hs`
- This slice used only the native PHP renderer and in-memory resource map. No
  Pandoc binary, Cabal build/solver/test command, Haskell runner, external
  template engine, browser renderer, JavaScript, online sanitizer, online
  service, or live provider test was executed.

## Evidence

- Rework note check found no current
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md`
  paths.
- Baseline focused command before edits:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`
  - Result: `1 test files, 123 assertions, 0 failures`.
- Red-first probe before implementation:
  - `${ items:row()/uppercase[ | ] }` failed with
    `UnexpectedValueException: Unsupported doctemplate pipe uppercase[ | ]`.
- Focused verification after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`
  - Result: `1 test files, 125 assertions, 0 failures`.
- WordPress smoke:
  `php lanes/pandoc/examples/wordpress-doctemplate-review-packet.php --self-test`
  - Result: `OK wordpress doctemplate review packet`.

## Status Delta

- `phpPass`: `1080 -> 1081`.
- `benchmarkDenominator.mapped`: `1532 -> 1533`.
- Focused doctemplate coverage: `DocTemplateTest.php` moved from 50 PASS cases /
  123 assertions to 51 PASS cases / 125 assertions.

## Non-Overlap

This slice does not repeat accepted doctemplate comments, delimiters,
conditionals, loop scoping, separators on ordinary variables, `$it$`, `$^$`,
automatic multiline nesting, parameter-free pipes, deterministic map-pairs
ordering, parameterized pipes, Unicode display-width padding, missing/null pipe
handling, resource-map partial discovery, path-style partial lookup, applied
partial variable rebinding, partial recursion guards, braced directive tokenizer
behavior, alpha overflow labels, boolean false output, Unicode identifier
parsing, multiline control boundary newline swallowing, empty standalone partial
line swallowing, `chomp` traversal, breakable-space wrapping, dedented nesting
termination, final newline stripping for included partials, extensionless
custom-template output-format fallback, unclosed dollar diagnostics,
default-template lookup, or built-in default HTML5 style partial resources.

It only owns the parser boundary where a partial call's pipe suffix is followed
by a trailing join separator, especially for applied partials used in WordPress
review-packet components.

## Dependency Closure

No new support component is needed. This reuses the accepted native PHP
`DocTemplate` renderer, partial rendering, pipe application, and WordPress
doctemplate review-packet example. Full filesystem-backed template discovery,
HTTP-backed template discovery, richer source-location diagnostics, writer-wide
default-template data parity, full doclayout value modeling, and full upstream
Pandoc runner parity remain separate activation slices.

Root harness: not run - isolated micro-slice.
