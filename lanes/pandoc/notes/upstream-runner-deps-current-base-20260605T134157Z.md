# Pandoc Upstream Runner Dependency Audit 2026-06-05

## Scope

Micro-slice: `pandoc-upstream-runner-deps-current-base-20260605T134157Z`.

Accepted base: `858af475bf12386a38b3216c0cd932565f7f894a`.

This is an upstream-runner dependency audit slice. No Pandoc binary, Cabal
solver/build/test command, Haskell test binary, Word, LibreOffice, `zip`/
`unzip`, `tar`, `lz4`, external template engine, TeX/PDF engine, MathJax,
KaTeX, Typst, browser renderer, roff renderer, media player, online conversion
service, online sanitizer, or other external converter was executed as
progress.

## Current-Base Evidence

- No current `port-pandoc-*.needs-lane-rework.md` note was present under
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates`.
- This isolated worktree started clean at accepted base
  `858af475bf12386a38b3216c0cd932565f7f894a`.
- The upstream cache currently contains Git checkouts for Gitoxide, libsqlite,
  and LightningCSS only. No hydrated Pandoc checkout or Pandoc Cabal package
  files were present under `/home/claude/port-libs/.upstream-cache`.
- `ghc --numeric-version` reported `9.10.3`.
- `cabal --numeric-version` reported `3.12.1.0`.
- `stack` and `pandoc` were not found on `PATH`.

## Implemented Audit Tightening

`UpstreamRunnerDependencyAudit` now records and validates the Cabal
`test-suite` `type` field for both upstream runner targets:

- `test:test-pandoc`
- `test:test-pandoc-lua-engine`

Both targets must remain `exitcode-stdio-1.0` before the audit reports
`readyForNonMutatingCabalPlan: true`. A hydrated checkout with stale
`detailed-0.9` runner types now remains blocked and reports a concrete
`mismatched Cabal runner entry points` reason. This keeps the runner activation
gate aligned with the lane-local source truth that the upstream runners are
compiled Haskell Tasty executables.

## Dependency-Backlog Decision

No new native PHP support component is needed. This slice reuses the existing
`UpstreamRunnerDependencyAudit` support row and adds one bounded native audit
case. Full upstream runner parity remains blocked by the missing hydrated
Pandoc checkout and Haskell/Cabal build closure, not by a missing local
conversion primitive.

Before claiming upstream runner dependency closure, hydrate a local Pandoc
checkout at `0640c4c9859aa5a3ede082c190fcd5883c24ac83` with
`cabal.project`, `pandoc.cabal`,
`pandoc-lua-engine/pandoc-lua-engine.cabal`, `test/test-pandoc.hs`, and
`pandoc-lua-engine/test/test-pandoc-lua-engine.hs` present. Then verify the
recorded package entries, flags, constraints, Git source-repository pins,
`exitcode-stdio-1.0` test-suite types, entry points, direct build-depends, and
runner executable options before recording a non-mutating Cabal solver/build
plan.

## Non-Overlap

This patch does not touch native Markdown/HTML readers or writers, XML/HTML5
DOM, ZIP/OPC, YAML, CSL/BibTeX, DOCX/ODT, EPUB3, table geometry, math/TeX,
PDF handoff planning, archive compression streams, charset/Unicode support,
doctemplates, syntax highlighting, or legacy DOC/CFB behavior. It maps one
additional upstream-runner dependency audit case and one PHP PASS case only.

## Verification

- PHP syntax:
  `php -l lanes/pandoc/src/UpstreamRunnerDependencyAudit.php`
  - `No syntax errors detected in lanes/pandoc/src/UpstreamRunnerDependencyAudit.php`
- PHP syntax:
  `php -l lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  - `No syntax errors detected in lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
- Focused test:
  `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  - `1 test files, 100 assertions, 0 failures`
  - PASS cases: `6`
- JSON validation:
  `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
  - `pandoc json ok`
- Whitespace:
  `git diff --check -- lanes/pandoc`
  - passed
- Toolchain inventory:
  `ghc --numeric-version`
  - `9.10.3`
- Toolchain inventory:
  `cabal --numeric-version`
  - `3.12.1.0`
- Toolchain inventory:
  `stack`
  - not found on `PATH`
- Toolchain inventory:
  `pandoc`
  - not found on `PATH`
- Example smoke: not run - no example added or changed.
- Root harness: not run - isolated micro-slice.
