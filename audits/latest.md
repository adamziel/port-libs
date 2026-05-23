# Independent Audit - 2026-05-23T23:09Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, every
`lanes/*/lane-status.json`, `dependency-backlog.json`, and recent Git history.
I also sampled the required pre-root harness gate, process state, tmux session
count, and worktree status.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, credential stores, provider
configs, or auth files. Bridge code, generated fixtures, copied oracle
fixtures, and shell-outs are treated as non-progress unless explicitly
temporary oracle tooling.

`jq empty` passed for every lane manifest, every lane-status file,
`porting-summary.json`, and `dependency-backlog.json`.

## Current Snapshot

```text
pre-audit-commit HEAD: 305e5d62c64c
pre-audit-commit HEAD moved during the audit after initial sample 1a112c6ebaef
latest commits: audit/status/integration-hold commits dominate the top of history
commits since 2026-05-23 00:00 UTC: 749
sampled audit/status/integration-hold commits since 2026-05-23 00:00 UTC: 426
latest sampled non-audit/status implementation commit: b75226d1
git status rows: 7769
tracked dirty rows: 278
git diff HEAD --shortstat: 278 files changed, 118365 insertions(+), 12077 deletions(-)
tmux sessions: 137
```

The required exact pre-root gate matched active root harnesses, so I did not
start `php tools/run-tests.php`.

Initial gate sample:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
3709835 php tools/run-tests.php
3710125 php tools/run-tests.php lanes/syncthing/tests/...
3710198 php tools/run-tests.php lanes/syncthing/tests/...
3710650 php tools/run-tests.php lanes/markerpdf/tests lanes/pandoc/tests lanes/readability/tests
3710762 php tools/run-tests.php lanes/rclone/tests lanes/syncthing/tests
3710823 php tools/run-tests.php lanes/libsqlite/tests lanes/lightningcss/tests lanes/quadrable/tests lanes/difftastic/tests lanes/esbuild/tests
3711738 php tools/run-tests.php lanes/markerpdf/tests/...
3711962 php tools/run-tests.php lanes/quadrable/tests
3711990 php tools/run-tests.php lanes/pandoc/tests

ps -o pid,user,ppid,stat,etime,cmd -p 3709835
3709835 claude 3709526 R+ 00:21 php tools/run-tests.php
```

Later handoff sample:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
3751339 php tools/run-tests.php

ps -o pid,user,ppid,stat,etime,cmd -p 3751339
3751339 claude 3751275 R+ 00:38 php tools/run-tests.php
```

Final active gate sample:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
3805967 php tools/run-tests.php

ps -o pid,user,ppid,stat,etime,cmd -p 3805967
3805967 claude 3805487 R+ 00:38 php tools/run-tests.php
```

A later exact gate was clear, but the tree still failed the stability check, so
no root aggregate was started.

Post-commit sanity sample:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
3941960 php tools/run-tests.php

ps -o pid,user,ppid,stat,etime,cmd -p 3941960
3941960 claude 3941903 R+ 00:18 php tools/run-tests.php
```

## Findings

1. **Critical - the integration baseline is still not trustworthy.**
   - Paths: `progress.md:32`, `progress.md:38`, `progress.md:49`,
     `.tmux-team/`, `scripts/run-capacity-controller-loop.sh`,
     `scripts/run-dashboard-updater-loop.sh`, `scripts/run-evaluator-loop.sh`,
     `scripts/run-team-watchdog.sh`, and the active root harness evidence above.
   - Goal requirement at risk: `goal.md:20`, `goal.md:29`,
     `goal.md:48`, and `goal.md:49` require capped supervision, small
     committed slices, cleanup before reassignment, and honest repo-wide
     verification.
   - Evidence: `progress.md` still says the launch target is 2 implementation
     lanes plus 1 auditor and lists every lane as `stopped`, while the audit
     sampled 134 tmux sessions, active lane/watchdog/dashboard/evaluator/
     capacity/integrator/auditor loops, broad Dolt BATS, focused PHP shards,
     active no-argument root harnesses, and a 278-file tracked diff.

2. **Critical - dashboard rows are not synchronized with the dirty manifests,
   even after the current dashboard refresh.**
   - Paths: `porting.html:34`, `porting.html:38`, `porting.html:56`,
     `porting.html:59`, `porting.html:65`,
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:15`, and
     `porting-summary.json`.
   - Goal requirement at risk: `goal.md:3`, `goal.md:44`, and
     `goal.md:45` require current visible denominator, mapped-test, PHP
     pass/fail, audit, blocker, and commit fields.
   - Evidence: `porting.html` self-declares generated time
     `2026-05-23 23:06:09 UTC` and snapshot `1a112c6ebaef`, while `HEAD` moved
     to `305e5d62c64c` before handoff. The table also disagrees with dirty
     manifests: Difftastic dashboard `367 / 735` vs manifest `369 / 735`,
     Gitoxide `2720 / 2877` vs `2730 / 2877`, and rclone `686 / 1601` vs
     `688 / 1601`. `porting.html` and `porting-summary.json` are themselves
     modified relative to `HEAD`, so this is not an accepted snapshot.

3. **High - every lane handoff remains a dirty batch rather than accepted
   implementation history.**
   - Paths: `lanes/difftastic/lane-status.json:13`,
     `lanes/dolt/lane-status.json:13`,
     `lanes/esbuild/lane-status.json:13`,
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
     reviewable commits with passing tests, progress updates, and cleanup.
   - Evidence: latest-commit fields are still `pending`, `uncommitted`, `not
     committed`, or dirty-batch prose across the portfolio. The top of Git
     history is audit/status/integration-hold work rather than accepted lane
     implementation commits.

4. **High - near-complete progress percentages overstate native parity.**
   - Paths: `porting.html:56` through `porting.html:67`,
     `lanes/gitoxide/lane-status.json:12`,
     `lanes/markerpdf/lane-status.json:12`,
     `lanes/readability/lane-status.json:12`,
     `lanes/syncthing/lane-status.json:12`,
     `lanes/rclone/lane-status.json:12`, and
     `lanes/pandoc/lane-status.json:12`.
   - Goal requirement at risk: `goal.md:35`, `goal.md:37`,
     `goal.md:38`, and `goal.md:40` require meaningful native parity,
     upstream tests as source of truth, explicit slices, and hard-feature
     blockers.
   - Evidence: the dashboard reports 97.4% average progress and 98-99% for
     many lanes, but root verification is active elsewhere and not accepted;
     full Cargo/Go/Haskell/Python/PDF/model/live-provider suites remain
     unexecuted or explicitly out of scope for the latest slices. These are
     useful focused slices, not proof of near-complete native ports.

5. **High - optional-library coverage is bounded but not yet auditable work,
   and the current backlog can become too broad if activated before the base
   lanes are frozen.**
   - Paths: `dependency-backlog.json:7`, `dependency-backlog.json:25`,
     `dependency-backlog.json:110`, `dependency-backlog.json:144`,
     `dependency-backlog.json:218`, `dependency-backlog.json:254`,
     `dependency-backlog.json:22`, `dependency-backlog.json:41`,
     `dependency-backlog.json:124`, `dependency-backlog.json:157`, and
     `progress.md:17`.
   - Goal requirement at risk: `goal.md:9`, `goal.md:12`,
     `goal.md:15`, `goal.md:18`, `goal.md:25`, `goal.md:30`,
     `goal.md:35`, and `goal.md:40` require rich conversion/runtime behavior,
     real denominators, no progress credit for bridge/shell-out work, and
     explicit hard-feature blockers.
   - Evidence: the backlog correctly names rich-function gaps: ZIP/package and
     XML/HTML5 DOM for Pandoc/markerPDF/readability, DOCX/legacy CFB/EPUB for
     Pandoc, PDF text/layout/OCR/table geometry for markerPDF, source maps and
     grammar subsets for esbuild/LightningCSS/difftastic, protobuf wire format
     for Syncthing, and checksum/archive/pathspec shared cores. But all items
     are only candidate/deferred; none has a dependency manifest, upstream/spec
     denominator, mapped tests, PHP pass/fail evidence, accepted owner, commit,
     or dashboard row. `blocker: "none"` on these candidate items is misleading
     until those gates exist.

6. **Medium - manifest and status schemas remain non-normalized and hard to
   compare across lanes.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2301`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:18`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:381`, and
     `lanes/*/lane-status.json:12`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:38`, and
     `goal.md:45` require real denominators, explicit slices, and comparable
     dashboard fields.
   - Evidence: `benchmarkDenominator.total` alternates between numbers and
     prose; Pandoc uses both `total` prose and `totalCount`; Dolt carries a
     late duplicate/prose `total`; `runnerStatus` alternates among objects,
     strings, and null; PHP counts mix behavior tests, assertions, PASS cases,
     and lane file counts. Portfolio percentages are not comparable until those
     units are normalized.

7. **Medium - blocker language still mixes slice-local green checks with
   full-port blockers.**
   - Paths: `lanes/esbuild/lane-status.json:12`,
     `lanes/gitoxide/lane-status.json:12`,
     `lanes/libsqlite/lane-status.json:12`,
     `lanes/lightningcss/lane-status.json:12`,
     `lanes/markerpdf/lane-status.json:12`,
     `lanes/pandoc/lane-status.json:12`,
     `lanes/rclone/lane-status.json:12`,
     `lanes/readability/lane-status.json:12`, and
     `lanes/syncthing/lane-status.json:12`.
   - Goal requirement at risk: `goal.md:31` and `goal.md:40` require precise
     blockers and no silent skipping of hard features.
   - Evidence: several lanes say there is no current local blocker while the
     same field lists unresolved full-port work: full workspace runners,
     release-extra target suites, live providers/FUSE/services, SQLite all/
     release permutations, PDF/model/OCR runtime stacks, credential-bearing
     integrations, and root aggregate verification. Use separate `slice`,
     `root`, `upstream-runner`, and `full-port` blocker fields.

## Test Gate

I did not run `php tools/run-tests.php`. The required duplicate-root gate
matched active no-argument root harnesses owned by `claude`, and the tree was
not stable enough for a trustworthy duplicate aggregate run.

Verification performed by this audit:

```text
jq empty lanes/*/UPSTREAM_TEST_MANIFEST.json lanes/*/lane-status.json porting-summary.json dependency-backlog.json
```

## Next Intervention

Freeze active lane agents, dashboard/evaluator/auditor/integrator/watchdog
loops, capacity jobs, broad upstream runners, focused PHP shards, and duplicate
root harnesses. Then validate manifests from the frozen tree, enforce atomic
manifest/status/dashboard writes, accept or reject dirty lane batches one lane
at a time, normalize denominator/mapped/PHP/runner/blocker/commit schemas,
give activated optional dependencies their own manifest-backed gated rows,
regenerate `progress.md`, `porting.html`, `porting-summary.json`, and lane
statuses from one accepted commit, and only then run the no-argument root
harness if the duplicate-root gate remains empty.
