# Independent Audit - 2026-05-24T11:32Z

Scope reviewed: `goal.md`, `progress.md`, current worktree `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, current
`lanes/*/lane-status.json`, `dependency-backlog.json`,
`audits/integration-status.md`, and recent Git history through
`e4fc1f56 Record integration hold status`. I did not edit lane implementation
files, launch agents or tmux sessions, push, read secrets, inspect process
environments, credential stores, provider configs, or auth files.

Bridge code, generated fixtures, shell-outs, whole applications, external
converter wrappers, and hidden process launchers are treated as non-progress
unless they are explicitly temporary oracle tooling.

## Current Snapshot

```text
UTC samples: 2026-05-24T11:26:03Z, 11:26:23Z, 11:26:46Z, 11:28:04Z, 11:30:23Z, 11:32:37Z
HEAD: e4fc1f56e24b
recent history: e4fc1f56 Record integration hold status; 106d9968 Refresh independent audit status; d0a30984 Record integration hold status; 535e559c Record integration hold status; 1e4d30e2 Record support dependency routing
tracked dirty rows: 328
default status rows including untracked: 17030 -> 17034
git diff --shortstat: 328 files changed, 225697 insertions(+), 29687 deletions(-) -> 328 files changed, 225742 insertions(+), 29687 deletions(-) -> 328 files changed, 225772 insertions(+), 29669 deletions(-) -> 328 files changed, 225978 insertions(+), 29669 deletions(-)
dashboard worktree snapshot: porting.html and porting-summary.json generated 2026-05-23 23:43:54 UTC from source 79768df0c427
dependency backlog: dependency-backlog.json updated 2026-05-24 11:14:00 UTC with 36 rows (1 blocked, 24 candidate, 11 deferred); dashboard summary still reports 22 rows
root run by this audit: not started
```

Required pre-root process-gate evidence:

```text
pgrep -af '^php tools/run-tests\.php( |$)' at 2026-05-24T11:26:03Z:
no rows

pgrep -af '^php tools/run-tests\.php( |$)' at 2026-05-24T11:26:23Z:
no rows

pgrep -af '^php tools/run-tests\.php( |$)' at 2026-05-24T11:26:46Z:
no rows

pgrep -af '^php tools/run-tests\.php( |$)' at 2026-05-24T11:28:04Z:
no rows

pgrep -af '^php tools/run-tests\.php( |$)' final validation sample at 2026-05-24T11:30:23Z:
1938898 php tools/run-tests.php lanes/syncthing/tests

owner evidence for active focused harness:
PID 1938898 USER claude PPID 1883120 STAT Rs ETIMES 57 COMMAND php tools/run-tests.php lanes/syncthing/tests

pgrep -af '^php tools/run-tests\.php( |$)' final handoff sample at 2026-05-24T11:32:37Z:
1959730 php tools/run-tests.php lanes/readability/tests

owner evidence for active focused harness:
PID 1959730 USER claude PPID 1925189 STAT Rs ETIMES 4 COMMAND php tools/run-tests.php lanes/readability/tests
```

I did not start `php tools/run-tests.php`. The exact process gate was clear
during the 11:26-11:28 UTC stability samples, but the checkout failed the
stability gate because untracked-inclusive status and shortstat changed across
the audit samples; final validation then matched focused Syncthing and
Readability harnesses. A root result from this moving, unaccepted checkout
would not prove the committed state. `jq empty` passed for all 12 lane
manifests, all 12 lane-status files, `porting-summary.json`, and
`dependency-backlog.json`.

Current count sample:

```text
lane          manifest total/mapped          lane-status php   dashboard total/mapped/php
difftastic    1025 / 796                     3180 / 0          735 / 374 / 374
dolt          string total / 613             422 / 0           inventory / 613 / 356
esbuild       2567 / 419                     419 / 0           2567 / 311 / 311
gitoxide      2877 / 2877                    7064 / 0          2877 / 2751 / 5634
libsqlite     1589 / 344                     343 / 0           1589 / 286 / 286
LightningCSS  3545 / 2755                    4033 / 0          3532 / 1732 / 2197
markerPDF     389 / 340                      477 / 0           330 / 280 / 416
pandoc        2276 / 1848                    355 / 0           2276 / 1061 / 278
quadrable     55 / 55                        229 / 0           55 / 55 / 190
rclone        1601 / 892                     892 / 0           1601 / 698 / 698
readability   1984 / 1984                    257 / 0           1984 / 1984 / 204
syncthing     658 / 658                      7705 / 0          658 / 658 / 4579
```

## Findings

1. **Critical - the checkout is still not an acceptance checkpoint.**
   - Paths: `progress.md:45`, `progress.md:47`,
     `audits/integration-status.md:1`, `lanes/*/lane-status.json`,
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`.
   - Goal requirement at risk: `goal.md:29` requires small reviewable
     committed slices with passing tests, and `goal.md:48` requires finished
     agent work to be verified, committed, integrated, and cleaned up.
   - Evidence: latest history is audit/integration status only, while tracked
     dirty rows remain at `328` across all 12 priority lanes. During this
     audit, untracked-inclusive rows moved `17030 -> 17034` and shortstat
     moved from `328 files changed, 225697 insertions(+), 29687 deletions(-)`
     to `328 files changed, 225978 insertions(+), 29669 deletions(-)`.
     Current lane statuses still report pending/uncommitted batches.

2. **Critical - there is still no acceptable no-argument repo-wide PHP result
   for the current snapshot.**
   - Paths: `tools/run-tests.php`, `progress.md:47`,
     `audits/integration-status.md:24`, `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:49` requires periodic repo-wide tests
     and static checks with failures recorded honestly.
   - Evidence: the exact pre-root gate was clear in the 11:26-11:28 UTC
     audit-owned samples, but the tree moved during those samples. Final
     validation then matched focused Syncthing PID `1938898` owned by
     `claude` (`php tools/run-tests.php lanes/syncthing/tests`, PPID
     `1883120`, state `Rs`, elapsed `57s` at owner sample), and the final
     handoff sample matched focused Readability PID `1959730` owned by
     `claude` (`php tools/run-tests.php lanes/readability/tests`, PPID
     `1925189`, state `Rs`, elapsed `4s` at owner sample). I did not start a
     duplicate or audit-owned root run. Focused lane evidence remains useful
     local proof, but it is not a serialized no-argument aggregate result from
     a frozen accepted tree.

3. **Critical - `porting.html` and `porting-summary.json` are stale
   publication artifacts.**
   - Paths: `porting.html:32`, `porting.html:35`, `porting.html:38`,
     `porting-summary.json:2`, `porting-summary.json:3`,
     `porting-summary.json:8`, `dependency-backlog.json:3`.
   - Goal requirement at risk: `goal.md:3` and `goal.md:45` require current
     coordination files and a dashboard showing denominator, mapped tests, PHP
     pass/fail, phase, audit, current work, blocker, and commit.
   - Evidence: the dashboard still publishes average progress `97.7%`,
     generated time `2026-05-23 23:43:54 UTC`, source snapshot
     `79768df0c427`, and a 22-row dependency backlog summary. Current `HEAD`
     is `e4fc1f56`, and `dependency-backlog.json` has 36 rows.

4. **High - manifest, lane-status, and dashboard counts are contradictory
   across every active lane.**
   - Paths: `porting.html:56` through `porting.html:67`,
     `porting-summary.json:11`, `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2511`,
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/*/lane-status.json:4`.
   - Goal requirement at risk: `goal.md:44` and `goal.md:45` require
     comparable upstream denominator, mapped tests, PHP pass/fail, blocker,
     current work, and commit fields at a glance.
   - Evidence: examples from the current sample: Difftastic is manifest
     `1025/796`, status `3180/0`, dashboard `735/374/374`; LightningCSS is
     manifest `3545/2755`, status `4033/0`, dashboard `3532/1732/2197`;
     Syncthing is manifest `658/658`, status `7705/0`, dashboard
     `658/658/4579`.

5. **High - Dolt still has a non-machine-checkable denominator.**
   - Paths: `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2511`,
     `lanes/dolt/lane-status.json:5`, `porting.html:57`,
     `porting-summary.json:28`.
   - Goal requirement at risk: `goal.md:25` requires a real upstream
     benchmark denominator to be mapped, and `goal.md:45` requires that
     denominator to be visible in the dashboard.
   - Evidence: `benchmarkDenominator.total` is a prose evidence paragraph at
     line 2511 instead of a numeric denominator, while
     `benchmarkDenominator.mapped` is numeric `613`. The dashboard falls back
     to `inventory`, so the denominator cannot be validated mechanically.

6. **High - near-complete percentages overstate accepted upstream and root
   parity.**
   - Paths: `porting.html:56` through `porting.html:67`,
     `lanes/difftastic/lane-status.json:4`,
     `lanes/esbuild/lane-status.json:4`,
     `lanes/gitoxide/lane-status.json:4`,
     `lanes/libsqlite/lane-status.json:4`,
     `lanes/lightningcss/lane-status.json:4`,
     `lanes/markerpdf/lane-status.json:4`,
     `lanes/pandoc/lane-status.json:4`,
     `lanes/rclone/lane-status.json:4`,
     `lanes/syncthing/lane-status.json:4`.
   - Goal requirement at risk: `goal.md:35` through `goal.md:40` say passing
     focused tests are not enough, upstream tests are the source of truth where
     possible, and hard gaps must be blockers or future slices.
   - Evidence: dashboard/status rows still show 95-99 percent while root
     verification is pending and several full upstream suites remain unrun or
     static-only: Difftastic full Cargo parity, esbuild `make test-all`,
     Gitoxide full Cargo workspace parity, SQLite broader `all`/`release`
     permutations, Pandoc full Haskell runner parity, rclone live
     provider/mount parity, markerPDF full model/runtime benchmark execution,
     and Syncthing full `go test ./...`.

7. **High - essential optional-library coverage is still backlog-only.**
   - Paths: `progress.md:17`, `progress.md:31`,
     `dependency-backlog.json:3`, `dependency-backlog.json:5`,
     `porting.html:75`, `porting-summary.json:214`.
   - Goal requirement at risk: this audit run requires support libraries to
     have a bounded native PHP component, activation gate,
     dependency-specific upstream/spec denominator, mapped fixtures, PHP
     pass/fail evidence, malformed/corrupt cases where relevant, and as much
     of the upstream/spec suite as can honestly run.
   - Evidence: all 36 support rows are still `blocked`, `candidate`, or
     `deferred`; none is an active support-library manifest with PHP pass/fail
     evidence. Rich-function gaps remain for ZIP/package containers,
     XML/HTML, WebDAV, URL percent encoding, Unicode/charset, JSON/JSON5,
     source maps, package resolution, tree-sitter grammar subsets, sequence
     diff/merge, protobuf, checksum/hash, SQL expression semantics,
     archive/compression, QR matrix generation, and MySQL wire protocol
     boundaries.

8. **High - dependency-adjacent behavior is accumulating inside lanes before
   shared support-library gates are opened.**
   - Paths: `lanes/rclone/lane-status.json:10`,
     `lanes/esbuild/lane-status.json:10`,
     `lanes/readability/lane-status.json:10`,
     `lanes/libsqlite/lane-status.json:10`,
     `lanes/syncthing/lane-status.json:10`,
     `dependency-backlog.json:45`, `dependency-backlog.json:61`,
     `dependency-backlog.json:378`, `dependency-backlog.json:396`,
     `dependency-backlog.json:499`.
   - Goal requirement at risk: this audit run requires optional dependency
     work to be bounded, gated, tested, shared where appropriate, and backed by
     dependency-specific denominators before it can count as support-library
     progress.
   - Evidence: rclone is carrying WebDAV/XML/URL behavior, Readability is
     carrying URL cleanup, esbuild is carrying source-map/JSON behavior,
     libsqlite is carrying JSON/JSON5/operator semantics, and Syncthing has QR
     route work while `qr-code-matrix-core` remains blocked pending accepted
     integration. These can be valid lane slices, but they are not
     support-library progress until separate activation-gated manifests,
     upstream/spec denominators, malformed cases, and PHP pass/fail evidence
     exist.

9. **High - markerPDF still mixes native PDF slices with external/runtime
   application boundaries.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/markerpdf/lane-status.json:5`,
     `lanes/markerpdf/lane-status.json:10`,
     `lanes/markerpdf/lane-status.json:12`.
   - Goal requirement at risk: `goal.md:30` forbids counting wrappers,
     bridge calls, shell-outs, external converter/runtime execution, and whole
     applications as native port progress.
   - Evidence: the lane has useful native PDF parsing/extraction slices, but
     its manifest/status also enumerate benchmark runners, Streamlit,
     FastAPI/Uvicorn, multiprocessing/model workers, OCR/Tesseract/Ghostscript
     style runtime boundaries, Texify/Nougat, Poetry/publish tooling, shell
     lifecycle plans, and server/app route planning. These must stay labeled
     as preflight or oracle metadata unless a bounded native PHP component owns
     and tests the behavior.

10. **Medium - progress still records stale support-library publication
    history even after backlog expansion.**
    - Paths: `progress.md:29`, `progress.md:30`,
      `progress.md:31`, `dependency-backlog.json:3`,
      `porting.html:75`, `porting-summary.json:214`.
    - Goal requirement at risk: `goal.md:44` and `goal.md:45` require current
      roadmap, blockers, dashboard, and support status to be visible and
      comparable.
    - Evidence: `progress.md` correctly records the 36-row backlog in the
      current coordination snapshot, but the public dashboard still shows an
      older support-library surface. This leaves reviewers with two different
      support-library realities: a current 36-row gated backlog in JSON and a
      stale 22-row dashboard summary.

11. **Medium - shell-out and external-process boundaries need continued
    explicit labeling.**
    - Paths: `lanes/gitoxide/lane-status.json:12`,
      `lanes/rclone/lane-status.json:12`,
      `lanes/markerpdf/lane-status.json:12`,
      `dependency-backlog.json:4`.
    - Goal requirement at risk: `goal.md:30` says generated fixtures, bridge
      calls, and shell-outs to upstream binaries must not count as native
      implementation progress.
    - Evidence: Gitoxide still leaves shell-backed filter/askpass child-process
      integration and real authenticated/channel adapters outside lane-local
      proof; rclone excludes live providers, FUSE, Docker, auth-proxy, and
      credential-bearing tests; markerPDF excludes external model/application
      execution. Keep this labeling strict so oracle tooling does not become
      hidden progress credit.

## Next Intervention

Hold implementation integration until there is a hard writer/runner/status
freeze, two stable polls of `HEAD`, tracked/default status counts, shortstat,
process gates, dependency/dashboard counts, and relevant log mtimes. Then
accept one coherent lane batch, normalize manifest/status/dashboard counts,
run focused verification plus `git diff --check`, create activation-gated
support-library manifests only where a concrete lane gate needs them,
regenerate the dashboard from the accepted commit, and run one serialized
no-argument `php tools/run-tests.php` only if the exact process gate stays
empty on that frozen snapshot.
