# Pandoc doctemplates core current-base 2026-06-05T00:37:12Z

## Slice

- Aligned `PortLibs\Pandoc\DocTemplate` partial recursion handling with
  upstream doctemplates: excessive partial nesting now returns the literal
  `(loop)` instead of throwing.
- Raised the bounded native partial nesting guard to the upstream-documented
  50-level limit.
- Kept missing partials as hard template errors.
- Extended the WordPress doctemplate review-packet smoke with a self-test check
  that recursive review partials fail soft with `(loop)`.

## Source Truth

- Hackage `doctemplates-0.11.0.1` README
  (https://hackage.haskell.org/package/doctemplates): partials may include
  other partials; exceeding nesting level 50 returns the literal `(loop)` to
  avoid infinite loops.
- Pandoc User's Guide `Template syntax`, `Partials`: partials may include other
  partials, final newlines are omitted, and array separators in square brackets
  remain literal
  (https://pandoc.org/demo/example33/6.1-template-syntax.html).
- No Pandoc binary, Haskell test binary, Cabal build, Word, LibreOffice,
  `zip`/`unzip`, external template engine, TeX/PDF engine, browser renderer,
  roff, Typst, MathJax, KaTeX, or online service was executed.

## Evidence

- Rework note check:
  `find /home/claude/port-libs/.tmux-team/tmp/handoff-candidates -maxdepth 1 -type f -name 'port-pandoc-*.needs-lane-rework.md' -print | sort | tail -20`
  returned no current Pandoc rework notes.
- Red-first probe before implementation:
  `php -r 'require "tools/bootstrap.php"; $r = new \PortLibs\Pandoc\DocTemplate(); echo $r->render("\${ loop() }", [], ["loop" => "x\${ loop() }"]);'`
  failed with `UnexpectedValueException: Recursive doctemplate partial loop`.
- Focused verification after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php` passed with
  1 test file, 34 assertions, 0 failures.
- `php lanes/pandoc/examples/wordpress-doctemplate-review-packet.php --self-test`
  passed with `OK wordpress doctemplate review packet`.
- PHP lint and `git diff --check -- lanes/pandoc` passed in the final worker
  verification.

## Non-Overlap

This slice does not repeat accepted doctemplate comments, delimiters,
interpolated variables, conditionals, loops, separators, `$it$`, `$^$`,
automatic multiline nesting, `$~$` breakable-space markers, parameter-free or
parameterized pipes, inline partial arrays, applied partial rendering, or
resource-map partial discovery. It only changes the recursive/nesting-limit
outcome for partial inclusion. It does not touch ZIP/OPC package primitives,
YAML metadata, Citation/CSL, BibTeX/CSL, Markdown/HTML readers,
Markdown/WordPress writers, DOCX/ODT/EPUB or legacy-DOC parsing, table
geometry, math conversion, PDF handoff planning, archive compression, syntax
highlighting, or upstream-runner dependency audit behavior.

## Dependency Closure

No new external support component is needed. This reuses the existing native
PHP `pandoc-doctemplates-core` renderer and tightens its partial recursion
semantics. Full doclayout wrapping, filesystem-backed template discovery,
default-template parity, and full upstream Pandoc runner parity remain separate
activation slices.

Root harness: not run - isolated micro-slice.
