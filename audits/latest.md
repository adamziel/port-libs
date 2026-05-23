# Independent Audit - 2026-05-23T12:52:50Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, lane
status files needed for status/dashboard alignment, recent Git history, dirty
tree state, active process state, and the required duplicate-root test gate.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, or start a root harness. Bridge,
generated, supplied, oracle, CLI, and shell-backed evidence is treated as
non-progress unless it is explicitly temporary oracle tooling.

Sampled `HEAD` for this audit was `51867989249e` (`Refresh independent audit
status`). Recent history reviewed includes `51867989`, `b75226d1`,
`30be5e3c`, `90d1fa3b`, `81419ac3`, `69405063`, `0f1444c1`,
`efa4e0c2`, `f8bd46e4`, `09995598`, `a04f2c8b`, and `5b6d5a84`.

## Findings

1. **Critical - the checkout is still not a stable integration checkpoint.**
   - Paths: `progress.md:25`, `progress.md:31`-`42`,
     `lanes/*/lane-status.json`, `porting.html`, `porting-summary.json`.
   - Goal requirement at risk: `goal.md` requires capped supervised workers,
     small reviewable slices with passing tests, current owner/session/status
     tracking, and honest repo-wide test records.
   - Evidence: `progress.md:25` still says the launch target is 2
     implementation lanes plus 1 auditor, while `progress.md:31`-`42` reports
     all 12 lane sessions as `stopped`.
   - Evidence: process sampling found active primary lane agents, auditor,
     integrator, evaluator, dashboard updater, capacity controller, capacity
     jobs, Dolt runner, and a no-argument root harness despite the stopped-lane
     table.
   - Evidence: the required duplicate-root gate later returned active root PIDs
     `180953 php tools/run-tests.php`, `212436 php tools/run-tests.php`, and
     `214873 php tools/run-tests.php`; owner evidence showed all owned by
     `claude`.
   - Evidence: latest dirty-tree samples reported `1550` default
     `git status --short --untracked-files=all` rows, `161` tracked changed
     files, and `161 files changed, 50128 insertions(+), 5110 deletions(-)`.
   - Audit judgment: current lane percentages, green lane-local claims,
     latest-commit fields, and root-test anecdotes are not acceptance evidence
     until active writers/status publishers are frozen and one regenerated
     snapshot is validated.

2. **High - `porting.html` and `porting-summary.json` are stale and still
   fail the dashboard contract.**
   - Paths: `porting.html:30`-`36`, `porting.html:43`-`44`,
     `porting-summary.json:2`-`8`.
   - Goal requirement at risk: `goal.md` requires the dashboard to show current
     benchmark source, upstream denominator, mapped tests, PHP pass/fail,
     WordPress scenarios, phase, audit, current work, blocker, and commit.
   - Evidence: `porting.html` still publishes generated time
     `2026-05-23 04:57:16 UTC` and snapshot `bda83c6b93d4`, while sampled
     `HEAD` is `51867989249e`; `porting-summary.json` has the same stale
     source commit.
   - Evidence: `porting.html` still collapses required fields into compact
     `Benchmark` and `Mapped` columns instead of separate benchmark source,
     upstream denominator, mapped tests, and PHP pass/fail columns.
   - Evidence: dashboard rows disagree with current manifests. Current
     manifest mapped counts are Difftastic `266`, Dolt `489`, Esbuild `228`,
     Gitoxide `1924`, libsqlite `215`, LightningCSS `1201`, markerPDF `220`,
     Pandoc `648`, rclone `458`, Readability `1579`, and Syncthing `337`, while
     the dashboard still publishes `160`, `242`, `164`, `1432`, `149`, `773`,
     `159`, `426`, `291`, `1031`, and `235` respectively. Quadrable's mapped
     denominator still matches `55 / 55`, but its dashboard PHP count is stale
     relative to current lane status.

3. **High - repo-wide PHP test records remain mutually non-comparable.**
   - Paths: `tools/run-tests.php`, `progress.md`, and all
     `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md` requires passing tests to be tied to
     accepted native slices and recorded honestly.
   - Evidence: lane statuses currently mix green aggregate claims, red
     aggregate claims, pending root claims, duplicate-gated claims, and
     focused-only claims. Examples: Gitoxide records root green with `226`
     files / `26600` assertions; rclone records root green with `226` files /
     `26546` assertions; Pandoc records `204` Difftastic failures; Syncthing
     records `2` LightningCSS failures; Readability and Quadrable record root
     pending/duplicate-gated evidence.
   - Evidence: duplicate-root gates were active during audit and handoff
     samples, so this audit did not start another root run.
   - Audit judgment: the next accepted test result must be one quiesced
     `php tools/run-tests.php` run from one accepted tree, not lane-by-lane
     anecdotes gathered while active workers mutate the checkout.

4. **High - manifest/status schemas still cannot produce trustworthy
   portfolio math.**
   - Paths: all `lanes/*/UPSTREAM_TEST_MANIFEST.json`, all
     `lanes/*/lane-status.json`, `porting-summary.json`, `porting.html`.
   - Goal requirement at risk: `goal.md` requires real upstream denominators,
     explicit slices, comparable mapped-test/PHP pass-fail counts, precise
     blockers, and a generated dashboard backed by those fields.
   - Evidence: denominator units remain mixed. Difftastic, Dolt, Esbuild,
     Pandoc, and Quadrable store prose-string totals; Gitoxide, libsqlite,
     LightningCSS, markerPDF, rclone, Readability, and Syncthing store numeric
     totals.
   - Evidence: `runnerStatus` remains non-normalized: objects appear in
     several manifests, strings appear in Gitoxide, markerPDF, and Quadrable,
     and Pandoc has no comparable denominator-level runner-status field. The
     contents mix upstream runner pass/fail, static reads, PHP test counts,
     root-test anecdotes, and skipped-suite rationale.
   - Evidence: `porting-summary.json` is generated from stale condensed fields
     (`denominator`, `mapped`, `php`, `coverage`) rather than the normalized
     source fields the goal asks the dashboard to expose.

5. **Medium - too much evidence is still unaccepted lane-local work rather
   than committed native implementation progress.**
   - Paths: `lanes/difftastic/lane-status.json:13`,
     `lanes/dolt/lane-status.json:13`, `lanes/esbuild/lane-status.json:13`,
     `lanes/gitoxide/lane-status.json:13`,
     `lanes/libsqlite/lane-status.json:13`,
     `lanes/markerpdf/lane-status.json:13`,
     `lanes/pandoc/lane-status.json:13`,
     `lanes/quadrable/lane-status.json:13`,
     `lanes/readability/lane-status.json:13`,
     `lanes/syncthing/lane-status.json:13`.
   - Goal requirement at risk: `goal.md` requires small reviewable committed
     slices and says generated fixtures, bridge calls, and shell-outs must not
     count as native implementation progress.
   - Evidence: many lane `latestCommit` fields say `pending`, `not committed`,
     `uncommitted`, or dirty-batch prose. Several manifests and statuses also
     mix static upstream reads, temporary oracle/CLI evidence, and aggregate
     root anecdotes into progress prose.
   - Audit judgment: the supervisor should accept/reject dirty lane batches one
     lane at a time and only then let dashboard math count them.

## Test Gate

I did not run `php tools/run-tests.php`.

Required duplicate-root gate before any possible root run:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
```

Observed during audit:

```text
159110 php tools/run-tests.php lanes/readability/tests
```

That focused process exited before owner sampling. Later required gates found
active no-argument root harnesses:

```text
180953 php tools/run-tests.php
212436 php tools/run-tests.php
214873 php tools/run-tests.php
```

Owner evidence:

```text
PID USER  PPID ELAPSED STAT COMMAND
180953 claude 33144 22 Rs php tools/run-tests.php
212436 claude 32975 18 Rs php tools/run-tests.php
214873 claude 136997 21 Rs php tools/run-tests.php
```

No duplicate root harness was started.

Validation commands run instead:

```text
jq empty lanes/*/UPSTREAM_TEST_MANIFEST.json lanes/*/lane-status.json porting-summary.json
git status --short --untracked-files=all | wc -l
git status --short --untracked-files=no | wc -l
git diff --shortstat
pgrep -af '^php tools/run-tests\.php( |$)'
ps -o pid,user,ppid,etimes,stat,args -p 180953
ps -o pid,user,ppid,etimes,stat,args -p 212436
ps -o pid,user,ppid,etimes,stat,args -p 214873
git log --oneline --decorate -n 30
git show --stat --oneline --decorate --no-renames -n 8
```

Results: all lane upstream manifests, lane status files, and
`porting-summary.json` parsed as valid JSON at the time checked. Latest samples
reported `1550` default status rows, `161` tracked changed files, and
`161 files changed, 50128 insertions(+), 5110 deletions(-)`.

## Next Intervention

Freeze active writers/status publishers and duplicate root/focused PHP loops
first. Then validate manifests from the frozen tree, accept or reject dirty
lane batches one lane at a time, normalize manifest/status denominator, mapped,
PHP pass/fail, runner, progress, and commit fields, regenerate `progress.md`,
`porting.html`, `porting-summary.json`, and lane statuses from that same
accepted snapshot, rerun the exact duplicate-root gate, and capture one
quiesced `php tools/run-tests.php` result.
