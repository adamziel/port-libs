# Independent Audit - 2026-05-24T10:50Z

Scope reviewed: `goal.md`, `progress.md`, current worktree `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, current
`lanes/*/lane-status.json`, `dependency-backlog.json`, and recent Git history
through `f62f2053 Record integration hold status`. I did not edit lane
implementation files, launch agents or tmux sessions, push, read secrets,
inspect process environments, credential stores, provider configs, or auth
files.

Bridge code, generated fixtures, shell-outs, whole applications, external
converter wrappers, and hidden process launchers are treated as non-progress
unless they are explicitly temporary oracle tooling.

## Current Snapshot

```text
UTC samples: 2026-05-24T10:40Z through 2026-05-24T10:50Z
HEAD moved during audit validation: 4ce14f9bde5d -> 23eafd3788c6 -> 2e436fba -> f62f2053
recent history: f62f2053 Record integration hold status; 9a91e686 Refresh independent audit status; 2e436fba Record integration hold status; 23eafd37 Record integration hold status
tracked dirty rows moved: 328 -> 329 -> 328 -> 330 -> 328
default status rows including untracked moved: 16930 -> 16939 -> 16940 -> 16946 -> 16949
git diff --shortstat moved: 328 files changed, 218746 insertions(+), 29074 deletions(-) -> 329 files changed, 219043 insertions(+), 29076 deletions(-) -> 328 files changed, 219271 insertions(+), 29105 deletions(-) -> 330 files changed, 219678 insertions(+), 29153 deletions(-) -> 328 files changed, 219921 insertions(+), 29107 deletions(-)
dashboard worktree snapshot: porting.html and porting-summary.json generated 2026-05-23 23:43:54 UTC from source 79768df0c427
dependency backlog: dependency-backlog.json updated 2026-05-24 10:42:36 UTC with 34 rows (23 candidate, 11 deferred); dashboard still shows 22 rows
root run by this audit: not started
```

Required pre-root process-gate evidence:

```text
pgrep -af '^php tools/run-tests\.php( |$)' initial sample:
no rows

pgrep -af '^php tools/run-tests\.php( |$)' later sample:
1435106 php tools/run-tests.php lanes/syncthing/tests
1436914 php tools/run-tests.php lanes/quadrable/tests

owner evidence after transient quadrable exit:
PID 1435106 USER claude PPID 1300575 STAT Rs ELAPSED 01:11 COMMAND php tools/run-tests.php lanes/syncthing/tests

pgrep -af '^php tools/run-tests\.php( |$)' final validation sample:
no rows
```

I did not start `php tools/run-tests.php`. The exact required gate was clear at
the initial sample, later matched active focused PHP harnesses, and cleared at
final validation; the checkout still failed the stability gate because `HEAD`,
tracked rows, untracked-inclusive status, and shortstat moved. `jq empty` passed
for all 12 root lane manifests, all 12 lane-status files,
`porting-summary.json`, and `dependency-backlog.json`.

Current count sample:

```text
lane          manifest total/mapped          lane-status php   dashboard total/mapped/php
difftastic    1004 / 770                     3136 / 0          735 / 374 / 374
dolt          string total / 613             421 / 0           inventory / 613 / 356
esbuild       2567 / 412                     412 / 0           2567 / 311 / 311
gitoxide      2877 / 2877                    7012 / 0          2877 / 2751 / 5634
libsqlite     1589 / 340                     340 / 0           1589 / 286 / 286
LightningCSS  3535 / 2741                    4006 / 0          3532 / 1732 / 2197
markerPDF     385 / 336                      473 / 0           330 / 280 / 416
pandoc        2276 / 1827                    351 / 0           2276 / 1061 / 278
quadrable     55 / 55                        227 / 0           55 / 55 / 190
rclone        1601 / 879                     880 / 0           1601 / 698 / 698
readability   1984 / 1984                    252 / 0           1984 / 1984 / 204
syncthing     658 / 658                      7518 / 0          658 / 658 / 4579
```

## Findings

1. **Critical - the checkout is still not an acceptance checkpoint.**
   - Paths: `progress.md:43`, `progress.md:45`,
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
     slices with passing tests, and requires the supervisor to verify, commit,
     integrate, and clean up finished agent work.
   - Evidence: `HEAD` moved from `4ce14f9bde5d` through `23eafd3788c6` and
     `2e436fba` to `f62f2053` during audit validation, and the aggregate
     worktree moved during this audit: tracked dirty rows moved `328 -> 329 -> 328 -> 330 -> 328`,
     untracked-inclusive rows moved `16930 -> 16939 -> 16940 -> 16946 -> 16949`, and shortstat
     moved from `328 files changed, 218746 insertions(+), 29074 deletions(-)`
     to `328 files changed, 219921 insertions(+), 29107 deletions(-)`. Lane
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
   - Evidence: the required process gate later matched active focused harness
     PID `1435106` owned by `claude` (`php tools/run-tests.php
     lanes/syncthing/tests`), after transient PID `1436914` for quadrable.
     Combined with the moving checkout, this audit did not start a duplicate or
     no-argument root run. Focused lane evidence is not a substitute for one
     serialized no-argument root result from a frozen accepted tree.

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
     `f62f2053`, and `dependency-backlog.json` has 34 rows.

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
   - Goal requirement at risk: `goal.md` requires the dashboard to expose
     comparable upstream denominator, mapped tests, PHP pass/fail, blocker, and
     commit fields at a glance.
   - Evidence: the count sample above shows every lane disagreeing with at
     least one of manifest, status, or dashboard. Examples: rclone is manifest
     `1601/879`, status `880/0`, dashboard `1601/698/698`; pandoc is manifest
     `2276/1827`, status `351/0`, dashboard `2276/1061/278`; markerPDF is
     manifest `385/336`, status `473/0`, dashboard `330/280/416`.

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
     numeric `613`. The dashboard falls back to `inventory`, status PHP is
     `421/0`, and dashboard PHP remains `356`, so the denominator cannot be
     validated mechanically.

6. **High - near-complete percentages overstate accepted upstream and root
   parity.**
   - Paths: `porting.html:56` through `porting.html:67`,
     `lanes/difftastic/lane-status.json:12`,
     `lanes/esbuild/lane-status.json:12`,
     `lanes/gitoxide/lane-status.json:12`,
     `lanes/libsqlite/lane-status.json:12`,
     `lanes/markerpdf/lane-status.json:12`,
     `lanes/pandoc/lane-status.json:12`,
     `lanes/rclone/lane-status.json:12`,
     `lanes/syncthing/lane-status.json:12`.
   - Goal requirement at risk: `goal.md` says passing focused tests are not
     enough, upstream tests are the source of truth where possible, and hard
     gaps must be blockers or future slices.
   - Evidence: dashboard rows still show 92-99 percent while root verification
     is pending. Difftastic full Cargo parity is unavailable, esbuild
     `make test-all` remains static-only, Gitoxide full workspace Cargo parity
     is unrun, SQLite `all`/`release` permutations remain outside the bounded
     pass, Pandoc full Haskell runner parity is unexecuted, rclone live
     provider/mount parity remains open, markerPDF full runner depends on heavy
     external runtimes/models, and Syncthing full `go test ./...` is unexecuted.

7. **High - essential optional-library coverage is still backlog-only.**
   - Paths: `progress.md:17`, `progress.md:29`,
     `dependency-backlog.json:3`, `dependency-backlog.json:4`,
     `dependency-backlog.json:7`, `dependency-backlog.json:45`,
     `dependency-backlog.json:61`, `porting.html:75`.
   - Goal requirement at risk: support libraries require a bounded native PHP
     component, activation gate, dependency-specific upstream/spec denominator,
     mapped fixtures, PHP pass/fail evidence, malformed/corrupt cases where
     relevant, and as much of the upstream/spec suite as can honestly run.
   - Evidence: all 34 support rows are still `candidate` or `deferred`; none is
     an active support-library manifest with PHP pass/fail evidence. Rich gaps
     remain for ZIP/package containers, XML/HTML, WebDAV, URL percent encoding,
     Unicode/charset, JSON/JSON5, source maps, package resolution, tree-sitter
     grammar subsets, sequence diff/merge, protobuf, checksum/hash, SQL
     expression semantics, archive/compression, QR matrix generation, and MySQL
     wire protocol boundaries.

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

10. **Medium - shell-out and external-process boundaries need continued
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

## Next Best Intervention

Freeze active writers, status/dashboard publishers, focused lane harnesses, and
external runners. Require two stable polls of `HEAD`, tracked dirty rows,
untracked-inclusive rows, shortstat, exact `php tools/run-tests.php` gate, and
dashboard source commit. Then accept one lane batch at a time: normalize
manifest/status/dashboard counts, run focused lane verification plus
`git diff --check`, add activation-gated support-library manifests only when a
real rich-function blocker requires them, regenerate `porting.html` and
`porting-summary.json` from the accepted commit, and run one serialized
no-argument root result only if the exact process gate remains empty on that
frozen snapshot.
