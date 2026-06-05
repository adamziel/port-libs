# Pandoc doctemplates core current-base 2026-06-05T22:28:01Z

## Slice

- Tightened `PortLibs\Pandoc\DocTemplate` parser validity for `$~$`
  breakable-space regions.
- The tokenizer now rejects templates that reach EOF while a breakable-space
  region is still open, instead of treating the rest of the template as a
  silently closed region.
- The guard covers direct `$~$`, braced `${~}` markers, wrapped rendering, and
  partial rendering. Closed regions keep the accepted whitespace collapsing and
  line-wrapping behavior.
- Updated the WordPress doctemplate review-packet smoke with a malformed
  breakable-space guard.

## Source Truth

- Hackage/Stackage `doctemplates-0.11.0.1` documents `$~$...$~$` as a paired
  breakable-space region for Pandoc templates.
- Upstream `Text.DocTemplates.Parser` parses breakable spaces as a bounded
  parser region rather than an implicit EOF-terminated mode.
- This slice used the native PHP renderer only. No Pandoc binary, Cabal
  build/solver/test command, Haskell runner, external template engine, browser
  renderer, JavaScript, online sanitizer, online conversion service, or live
  provider test was executed.

## Evidence

- Rework note check found no current
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md`
  paths.
- Baseline focused command before edits:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`
  - Result before edits: `1 test files, 123 assertions, 0 failures`.
- Red-first focused run after adding the parser-validity expectation:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`
  - Result: `1 test files, 124 assertions, 1 failures`.
  - Failure: `Expected exception UnexpectedValueException was not thrown`.
- Focused verification after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`
  - Result: `1 test files, 127 assertions, 0 failures`.
- WordPress smoke:
  `php lanes/pandoc/examples/wordpress-doctemplate-review-packet.php --self-test`
  - Result: `OK wordpress doctemplate review packet`.

## Non-Overlap

This slice does not repeat accepted doctemplate comments, delimiters,
conditionals, loop scoping, separators, `$it$`, `$^$`, automatic multiline
nesting, parameter-free pipes, deterministic map-pairs ordering,
parameterized pipes, Unicode display-width padding, missing/null pipe handling,
resource-map partial discovery, path-style partial lookup, applied partial
variable rebinding, partial recursion guards, braced directive tokenizer
behavior, alpha overflow labels, boolean false output, Unicode identifier
parsing, multiline control boundary newline swallowing, empty standalone
partial line swallowing, `chomp` traversal, breakable-space rendering/wrapping,
dedented nesting termination, final newline stripping for included partials,
extensionless custom-template output-format fallback, unclosed ordinary-dollar
diagnostics, default-template lookup, default-template metadata expansion, or
default HTML style partials.

It only adds parser rejection for unclosed `$~$` breakable-space regions.

It does not touch ZIP/OPC package primitives, YAML metadata, Citation/CSL,
BibTeX/CSL, Markdown/HTML readers, Markdown/WordPress writers, DOCX/ODT/EPUB
or legacy-DOC parsing, table geometry, math conversion, PDF handoff planning,
archive compression, syntax highlighting, XML/HTML5 DOM, charset/Unicode, or
upstream-runner dependency audit behavior.

## Dependency Closure

No new support component is needed. This reuses the accepted native PHP
`DocTemplate` renderer and the accepted WordPress doctemplate review-packet
example. Full doclayout `Doc` value modeling, richer source-location
diagnostics, filesystem/HTTP-backed template discovery beyond the existing
resource map, full upstream default-template data-file parity, and full
upstream Pandoc runner parity remain separate activation slices.

Root harness: not run - isolated micro-slice.
