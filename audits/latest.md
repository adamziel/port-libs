# Independent Audit - 2026-05-23T12:45:49Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, lane
status files needed for status/dashboard alignment, recent Git history, dirty
tree state, active process state, and the required duplicate-root test gate.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, or start a root harness. Bridge,
generated, supplied, oracle, CLI, and shell-backed evidence is treated as
non-progress unless it is explicitly temporary oracle tooling.

Sampled `HEAD` for this audit was `30be5e3c193c` (`Refresh independent audit
status`). Recent history reviewed includes `30be5e3c`, `90d1fa3b`,
`81419ac3`, `69405063`, `0f1444c1`, `efa4e0c2`, `f8bd46e4`,
`09995598`, `a04f2c8b`, `5b6d5a84`, `957f8587`, and `f40a591b`.

## Findings

1. **Critical - the checkout is still not a stable integration checkpoint.**
   - Paths: `progress.md:25`, `progress.md:31`-`42`,
     `lanes/*/lane-status.json`, `porting.html`, and
     `porting-summary.json`.
   - Goal requirement at risk: `goal.md:20` requires supervised parallel work
     capped to VM capacity, `goal.md:29` requires small reviewable slices with
     passing tests, `goal.md:44` requires current owner/session/status
     tracking, and `goal.md:49` requires honest repo-wide test recording.
   - Evidence: `progress.md:25` still documents a launch target of 2
     implementation lanes plus 1 auditor, while `progress.md:31`-`42` still
     reports all 12 lane sessions as `stopped`.
   - Evidence: process sampling found 20 active repo worker/status processes:
     team watchdog, evaluator, capacity controller, dashboard updater,
     auditor, integrator, 12 primary lane agents, `port-dolt-runner`, and a
     capacity SQLite job.
   - Evidence: the dirty tree grew during this audit. The latest sample
     reported `1547` default `git status --short --untracked-files=all` rows,
     `165` tracked changed files, and `165 files changed, 49263
     insertions(+), 5120 deletions(-)`.
   - Audit judgment: current lane percentages, green lane-local claims,
     latest-commit fields, and root-test anecdotes are not acceptance evidence
     until active writers/status publishers are frozen and one regenerated
     snapshot is validated.

2. **High - `porting.html` and `porting-summary.json` are stale and do not
   satisfy the dashboard contract.**
   - Paths: `porting.html:30`-`36`, `porting.html:41`-`50`,
     `porting.html:54`-`65`, and `porting-summary.json:2`-`8`.
   - Goal requirement at risk: `goal.md:3` and `goal.md:45` require current
     tracking of benchmark source, upstream denominator, mapped tests, PHP
     pass/fail, WordPress scenarios, phase, audit, current work, blocker, and
     latest commit.
   - Evidence: `porting.html:32`-`36` and `porting-summary.json:2`-`8` still
     publish generated time `2026-05-23 04:57:16 UTC` and source commit
     `bda83c6b93d4`, while sampled `HEAD` is `30be5e3c193c`.
   - Evidence: `porting.html:41`-`50` still exposes compact `Benchmark` and
     `Mapped` columns instead of separate benchmark source, upstream
     denominator, mapped tests, and PHP pass/fail columns.
   - Evidence: dashboard rows disagree with current manifests. Examples:
     Difftastic publishes `160 / 417` at `porting.html:54`, but the manifest
     is `266` mapped over `587...` at
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14`-`15`; Dolt publishes
     `242 / 613` at `porting.html:55`, but the manifest is `489 / 613...` at
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`-`15`; Esbuild publishes
     `164 / 2,567` at `porting.html:56`, but the manifest is `227 / 2,567` at
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:14`-`15`; Gitoxide publishes
     `1432 / 2877` at `porting.html:57`, but the manifest is `1920 / 2877` at
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:14`-`15`; libsqlite publishes
     `149 / 1454` at `porting.html:58`, but the manifest is `214 / 1589` at
     `lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json:15`-`16`; LightningCSS
     publishes `773 / 3532` at `porting.html:59`, but the manifest is
     `1201 / 3532` at
     `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json:14`-`15`; markerPDF
     publishes `159 / 78` at `porting.html:60`, but the manifest is
     `220 / 274` at `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14`-`15`;
     Pandoc publishes `426 / 2028` at `porting.html:61`, but the manifest is
     `648 / 2276...` at `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14`-`15`;
     rclone publishes `291 / 327` at `porting.html:63`, but the manifest is
     `458 / 2553` at `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:14`-`15`;
     Readability publishes `1031 / 1984` at `porting.html:64`, but the
     manifest is `1579 / 1984` at
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:14`-`15`; and Syncthing
     publishes `235 / 658` at `porting.html:65`, but the manifest is
     `337 / 658` at `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:14`-`15`.

3. **High - repo-wide PHP test records remain mutually non-comparable.**
   - Paths: `tools/run-tests.php`, `progress.md`, and all
     `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:29`, `goal.md:35`, and `goal.md:49`
     require passing tests to be tied to accepted native slices and recorded
     honestly.
   - Evidence: current lane statuses mix incompatible aggregate claims. Dolt
     records a root rerun passing with 226 files and 26,546 assertions in
     `lanes/dolt/lane-status.json:10`; Gitoxide records a root failure outside
     Gitoxide in Readability in `lanes/gitoxide/lane-status.json:10`;
     Pandoc records 204 Difftastic failures in
     `lanes/pandoc/lane-status.json:10`; Syncthing records 2 LightningCSS
     failures in `lanes/syncthing/lane-status.json:10`; rclone records a green
     aggregate in `lanes/rclone/lane-status.json:10`; and Readability records
     root verification as pending in `lanes/readability/lane-status.json:10`.
   - Evidence: many latest-commit fields are not immutable accepted commits:
     examples include `pending`, `not committed`, `uncommitted`, and dirty
     batch prose at `lanes/difftastic/lane-status.json:13`,
     `lanes/dolt/lane-status.json:13`, `lanes/esbuild/lane-status.json:13`,
     `lanes/gitoxide/lane-status.json:13`,
     `lanes/readability/lane-status.json:13`, and
     `lanes/syncthing/lane-status.json:13`.
   - Audit judgment: the next accepted test result must be one quiesced
     `php tools/run-tests.php` run from one accepted tree, not lane-by-lane
     anecdotes gathered while active workers mutate the checkout.

4. **High - manifest/status schemas still cannot produce trustworthy
   portfolio math.**
   - Paths: all `lanes/*/UPSTREAM_TEST_MANIFEST.json`, all
     `lanes/*/lane-status.json`, `porting-summary.json`, and `porting.html`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:38`, `goal.md:44`, and
     `goal.md:45` require real upstream denominators, explicit slices,
     comparable mapped-test/PHP pass-fail counts, precise blockers, and a
     dashboard generated from those fields.
   - Evidence: denominator units remain mixed. Difftastic, Dolt, Esbuild,
     Pandoc, and Quadrable store prose-string totals at
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14`, and
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:14`, while Gitoxide,
     libsqlite, LightningCSS, markerPDF, rclone, Readability, and Syncthing use
     numeric totals.
   - Evidence: `runnerStatus` remains non-normalized: objects appear in
     Difftastic, Dolt, libsqlite, LightningCSS, rclone, Readability, and
     Syncthing; long strings appear in Gitoxide and Quadrable; markerPDF uses
     the string `not-executed`; and Pandoc has no comparable runner-status
     field in the denominator block.
   - Evidence: manifest/status values moved while this audit was reading them.
     For example, Dolt changed from `481 / 613...` to `489 / 613...`, and
     rclone lane status moved from `453` to `458` PHP behavior tests during
     the same audit window.

5. **Medium - upstream runner, oracle, generated-fixture, and CLI evidence is
   still over-mixed with native PHP progress.**
   - Paths: `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:18`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:275`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:18`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:517`,
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:432`, and
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:40`.
   - Goal requirement at risk: `goal.md:1`, `goal.md:30`, `goal.md:35`,
     `goal.md:37`, and `goal.md:40` require bridge/shell-out evidence not to
     count as native implementation progress, upstream tests as source of
     truth where possible, meaningful fixture parity, and hard gaps recorded
     explicitly.
   - Evidence: Gitoxide's runner string combines bounded upstream runner
     shards, static source inventory, PHP assertion counts, and a red root
     anecdote. Quadrable folds C++ runner passes, LMDB dump/load oracles,
     command-output wrappers, and benchmark exclusions into one field. rclone
     and Syncthing include useful bounded upstream evidence, but visible fields
     still do not separate upstream runner pass, temporary oracle/CLI probe,
     native PHP behavior count, assertions, failures, blocker, and accepted
     commit.

## Test Gate

I did not run `php tools/run-tests.php`.

Required duplicate-root gate before any possible root run:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
```

Observed output at audit sample `2026-05-23T12:45:49Z`: no rows.

No duplicate root harness was active at that sample, but no root run was
started because the tree was not stable enough for accepted aggregate evidence:
active writer/status processes persisted, manifest/status values changed during
the audit, and the dirty tree remained broad.

Validation commands run instead:

```text
jq empty lanes/*/UPSTREAM_TEST_MANIFEST.json lanes/*/lane-status.json porting-summary.json
git status --short --untracked-files=all | wc -l
git status --short --untracked-files=no | wc -l
git diff --shortstat
pgrep -af '^php tools/run-tests\.php( |$)'
ps -eo pid,user,ppid,etimes,stat,args | rg 'run-|php tools/run-tests\.php'
git log --oneline --decorate -n 30
git show --stat --oneline --decorate --no-renames -n 8
```

Results: all lane upstream manifests, lane status files, and
`porting-summary.json` parsed as valid JSON at the time checked. Latest samples
reported `1547` default status rows, `165` tracked changed files, and
`165 files changed, 49263 insertions(+), 5120 deletions(-)`.

## Next Intervention

Freeze active writers/status publishers and duplicate focused PHP loops first.
Then validate manifests from the frozen tree, accept or reject dirty lane
batches one lane at a time, normalize manifest/status denominator, mapped, PHP
pass/fail, runner, progress, and commit fields, regenerate `progress.md`,
`porting.html`, `porting-summary.json`, and lane statuses from that same
accepted snapshot, rerun the exact duplicate-root gate, and capture one
quiesced `php tools/run-tests.php` result.
