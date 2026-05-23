# Independent Audit - 2026-05-23T22:49Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`,
lane status files for cross-checking, `dependency-backlog.json`, the latest
dependency tracker artifact, recent Git history, current worktree/process
state, and the required pre-root harness gate.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, credential stores, provider
configs, or auth files. Bridge code, generated fixtures, copied oracle
fixtures, and shell-outs are treated as non-progress unless explicitly
temporary oracle tooling.

`jq empty` passed for every lane manifest, every lane-status file, and
`porting-summary.json`.

## Current Snapshot

Sampled repository state:

```text
HEAD: 74a2d0b0ead0
7066 total git status rows
274 tracked dirty files
274 files changed, 115664 insertions(+), 11950 deletions(-)
127 tmux sessions
33 sampled dashboard/watchdog/evaluator/integrator/capacity/lane-agent/test-control processes
221 audit/status commits since 2026-05-23 00:00 UTC
latest sampled non-audit/status implementation commit: b75226d1
```

The required exact pre-root gate changed during sampling:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
3212866 php tools/run-tests.php lanes/lightningcss/tests
```

That focused LightningCSS process exited before owner sampling. A later exact
gate returned no rows, but I did not start `php tools/run-tests.php` because the
tree is still not stable enough for accepted aggregate evidence.

A final pre-commit gate then matched active no-argument root PID `3322527`,
owned by `claude`:

```text
3322527 claude 3322182 00:15 R php php tools/run-tests.php
```

## Findings

1. **Critical - the repository still has no trustworthy integration baseline.**
   - Paths: `progress.md:31`, `progress.md:33`, `progress.md:37`,
     `progress.md:48`, `scripts/run-team-watchdog.sh`,
     `scripts/run-capacity-controller-loop.sh`,
     `scripts/run-dashboard-updater-loop.sh`,
     `scripts/run-evaluator-loop.sh`, and `.tmux-team/`.
   - Goal requirement at risk: `goal.md:20`, `goal.md:29`,
     `goal.md:48`, and `goal.md:49` require capped supervision, small
     committed slices, cleanup before reassignment, and honest repo-wide
     verification.
   - Evidence: the documented launch target remains 2 implementation lanes plus
     1 auditor, while the audit sampled 127 tmux sessions and 33 active
     worker/status/test-control processes. The Active Lanes table still reports
     every lane as `stopped`, but live lane-agent and capacity processes are
     present. The tree has 7066 status rows, 274 tracked dirty files, and a
     274-file diff. Recent history is dominated by audit/status commits, with
     221 matching audit/status commits since 2026-05-23 00:00 UTC.

2. **Critical - `porting.html` and `porting-summary.json` are materially stale
   and conflict with current manifests.**
   - Paths: `porting.html:30`, `porting.html:32`,
     `porting.html:33`, `porting.html:36`, `porting-summary.json:2`,
     `porting-summary.json:3`, `porting-summary.json:4`,
     `porting-summary.json:8`, and `lanes/*/UPSTREAM_TEST_MANIFEST.json`.
   - Goal requirement at risk: `goal.md:3`, `goal.md:44`,
     `goal.md:45`, and `goal.md:52` require current human-readable and
     generated coordination state with denominator, mapped tests, PHP
     pass/fail, audit, blocker, and commit fields.
   - Evidence: the dashboard still publishes generated time
     `2026-05-23 04:57:16 UTC` and snapshot `bda83c6b93d4`, while sampled
     `HEAD` is `74a2d0b0ead0`. Current manifests disagree with the dashboard:
     Difftastic is `365` mapped against a `735` prose denominator
     (`lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:15`) versus dashboard
     `160 / 417`; Gitoxide is `2715 / 2877`
     (`lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:15`) versus `1432 / 2877`;
     LightningCSS is `1727 / 3532`
     (`lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json:15`) versus `773 / 3532`;
     markerPDF is `276 / 326`
     (`lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:15`) versus `159 / 78`;
     Pandoc is `1026 / 2276`
     (`lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:30`) versus `426 / 2028`;
     rclone is `684 / 1601`
     (`lanes/rclone/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:15`) versus `291 / 327`;
     Readability is `1984 / 1984`
     (`lanes/readability/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:15`) versus
     `1031 / 1984`; Syncthing is `651 / 658`
     (`lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:15`) versus `235 / 658`.

3. **High - every lane handoff is still pending dirty integration instead of
   accepted implementation history.**
   - Paths: `lanes/difftastic/lane-status.json:13`,
     `lanes/dolt/lane-status.json:13`, `lanes/esbuild/lane-status.json:13`,
     `lanes/gitoxide/lane-status.json:13`,
     `lanes/libsqlite/lane-status.json:13`,
     `lanes/lightningcss/lane-status.json:13`,
     `lanes/markerpdf/lane-status.json:13`,
     `lanes/pandoc/lane-status.json:13`,
     `lanes/quadrable/lane-status.json:13`,
     `lanes/rclone/lane-status.json:13`,
     `lanes/readability/lane-status.json:13`, and
     `lanes/syncthing/lane-status.json:13`.
   - Goal requirement at risk: `goal.md:29` and `goal.md:48` require small,
     reviewable committed slices with verification and cleanup before the next
     assignment.
   - Evidence: sampled `latestCommit` fields are `pending`, `uncommitted`,
     `not committed`, or dirty-batch prose. The newest sampled non-audit/status
     implementation commit is still `b75226d1`, while the top of history is
     repeated `Refresh independent audit status` / `Record integration hold
     status` commits.

4. **High - near-complete parity signals overstate what is proven natively.**
   - Paths: `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:18`,
     `lanes/gitoxide/lane-status.json:12`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/markerpdf/lane-status.json:12`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/readability/lane-status.json:6`,
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:15`, and
     `lanes/syncthing/lane-status.json:12`.
   - Goal requirement at risk: `goal.md:35`, `goal.md:37`, and
     `goal.md:40` require meaningful native parity, upstream tests as source of
     truth, and explicit hard-feature blockers.
   - Evidence: Gitoxide maps `2715 / 2877` but full cargo workspace parity is
     explicitly not executed. markerPDF maps `276 / 326` static behavior units
     with full Python/PDF/model/runtime execution still blocked. Readability
     maps `1984 / 1984` upstream Mocha checks, but native PHP evidence is only
     `199` local behavior tests. Syncthing maps `651 / 658` while full
     `go test ./...` remains unexecuted. These are useful slices, but they
     should not read as near-complete native ports without accepted root and
     upstream-runner context.

5. **High - manifest/status schemas remain non-normalized and internally
   inconsistent.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:1068`,
     `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json:2478`, and
     `lanes/*/lane-status.json:6`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:38`, and
     `goal.md:45` require real denominators, explicit slices, and comparable
     dashboard fields.
   - Evidence: Difftastic and Quadrable store prose in `benchmarkDenominator.total`;
     Pandoc has both prose `total` and numeric `totalCount`. PHP pass fields
     mix behavior cases and assertions across lanes. Some files contradict
     themselves: rclone currently records `mapped: 684`, while its warning still
     says native progress maps `679`; LightningCSS records `mapped: 1727`, while
     its warning says `1,726` focused checks. Portfolio progress math remains
     non-comparable until these units are normalized.

6. **Medium - blocker language still conflates slice-local green checks with
   full-port blockers.**
   - Paths: `lanes/esbuild/lane-status.json:12`,
     `lanes/gitoxide/lane-status.json:12`,
     `lanes/markerpdf/lane-status.json:12`,
     `lanes/rclone/lane-status.json:12`,
     `lanes/readability/lane-status.json:12`, and
     `lanes/syncthing/lane-status.json:12`.
   - Goal requirement at risk: `goal.md:31` and `goal.md:40` require precise
     blockers and no silent skipping of hard features.
   - Evidence: several lanes say no focused/local blocker, then list major
     unresolved full-port gaps: release-extra `make test-all`, full cargo/go
     suites, live provider/FUSE/service integrations, Python/PDF/model stacks,
     actual off-band packfile URI download, and root aggregate verification.
     The blocker field should separate `slice blocker`, `root blocker`,
     `upstream-runner blocker`, and `full-port blocker`.

7. **Medium - optional dependency coverage is now bounded, but it is not yet
   integrated into the roadmap as auditable work.**
   - Paths: `dependency-backlog.json:3`, `dependency-backlog.json:7`,
     `dependency-backlog.json:25`, `dependency-backlog.json:94`,
     `dependency-backlog.json:128`, `dependency-backlog.json:201`,
     `dependency-backlog.json:253`, `progress.md:17`,
     `progress.md:19`, `progress.md:20`, and
     `audits/dependency-porting-tracker-20260523T2240Z.md:31`.
   - Goal requirement at risk: `goal.md:12`, `goal.md:35`,
     `goal.md:38`, and `goal.md:40` cover rich Pandoc/PDF/HTML import
     capability, meaningful fixture parity, explicit slices, and hard-feature
     blockers. The user also explicitly asked this audit to cover essential
     optional-library gaps and over-broad dependency expansion.
   - Evidence: the 17-item backlog correctly excludes whole applications and
     model stacks as direct ports, and the top candidates are bounded shared
     cores such as ZIP/package, XML/HTML5 DOM, PDF text dictionary, layout/OCR
     result, source maps, and checksum/hash. But all items are only
     `candidate` or `deferred`; none has a dependency manifest, upstream/spec
     denominator, mapped-test count, PHP pass/fail evidence, owner, or dashboard
     row. `blocker: "none"` on critical candidate dependencies is misleading
     until those manifests and gates exist. Do not start broad dependency
     implementation now; first attach these gates to accepted base-lane
     blockers and give each activated dependency the same denominator/mapped
     evidence discipline as a lane.

## Test Gate

I did not run `php tools/run-tests.php`.

The required exact gate initially matched a transient focused LightningCSS
runner (`3212866 php tools/run-tests.php lanes/lightningcss/tests`), which
exited before owner sampling. A later exact gate returned no rows, but the tree
failed the stability requirement: active writer/status/test-control loops
persist, 127 tmux sessions are present, the tracked diff spans 274 files, and
manifest/status values changed during this audit. A final pre-commit gate then
matched active no-argument root PID `3322527`, owned by `claude`
(`3322527 claude 3322182 00:15 R php php tools/run-tests.php`), so a duplicate
root run would also be blocked at handoff.

## Next Intervention

Freeze active lane agents, dashboard/evaluator/auditor/integrator loops,
capacity jobs, broad upstream runners, and focused/root PHP harnesses. Then
validate manifests from the frozen tree, enforce atomic manifest/status writes,
accept or reject dirty lane batches one lane at a time, normalize
denominator/mapped/PHP/runner/blocker/commit schemas, integrate the optional
dependency backlog as gated manifest-backed work, regenerate `progress.md`,
`porting.html`, `porting-summary.json`, and lane statuses from one accepted
commit, and only then run the no-argument root harness if the duplicate-root
gate remains empty.
