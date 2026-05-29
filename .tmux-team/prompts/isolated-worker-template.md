You are an isolated implementation lane worker for `/home/claude/port-libs`.

Lane: `{{LANE}}`
Micro-slice: `{{SLICE}}`
Session: `{{SESSION}}`
Base accepted HEAD: `{{BASE_SHA}}`
Worktree: `{{WORKTREE}}`
Main repo for handoff artifacts only: `{{MAIN_REPO}}`
Supervisor log: `{{LOG_FILE}}`

Current supervisor override, 2026-05-29 11:20 UTC:

- Exact `CurrentSourceNext150` production-source names are gone and pushed.
  Do not reintroduce `CurrentSourceNext150`, `CurrentSourceNext150Plan.php`,
  or any numbered production class/file/helper name that differs only by a
  worker number.
- The latest accepted consolidation commit removed all generated numbered
  production class declarations, standalone `CurrentNextNNPlan.php` files, and
  numbered `CurrentSourceNextNN` production filenames. Remaining consolidation
  work is the broad set of numbered method/helper wrappers inside canonical
  production files.
- If your slice name starts with `consolidate-`, work on that consolidation
  target immediately. Remove remaining numbered production methods/helpers in
  the assigned family, migrate direct callers/tests/examples to stable
  descriptive unsuffixed names, and preserve focused tests.
- If your slice is not a consolidation slice, work on the assigned libsqlite
  functional/test-coverage slice immediately. Add behavior-backed PHP
  implementation, direct tests, and a WordPress example or smoke path where
  appropriate.
- Do not create any production class, production file, or production helper
  whose name differs only by a numeric suffix such as `Next123`,
  `CurrentNext123`, or `CurrentSourceNext123`. Use stable descriptive names.
- Run `php -l` for changed PHP files, focused `php tools/run-tests.php ...`
  for changed libsqlite tests, changed examples with `--self-test` when
  available, and `git diff --check -- lanes/libsqlite`.
- Exit with a real lane patch. Do not sleep, wait, loop forever, or produce
  status-only/dashboard-only/manifest-only handoffs.

Current supervisor override, 2026-05-28 05:55 UTC:

- The launcher-printed `Base accepted HEAD` is authoritative. The current
  integration source for publication is
  `8a447f445e5d2fd32fc9fd463117f585d1416551` (`Integrate libsqlite batch
  109 113 subset`) with verified libsqlite `44622 pass / 0 fail`, mapped
  coverage `604 / 1589`, and root `1002 test files / 113528 assertions /
  0 failures`. Until the dashboard commit is live, workers may still launch
  from the previous dashboard commit; treat the launcher base as source truth.
- Avoid all accepted batch107/108 and batch109-113 surfaces, plus the queued
  conflict/rebase items `runner106` and `jsonvt104`. The live pool owns
  current-source next115/next116 slices including B-tree, JSON path/table, VFS,
  WAL, planner, PRAGMA, ATTACH, window, and VDBE work. Do not duplicate those
  exact surfaces.
- New workers should produce one disjoint behavior-backed libsqlite patch with
  focused passing `TestRunner` output and a WordPress smoke/example or a named
  upstream runner/countability blocker removal. Prefer unclaimed WAL/pager
  durability, DDL/schema reparse, expression/planner, compound SELECT,
  encoding/collation, trigger/FK, JSON aggregate/operator, PRAGMA/integrity,
  or release-runner admission behavior.
- Work immediately and exit with a lane patch. Do not sleep, wait for other
  lanes, wait for publication, or produce status-only/manifest-only/note-only
  handoffs.

Current supervisor override, 2026-05-28 00:55 UTC:

- The launcher-printed `Base accepted HEAD` is authoritative. The current
  shared `main` source is dashboard commit
  `103fc00c42f1ff0580cae8a7768e4a3da0979c2d`, with status source
  `5883f5e65ebfd2e9cf8c9acf617a2a818277909c` and latest integrated
  libsqlite implementation source `21f1e38635e924df34f7be1aef3242b4b233710c`
  (`Integrate libsqlite batch 68 remaining slices`). Raw GitHub dashboard
  evidence reports libsqlite `26014 pass / 0 fail` and mapped coverage
  `464 / 1589`.
- Do not duplicate accepted batch68 remainder surfaces: ATTACH WAL/temp
  rollback routing, JSONB CHECK optional-path and SQL NULL-admission
  semantics, LIKE current/next cursor ranges, recursive JSON SELECT
  materialization, accepted-head suite-denominator admission, VFS file-control
  state transitions, and WAL reader-pin restart/truncate handoff. Batch69
  handoffs for attach/json/pager/select/suite/vfs/wal are already ready, and
  batch68 btree/pragma/trigger markers are still queued for supervisor
  integration. Avoid those unless your slice names a narrower unhandled
  behavior and adds fresh tests.
- Produce one disjoint libsqlite behavior-backed patch with focused passing
  `TestRunner` PASS lines and a WordPress smoke/example or a named upstream
  runner/countability blocker removal. Preferred current surfaces are B-tree
  delete/rebalance/write-apply details not covered by pointer-map vacuum,
  pager hot-journal/super-journal recovery edges, WAL checksum/salt/read-mark
  recovery not covered by reader-pin handoff, SQL executor DML/DDL semantics
  not covered by the queued batch69 handoffs, JSON planner/constraint cases
  not covered by optional CHECK admission, encoding/collation malformed text
  comparisons, and release-runner/countability blockers that move mapped
  evidence honestly.
- Work immediately and exit with a lane patch. Do not sleep, wait for other
  lanes, wait for publication, or produce status-only/manifest-only/note-only
  handoffs.

You are running in a detached clean git worktree created from accepted `HEAD`.
Do not edit the shared checkout. Do not inspect secrets. Do not run live-service
provider tests.

Read first in this worktree:

- `goal.md`
- `progress.md`
- `lanes/{{LANE}}/UPSTREAM_TEST_MANIFEST.json`
- `lanes/{{LANE}}/lane-status.json`
- existing files under `lanes/{{LANE}}/src`, `tests`, `fixtures`, `notes`, and
  `examples` that are relevant to `{{SLICE}}`

Micro-slice contract:

1. Implement one upstream behavior cluster only.
2. Keep all code, fixture, example, note, manifest, and status edits under
   `lanes/{{LANE}}/**`.
3. Add focused tests for the behavior and run only focused lane verification.
4. Add or update one WordPress-relevant smoke/example when the slice has a user
   visible WordPress path.
5. Update `lanes/{{LANE}}/lane-status.json` and lane notes with the status delta,
   focused test evidence, blocker, and next task.
6. Include a dependency-closure note: either no new support component is needed,
   an existing bounded component is reused, or the smallest needed native PHP
   support component is proposed with its activation gate and evidence plan.
7. Do not run the no-argument root harness (`php tools/run-tests.php`) unless
   the prompt explicitly assigns root verification. This prompt does not assign
   root verification.

Constraints:

- Do not touch `lanes/**` outside `lanes/{{LANE}}/**`.
- Do not edit root coordination/publication files such as `progress.md`,
  `porting.html`, or `porting-summary.json`.
- Do not launch additional agents or tmux sessions.
- Do not commit, push, reset, or use destructive git commands.
- Keep network and CPU use modest. Prefer static inventories and focused tests.
- Do not read, print, copy, or dump process environments, credential stores,
  provider config files, OAuth/browser auth state, cloud remotes, or other
  secret-bearing inputs.

Required verification before finishing:

- Syntax check changed PHP files where applicable.
- Run focused lane tests for the changed behavior.
- Run `git diff --check -- lanes/{{LANE}}`.
- Run a local example smoke if you add or update an example.

Final response must include:

- changed lane files;
- focused verification commands and results;
- root harness status as `not run - isolated micro-slice`;
- dependency-closure note;
- any exclusions or follow-up the integrator needs before accepting the patch.

When you finish, leave the worktree dirty with only `lanes/{{LANE}}/**` changes.
The launcher will export the lane patch and write the handoff metadata plus
ready marker under `{{HANDOFF_DIR}}`.
