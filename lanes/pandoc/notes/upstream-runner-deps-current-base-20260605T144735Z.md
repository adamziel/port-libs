# Pandoc Upstream Runner Dependency Audit 2026-06-05

## Scope

Micro-slice: `pandoc-upstream-runner-deps-current-base-20260605T144735Z`.

Accepted base: `5f01d9e717201ba19e7b761448a3221c1052cf70`.

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
  `5f01d9e717201ba19e7b761448a3221c1052cf70`.
- Filename searches under `/home/claude/port-libs/.upstream-cache` and this
  lane tree found no `pandoc.cabal`, `pandoc-lua-engine.cabal`,
  `cabal.project`, `cabal.project.freeze`, `test-pandoc.hs`, or
  `test-pandoc-lua-engine.hs` source files.
- `ghc --numeric-version` reported `9.10.3`.
- `cabal --numeric-version` reported `3.12.1.0`.
- `stack` and `pandoc` were not found on `PATH`.

## Implemented Audit Tightening

`UpstreamRunnerDependencyAudit` now records and validates exact
`cabal.project` `source-repository-package` type and location closure for the
five Git-pinned upstream runner dependencies:

- `doclayout` at `https://github.com/jgm/doclayout.git`
- `typst-symbols` at `https://github.com/jgm/typst-symbols.git`
- `typst-hs` at `https://github.com/jgm/typst-hs.git`
- `texmath` at `https://github.com/jgm/texmath.git`
- `citeproc` at `https://github.com/jgm/citeproc.git`

Before this slice, a fixture with the expected repository basename and tag
could still be marked ready when the repository `type` was not `git` or the
`location` pointed at a mirror. The red-first test reproduced that gap: the
audit incorrectly returned `readyForNonMutatingCabalPlan: true` for a matching
tag set with `doclayout` declared as `svn` and `typst-symbols` pointed at a
mirror URL.

The activation gate now requires package entries, flags, solver constraints,
exact source-repository-package type/location/tag closure, test-suite type,
entry points, direct build-depends, executable options, `ghc`, and `cabal`
before a non-mutating Cabal solver/build plan can be marked ready.

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
recorded package entries, flags, constraints, Git source-repository package
types, locations, and tags, `exitcode-stdio-1.0` test-suite types, entry
points, direct build-depends, and runner executable options before recording a
non-mutating Cabal solver/build plan.

## Non-Overlap

This patch does not touch native Markdown/HTML readers or writers, XML/HTML5
DOM, ZIP/OPC, YAML, CSL/BibTeX, DOCX/ODT, EPUB3, table geometry, math/TeX,
PDF handoff planning, archive compression streams, charset/Unicode support,
doctemplates, syntax highlighting, or legacy DOC/CFB behavior. It maps one
additional upstream-runner dependency audit case and one PHP PASS case only.

## Verification

- Red-first focused test:
  `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  - Failed before implementation with `readyForNonMutatingCabalPlan` still
    `true` for mismatched source repository type/location.
- PHP syntax:
  `php -l lanes/pandoc/src/UpstreamRunnerDependencyAudit.php`
  - `No syntax errors detected in lanes/pandoc/src/UpstreamRunnerDependencyAudit.php`
- PHP syntax:
  `php -l lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  - `No syntax errors detected in lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
- Focused test:
  `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  - `1 test files, 113 assertions, 0 failures`
  - PASS cases: `7`
- JSON validation:
  `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
  - `pandoc json ok`
- Toolchain inventory:
  `ghc --numeric-version`
  - `9.10.3`
- Toolchain inventory:
  `cabal --numeric-version`
  - `3.12.1.0`
- Toolchain inventory:
  `command -v stack || true; command -v pandoc || true`
  - no output
- Example smoke: not run - no example added or changed.
- Whitespace:
  `git diff --check -- lanes/pandoc`
  - passed
- Root harness: not run - isolated micro-slice.
