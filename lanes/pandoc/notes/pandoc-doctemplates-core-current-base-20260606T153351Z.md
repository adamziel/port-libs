# Pandoc doctemplates core current-base 2026-06-06T15:33:51Z

## Slice

- Aligned `PortLibs\Pandoc\DocTemplate` conditional branch parsing with
  upstream doctemplates by rejecting malformed branch chains after a final
  `$else$`.
- Duplicate `$else$` and `$elseif(...)$` after `$else$` now raise
  source-located `UnexpectedValueException` diagnostics instead of silently
  treating later branches as valid template content.
- Updated the WordPress doctemplate review-packet smoke so custom review
  templates fail closed on post-else `elseif` branch ordering mistakes.

## Source Truth

- Pandoc template syntax documents `elseif` as equivalent to nested
  `$else$` + `$if(...)$`, making it a pre-final-else branch simplification:
  https://pandoc.org/demo/example33/6.1-template-syntax.html
- Hackage `doctemplates-0.11.0.1` documents the same conditional structure and
  `elseif` desugaring for the Pandoc-style template engine:
  https://hackage.haskell.org/package/doctemplates
- No Pandoc binary, Cabal build, Haskell runner, external template engine,
  browser renderer, JavaScript, online sanitizer, online conversion service,
  live provider test, or live-service provider test was executed.

## Evidence

- Rework note check found no current
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md`
  paths.
- Baseline focused command before edits:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`
  - Result before edits: `1 test files, 229 assertions, 0 failures`.
- Red-first focused command after adding the conditional branch-order test:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`
  - Result before implementation: `1 test files, 229 assertions, 1 failures`;
    duplicate `else` was not rejected.
- Focused verification after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`
  - Result: `1 test files, 231 assertions, 0 failures`.
- WordPress smoke:
  `php lanes/pandoc/examples/wordpress-doctemplate-review-packet.php --self-test`
  - Result: `OK wordpress doctemplate review packet`.
- Root harness: not run - isolated micro-slice.

## Non-Overlap

This slice does not repeat accepted doctemplate comments, matched delimiters,
conditionals, normal `elseif` rendering, loop iteration rebinding, separators,
`$it$`, `$^$`, automatic multiline nesting, parameter-free pipes,
deterministic map-pairs ordering, parameterized pipes, Unicode display-width
padding, missing/null pipe rendering, resource-map partial discovery,
path-style partial lookup, applied partial variable rebinding, partial
recursion guards, braced directive tokenizer behavior, alpha overflow labels,
boolean false output, Unicode identifier parsing, multiline control boundary
newline swallowing, empty standalone partial line swallowing, `chomp`
traversal, breakable-space wrapping, dedented nesting termination, final
newline stripping for included partials, extensionless output-format resource
fallback, default Markdown/CommonMark resources, braced separator parsing, or
unclosed-dollar directive handling.

It only tightens parser diagnostics for malformed conditional branch ordering
after a final `else`.

It does not touch ZIP/OPC package primitives, YAML metadata, Citation/CSL,
BibTeX/CSL, Markdown/HTML readers, Markdown/WordPress writers, DOCX/ODT/EPUB
or legacy-DOC parsing, table geometry, math conversion, PDF handoff planning,
archive compression, syntax highlighting, XML/HTML5 DOM, charset/Unicode, or
upstream-runner dependency audit behavior.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP
`DocTemplate` parser/renderer and the accepted WordPress doctemplate
review-packet example. Full doclayout `Doc` value modeling, richer parser
recovery/source-span diagnostics, filesystem-backed template discovery beyond
the current bounded resource loaders, default-template data-file parity, and
full upstream Pandoc runner parity remain separate activation slices.
