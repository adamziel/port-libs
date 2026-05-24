# Independent Audit - 2026-05-24T02:25Z

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
HEAD movement during audit: 11de85cb -> be139e06 via integration-hold/status commits
latest visible commits: be139e06 Record integration hold status; 11de85cb Refresh independent audit status; e53023f4 Record integration hold status
branch sample: main...origin/main [ahead 653, behind 68]
tracked dirty rows: 297
default status rows including untracked: 11419
git diff --shortstat sample: 297 files changed, 146227 insertions(+), 16818 deletions(-)
repo worker/test-control process sample: 43 matching tmux/dashboard/evaluator/capacity/watchdog processes
root run by this audit: not started
pre-root gate at 2026-05-24T02:25: pgrep -af '^php tools/run-tests\.php( |$)' returned no rows
```

## Findings

1. **Critical - the checkout is still a moving dirty aggregate, not an
   acceptance or root-verification target.**
   - Paths: `progress.md:39`, `progress.md:58` through `progress.md:77`,
     `audits/integration-status.md:5` through
     `audits/integration-status.md:46`, and every current
     `lanes/*/lane-status.json:13`.
   - Goal requirement at risk: `goal.md:29`, `goal.md:48`, and
     `goal.md:49` require small committed slices, supervisor verification,
     cleanup, and honest repo-wide test/static-check records.
   - Evidence: `HEAD` moved from `11de85cb` to `be139e06` during this audit
     window; the branch is `ahead 653, behind 68`; the tracked dirty count is
     still 297; the total status surface is above 11k rows; shortstat changed
     while reading; and process sampling still showed 43 coordination or worker
     processes. Lane-status files still describe uncommitted or pending
     handoffs, not accepted implementation commits.

2. **Critical - `porting.html` and `porting-summary.json` are stale and
   contradict the source checkout.**
   - Paths: `porting.html:32` through `porting.html:38`,
     `porting.html:56` through `porting.html:78`,
     `porting-summary.json:2` through `porting-summary.json:8`,
     `porting-summary.json:215` through `porting-summary.json:227`,
     `dependency-backlog.json:3`, and `dependency-backlog.json:110`
     through `dependency-backlog.json:123`.
   - Goal requirement at risk: `goal.md:3` and `goal.md:45` require the
     dashboard to show current denominator, mapped tests, PHP pass/fail,
     WordPress scenarios, phase, audit, current work, blocker, and commit.
   - Evidence: the dashboard still claims it is a verified snapshot of
     `79768df0c427` generated on `2026-05-23 23:43:54 UTC`, while the checkout
     is now at `be139e06` with newer dirty manifests/status files. It still
     reports 22 dependency backlog items, 12 candidates, and 10 medium-priority
     items; `dependency-backlog.json` has 23 items, 13 candidates, and 11
     medium-priority items after `pandoc-doctemplates-core`.

3. **High - `progress.md` active-lane handoffs lag the current lane-status
   files.**
   - Paths: `progress.md:64` through `progress.md:75`,
     `lanes/gitoxide/lane-status.json:11`,
     `lanes/lightningcss/lane-status.json:11`,
     `lanes/markerpdf/lane-status.json:11`,
     `lanes/pandoc/lane-status.json:11`,
     `lanes/rclone/lane-status.json:11`,
     `lanes/syncthing/lane-status.json:11`, and
     `lanes/esbuild/lane-status.json:11`.
   - Goal requirement at risk: `goal.md:44` requires `progress.md` to show
     current lanes, current work, blockers, next tasks, owners/sessions, and
     percentage estimates.
   - Evidence: `progress.md` still names older handoffs such as Gitoxide SSH
     config options, LightningCSS trig/math, markerPDF benchmark file
     inventory, Pandoc figure/citation, Syncthing system log, rclone VFS
     Statfs/usage, and esbuild automatic JSX key/spread. Current lane-status
     files describe newer work such as Gitoxide pathspec attributes,
     LightningCSS gradient fallback, markerPDF code-block mutation, Pandoc DOCX
     empty index fields, Syncthing system connections, rclone HTTP favicon,
     and esbuild private class-expression field decorators.

4. **High - focused lane-green evidence is still being recorded before
   supervisor acceptance and root verification.**
   - Paths: all current `lanes/*/lane-status.json:10` through
     `lanes/*/lane-status.json:13`.
   - Goal requirement at risk: `goal.md:29`, `goal.md:48`, and
     `goal.md:49`.
   - Evidence: every lane records focused PHP passes or local upstream probes,
     but the same files say `pending`, `uncommitted`, or not committed because
     root verification and supervisor acceptance were not assigned. Those
     results are useful review evidence, but they are not portfolio progress
     until one quiet lane batch is accepted, root-tested from that snapshot,
     and committed.

5. **High - markerPDF still over-credits plan-only external/runtime
   orchestration as mapped native port progress.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:12` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:19`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:30` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:49`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:90` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:138`,
     `lanes/markerpdf/lane-status.json:9`, and
     `lanes/markerpdf/lane-status.json:12`.
   - Goal requirement at risk: `goal.md:1`, `goal.md:25`,
     `goal.md:30`, `goal.md:31`, and `goal.md:35`.
   - Evidence: markerPDF has real native PHP slices, but its denominator and
     mapped-progress text also count benchmark CLI/report/memory/Nougat plans,
     CI workflow plans, OCR/model readiness, Streamlit/FastAPI/Uvicorn route
     planning, Poetry/package metadata, Tesseract/OCRMyPDF/Ghostscript/Texify/
     Surya readiness, and shell lifecycle planning. Those are blockers or
     preflight metadata unless converted into bounded native PHP behavior with
     dependency-specific denominators and fixture evidence.

6. **High - optional support libraries remain backlog-only while rclone grows
   lane-local archive/serve helpers outside the shared support-library gate.**
   - Paths: `progress.md:17` through `progress.md:24`,
     `dependency-backlog.json:7` through `dependency-backlog.json:23`,
     `dependency-backlog.json:110` through `dependency-backlog.json:123`,
     `lanes/rclone/src/VfsZipArchive.php:7` through
     `lanes/rclone/src/VfsZipArchive.php:13`,
     `lanes/rclone/src/VfsServeZipResponse.php:7` through
     `lanes/rclone/src/VfsServeZipResponse.php:10`, and
     `lanes/rclone/lane-status.json:8` through
     `lanes/rclone/lane-status.json:13`.
   - Goal requirement at risk: `goal.md:24` through `goal.md:31`,
     `goal.md:35`, and the current audit instruction requiring support
     libraries to have bounded native PHP components, activation gates,
     dependency-specific denominators, mapped fixtures, PHP pass/fail evidence,
     malformed/corrupt cases where relevant, and no hidden shell-outs.
   - Evidence: `dependency-backlog.json` defines gates but no support-library
     manifest, accepted commit, dashboard row, or corrupt-case evidence exists.
     Rclone's ZIP writer and serve helpers may be valid lane-local VFS slices,
     but they must not count as `shared-zip-package-core` or
     `archive-compression-streams` progress until the shared component has its
     own denominator, fixtures, corrupt archive cases, and root evidence.

7. **High - near-complete percentages overstate accepted upstream parity.**
   - Paths: `porting.html:32`, `porting.html:56` through
     `porting.html:67`, `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:14`
     through `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:21`,
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:813`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:36`,
     `lanes/rclone/lane-status.json:12`, and
     `lanes/syncthing/lane-status.json:12`.
   - Goal requirement at risk: `goal.md:35`, `goal.md:37`,
     `goal.md:38`, and `goal.md:40`.
   - Evidence: the dashboard advertises 97.7% average progress and 98-99% for
     most lanes. At the same time, major parity is still unexecuted, excluded,
     or static-only: Gitoxide full Cargo workspace, Difftastic full Cargo,
     Pandoc Haskell `test-pandoc`, Syncthing full `go test ./...`, markerPDF
     full benchmark/model runner, rclone provider/mount/live-service parity,
     esbuild `make test-all`, and libsqlite all/release permutations.

8. **Medium - manifest/status schemas and denominator fields remain
   non-normalized.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2388` through
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2389`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14` through
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:14`, and
     `porting-summary.json:15` through `porting-summary.json:18`.
   - Goal requirement at risk: `goal.md:3`, `goal.md:25`, and
     `goal.md:45`.
   - Evidence: `benchmarkDenominator.total` is sometimes numeric, sometimes a
     narrative string, and sometimes split from `totalCount`; Dolt has a
     numeric `mapped` but a narrative late `total`; dashboard rows can still
     say `inventory` instead of a machine-comparable denominator. PHP pass
     values also mix behavior tests, assertions, and mapped denominator checks.

9. **Medium - blocker wording is better in lane-status files, but the public
   summary still leads with local-green language.**
   - Paths: `porting-summary.json:41`, `porting-summary.json:58`,
     `porting-summary.json:92`, `porting-summary.json:109`,
     `porting-summary.json:126`, `porting-summary.json:160`,
     `porting-summary.json:177`, `porting-summary.json:194`,
     `porting-summary.json:211`, and current
     `lanes/*/lane-status.json:12`.
   - Goal requirement at risk: `goal.md:31` and `goal.md:40`.
   - Evidence: stale public blocker cells still say "No current", "No
     focused", or "none" for several lanes. Current lane-status files more
     honestly name root verification and full upstream parity blockers, but the
     published summary remains the surface readers will see first.

## Test Gate

I did not run `php tools/run-tests.php`.

The required process gate was checked before considering a root run:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
<no rows>
```

Even with no matching PHP harness at the final gate, the tree is not stable
enough for an audit-owned no-argument root run. `HEAD`, status counts, and
shortstat moved during review; active worker/control infrastructure is still
visible; all lane handoffs remain pending or uncommitted; and dashboard/status
artifacts disagree with the source checkout. Starting a root run here would
produce another moving-snapshot anecdote rather than the accepted verification
record required by the goal.

## Next Intervention

Require a hard writer/runner/status freeze. Confirm no exact root or focused
PHP runner is active, then poll `HEAD`, tracked status count, shortstat, runner
state, and relevant log mtimes twice without movement. Accept exactly one quiet
lane-scoped batch, rerun focused inspection/tests, run one serialized
no-argument `php tools/run-tests.php` from that same snapshot, run
`git diff --check`, regenerate dashboard artifacts from the accepted commit,
then commit or reject.
