# Independent Audit - 2026-05-23T09:39:56Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, lane
status files needed for alignment checks, recent Git history through the
observed pre-audit `HEAD` `106f686490d8`, dirty-tree state, active process
state, and the required PHP test gate.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, or start a root harness. Bridge,
generated, supplied, oracle, and shell-backed evidence is treated as
non-progress unless it is explicitly temporary oracle tooling.

## Findings

1. **Critical - there is still no stable integration snapshot to accept.**
   - Paths: `progress.md:25`, `progress.md:31`-`42`, current dirty
     `lanes/*` files, `.tmux-team/prompts/*`, `.tmux-team/logs/*`, and
     automation scripts under `scripts/`.
   - Goal requirement at risk: `goal.md:20`, `goal.md:29`,
     `goal.md:48`, `goal.md:49`, and `goal.md:52` require capped
     supervision, small committed slices with passing tests, cleanup, and
     one visible current status surface.
   - Evidence: `progress.md:25` still documents a two-worker-plus-auditor
     target and `progress.md:31`-`42` still reports all lanes as `stopped`,
     while process sampling found 61 matching automation/test/agent processes.
   - Evidence: dirty-tree samples moved during the audit from `133` to `135`
     to `141` tracked changed entries; the latest sample reported a dirty
     Readability manifest plus many other uncommitted lane/status changes.
   - Evidence: manifest data changed mid-audit. `libsqlite` was first read as
     `1454 / 194` and later as `1589 / 195`; Readability was briefly
     unparsable due to an invalid namespace escape in a dirty working-tree
     warning string, then another worker repaired it before final handoff.
     The current working-tree Readability manifest parses and reports
     `1370 / 1984` with `128` PHP behavior tests, but the repair is still an
     uncommitted lane-file change outside this audit.
   - Audit judgment: no portfolio pass/fail, percentage, or lane commit field
     should be accepted until active writers/status publishers are frozen and a
     single snapshot is tested.

2. **High - the root-test record is still contradictory and was not rerun.**
   - Paths: `tools/run-tests.php`,
     `lanes/dolt/lane-status.json:12`-`13`,
     `lanes/esbuild/lane-status.json:12`-`13`,
     `lanes/libsqlite/lane-status.json:12`-`13`,
     `lanes/lightningcss/lane-status.json:12`-`13`,
     `lanes/markerpdf/lane-status.json:12`-`13`,
     `lanes/pandoc/lane-status.json:12`-`13`,
     `lanes/quadrable/lane-status.json:12`-`13`,
     `lanes/rclone/lane-status.json:12`-`13`,
     `lanes/readability/lane-status.json:12`-`13`,
     `lanes/syncthing/lane-status.json:12`-`13`.
   - Goal requirement at risk: `goal.md:29` and `goal.md:49` require passing
     tests on committed slices and honest repo-wide failure records.
   - Evidence: the initial required duplicate-root gate returned
     `2661450 php tools/run-tests.php`. That process exited before owner
     sampling could recover owner evidence. Later exact gates were clear, but
     the tree was not stable enough for a trustworthy root run because active
     automation continued and tracked files/manifests changed during the audit.
     Final post-amend gates returned active root PIDs `2694170` and
     `2695408`, with owner evidence
     `2694170 claude 2667629 00:17 Rs php tools/run-tests.php` and
     `2695408 claude 2695407 00:00 R php tools/run-tests.php`, so no duplicate
     run was started.
   - Evidence: lane statuses still disagree: Dolt, libsqlite, and rclone claim
     aggregate root PHP passed; LightningCSS, Pandoc, Readability, and
     Syncthing report red root runs against different unrelated lanes; esbuild,
     markerPDF, and Quadrable report duplicate-root pending gates with stale
     PIDs.
   - Audit judgment: collapse root-test state to one repo-level record from a
     frozen snapshot. Lane-local root anecdotes should not drive acceptance.

3. **High - `porting.html` and `porting-summary.json` are stale and still miss the dashboard column contract.**
   - Paths: `porting.html:30`-`36`, `porting.html:41`-`45`,
     `porting.html:54`-`65`, `porting-summary.json:2`-`8`, and every
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`.
   - Goal requirement at risk: `goal.md:3` and `goal.md:45` require current
     dashboard fields for benchmark source, upstream denominator, mapped tests,
     PHP pass/fail, WordPress scenarios, phase, audit, current work, blocker,
     and commit.
   - Evidence: `porting.html:32`-`36` still publishes generated time
     `2026-05-23 04:57:16 UTC` and source commit `bda83c6b93d4`; inspected
     `HEAD` is `106f686490d8`.
   - Evidence: the table still combines benchmark source and denominator in a
     single `Benchmark` column and combines PHP pass/fail with mapped tests in
     a single `Mapped` column (`porting.html:41`-`45`), contrary to the
     separate-column requirement in `goal.md:45`.
   - Evidence: current manifest counts disagree with the dashboard: Difftastic
     `223 / 583` vs `160 / 417`, Dolt `390 / 613` vs `242 / 613`, esbuild
     `202 / 2567` vs `164 / 2567`, Gitoxide `1718 / 2877` vs `1432 / 2877`,
     libsqlite `195 / 1589` vs `149 / 1454`, LightningCSS `1001 / 3532` vs
     `773 / 3532`, markerPDF `192 / 247` vs `159 / 78`, Pandoc `548 / 2276`
     vs `426 / 2028`, rclone `393 / 2553` vs `291 / 327`, Readability
     `1370 / 1984` vs `1031 / 1984`, and Syncthing `287 / 658` vs
     `235 / 658`.

4. **High - manifest/status schemas still cannot produce trustworthy portfolio percentages.**
   - Paths: all `lanes/*/UPSTREAM_TEST_MANIFEST.json` and
     `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:35`,
     `goal.md:38`, `goal.md:44`, and `goal.md:45` require real denominators,
     explicit slices, current coordination fields, and meaningful percentages.
   - Evidence: `benchmarkDenominator.total` is prose in Difftastic, Dolt,
     esbuild, Pandoc, and Quadrable but numeric in other lanes. Examples:
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14`, and
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:14`.
   - Evidence: `runnerStatus` is a string in Gitoxide, markerPDF, and
     Quadrable, an object in several lanes, and absent/null in Pandoc. Examples:
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:18`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:240`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:18`.
   - Evidence: PHP behavior counts are not normalized: markerPDF records
     `phpBehaviorTests: 300` at
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:437` while the same manifest
     maps only `192` upstream units at line 15; several other lanes only expose
     PHP counts through `lane-status.json`.
   - Audit judgment: normalize denominator, mapped, PHP pass/fail, and runner
     evidence fields before using percentages for portfolio decisions.

5. **Medium - high progress language still over-credits bounded, supplied, generated, or shell/oracle-backed evidence.**
   - Paths: `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json`, and
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json`.
   - Goal requirement at risk: `goal.md:30`, `goal.md:35`,
     `goal.md:37`, and `goal.md:40` prohibit crediting bridge/shell/generated
     artifacts as native implementation progress and require hard gaps to be
     explicit.
   - Evidence: Gitoxide and Difftastic still lack full Cargo runner parity,
     Pandoc has no full Haskell test runner, markerPDF's full benchmark runner
     is not executed and uses supplied/model-output boundary evidence, rclone
     excludes live provider/mount/FUSE/Docker coverage, Syncthing full
     `go test ./...` remains unrun, and Quadrable still leans heavily on
     oracle dump/load fixtures plus excludes heavy sync-fuzzer breadth from the
     normal fast suite.
   - Audit judgment: keep these as explicit blockers/future slices rather than
     treating them as near-complete native parity.

## Test Gate

I did not run `php tools/run-tests.php`.

Required duplicate-root gate before any possible root run:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
```

Initial active exact root sample:

```text
2661450 php tools/run-tests.php
```

The process exited before owner sampling could recover owner evidence. Later
exact gates returned no active root process, but I still did not start a root
run because the repository was not stable enough: active automation remained
present, tracked changes increased during the audit, and one manifest changed
while evidence was being gathered.

Final active exact root samples:

```text
2694170 php tools/run-tests.php
2695408 php tools/run-tests.php
```

Owner evidence:

```text
2694170 claude 2667629 00:17 Rs php tools/run-tests.php
2695408 claude 2695407 00:00 R php tools/run-tests.php
```

Validation commands run instead:

```text
jq empty lanes/*/UPSTREAM_TEST_MANIFEST.json
jq empty lanes/readability/UPSTREAM_TEST_MANIFEST.json
php -r 'json_decode(file_get_contents("lanes/readability/UPSTREAM_TEST_MANIFEST.json")); echo json_last_error_msg(), "\n";'
```

Results:

```text
Initial dirty Readability manifest sample: invalid JSON, Invalid escape at line 407, column 1231
Final dirty Readability manifest sample: jq-valid after an external working-tree repair
Other upstream manifests: jq-valid at the time checked
```

Dirty/process snapshot:

```text
pre-audit HEAD: 106f686490d8
latest tracked-only dirty sample: 141 tracked entries
current dirty Readability manifest: valid JSON but uncommitted
active automation/test/agent process count sample: 61
```

Recent history reviewed:

```text
106f6864 Refresh independent audit status
7a8809ea Record rclone lane implementation commit
fc1428e9 Advance rclone provider copy parity
d8c51049 Record quadrable implementation commit
316b477e Refresh independent audit status
8d345b5e Advance quadrable proof command parity
a1af8ce1 Record difftastic lane implementation commit
3e6be275 Advance difftastic TOML and highlight mapping
c90ef906 Record active root audit handoff
b92b6b8a Refresh independent audit status
```

## Next Intervention

Freeze active writers/status publishers and duplicate root loops first. Then
validate all manifests from the frozen tree, accept or reject the current dirty
Readability manifest repair with its lane owner, rerun the exact duplicate-root
gate, and capture one quiesced `php tools/run-tests.php` result from a single
accepted snapshot. Only after that, accept or reject dirty lane batches one lane
at a time, collapse root-test state to one repo-level record, normalize
manifest/status schemas, and regenerate `progress.md`, `porting.html`,
`porting-summary.json`, and lane statuses from that same snapshot.
