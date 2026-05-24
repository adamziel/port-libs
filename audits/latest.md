# Independent Audit - 2026-05-24T01:58Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, every
`lanes/*/lane-status.json`, `dependency-backlog.json`, `audits/latest.md`,
`audits/integration-status.md`, and recent Git history.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, credential stores, provider
configs, or auth files. Bridge code, generated fixtures, plan-only wrappers,
and shell-outs are treated as non-progress unless they are explicitly temporary
oracle tooling.

`jq empty` passed for every lane manifest, every lane-status file,
`porting-summary.json`, and `dependency-backlog.json`.

## Current Snapshot

```text
HEAD movement during audit: e177715c -> ce1a480e via integration-hold/status commits
HEAD at final sample before writing: ce1a480e
latest visible commits: ce1a480e Record integration hold status; e177715c Refresh independent audit status; 7567e173 Record integration hold status
branch sample: main...origin/main [ahead 645, behind 68]
tracked dirty rows: 297
total status rows including untracked: 11265
git diff --shortstat: 297 files changed, 142435 insertions(+), 16647 deletions(-)
root run by this audit: not started
initial pre-root gate: 2492226 php tools/run-tests.php lanes/quadrable/tests
owner sample for PID 2492226: process had exited before `ps -o pid,user,ppid,stat,etime,command -p 2492226` could report owner
final pre-write gate: pgrep -af '^php tools/run-tests\.php( |$)' returned no rows
```

## Findings

1. **Critical - the worktree is still not a valid aggregate verification or
   lane-acceptance target.**
   - Paths: `progress.md:39`, `progress.md:58`,
     `audits/integration-status.md:13` through
     `audits/integration-status.md:46`, `lanes/*/lane-status.json:13`.
   - Goal requirement at risk: `goal.md:29`, `goal.md:48`, and
     `goal.md:49` require small committed slices, verification, cleanup, and
     honest repo-wide test/static-check records.
   - Evidence: `HEAD` moved while this audit was running, the branch is
     `ahead 645, behind 68`, the checkout has 297 tracked dirty rows and
     11,265 total status rows, and every lane-status file still reports a
     pending or uncommitted handoff. The latest history is audit/integration
     status, not accepted lane feature commits.

2. **Critical - `porting.html` and `porting-summary.json` are stale enough to
   mislead reviewers.**
   - Paths: `porting.html:32` through `porting.html:38`,
     `porting.html:56` through `porting.html:67`, `porting.html:75` through
     `porting.html:78`, `porting-summary.json`, `dependency-backlog.json:1`
     through `dependency-backlog.json:5`.
   - Goal requirement at risk: `goal.md:3` and `goal.md:45` require a current
     dashboard with denominator, mapped tests, PHP pass/fail, WordPress
     scenarios, phase, audit, current work, blocker, and commit.
   - Evidence: the dashboard advertises generated time
     `2026-05-23 23:43:54 UTC` and snapshot `main 79768df0c427`, while the
     current checkout is at `ce1a480e`. It still shows old lane work such as
     Gitoxide `GitConfig`, markerPDF `Span.fix_unicode`, rclone VFS
     `Dump/AddVirtual`, and Syncthing `ConfigLdap`; current lane-status files
     describe Gitoxide `GitIgnore`, markerPDF debug bbox stdout, rclone
     directory-listing responses, and Syncthing random-string service behavior.
     The dashboard dependency table still says 22 items and 12 candidates,
     while `dependency-backlog.json` has 23 items and 13 candidates.

3. **High - `progress.md` active-lane handoff labels lag current
   lane-status handoffs.**
   - Paths: `progress.md:62` through `progress.md:73`,
     `lanes/gitoxide/lane-status.json:11`,
     `lanes/markerpdf/lane-status.json:11`,
     `lanes/pandoc/lane-status.json:11`,
     `lanes/rclone/lane-status.json:11`,
     `lanes/syncthing/lane-status.json:11`,
     `lanes/esbuild/lane-status.json:11`.
   - Goal requirement at risk: `goal.md:44` requires `progress.md` to show
     current lanes, current work, blockers, and next task state.
   - Evidence: `progress.md` still lists Gitoxide SSH config-options,
     markerPDF benchmark file-inventory, Pandoc figure/citation, Syncthing
     system log, rclone Statfs/usage, and esbuild automatic JSX key/spread
     handoffs. Current lane-status files instead describe Gitoxide ignore-file
     loading, markerPDF debug bbox stdout reporting, Pandoc DOCX paragraph
     changes, rclone HTTP/WebDAV directory listing, Syncthing random-string
     service behavior, and esbuild class-expression field/accessor decorators.

4. **High - every primary lane still reports unaccepted lane output.**
   - Paths: `lanes/difftastic/lane-status.json:13`,
     `lanes/dolt/lane-status.json:13`, `lanes/esbuild/lane-status.json:13`,
     `lanes/gitoxide/lane-status.json:13`,
     `lanes/libsqlite/lane-status.json:13`,
     `lanes/lightningcss/lane-status.json:13`,
     `lanes/markerpdf/lane-status.json:13`,
     `lanes/pandoc/lane-status.json:13`,
     `lanes/quadrable/lane-status.json:13`,
     `lanes/rclone/lane-status.json:13`,
     `lanes/readability/lane-status.json:13`,
     `lanes/syncthing/lane-status.json:13`.
   - Goal requirement at risk: `goal.md:29` and `goal.md:48` require
     committed, reviewable slices after verification and cleanup.
   - Evidence: focused lane-green evidence is recorded, but all lanes defer
     root verification and commit acceptance to supervisor/integrator ownership.
     Those handoffs are review inputs, not accepted native progress.

5. **High - markerPDF continues to over-credit runtime orchestration and
   external-app planning as mapped native port progress.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:19`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:426` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:428`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:905` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:941`,
     `lanes/markerpdf/lane-status.json:5`,
     `lanes/markerpdf/lane-status.json:12`.
   - Goal requirement at risk: `goal.md:1`, `goal.md:25`, `goal.md:30`,
     `goal.md:31`, and `goal.md:40` require native PHP implementation,
     defensible denominators, no wrapper/shell-out progress credit, and
     explicit hard blockers.
   - Evidence: valid native slices exist, but the mapped surface also counts
     Pandoc/XeLaTeX helper command planning, shell lifecycle planning,
     Streamlit/FastAPI/Uvicorn app planning, Poetry/package metadata, OCR
     installer readiness, model/runtime dependency graphs, and CI/publish/CLA
     workflow plans. Those are preflight metadata or blockers, not native PDF
     extraction parity.

6. **High - essential optional-library coverage is still backlog-only, while
   rclone is growing lane-local ZIP and serve helpers outside the support
   library gate.**
   - Paths: `progress.md:17` through `progress.md:24`,
     `dependency-backlog.json:7` through `dependency-backlog.json:23`,
     `lanes/rclone/src/VfsZipArchive.php:7` through
     `lanes/rclone/src/VfsZipArchive.php:13`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:1246` through
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:1248`,
     `lanes/rclone/lane-status.json:5`,
     `lanes/rclone/lane-status.json:9`.
   - Goal requirement at risk: `goal.md:24` through `goal.md:31`,
     `goal.md:35`, and the run instruction requiring support libraries to have
     bounded native PHP components, activation gates, dependency-specific
     denominators, mapped fixtures, PHP evidence, malformed/corrupt cases where
     relevant, and no hidden shell-outs.
   - Evidence: `dependency-backlog.json` has 23 gated support-library rows and
     zero active manifest-backed support ports. Rclone's `VfsZipArchive` and
     HTTP/WebDAV directory/ZIP response work may be legitimate lane-local VFS
     slices, but they are not `shared-zip-package-core` until there is a
     support-library manifest, activation gate, spec/upstream denominator,
     cross-lane fixtures, corrupt ZIP cases, and root evidence.

7. **High - near-complete percentages overstate accepted upstream parity.**
   - Paths: `porting.html:32`, `porting.html:56` through `porting.html:67`,
     `lanes/difftastic/lane-status.json:4` through
     `lanes/difftastic/lane-status.json:12`,
     `lanes/gitoxide/lane-status.json:4` through
     `lanes/gitoxide/lane-status.json:12`,
     `lanes/pandoc/lane-status.json:4` through
     `lanes/pandoc/lane-status.json:12`,
     `lanes/syncthing/lane-status.json:4` through
     `lanes/syncthing/lane-status.json:12`.
   - Goal requirement at risk: `goal.md:35`, `goal.md:37`, `goal.md:38`, and
     `goal.md:40` require upstream tests as source of truth and honest marking
     of hard features.
   - Evidence: the dashboard advertises 97.7% average progress and 98-99% for
     many lanes while major parity remains unexecuted or excluded: Gitoxide
     full Cargo workspace, Difftastic full Cargo, Pandoc Haskell
     `test-pandoc`, Syncthing full `go test ./...`, markerPDF full
     benchmark/model runner, rclone provider/mount/live-service parity,
     esbuild `make test-all`, and libsqlite all/release permutations.

8. **Medium - manifest/status schemas remain non-normalized across lanes.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:13` through
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:12` through
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:12` through
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/gitoxide/lane-status.json:6`,
     `lanes/lightningcss/lane-status.json:6`.
   - Goal requirement at risk: `goal.md:3`, `goal.md:25`, and `goal.md:45`
     require comparable upstream denominators, mapped-test counts, and PHP
     pass/fail fields.
   - Evidence: `benchmarkDenominator.total` is sometimes a narrative string,
     Pandoc has both a narrative `total` and numeric `totalCount`, Dolt has
     `mapped` without a top-level numeric `total`, and PHP pass values mix
     behavior tests, assertions, and mapped denominator checks.

9. **Medium - blocker fields still lead with local-green wording instead of
   acceptance blockers.**
   - Paths: `lanes/dolt/lane-status.json:12`,
     `lanes/esbuild/lane-status.json:12`,
     `lanes/gitoxide/lane-status.json:12`,
     `lanes/libsqlite/lane-status.json:12`,
     `lanes/markerpdf/lane-status.json:12`,
     `lanes/rclone/lane-status.json:12`,
     `lanes/readability/lane-status.json:12`,
     `lanes/syncthing/lane-status.json:12`.
   - Goal requirement at risk: `goal.md:31` and `goal.md:40` require precise
     blockers and explicit marking of unported hard features.
   - Evidence: several blocker fields start with "No current" or "No focused"
     blocker, then later acknowledge pending root verification, uncommitted
     batches, unexecuted full upstream runners, excluded providers/services, or
     heavy model/runtime requirements. The unresolved acceptance blocker should
     be first.

## Test Gate

I did not run `php tools/run-tests.php`.

The required pre-root gate was checked before considering a root run:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
2492226 php tools/run-tests.php lanes/quadrable/tests
```

That process exited before owner sampling:

```text
ps -o pid,user,ppid,stat,etime,command -p 2492226
<no rows>
```

Later gates returned no rows, but the tree was still not stable enough for an
audit-owned root harness: `HEAD` moved during the audit, all lane handoffs
remain dirty/unaccepted, and recent integration status shows active lane,
runner, and status automation.

## Next Intervention

Freeze active writers, status publishers, lane runners, and root/focused
runners. After two stable polls of `HEAD`, tracked status count, shortstat,
runner state, and relevant status/log mtimes, select one lane-scoped batch,
rerun focused verification plus `git diff --check`, run one serialized
no-argument `php tools/run-tests.php` from that exact snapshot if the process
gate is empty, regenerate dashboard artifacts only from the accepted commit,
then commit or reject that batch.
