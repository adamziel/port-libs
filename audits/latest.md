# Independent Audit - 2026-05-24T10:59Z

Scope reviewed: `goal.md`, `progress.md`, current worktree `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, current
`lanes/*/lane-status.json`, `dependency-backlog.json`, and recent Git history
through `550ac606 Record integration hold status`. I did not edit lane
implementation files, launch agents or tmux sessions, push, read secrets,
inspect process environments, credential stores, provider configs, or auth
files.

Bridge code, generated fixtures, shell-outs, whole applications, external
converter wrappers, and hidden process launchers are treated as non-progress
unless they are explicitly temporary oracle tooling.

## Current Snapshot

```text
UTC samples: 2026-05-24T10:50Z through 2026-05-24T10:59Z
HEAD moved during audit validation: 07ad9be61b09 -> 73991a9a7f6e -> 550ac606f3df
recent history: 550ac606 Record integration hold status; 73991a9a Record integration hold status; 07ad9be6 Refresh independent audit status; f62f2053 Record integration hold status
tracked dirty rows moved: 329 -> 330
default status rows including untracked moved: 16957 -> 16975
status rows with all untracked moved: 17046 -> 17063
git diff --shortstat moved: 329 files changed, 220409 insertions(+), 29107 deletions(-) -> 330 files changed, 221203 insertions(+), 28795 deletions(-), with intermediate samples also moving
dashboard worktree snapshot: porting.html and porting-summary.json generated 2026-05-23 23:43:54 UTC from source 79768df0c427
dependency backlog: dependency-backlog.json updated 2026-05-24 10:51:38 UTC with 34 rows (1 blocked, 22 candidate, 11 deferred); dashboard still shows 22 rows (12 candidate, 10 deferred)
root run by this audit: not started
```

Required pre-root process-gate evidence:

```text
pgrep -af '^php tools/run-tests\.php( |$)' initial sample:
no rows

pgrep -af '^php tools/run-tests\.php( |$)' later validation samples:
no rows

pgrep -af '^php tools/run-tests\.php( |$)' final validation samples:
1585568 php tools/run-tests.php lanes/readability/tests
1587954 php tools/run-tests.php lanes/syncthing/tests

owner evidence for active focused harness:
PID 1587954 USER claude PPID 1510084 STAT Rs ETIMES 11 COMMAND php tools/run-tests.php lanes/syncthing/tests
```

I did not start `php tools/run-tests.php`. The required process gate was clear
in early audit-owned samples, later matched focused lane harnesses, and the
checkout failed the stability gate because `HEAD`, untracked-inclusive status,
and shortstat moved during validation. Focused lane evidence is not a substitute
for one serialized no-argument root result from a frozen accepted tree. `jq
empty` passed for all 12 lane manifests, all 12 lane-status files,
`porting-summary.json`, and `dependency-backlog.json`.

Current count sample:

```text
lane          manifest total/mapped          lane-status php   dashboard total/mapped/php
difftastic    1012 / 778                     3148 / 0          735 / 374 / 374
dolt          string total / 613             421 / 0           inventory / 613 / 356
esbuild       2567 / 413                     413 / 0           2567 / 311 / 311
gitoxide      2877 / 2877                    7024 / 0          2877 / 2751 / 5634
libsqlite     1589 / 341                     341 / 0           1589 / 286 / 286
LightningCSS  3535 / 2741                    4006 / 0          3532 / 1732 / 2197
markerPDF     386 / 337                      474 / 0           330 / 280 / 416
pandoc        2276 / 1832                    352 / 0           2276 / 1061 / 278
quadrable     55 / 55                        227 / 0           55 / 55 / 190
rclone        1601 / 884                     884 / 0           1601 / 698 / 698
readability   1984 / 1984                    252 / 0           1984 / 1984 / 204
syncthing     658 / 658                      7518 / 0          658 / 658 / 4579
```

## Findings

1. **Critical - the checkout is still not an acceptance checkpoint.**
   - Paths: `progress.md:45`, `progress.md:47`,
     `lanes/difftastic/lane-status.json:13`,
     `lanes/dolt/lane-status.json:13`,
     `lanes/esbuild/lane-status.json:13`,
     `lanes/gitoxide/lane-status.json:13`,
     `lanes/libsqlite/lane-status.json:13`,
     `lanes/lightningcss/lane-status.json:13`,
     `lanes/markerpdf/lane-status.json:13`,
     `lanes/readability/lane-status.json:13`,
     `lanes/rclone/lane-status.json:13`,
     `lanes/syncthing/lane-status.json:13`.
   - Goal requirement at risk: `goal.md` requires small, reviewable committed
     slices with passing tests, and requires finished agent work to be verified,
     committed, integrated, and cleaned up.
   - Evidence: `HEAD` moved from `07ad9be61b09` through `73991a9a7f6e` to
     `550ac606f3df` during this audit, tracked dirty rows moved `329 -> 330`,
     default status rows moved `16957 -> 16975`, all-untracked status rows
     moved `17046 -> 17063`, and shortstat moved across multiple samples. Lane
     statuses still describe pending, uncommitted, or supervisor-owned batches.

2. **Critical - there is still no acceptable no-argument repo-wide PHP result
   for the current snapshot.**
   - Paths: `tools/run-tests.php`, `progress.md:45`,
     `lanes/dolt/lane-status.json:12`,
     `lanes/esbuild/lane-status.json:12`,
     `lanes/gitoxide/lane-status.json:12`,
     `lanes/libsqlite/lane-status.json:12`,
     `lanes/markerpdf/lane-status.json:12`,
     `lanes/pandoc/lane-status.json:12`,
     `lanes/rclone/lane-status.json:12`,
     `lanes/readability/lane-status.json:12`,
     `lanes/syncthing/lane-status.json:12`.
   - Goal requirement at risk: `goal.md` requires periodic repo-wide tests and
     static checks with failures recorded honestly.
   - Evidence: the required process gate returned no rows in early samples, then
     matched focused Readability PID `1585568` and focused Syncthing PID
     `1587954` owned by `claude`. Combined with the moving checkout, this audit
     did not start a duplicate or no-argument root run. A root run from a
     moving, unaccepted checkout would not prove the committed state.

3. **Critical - `porting.html` and `porting-summary.json` are stale
   publication artifacts.**
   - Paths: `porting.html:32`, `porting.html:34`, `porting.html:35`,
     `porting.html:75`, `porting-summary.json:2`,
     `porting-summary.json:3`, `porting-summary.json:8`,
     `dependency-backlog.json:3`.
   - Goal requirement at risk: `goal.md` requires current coordination files and
     a dashboard showing denominator, mapped tests, PHP pass/fail, phase, audit,
     current work, blocker, and commit for each lane.
   - Evidence: the dashboard still publishes average progress `97.7%`,
     generated time `2026-05-23 23:43:54 UTC`, source snapshot
     `79768df0c427`, and 22 dependency rows. Current `HEAD` is
     `550ac606f3df`, and `dependency-backlog.json` has 34 rows including one
     blocked support-library row.

4. **High - manifest, lane-status, and dashboard counts remain non-normalized
   and contradictory across active lanes.**
   - Paths: `porting.html:56` through `porting.html:67`,
     `porting-summary.json:11` through `porting-summary.json:59`,
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:12`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:12`,
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:12`,
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:12`,
     `lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json:12`,
     `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json:12`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:12`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:12`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:12`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:12`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:12`,
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:12`,
     `lanes/*/lane-status.json:6`.
   - Goal requirement at risk: `goal.md` requires comparable upstream
     denominator, mapped tests, PHP pass/fail, blocker, and commit fields at a
     glance.
   - Evidence: every lane disagrees with at least one of manifest, status, or
     dashboard. Examples: difftastic is manifest `1012/778`, status `3148/0`,
     dashboard `735/374/374`; rclone is manifest `1601/884`, status `884/0`,
     dashboard `1601/698/698`; markerPDF is manifest `386/337`, status
     `474/0`, dashboard `330/280/416`.

5. **High - Dolt still has a non-machine-checkable denominator.**
   - Paths: `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:12`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2508`,
     `lanes/dolt/lane-status.json:5`, `porting.html:57`,
     `porting-summary.json:32`.
   - Goal requirement at risk: `goal.md` requires a real upstream benchmark
     denominator to be mapped and visible in the dashboard.
   - Evidence: `benchmarkDenominator.total` is a prose string at line 2508
     instead of a numeric denominator, while `benchmarkDenominator.mapped` is
     numeric `613`. The dashboard falls back to `inventory`.

6. **High - near-complete percentages overstate accepted upstream and root
   parity.**
   - Paths: `porting.html:56` through `porting.html:67`,
     `lanes/difftastic/lane-status.json:4`,
     `lanes/esbuild/lane-status.json:4`,
     `lanes/gitoxide/lane-status.json:4`,
     `lanes/libsqlite/lane-status.json:4`,
     `lanes/markerpdf/lane-status.json:4`,
     `lanes/pandoc/lane-status.json:4`,
     `lanes/rclone/lane-status.json:4`,
     `lanes/syncthing/lane-status.json:4`.
   - Goal requirement at risk: `goal.md` says passing focused tests are not
     enough, upstream tests are the source of truth where possible, and hard
     gaps must be blockers or future slices.
   - Evidence: dashboard rows still show 92-99 percent while root verification
     is pending and several full upstream suites remain unexecuted or
     static-only: Difftastic full Cargo parity, esbuild `make test-all`,
     Gitoxide full workspace Cargo parity, SQLite `all`/`release`
     permutations, Pandoc full Haskell runner parity, rclone live
     provider/mount parity, markerPDF full model/runtime benchmark execution,
     and Syncthing full `go test ./...`.

7. **High - essential optional-library coverage is still backlog-only.**
   - Paths: `progress.md:17`, `progress.md:30`,
     `dependency-backlog.json:3`, `dependency-backlog.json:4`,
     `dependency-backlog.json:7`, `dependency-backlog.json:45`,
     `dependency-backlog.json:61`, `porting.html:75`.
   - Goal requirement at risk: support libraries require a bounded native PHP
     component, activation gate, dependency-specific upstream/spec denominator,
     mapped fixtures, PHP pass/fail evidence, malformed/corrupt cases where
     relevant, and as much of the upstream/spec suite as can honestly run.
   - Evidence: all 34 support rows are still `blocked`, `candidate`, or
     `deferred`; none is an active support-library manifest with PHP pass/fail
     evidence. Rich gaps remain for ZIP/package containers, XML/HTML, WebDAV,
     URL percent encoding, Unicode/charset, JSON/JSON5, source maps, package
     resolution, tree-sitter grammar subsets, sequence diff/merge, protobuf,
     checksum/hash, SQL expression semantics, archive/compression, QR matrix
     generation, and MySQL wire protocol boundaries.

8. **High - dependency-adjacent behavior is accumulating inside lanes before
   shared support-library gates are opened.**
   - Paths: `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:12`,
     `lanes/rclone/lane-status.json:5`,
     `lanes/esbuild/lane-status.json:5`,
     `lanes/readability/lane-status.json:5`,
     `lanes/libsqlite/lane-status.json:5`,
     `dependency-backlog.json:45`, `dependency-backlog.json:61`.
   - Goal requirement at risk: optional dependency work must be bounded, gated,
     tested, shared where appropriate, and backed by dependency-specific
     denominators before it can count as support-library progress.
   - Evidence: rclone is carrying WebDAV/XML/URL behavior, Readability is
     carrying URL cleanup, esbuild is carrying source-map/JSON behavior, and
     libsqlite is carrying JSON/JSON5/operator semantics. These may be valid
     lane slices, but they are not support-library progress until separate
     activation-gated manifests, upstream/spec denominators, malformed cases,
     and PHP pass/fail evidence exist.

9. **High - markerPDF still mixes native PDF slices with external/runtime
   application boundaries.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:19`,
     `lanes/markerpdf/lane-status.json:5`,
     `lanes/markerpdf/lane-status.json:9`,
     `lanes/markerpdf/lane-status.json:12`.
   - Goal requirement at risk: `goal.md` forbids counting wrappers, bridge
     calls, shell-outs, external converter/runtime execution, and whole
     applications as native port progress.
   - Evidence: the lane has useful native PDF parsing/extraction slices, but
     its manifest/status also enumerate benchmark runners, Streamlit,
     FastAPI/Uvicorn, multiprocessing/model workers, OCRMyPDF/Tesseract/
     Ghostscript, Texify/Nougat, Poetry/publish tooling, shell lifecycle plans,
     and server/app route planning. These must stay labeled as preflight or
     oracle metadata unless a bounded native PHP component owns and tests the
     behavior.

10. **Medium - Syncthing status has a future audit timestamp.**
    - Paths: `lanes/syncthing/lane-status.json:10`.
    - Goal requirement at risk: `goal.md` requires honest current status,
      blockers, audit state, and latest commit evidence.
    - Evidence: the shell clock for this audit was `2026-05-24T10:58Z`, but
      Syncthing line 10 records `audited 2026-05-24 12:30 UTC`. That makes the
      status chronology unreliable until corrected or explicitly explained.

11. **Medium - shell-out and external-process boundaries need continued
    explicit labeling.**
    - Paths: `lanes/gitoxide/lane-status.json:12`,
      `lanes/rclone/lane-status.json:12`,
      `lanes/markerpdf/lane-status.json:12`,
      `dependency-backlog.json:4`.
    - Goal requirement at risk: `goal.md` says generated fixtures, bridge calls,
      and shell-outs to upstream binaries must not count as native
      implementation progress.
    - Evidence: Gitoxide still leaves shell-backed filter/askpass child-process
      integration and real authenticated/channel adapters outside lane-local
      proof; rclone excludes live providers, FUSE, Docker, auth-proxy, and
      credential-bearing tests; markerPDF excludes external model/application
      execution. Keep this labeling strict so oracle tooling does not become
      hidden progress credit.
