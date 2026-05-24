# Independent Audit - 2026-05-24T02:29Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, every
`lanes/*/lane-status.json`, `dependency-backlog.json`, current process state,
and recent Git history.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, credential stores, provider
configs, or auth files. Bridge code, generated fixtures, plan-only wrappers,
and shell-outs are treated as non-progress unless explicitly temporary oracle
tooling.

`jq empty` passed for every lane manifest, every lane-status file,
`porting-summary.json`, and `dependency-backlog.json`.

## Current Snapshot

```text
current UTC sample: 2026-05-24T02:29:41Z
HEAD: 0a607ae0ed24 Record integration hold status
recent commits: 0a607ae0 Record integration hold status; 165f7f48 Refresh independent audit status; be139e06 Record integration hold status
branch sample: main...origin/main [ahead 655, behind 68]
tracked dirty rows: 299
default status rows including untracked: 11544
git diff --shortstat sample: 299 files changed, 146489 insertions(+), 16834 deletions(-)
coordination/test-control sample: dashboard, evaluator, watchdog, capacity, integrator, lane agents, support-library nudge, broad Dolt BATS, and Dolt subprocesses active
root run by this audit: not started
pre-root gate: pgrep -af '^php tools/run-tests\.php( |$)' returned no rows
```

## Findings

1. **Critical - the checkout is still a moving dirty aggregate, not an
   acceptance or root-verification target.**
   - Paths: `progress.md:39`, `progress.md:61` through `progress.md:78`,
     `lanes/difftastic/lane-status.json:13`,
     `lanes/dolt/lane-status.json:13`,
     `lanes/esbuild/lane-status.json:13`,
     `lanes/gitoxide/lane-status.json:13`,
     `lanes/quadrable/lane-status.json:13`,
     `lanes/rclone/lane-status.json:13`,
     `lanes/readability/lane-status.json:13`, and
     `lanes/syncthing/lane-status.json:13`.
   - Goal requirement at risk: `goal.md:29`, `goal.md:48`, and
     `goal.md:49` require small committed slices, verification, cleanup, and
     honest repo-wide test/static-check records.
   - Evidence: current `HEAD` is `0a607ae0ed24`, branch state is
     `ahead 655, behind 68`, tracked dirty rows are now 299, the default
     status surface is 11,544 rows, and shortstat is 299 files with 146,489
     insertions. Recent history is audit/integration-hold churn, not accepted
     lane commits. Active dashboard/evaluator/watchdog/capacity/integrator/lane
     processes and a broad Dolt BATS run were visible during the sample.

2. **Critical - `porting.html` and `porting-summary.json` are stale and
   contradict the current source of truth.**
   - Paths: `porting.html:32` through `porting.html:38`,
     `porting.html:56` through `porting.html:67`,
     `porting.html:75` through `porting.html:78`,
     `porting-summary.json:2` through `porting-summary.json:8`,
     `porting-summary.json:215` through `porting-summary.json:227`,
     `dependency-backlog.json:3`, and
     `dependency-backlog.json:110` through `dependency-backlog.json:123`.
   - Goal requirement at risk: `goal.md:3` and `goal.md:45` require a current
     dashboard with denominator, mapped tests, PHP pass/fail, WordPress
     scenarios, phase, audit, current work, blocker, and commit.
   - Evidence: the dashboard still says it was generated
     `2026-05-23 23:43:54 UTC` from `79768df0c427`, while the repo is at
     `0a607ae0ed24` with newer dirty manifests/status files. The dashboard
     dependency section still reports 22 items, 12 candidates, and 10
     medium-priority items; `dependency-backlog.json` has 23 items, 13
     candidates, and 11 medium-priority items after
     `pandoc-doctemplates-core`.

3. **High - public counts and current manifests disagree in multiple lanes.**
   - Paths: `porting.html:56` through `porting.html:67`,
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:13` through
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:13` through
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:18`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:19`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:13` through
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:16`, and
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:13` through
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:16`.
   - Goal requirement at risk: `goal.md:3`, `goal.md:25`, and
     `goal.md:45`.
   - Evidence: current Difftastic manifest says 789 artifacts and 407 mapped,
     but the dashboard says 735 and 374. Gitoxide says 2,877 mapped of 2,877,
     while the dashboard says 2,751 mapped. markerPDF says 343 total and 294
     mapped, while the dashboard says 330 and 280. rclone says 752 mapped, but
     the dashboard says 698. Readability says all 1,984 mapped, but the
     dashboard still shows only 204 PHP pass in the lane row.

4. **High - focused lane-green evidence is still being recorded before
   supervisor acceptance and aggregate verification.**
   - Paths: all current `lanes/*/lane-status.json:5` through
     `lanes/*/lane-status.json:13`.
   - Goal requirement at risk: `goal.md:29`, `goal.md:48`, and
     `goal.md:49`.
   - Evidence: lane statuses record focused PHP passes such as Gitoxide 6,091
     assertions, rclone 752 behavior tests, Syncthing 5,579 assertions, and
     Readability 218 tests. The same records say root aggregate verification is
     pending and latest commits are pending, uncommitted, or shared-dirty
     handoffs. These are review signals, not accepted portfolio progress.

5. **High - markerPDF still over-credits plan-only external/runtime
   orchestration as mapped native port progress.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:10`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:19`,
     and `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:30` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:49`.
   - Goal requirement at risk: `goal.md:1`, `goal.md:25`,
     `goal.md:30`, `goal.md:31`, and `goal.md:35`.
   - Evidence: markerPDF has real native PHP slices, but its manifest status
     and mapped source also count benchmark CLI/memory/Nougat plans, CI
     workflow plans, OCR/model readiness, Tesseract/OCRMyPDF/Ghostscript/Texify
     readiness, Streamlit/FastAPI/Uvicorn route planning, Poetry/package
     metadata, and chunk-convert shell lifecycle planning. Those are blockers
     or support-library candidates unless converted into bounded native PHP
     behavior with dependency-specific denominators and fixtures.

6. **High - optional support libraries remain backlog-only while lane-local
   archive/provider helpers expand outside shared gates.**
   - Paths: `progress.md:17` through `progress.md:24`,
     `dependency-backlog.json:7` through `dependency-backlog.json:23`,
     `dependency-backlog.json:110` through `dependency-backlog.json:123`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:96` through
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:103`,
     `lanes/rclone/src/VfsZipArchive.php:7` through
     `lanes/rclone/src/VfsZipArchive.php:13`, and
     `lanes/rclone/src/VfsServeZipResponse.php:7` through
     `lanes/rclone/src/VfsServeZipResponse.php:10`.
   - Goal requirement at risk: `goal.md:24` through `goal.md:31`,
     `goal.md:35`, and this run's support-library audit requirement.
   - Evidence: `shared-zip-package-core` and `pandoc-doctemplates-core` are
     candidate rows with expectations but no support-library manifest, accepted
     commit, dashboard row, or malformed/corrupt-case evidence. rclone's ZIP
     writer and serve-zip response may be valid lane-local VFS slices, but they
     must not count as shared ZIP/archive progress until a bounded shared
     component has its own denominator, activation gate, mapped fixtures, PHP
     pass/fail evidence, and corrupt archive cases.

7. **High - near-complete percentages overstate accepted upstream parity.**
   - Paths: `porting.html:32`, `porting.html:56` through
     `porting.html:67`, `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:17`
     through `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:22`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:36` through
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:38`,
     `lanes/rclone/lane-status.json:12`, and
     `lanes/syncthing/lane-status.json:12`.
   - Goal requirement at risk: `goal.md:35`, `goal.md:37`,
     `goal.md:38`, and `goal.md:40`.
   - Evidence: the dashboard advertises 97.7% average progress and 98-99% for
     most lanes. Major parity remains unexecuted, excluded, or static-only:
     Gitoxide full Cargo workspace, Pandoc Haskell runner, Syncthing full
     `go test ./...`, markerPDF full benchmark/model runner, rclone provider
     and mount parity, Difftastic full Cargo, esbuild `make test-all`, and
     libsqlite all/release permutations.

8. **Medium - manifest/status schemas remain non-normalized and make the
   dashboard hard to trust mechanically.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:13` through
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:13` through
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2388` through
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2389`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:13` through
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:15`, and
     `porting-summary.json:15` through `porting-summary.json:18`.
   - Goal requirement at risk: `goal.md:3`, `goal.md:25`, and
     `goal.md:45`.
   - Evidence: `benchmarkDenominator.total` is sometimes numeric, sometimes a
     long narrative string, sometimes paired with `totalCount`, and in Dolt
     appears after `mapped` as a late narrative field. PHP pass/fail values
     mix behavior tests, assertions, PASS cases, and mapped denominator checks.

9. **Medium - Syncthing manifest/status handoff is internally stale.**
   - Paths: `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:1593` through
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:1599` and
     `lanes/syncthing/lane-status.json:9` through
     `lanes/syncthing/lane-status.json:13`.
   - Goal requirement at risk: `goal.md:3`, `goal.md:31`, and
     `goal.md:44`.
   - Evidence: the manifest `currentSlice` still stops at service/report
     coverage and its `nextTask` says to map `/rest/system/connections`.
     `lane-status.json` says `/rest/system/connections` has already been
     mapped and tested. That mismatch is exactly why dashboard generation from
     a dirty moving tree is unsafe.

10. **Medium - `progress.md` active-lane handoffs lag current lane-status
    files.**
    - Paths: `progress.md:63` through `progress.md:76`,
      `lanes/gitoxide/lane-status.json:11`,
      `lanes/rclone/lane-status.json:11`,
      `lanes/readability/lane-status.json:11`,
      `lanes/syncthing/lane-status.json:11`, and
      `lanes/esbuild/lane-status.json:11`.
    - Goal requirement at risk: `goal.md:44`.
    - Evidence: `progress.md` still lists older handoffs such as Gitoxide SSH
      config options, rclone VFS Statfs/usage, Readability negative-header
      cleanup, Syncthing system log route, and Esbuild automatic JSX
      key/spread. Current lane-status files describe newer work such as
      Gitoxide worktree ignore-stack, rclone HTTP favicon, Readability
      data-table descendant cleanup, Syncthing system connections, and Esbuild
      static private class-expression decorators.

## Test Gate

I did not run `php tools/run-tests.php`.

The required process gate was checked before considering a root run:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
<no rows>
```

Even with no exact PHP harness at that sample, the checkout was not stable
enough for an audit-owned no-argument root run. Active coordination/lane
processes and broad Dolt BATS were present, the dirty surface is enormous, the
dashboard is stale, lane handoffs are unaccepted, and manifest/status artifacts
disagree. A root run here would be another moving-snapshot anecdote rather than
the accepted verification record required by the goal.

## Next Intervention

Freeze writers, status publishers, focused PHP loops, broad upstream runners,
and dashboard generation. Confirm the exact PHP harness gate is empty, then
poll `HEAD`, tracked status count, shortstat, runner state, and relevant log
mtimes twice without movement. Accept exactly one lane-scoped batch, normalize
its manifest/status schema, run focused verification and `git diff --check`,
run one serialized no-argument `php tools/run-tests.php` from the same
snapshot, regenerate `porting.html` and `porting-summary.json` from the
accepted commit, then commit or reject.
