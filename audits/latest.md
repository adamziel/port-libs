# Independent Audit - 2026-05-23T22:56Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, every
`lanes/*/lane-status.json`, `dependency-backlog.json`, the latest dependency
tracker artifact, recent Git history, current worktree/process state, and the
required pre-root harness gate.

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
HEAD: de6c03186270
7365 total git status rows
274 tracked dirty files
274 files changed, 116534 insertions(+), 11934 deletions(-)
133 tmux sessions
38 sampled dashboard/watchdog/evaluator/integrator/capacity/lane-agent/test-control processes
742 commits since 2026-05-23 00:00 UTC
222 sampled audit/status commits since 2026-05-23 00:00 UTC
latest sampled non-audit/status implementation commit: b75226d1
```

The required exact pre-root gate returned active no-argument root harnesses, so
I did not start `php tools/run-tests.php`:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
3386351 php tools/run-tests.php
3394963 php tools/run-tests.php

ps -o pid,user,ppid,stat,etime,cmd -p 3386351,3394963
3386351 claude   3318551 Rs         00:32 php tools/run-tests.php
3394963 claude   3394496 D+         00:15 php tools/run-tests.php
```

## Findings

1. **Critical - the repository still has no trustworthy integration baseline.**
   - Paths: `progress.md:31`, `progress.md:37`, `progress.md:48`,
     `scripts/run-team-watchdog.sh`, `scripts/run-capacity-controller-loop.sh`,
     `scripts/run-dashboard-updater-loop.sh`, `scripts/run-evaluator-loop.sh`,
     `.tmux-team/`, and the active root processes shown above.
   - Goal requirement at risk: `goal.md:20`, `goal.md:29`,
     `goal.md:48`, and `goal.md:49` require capped supervision, small
     committed slices, cleanup before reassignment, and honest repo-wide
     verification.
   - Evidence: the documented launch target remains 2 implementation lanes plus
     1 auditor, while the audit sampled 133 tmux sessions and 38 active
     worker/status/test-control processes. The Active Lanes table still reports
     every lane as `stopped`, yet live lane-agent, capacity, dashboard,
     evaluator, watchdog, auditor, and duplicate root-test processes are
     present. The tree has 7365 status rows, 274 tracked dirty files, and a
     274-file diff.

2. **Critical - `porting.html` and `porting-summary.json` are materially stale
   and conflict with current manifests.**
   - Paths: `porting.html:30`, `porting.html:32`,
     `porting.html:33`, `porting.html:36`, `porting.html:54`,
     `porting.html:65`, `porting-summary.json:2`,
     `porting-summary.json:3`, and `lanes/*/UPSTREAM_TEST_MANIFEST.json`.
   - Goal requirement at risk: `goal.md:3`, `goal.md:44`,
     `goal.md:45`, and `goal.md:52` require current coordination state with
     denominator, mapped tests, PHP pass/fail, audit, blocker, and commit
     fields visible in `progress.md` and `porting.html`.
   - Evidence: the dashboard still publishes generated time
     `2026-05-23 04:57:16 UTC` and snapshot `bda83c6b93d4`, while sampled
     `HEAD` is `de6c03186270`. Current manifest/dashboard mismatches include
     Difftastic `365 / 735` vs dashboard `160 / 417`, Gitoxide `2715 / 2877`
     vs `1432 / 2877`, libsqlite `282 / 1589` vs `149 / 1454`,
     LightningCSS `1727 / 3532` vs `773 / 3532`, markerPDF `277 / 327`
     vs `159 / 78`, Pandoc `1032 / 2276` vs `426 / 2028`, rclone
     `684 / 1601` vs `291 / 327`, Readability `1984 / 1984` vs
     `1031 / 1984`, and Syncthing `651 / 658` vs `235 / 658`.

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
     `not committed`, or dirty-batch prose. Recent history remains dominated
     by `Refresh independent audit status` / `Record integration hold status`
     commits; the latest sampled non-audit/status implementation commit is
     still `b75226d1`.

4. **High - near-complete parity signals overstate what is proven natively.**
   - Paths: `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:18`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:19`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:782`,
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:15`, and
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:1333`.
   - Goal requirement at risk: `goal.md:35`, `goal.md:37`,
     `goal.md:38`, and `goal.md:40` require meaningful native parity, upstream
     tests as source of truth, explicit slices, and hard-feature blockers.
   - Evidence: Gitoxide maps `2715 / 2877`, but full cargo workspace parity is
     not executed. markerPDF maps `277 / 327` static behavior/reference units
     while full Python/PDF/model/runtime execution remains blocked.
     Readability maps the full `1984 / 1984` upstream Mocha denominator, but
     native PHP evidence is `199` local behavior tests. Syncthing maps
     `651 / 658`, while full `go test ./...` remains unexecuted. These are
     useful focused slices, but they should not read as near-complete native
     ports without accepted root and upstream-runner context.

5. **High - progress estimates and active-lane state are stale against lane
   status files.**
   - Paths: `progress.md:37`, `progress.md:48`,
     `lanes/difftastic/lane-status.json:4`,
     `lanes/rclone/lane-status.json:4`,
     `lanes/readability/lane-status.json:4`, and
     `lanes/syncthing/lane-status.json:4`.
   - Goal requirement at risk: `goal.md:3`, `goal.md:44`, and
     `goal.md:45` require current owner/session, phase, audit status, work,
     blocker, and percentage estimates.
   - Evidence: `progress.md` still shows lanes as `stopped` with early
     estimates such as Gitoxide `66%`, rclone `9%`, Readability `12%`, and
     Syncthing `8%`. Current lane-status files simultaneously report many
     lanes at `98%` or `99%` with live agents still writing. Both surfaces
     cannot be used for supervision without a frozen, regenerated snapshot.

6. **High - optional-library coverage is bounded but not yet auditable work.**
   - Paths: `dependency-backlog.json:7`, `dependency-backlog.json:25`,
     `dependency-backlog.json:94`, `dependency-backlog.json:128`,
     `dependency-backlog.json:253`, `progress.md:17`,
     `progress.md:20`, and
     `audits/dependency-porting-tracker-20260523T2240Z.md:31`.
   - Goal requirement at risk: `goal.md:9`, `goal.md:12`,
     `goal.md:35`, `goal.md:38`, and `goal.md:40` cover rich PDF/Pandoc
     conversion behavior, meaningful fixture parity, explicit slices, and
     hard-feature blockers. The audit request also specifically asks for
     optional-library gap review.
   - Evidence: the 17-item backlog correctly keeps whole applications and model
     stacks out of direct scope, and it identifies real bounded cores:
     ZIP/package, XML/HTML5 DOM, PDF text dictionaries, layout/OCR results,
     table geometry, source maps, checksum/hash, and shared path matching. But
     every item is only `candidate` or `deferred`; there are no dependency
     manifests, upstream/spec denominators, mapped-test counts, PHP pass/fail
     counts, owners, or dashboard rows. `blocker: "none"` on critical
     candidates is misleading until those gates and manifests exist. Do not
     start broad dependency implementation now; first attach activated
     dependency work to accepted base-lane blockers and give each one the same
     denominator discipline as a lane.

7. **Medium - manifest/status schemas remain non-normalized and internally
   inconsistent.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:637`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:782`, and
     `lanes/*/lane-status.json:6`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:38`, and
     `goal.md:45` require real denominators, explicit slices, and comparable
     dashboard fields.
   - Evidence: Difftastic and Quadrable store prose in
     `benchmarkDenominator.total`; Pandoc has both prose `total` and numeric
     `totalCount`; `runnerStatus` is an object in some manifests and a string
     in others; PHP pass fields mix behavior tests, assertions, and PASS cases.
     Portfolio progress math remains non-comparable until denominator, mapped,
     PHP, runner, and blocker units are normalized.

8. **Medium - blocker language still conflates slice-local green checks with
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
     unresolved full-port gaps: `make test-all`, full cargo/go suites, live
     provider/FUSE/service integrations, Python/PDF/model stacks, actual
     off-band packfile URI download, and root aggregate verification. The
     blocker field should separate `slice blocker`, `root blocker`,
     `upstream-runner blocker`, and `full-port blocker`.

## Test Gate

I did not run `php tools/run-tests.php`.

The required exact gate matched two active no-argument root harnesses owned by
`claude` (`3386351` and `3394963`), and the tree also failed the stability
requirement: active writer/status/test-control loops persist, 133 tmux sessions
are present, and the tracked diff spans 274 files.

A later handoff sanity sample also found active no-argument root PID `3423206`,
owned by `claude`:

```text
3423206 claude   3422995 R+         01:43 php tools/run-tests.php
```

## Next Intervention

Freeze active lane agents, dashboard/evaluator/auditor/integrator loops,
capacity jobs, broad upstream runners, focused PHP shards, and duplicate root
harnesses. Then validate manifests from the frozen tree, enforce atomic
manifest/status writes, accept or reject dirty lane batches one lane at a time,
normalize denominator/mapped/PHP/runner/blocker/commit schemas, integrate the
optional dependency backlog as gated manifest-backed work, regenerate
`progress.md`, `porting.html`, `porting-summary.json`, and lane statuses from
one accepted commit, and only then run the no-argument root harness if the
duplicate-root gate remains empty.
