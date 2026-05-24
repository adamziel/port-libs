# Independent Audit - 2026-05-24T02:04Z

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
HEAD movement during audit: 8e2767a1 -> 2802f410 via integration-hold/status commits
HEAD at final pre-write sample: 2802f4102e1d
latest visible commits: 2802f410 Record integration hold status; 8e2767a1 Refresh independent audit status; ce1a480e Record integration hold status
branch sample: main...origin/main [ahead 647, behind 68]
tracked dirty rows: 297
total status rows including untracked: 11276
git diff --shortstat: 297 files changed, 143707 insertions(+), 16775 deletions(-)
root run by this audit: not started
pre-root gate: pgrep -af '^php tools/run-tests\.php( |$)' returned no rows
final pre-write gate: pgrep -af '^php tools/run-tests\.php( |$)' returned no rows
post-commit pre-finish gate: 2712655 claude php tools/run-tests.php lanes/syncthing/tests
final post-amend gate: pgrep -af '^php tools/run-tests\.php( |$)' returned no rows
```

## Findings

1. **Critical - the checkout is still a moving aggregate, not a valid
   acceptance or root-verification target.**
   - Paths: `progress.md:39`, `progress.md:58`,
     `audits/integration-status.md:13` through
     `audits/integration-status.md:46`, `lanes/*/lane-status.json:13`.
   - Goal requirement at risk: `goal.md:29`, `goal.md:48`, and
     `goal.md:49` require small committed slices, supervisor verification,
     cleanup, and honest repo-wide test/static-check records.
   - Evidence: `HEAD` moved during this audit from `8e2767a1` to
     `2802f410`; the branch is `ahead 647, behind 68`; the checkout still has
     297 tracked dirty rows and 11,276 total status rows; and every primary
     lane still reports a pending or uncommitted handoff. The latest history is
     audit/integration-hold status, not accepted lane feature commits.

2. **Critical - `porting.html` and `porting-summary.json` are stale enough to
   mislead reviewers.**
   - Paths: `porting.html:32` through `porting.html:38`,
     `porting.html:56` through `porting.html:67`,
     `porting.html:75` through `porting.html:78`,
     `porting-summary.json:2` through `porting-summary.json:8`,
     `porting-summary.json:215` through `porting-summary.json:227`,
     `dependency-backlog.json:1` through `dependency-backlog.json:5`.
   - Goal requirement at risk: `goal.md:3` and `goal.md:45` require a current
     dashboard with denominator, mapped tests, PHP pass/fail, WordPress
     scenarios, phase, audit, current work, blocker, and commit.
   - Evidence: the dashboard advertises generated time
     `2026-05-23 23:43:54 UTC` and snapshot `main 79768df0c427`, while the
     checkout is at `2802f410`. It still shows old lane work such as Gitoxide
     `GitConfig`, markerPDF `Span.fix_unicode`, rclone VFS `Dump/AddVirtual`,
     and Syncthing `ConfigLdap`; current lane-status files describe Gitoxide
     pathspec filtering, markerPDF debug bbox stdout, rclone HTTP file
     responses, and Syncthing random-string service behavior. The dashboard
     dependency table still says 22 items and 12 candidates, while
     `dependency-backlog.json` has 23 items and 13 candidates.

3. **High - `progress.md` active-lane labels are stale relative to current
   lane-status handoffs.**
   - Paths: `progress.md:62` through `progress.md:73`,
     `lanes/gitoxide/lane-status.json:11`,
     `lanes/markerpdf/lane-status.json:11`,
     `lanes/pandoc/lane-status.json:11`,
     `lanes/rclone/lane-status.json:11`,
     `lanes/syncthing/lane-status.json:11`,
     `lanes/esbuild/lane-status.json:11`.
   - Goal requirement at risk: `goal.md:44` requires `progress.md` to show
     current lanes, current work, blockers, next task, and estimates.
   - Evidence: `progress.md` still lists Gitoxide SSH config-options,
     markerPDF benchmark file-inventory, Pandoc figure/citation, Syncthing
     system log, rclone Statfs/usage, and esbuild automatic JSX key/spread
     handoffs. Current lane-status files instead describe Gitoxide
     `PathSpec::filterRelativePaths()`, markerPDF debug bbox stdout, Pandoc
     DOCX Native custom-style attributes, rclone HTTP file response metadata,
     Syncthing `/rest/svc/random/string`, and esbuild class-expression
     field/accessor decorators.

4. **High - every primary lane still reports unaccepted output.**
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
     committed, reviewable slices after tests, cleanup, and supervisor
     acceptance.
   - Evidence: focused lane-green evidence is recorded, but each lane defers
     root verification and commit acceptance to supervisor/integrator
     ownership. These handoffs are review inputs, not accepted native progress.

5. **High - markerPDF still over-credits runtime orchestration and external
   application planning as mapped native port progress.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:19`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:447` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:451`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:514`,
     `lanes/markerpdf/lane-status.json:9`,
     `lanes/markerpdf/lane-status.json:12`.
   - Goal requirement at risk: `goal.md:1`, `goal.md:25`,
     `goal.md:30`, `goal.md:31`, and `goal.md:40` require native PHP
     implementation, defensible denominators, no wrapper/shell-out progress
     credit, and explicit blockers for hard features.
   - Evidence: markerPDF has real native slices, but the counted/mapped surface
     also includes Pandoc/XeLaTeX helper command plans, shell lifecycle plans,
     supplied model orchestration, Streamlit/FastAPI/Uvicorn app plans,
     Poetry/package/workflow plans, OCR installer readiness, model/runtime
     dependency graphs, and CI/publish/CLA workflow planning. Those are
     preflight metadata or blockers, not native PDF extraction parity.

6. **High - essential optional-library coverage remains backlog-only, while
   rclone is growing lane-local archive/serve helpers outside the shared
   dependency gate.**
   - Paths: `progress.md:17` through `progress.md:24`,
     `dependency-backlog.json:7` through `dependency-backlog.json:23`,
     `lanes/rclone/src/VfsZipArchive.php:7` through
     `lanes/rclone/src/VfsZipArchive.php:13`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:1246` through
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:1248`,
     `lanes/rclone/lane-status.json:5`, `lanes/rclone/lane-status.json:12`.
   - Goal requirement at risk: `goal.md:24` through `goal.md:31`,
     `goal.md:35`, and the run instruction requiring support libraries to have
     bounded native PHP components, activation gates, dependency-specific
     upstream/spec denominators, mapped fixtures, PHP evidence,
     malformed/corrupt cases where relevant, and no hidden shell-outs.
   - Evidence: `dependency-backlog.json` has 23 gated support-library rows and
     zero active manifest-backed support ports. Rclone's `VfsZipArchive`,
     directory-listing, serve-zip, and file-response work may be legitimate
     lane-local VFS slices, but it is not `shared-zip-package-core` or
     `archive-compression-streams` progress until there is a support-library
     manifest, activation gate, spec/upstream denominator, cross-lane fixtures,
     corrupt archive cases, and root evidence.

7. **High - near-complete percentages still overstate accepted upstream
   parity.**
   - Paths: `porting.html:32`, `porting.html:56` through
     `porting.html:67`, `lanes/gitoxide/lane-status.json:4` through
     `lanes/gitoxide/lane-status.json:12`,
     `lanes/markerpdf/lane-status.json:4` through
     `lanes/markerpdf/lane-status.json:12`,
     `lanes/rclone/lane-status.json:4` through
     `lanes/rclone/lane-status.json:12`,
     `lanes/syncthing/lane-status.json:4` through
     `lanes/syncthing/lane-status.json:12`.
   - Goal requirement at risk: `goal.md:35`, `goal.md:37`,
     `goal.md:38`, and `goal.md:40` require upstream tests as the source of
     truth and honest marking of skipped hard features.
   - Evidence: the dashboard advertises 97.7% average progress and 98-99% for
     most lanes while major parity remains unexecuted or excluded: Gitoxide
     full Cargo workspace, Difftastic full Cargo, Pandoc Haskell
     `test-pandoc`, Syncthing full `go test ./...`, markerPDF full benchmark
     and model runner, rclone provider/mount/live-service parity, esbuild
     `make test-all`, and libsqlite all/release permutations.

8. **Medium - manifest/status schemas and counts remain non-normalized.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:12` through
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:16`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:12` through
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:12` through
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:16`,
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:12` through
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:18`,
     `porting-summary.json:15` through `porting-summary.json:18`.
   - Goal requirement at risk: `goal.md:3`, `goal.md:25`, and
     `goal.md:45` require comparable upstream denominators, mapped-test
     counts, and PHP pass/fail fields.
   - Evidence: `benchmarkDenominator.total` is sometimes a narrative string
     and sometimes numeric; Pandoc has both `total` and `totalCount`; Dolt
     currently lacks a simple top-level numeric `total` beside `mapped`; and
     PHP pass values mix behavior tests, assertions, and mapped denominator
     checks. Difftastic changed during the audit to manifest `782`/`405` while
     lane status still reports `778` artifacts and `401` PHP pass checks, and
     stale dashboard artifacts still report `735`/`374`.

9. **Medium - blocker fields still often lead with local-green wording instead
   of the acceptance blocker.**
   - Paths: `lanes/dolt/lane-status.json:12`,
     `lanes/esbuild/lane-status.json:12`,
     `lanes/libsqlite/lane-status.json:12`,
     `lanes/lightningcss/lane-status.json:12`,
     `lanes/markerpdf/lane-status.json:12`,
     `lanes/readability/lane-status.json:12`,
     `lanes/syncthing/lane-status.json:12`.
   - Goal requirement at risk: `goal.md:31` and `goal.md:40` require precise
     blockers and explicit marking of unported hard features.
   - Evidence: several blocker fields start with "No current" or "No focused"
     blocker and only later acknowledge pending root verification,
     uncommitted batches, unexecuted full upstream runners, excluded providers,
     or heavy runtime/model requirements. The unresolved acceptance blocker
     should be first.

## Test Gate

I did not run `php tools/run-tests.php`.

The required process gate was checked before considering a root run and again
before writing:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
<no rows>
```

A post-commit pre-finish recheck then found a focused Syncthing lane harness,
not a no-argument root harness:

```text
2712655 claude Rs 00:55 php tools/run-tests.php lanes/syncthing/tests
```

The final post-amend process gate returned no rows.

Despite the empty process gate, the tree was not stable enough for an
audit-owned root harness: `HEAD` moved during the audit, manifest/status counts
changed under review, all lane handoffs remain dirty or unaccepted, and recent
integration status shows active lane/status/writer sessions. Starting a root
run from this state would produce another moving-snapshot anecdote rather than
the goal's required accepted verification record.

## Next Intervention

Freeze active writers, status publishers, lane runners, and root/focused
runners. After two stable polls of `HEAD`, tracked status count, shortstat,
runner state, and relevant status/log mtimes, select one lane-scoped batch,
rerun focused verification plus `git diff --check`, run one serialized
no-argument `php tools/run-tests.php` from that exact snapshot if the process
gate is empty, regenerate dashboard artifacts only from the accepted commit,
then commit or reject that batch.
