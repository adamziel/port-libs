# Independent Audit - 2026-05-23T23:04Z

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
HEAD: dd96a7d739a3
HEAD moved during the audit after initial sample 282ca144e8ba
7439 total git status rows
274 tracked dirty files
274 files changed, 116977 insertions(+), 11928 deletions(-)
138 tmux sessions
745 commits since 2026-05-23 00:00 UTC
460 sampled audit/status/integration-hold-style commits since 2026-05-23 00:00 UTC
latest sampled non-audit/status implementation commit: b75226d1
```

The required exact pre-root gate returned an active no-argument root harness, so
I did not start `php tools/run-tests.php`:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
3622625 php tools/run-tests.php
3628298 php tools/run-tests.php lanes/quadrable/tests

ps -o pid,user,ppid,stat,etime,cmd -p 3622625,3628298
3622625 claude   3622522 R+         00:24 php tools/run-tests.php
```

The focused Quadrable shard PID `3628298` exited before owner sampling. Earlier
samples in this same audit also saw root PIDs `3497776` and `3530063` plus
focused Syncthing shard PID `3497969`, owned by `claude`; no duplicate root run
was started.

## Findings

1. **Critical - there is still no trustworthy integration baseline.**
   - Paths: `progress.md:31`, `progress.md:37`, `progress.md:48`,
     `.tmux-team/`, `scripts/run-team-watchdog.sh`,
     `scripts/run-capacity-controller-loop.sh`,
     `scripts/run-dashboard-updater-loop.sh`, and the active root process shown
     above.
   - Goal requirement at risk: `goal.md:20`, `goal.md:29`,
     `goal.md:48`, and `goal.md:49` require capped supervision, small committed
     slices, cleanup before reassignment, and honest repo-wide verification.
   - Evidence: `progress.md` still documents a target of 2 implementation lanes
     plus 1 auditor and lists every lane session as `stopped`, while the audit
     sampled 138 tmux sessions, active watchdog/dashboard/evaluator/capacity/
     integrator/auditor/lane-agent processes, a running no-argument root
     harness, 7439 status rows, and a 274-file tracked diff.

2. **Critical - `porting.html` and `porting-summary.json` are stale and
   conflict with current manifests.**
   - Paths: `porting.html:30`, `porting.html:32`, `porting.html:33`,
     `porting.html:36`, `porting.html:54`, `porting.html:65`,
     `porting-summary.json:2`, `porting-summary.json:4`,
     `porting-summary.json:15`, `porting-summary.json:67`,
     `porting-summary.json:117`, `porting-summary.json:168`,
     `porting-summary.json:185`, and `lanes/*/UPSTREAM_TEST_MANIFEST.json`.
   - Goal requirement at risk: `goal.md:3`, `goal.md:44`, and
     `goal.md:45` require current coordination state with denominator, mapped
     tests, PHP pass/fail, audit, blocker, and commit fields visible.
   - Evidence: both dashboard artifacts still publish generated time
     `2026-05-23 04:57:16 UTC` and source snapshot `bda83c6b93d4`, while sampled
     `HEAD` is `dd96a7d739a3`. Current manifest/dashboard mismatches include
     Difftastic `367 / 735` vs `160 / 417`, Gitoxide `2720 / 2877` vs
     `1432 / 2877`, libsqlite `282 / 1589` vs `149 / 1454`, LightningCSS
     `1728 / 3532` vs `773 / 3532`, markerPDF `277 / 327` vs `159 / 78`,
     Pandoc `1032 / 2276` vs `426 / 2028`, rclone `686 / 1601` vs `291 / 327`,
     Readability `1984 / 1984` vs `1031 / 1984`, and Syncthing `651 / 658` vs
     `235 / 658`.

3. **High - every lane handoff is still pending dirty integration rather than
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
     reviewable committed slices with tests and cleanup before the next
     assignment.
   - Evidence: latest-commit fields are `pending`, `uncommitted`, `not
     committed`, or dirty-batch prose across lanes. Recent history is still
     dominated by audit/status/integration-hold commits, with `b75226d1` the
     latest sampled non-audit/status implementation commit.

4. **High - near-complete parity signals overstate what is proven natively.**
   - Paths: `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/markerpdf/lane-status.json:12`,
     `lanes/readability/lane-status.json:12`, and
     `lanes/syncthing/lane-status.json:12`.
   - Goal requirement at risk: `goal.md:35`, `goal.md:37`,
     `goal.md:38`, and `goal.md:40` require meaningful native parity, upstream
     tests as source of truth, explicit slices, and hard-feature blockers.
   - Evidence: Gitoxide maps `2720 / 2877` without full cargo workspace parity;
     Readability maps `1984 / 1984` while the status still describes focused PHP
     and local oracle evidence; Syncthing maps `651 / 658` while full
     `go test ./...` remains unexecuted; markerPDF maps `277 / 327` while full
     Python/PDF/model/runtime execution remains explicitly unexecuted. These are
     useful focused slices, not proof of near-complete native ports.

5. **High - optional-library coverage is bounded, but not yet auditable
   implementation work.**
   - Paths: `dependency-backlog.json:7`, `dependency-backlog.json:25`,
     `dependency-backlog.json:94`, `dependency-backlog.json:128`,
     `dependency-backlog.json:253`, `progress.md:17`,
     `progress.md:19`, `progress.md:20`, and
     `audits/dependency-porting-tracker-20260523T2240Z.md:31`.
   - Goal requirement at risk: `goal.md:9`, `goal.md:12`,
     `goal.md:35`, `goal.md:38`, and `goal.md:40` cover rich PDF/Pandoc
     conversion behavior, meaningful fixture parity, explicit slices, and
     hard-feature blockers. This audit was also asked to review optional-library
     coverage.
   - Evidence: the backlog correctly identifies bounded shared cores such as
     ZIP/package, XML/HTML5 DOM, PDF text dictionaries, layout/OCR results,
     table geometry, source maps, checksum/hash, compression, and path matching.
     But all 17 items remain only `candidate` or `deferred`; they have no
     dependency manifests, upstream/spec denominators, mapped-test counts, PHP
     pass/fail counts, owners, commits, or dashboard rows. `blocker: "none"` is
     misleading for critical candidates until those gates exist. Do not broaden
     dependency implementation now; attach activated dependency work to accepted
     base-lane blockers and give each dependency the same denominator discipline
     as a lane.

6. **Medium - manifest/status schemas remain non-normalized and internally
   inconsistent.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2301`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/*/lane-status.json:12`, and `porting-summary.json:15`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:38`, and
     `goal.md:45` require real denominators, explicit slices, and comparable
     dashboard fields.
   - Evidence: `benchmarkDenominator.total` is sometimes a number and sometimes
     prose; Pandoc carries both `total` prose and `totalCount`; Dolt stores
     `mapped` before a later prose `total`; `runnerStatus` is not consistently
     typed; PHP counts mix tests, assertions, focused behavior checks, and PASS
     cases. Portfolio progress math remains non-comparable until denominator,
     mapped, PHP, runner, blocker, and commit units are normalized.

7. **Medium - blocker language still conflates slice-local green checks with
   full-port blockers.**
   - Paths: `lanes/esbuild/lane-status.json:12`,
     `lanes/gitoxide/lane-status.json:12`,
     `lanes/markerpdf/lane-status.json:12`,
     `lanes/pandoc/lane-status.json:12`,
     `lanes/rclone/lane-status.json:12`,
     `lanes/readability/lane-status.json:12`, and
     `lanes/syncthing/lane-status.json:12`.
   - Goal requirement at risk: `goal.md:31` and `goal.md:40` require precise
     blockers and no silent skipping of hard features.
   - Evidence: several lanes say no current local blocker while listing major
     unresolved full-port work: `make test-all`, full cargo/go/Haskell suites,
     live provider/FUSE/service integrations, Python/PDF/model stacks, root
     aggregate verification, and secret-bearing or external-service paths. The
     blocker field should separate `slice blocker`, `root blocker`,
     `upstream-runner blocker`, and `full-port blocker`.

## Test Gate

I did not run `php tools/run-tests.php`.

The required exact gate matched an active no-argument root harness owned by
`claude` (`3622625`), and the tree also failed the stability requirement:
active writer/status/test-control loops persist, 138 tmux sessions are present,
and the tracked diff spans 274 files.

Verification performed by this audit was limited to JSON parsing:

```text
jq empty lanes/*/UPSTREAM_TEST_MANIFEST.json lanes/*/lane-status.json porting-summary.json
```

## Next Intervention

Freeze active lane agents, dashboard/evaluator/auditor/integrator/watchdog
loops, capacity jobs, broad upstream runners, focused PHP shards, and duplicate
root harnesses. Then validate manifests from the frozen tree, enforce atomic
manifest/status writes, accept or reject dirty lane batches one lane at a time,
normalize denominator/mapped/PHP/runner/blocker/commit schemas, integrate the
optional dependency backlog as gated manifest-backed work, regenerate
`progress.md`, `porting.html`, `porting-summary.json`, and lane statuses from
one accepted commit, and only then run the no-argument root harness if the
duplicate-root gate remains empty.
