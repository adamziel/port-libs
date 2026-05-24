# Independent Audit - 2026-05-24T02:21Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, every
`lanes/*/lane-status.json`, `dependency-backlog.json`,
`audits/integration-status.md`, `audits/latest.md`, and recent Git history.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, credential stores, provider
configs, or auth files. Bridge code, generated fixtures, plan-only wrappers,
and shell-outs are treated as non-progress unless explicitly temporary oracle
tooling.

`jq empty` passed for every lane manifest, every lane-status file,
`porting-summary.json`, and `dependency-backlog.json`.

## Current Snapshot

```text
HEAD movement during audit: 2fb34253 -> e53023f4 via integration-hold/status commits
latest visible commits: e53023f4 Record integration hold status; f3ddd25e Record integration hold status; 80c3b913 Record integration hold status
branch sample: main...origin/main [ahead 651, behind 68]
tracked dirty rows: 299
default status rows including untracked: 11360
untracked-all status rows: 11448
git diff --shortstat sample: 299 files changed, 145773 insertions(+), 16964 deletions(-)
repo worker/test-control process sample: 38 matching lane/watchdog/dashboard/evaluator/capacity processes
root run by this audit: not started
pre-root gate: pgrep -af '^php tools/run-tests\.php( |$)' returned no rows
post-write validation gate: transient focused Esbuild PID 2816569 (`php tools/run-tests.php lanes/esbuild/tests`), exited before owner sample
later validation gate: focused Readability PID 2830009 owned by claude (`php tools/run-tests.php lanes/readability/tests`)
later validation gate: active focused Syncthing PID 2844819 owned by claude (`php tools/run-tests.php lanes/syncthing/tests`) and active no-argument root PID 2845298 owned by claude (`php tools/run-tests.php`)
final pre-commit gate: active no-argument root PID 2870560 owned by claude (`php tools/run-tests.php`) and focused Syncthing shard PID 2873269 owned by claude (`php tools/run-tests.php lanes/syncthing/tests/...`)
```

## Findings

1. **Critical - the checkout is still a moving dirty aggregate, not a valid
   acceptance or root-verification target.**
   - Paths: `progress.md:39`, `progress.md:58` through `progress.md:75`,
     `lanes/difftastic/lane-status.json:13`,
     `lanes/dolt/lane-status.json:13`,
     `lanes/esbuild/lane-status.json:13`,
     `lanes/gitoxide/lane-status.json:13`,
     `lanes/libsqlite/lane-status.json:13`,
     `lanes/lightningcss/lane-status.json:13`,
     `lanes/markerpdf/lane-status.json:13`,
     `lanes/pandoc/lane-status.json:13`,
     `lanes/quadrable/lane-status.json:13`,
     `lanes/rclone/lane-status.json:13`,
     `lanes/readability/lane-status.json:13`,
     `lanes/syncthing/lane-status.json:13`.
   - Goal requirement at risk: `goal.md:29`, `goal.md:48`, and
     `goal.md:49` require small committed slices, supervisor verification,
     cleanup, and honest repo-wide test/static-check records.
   - Evidence: `HEAD` moved from `2fb34253` to `e53023f4` during this audit;
     status and shortstat counts changed while reading; the branch is
     `ahead 651, behind 68`; the tree still has 299 tracked dirty rows and
     more than 11k total status rows; and the current process sample still
     shows 38 lane/watchdog/dashboard/evaluator/capacity processes. Every lane
     still reports pending or uncommitted handoff status.

2. **Critical - `porting.html` and `porting-summary.json` are stale and
   contradict the source checkout.**
   - Paths: `porting.html:32` through `porting.html:38`,
     `porting.html:56` through `porting.html:67`,
     `porting.html:75` through `porting.html:78`,
     `porting-summary.json:2` through `porting-summary.json:8`,
     `porting-summary.json:215` through `porting-summary.json:227`,
     `dependency-backlog.json:3`, `dependency-backlog.json:110` through
     `dependency-backlog.json:123`.
   - Goal requirement at risk: `goal.md:3` and `goal.md:45` require the
     dashboard to show current denominator, mapped tests, PHP pass/fail,
     WordPress scenarios, phase, audit, current work, blocker, and commit.
   - Evidence: the dashboard is generated from `79768df0c427` on
     `2026-05-23 23:43:54 UTC`, while the checkout is at `80c3b913` with
     newer dirty manifests/statuses. It still shows older lane work such as
     Gitoxide `GitConfig`, markerPDF `Span.fix_unicode`, rclone VFS
     `Dump/AddVirtual`, and Syncthing `ConfigLdap`. The dependency table still
     says 22 items and 12 candidates, while `dependency-backlog.json` has 23
     items and includes `pandoc-doctemplates-core`.

3. **High - `progress.md` active-lane handoffs are stale relative to current
   lane-status files.**
   - Paths: `progress.md:58` through `progress.md:75`,
     `lanes/gitoxide/lane-status.json:11`,
     `lanes/markerpdf/lane-status.json:11`,
     `lanes/pandoc/lane-status.json:11`,
     `lanes/rclone/lane-status.json:11`,
     `lanes/syncthing/lane-status.json:11`,
     `lanes/esbuild/lane-status.json:11`.
   - Goal requirement at risk: `goal.md:44` requires `progress.md` to show
     current lanes, current work, blockers, next tasks, owners/sessions, and
     percentage estimates.
   - Evidence: the active-lane table still names markerPDF benchmark
     file-inventory, Pandoc figure/citation, Syncthing system log, rclone VFS
     Statfs/usage, and esbuild automatic JSX key/spread handoffs. Current
     lane-status files instead describe newer slices such as Gitoxide
     pathspec filtering, markerPDF code-block page mutation, Pandoc DOCX
     Native document-properties metadata, rclone HTTP file responses,
     Syncthing `/rest/svc/report`, and esbuild computed class-expression
     decorators.

4. **High - focused lane-green evidence is being recorded before supervisor
   acceptance and root verification.**
   - Paths: all current `lanes/*/lane-status.json:10` through
     `lanes/*/lane-status.json:13`.
   - Goal requirement at risk: `goal.md:29`, `goal.md:48`, and `goal.md:49`.
   - Evidence: the lane files record many focused passes, but their
     `latestCommit` fields still say pending, uncommitted, or supervisor-owned
     acceptance. That makes the evidence useful for review, but not accepted
     portfolio progress.

5. **High - markerPDF still over-credits runtime orchestration and external
   application planning as mapped native port progress.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:12` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:19`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:31` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:49`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:95` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:120`,
     `lanes/markerpdf/lane-status.json:9`,
     `lanes/markerpdf/lane-status.json:12`.
   - Goal requirement at risk: `goal.md:1`, `goal.md:25`,
     `goal.md:30`, `goal.md:31`, and `goal.md:35`.
   - Evidence: markerPDF has real native PHP slices, but the counted/mapped
     surface also includes benchmark CLI plans, memory/profile/report plans,
     Nougat comparison plans, CI workflow plans, OCR/model readiness,
     Streamlit/FastAPI/Uvicorn route/app planning, Poetry/package metadata,
     Tesseract/OCRMyPDF/Ghostscript/Texify/Surya model handoff, and shell
     lifecycle planning. Those are blockers or preflight metadata unless tied
     to bounded native PHP extraction behavior with upstream/spec evidence.

6. **High - essential optional-library coverage remains backlog-only while
   rclone is growing lane-local archive/serve helpers outside the shared gate.**
   - Paths: `progress.md:17` through `progress.md:24`,
     `dependency-backlog.json:7` through `dependency-backlog.json:23`,
     `dependency-backlog.json:110` through `dependency-backlog.json:123`,
     `lanes/rclone/src/VfsZipArchive.php:7` through
     `lanes/rclone/src/VfsZipArchive.php:13`,
     `lanes/rclone/src/VfsServeZipResponse.php:7` through
     `lanes/rclone/src/VfsServeZipResponse.php:10`,
     `lanes/rclone/lane-status.json:5`, `lanes/rclone/lane-status.json:8`,
     `lanes/rclone/lane-status.json:9`.
   - Goal requirement at risk: `goal.md:24` through `goal.md:31`,
     `goal.md:35`, and the run instruction requiring support libraries to
     have bounded native PHP components, activation gates,
     dependency-specific denominators, mapped fixtures, PHP pass/fail
     evidence, malformed/corrupt cases where relevant, and no hidden
     shell-outs.
   - Evidence: `dependency-backlog.json` defines support-library gates, but no
     support-library manifests, accepted commits, or dashboard rows exist.
     Rclone's ZIP writer and serve helpers may be valid lane-local VFS slices,
     but they must not count as `shared-zip-package-core` or
     `archive-compression-streams` progress until the shared component has its
     own denominator, fixtures, corrupt archive cases, and root evidence.

7. **High - near-complete percentages still overstate accepted upstream
   parity.**
   - Paths: `porting.html:32`, `porting.html:56` through `porting.html:67`,
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:811`,
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:12` through
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:18`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:19`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:36`,
     `lanes/rclone/lane-status.json:12`,
     `lanes/syncthing/lane-status.json:12`.
   - Goal requirement at risk: `goal.md:35`, `goal.md:37`,
     `goal.md:38`, and `goal.md:40`.
   - Evidence: the dashboard advertises 97.7% average progress and 98-99% for
     most lanes while major parity remains unexecuted or excluded: Gitoxide
     full Cargo workspace, Difftastic full Cargo, Pandoc Haskell
     `test-pandoc`, Syncthing full `go test ./...`, markerPDF full benchmark
     and model runner, rclone provider/mount/live-service parity, esbuild
     `make test-all`, and libsqlite all/release permutations.

8. **Medium - manifest/status schemas and denominator fields remain
   non-normalized.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2389`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14` through
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:15`,
     `porting-summary.json:15` through `porting-summary.json:18`,
     `porting-summary.json:32` through `porting-summary.json:35`.
   - Goal requirement at risk: `goal.md:3`, `goal.md:25`, and
     `goal.md:45`.
   - Evidence: `benchmarkDenominator.total` is sometimes a narrative string,
     sometimes numeric, and sometimes paired with a separate `totalCount`;
     Dolt's manifest uses a narrative `total` field while `mapped` is numeric;
     dashboard values remain strings and can say `inventory` instead of a
     machine-comparable count. PHP pass values also mix behavior tests,
     assertions, and mapped denominator checks across lanes.

9. **Medium - blocker wording still lets local-green slices hide acceptance
   blockers.**
   - Paths: `porting-summary.json:41`, `porting-summary.json:58`,
     `porting-summary.json:92`, `porting-summary.json:109`,
     `porting-summary.json:126`, `porting-summary.json:160`,
     `porting-summary.json:177`, `porting-summary.json:194`,
     `porting-summary.json:211`,
     `lanes/markerpdf/lane-status.json:12`,
     `lanes/dolt/lane-status.json:12`.
   - Goal requirement at risk: `goal.md:31` and `goal.md:40`.
   - Evidence: stale dashboard blocker cells still lead with "No current" or
     "No focused" blocker language. Current lane-status files are better in
     several places, but markerPDF and Dolt still lead with focused-local
     green/tooling wording before the real portfolio blockers: unaccepted
     dirty handoffs, pending root verification, unexecuted full upstream
     parity, and excluded live/provider/model/runtime requirements.

## Test Gate

I did not run `php tools/run-tests.php`.

The required process gate was checked before considering a root run:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
<no rows>
```

During post-write validation the same gate briefly matched a focused Esbuild
lane harness, not a no-argument root harness:

```text
2816569 php tools/run-tests.php lanes/esbuild/tests
```

It exited before owner sampling with `ps`; the final validation gate returned
no rows at that moment. A later validation gate then matched another focused
lane harness:

```text
2830009 claude Rs 00:08 php tools/run-tests.php lanes/readability/tests
```

Another validation gate then matched an active no-argument root harness plus a
focused Syncthing lane harness:

```text
2844819 claude Rs 00:28 php tools/run-tests.php lanes/syncthing/tests
2845298 claude Rs 00:25 php tools/run-tests.php
```

The final pre-commit validation gate matched an active no-argument root harness
plus focused lane shards:

```text
2870560 claude R+ 00:21 php tools/run-tests.php
2873269 claude R+ 00:16 php tools/run-tests.php lanes/syncthing/tests/...
```

Even at samples where the no-argument root process gate was empty, the tree was
not stable enough for an audit-owned no-argument root harness. `HEAD`, status
counts, and shortstat changed during review; active lane/status/writer
infrastructure is still visible; focused lane harnesses appeared during
validation; and every lane handoff remains pending or uncommitted. Starting a
root run from this state would produce another moving-snapshot anecdote rather
than the accepted verification record required by the goal.

## Next Intervention

Freeze active writers, status publishers, lane runners, and root/focused
runners. After two stable polls of `HEAD`, tracked status count, shortstat,
runner state, and relevant status/log mtimes, select one lane-scoped batch,
rerun focused verification plus `git diff --check`, run one serialized
no-argument `php tools/run-tests.php` from that exact snapshot if the process
gate is empty, regenerate dashboard artifacts only from the accepted commit,
then commit or reject that batch.
