# Pandoc doctemplates core current-base 2026-06-05T16:03:19Z

## Slice

- Aligned `PortLibs\Pandoc\DocTemplate` tokenizer error handling with upstream
  doctemplates for unclosed dollar-delimited directives.
- Non-escaped lone-dollar input such as `Title: $title` or `Cost: $5` now
  raises `UnexpectedValueException` instead of being silently preserved as
  literal text.
- Escaped literal dollars with `$$` continue to render as literal `$`, keeping
  reviewer currency text such as `Cost: $$5` valid.
- Updated the WordPress doctemplate review-packet smoke with checks for escaped
  literal dollars and malformed lone-dollar rejection.

## Source Truth

- Hackage `doctemplates-0.11.0.1` documents matched `$...$` and `${...}`
  delimiters, plus `$$` for literal dollars:
  https://hackage.haskell.org/package/doctemplates
- Upstream `Text.DocTemplates.Parser` parses `$...$` through `pOpenDollar` and
  requires the matching close parser; unmatched non-escaped dollar directives
  therefore fail parsing:
  https://raw.githubusercontent.com/jgm/doctemplates/master/src/Text/DocTemplates/Parser.hs
- No Pandoc binary, Cabal build, Haskell runner, external template engine,
  browser renderer, JavaScript, online sanitizer, online conversion service, or
  live service was executed.

## Evidence

- Rework note check found no current
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md`
  paths.
- Baseline focused command before edits:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`
  - Result before edits: `1 test files, 70 assertions, 0 failures`.
- Red-first focused command after adding the parser-validity test:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`
  - Result before implementation: `1 test files, 72 assertions, 1 failures`;
    expected `UnexpectedValueException` was not thrown.
- Focused verification after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`
  - Result: `1 test files, 73 assertions, 0 failures`.
- WordPress smoke:
  `php lanes/pandoc/examples/wordpress-doctemplate-review-packet.php --self-test`
  - Result: `OK wordpress doctemplate review packet`.
- Root harness: not run - isolated micro-slice.

## Non-Overlap

This slice does not repeat accepted doctemplate comments, matched delimiters,
conditionals, normal loop iteration rebinding, separators, `$it$`, `$^$`,
automatic multiline nesting, parameter-free pipes, deterministic map-pairs
ordering, parameterized pipes, Unicode display-width padding, missing/null pipe
rendering, resource-map partial discovery, path-style partial lookup, applied
partial variable rebinding, partial recursion guards, braced directive tokenizer
behavior, alpha overflow labels, boolean false output, Unicode identifier
parsing, multiline control boundary newline swallowing, empty standalone partial
line swallowing, `chomp` traversal, breakable-space wrapping, dedented nesting
termination, final newline stripping for included partials, or extensionless
output-format resource fallback.

It only changes malformed non-escaped `$...` directive handling when the closing
`$` is absent, while preserving the accepted `$$` literal escape path.

It does not touch ZIP/OPC package primitives, YAML metadata, Citation/CSL,
BibTeX/CSL, Markdown/HTML readers, Markdown/WordPress writers, DOCX/ODT/EPUB
or legacy-DOC parsing, table geometry, math conversion, PDF handoff planning,
archive compression, syntax highlighting, XML/HTML5 DOM, charset/Unicode, or
upstream-runner dependency audit behavior.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP
`DocTemplate` renderer and the accepted WordPress doctemplate review-packet
example. Full doclayout `Doc` value modeling, richer source-location
diagnostics, filesystem-backed template discovery beyond the existing resource
map, default-template data-file parity, and full upstream Pandoc runner parity
remain separate activation slices.
