# Pandoc Upstream Runner Dependency Audit 2026-06-05

## Scope

Micro-slice: `pandoc-upstream-runner-deps-current-base-20260605T130938Z`.

Accepted base: `000c54da45877453841cd99c6f6e40bc0e0a707e`.

This is an upstream-runner dependency audit slice, not a native conversion
behavior slice. No Pandoc binary, Cabal solver/build/test command, Haskell test
binary, Word, LibreOffice, `zip`/`unzip`, `tar`, `lz4`, external template
engine, TeX/PDF engine, MathJax, KaTeX, Typst, browser renderer, roff renderer,
media player, online conversion service, online sanitizer, or other external
converter was executed as progress.

## Current-Base Evidence

- No current `port-pandoc-*.needs-lane-rework.md` note was present under
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates`.
- This isolated worktree started clean at accepted base
  `000c54da45877453841cd99c6f6e40bc0e0a707e`.
- Filename searches under `/home/claude/port-libs/.upstream-cache` found no
  local Pandoc upstream checkout or Cabal package/project files for this slice.
- PATH discovery found `/usr/bin/ghc` and `/usr/bin/cabal`; `stack` was not
  found. This slice did not invoke GHC, Cabal, Stack, Pandoc, or any Haskell
  runner.
- Source truth for the added closure fields is the pinned Pandoc
  `cabal.project` and `pandoc.cabal` at
  `0640c4c9859aa5a3ede082c190fcd5883c24ac83`, plus the prior lane-local
  raw-source dependency audit.

## Patch

`UpstreamRunnerDependencyAudit` now models two additional prerequisites before
claiming the checkout is ready for a non-mutating Cabal plan:

- `cabal.project` solver constraints for
  `skylighting-format-blaze-html >= 0.1.2`,
  `skylighting-format-context >= 0.1.0.2`, `auto-update >= 0.2.6`, and
  `crypton >= 1.1.1`.
- inherited runner `ghc-options` for `test:test-pandoc`, specifically
  `-rtsopts`, `-with-rtsopts=-A8m`, and `-threaded`.

The audit now blocks missing or stale solver constraints and stripped runner
RTS/threading options. A hydrated checkout still must pass the existing checks
for required files, Cabal tools, project packages, Pandoc flags, source
repository pins, runner entry points, and direct runner `build-depends`.

## Dependency-Backlog Decision

No new native PHP conversion support component is needed. The blocker is still
the upstream Haskell runner/build closure, not a missing bounded PHP format
primitive. Existing Pandoc support-library rows remain the correct path for
real conversion coverage. This audit tightens the activation gate for a future
runner slice.

## Non-Overlap

This slice deliberately avoids Markdown/HTML readers and writers, XML/HTML5
DOM, ZIP/OPC, YAML, CSL/BibTeX, DOCX/ODT, EPUB3, table geometry, math/TeX, PDF
handoff, archive compression streams, charset/Unicode, syntax highlighting, and
legacy DOC/CFB behavior. It claims no mapped-denominator movement and no
upstream-runner parity.

## Next Activation Gate

Before claiming upstream runner dependency closure, hydrate a local Pandoc
checkout at `0640c4c9859aa5a3ede082c190fcd5883c24ac83` with `cabal.project`,
`pandoc.cabal`, `pandoc-lua-engine/pandoc-lua-engine.cabal`,
`test/test-pandoc.hs`, and
`pandoc-lua-engine/test/test-pandoc-lua-engine.hs` present. Then record a
non-mutating Cabal solver/build plan only after this audit shows package
entries, Pandoc flags, solver constraints, exact Git pins, runner direct
`build-depends`, and runner executable options are all present.

## Verification

- PHP syntax:
  `php -l lanes/pandoc/src/UpstreamRunnerDependencyAudit.php`
  - `No syntax errors detected in lanes/pandoc/src/UpstreamRunnerDependencyAudit.php`
- PHP syntax:
  `php -l lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  - `No syntax errors detected in lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
- Focused audit test:
  `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  - `1 test files, 84 assertions, 0 failures`
- JSON validation:
  `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
  - `pandoc json ok`
- Diff check:
  `git diff --check -- lanes/pandoc`
  - passed with no output
- Example smoke: not run - no example added or changed.
- Root harness: not run - isolated micro-slice.
