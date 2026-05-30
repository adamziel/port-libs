# Supervisor Goal: Port-Libs Throughput

## Outcome
- Keep the tmux worker team moving on native PHP ports while integrating only clean, verified slices.
- Keep `progress.md`, audits, and the public GitHub Pages dashboard current from committed evidence.

## Intensity
- Level: max.
- Starting workers: 10-20 active sessions, resource-gated by CPU, RAM, disk, and independent work availability.
- Scaling rule: add workers only for bounded slices with clear artifacts; remove or redirect workers that produce broad dirty state without integration-ready outputs.

## Non-Goals
- No lane implementation in the supervisor session.
- No live-service provider tests unless bounded, opt-in, and documented.
- No secret inspection, printing, or copying.
- No wrappers as final port deliverables.

## Worker Topology
- supervisor: manage queue, liveness, resource use, integration pressure, and anti-drift.
- lane workers: implement lane-local native PHP slices and leave focused evidence. Normal watchdog restarts run as isolated worktree workers by default; use `WATCHDOG_USE_ISOLATED_LANE_WORKERS=0` only for an explicit shared-checkout compatibility fallback.
- preflight/isolation workers: extract broad dirty handoffs into clean worktree patches and audits.
- lane-group integrators: pre-review independent ready markers by disjoint lane group, run only focused checks, and queue acceptable candidates for the serialized root/commit gate.
- integrator: accept or reject one isolated slice at a time after stability checks and verification; this remains the fallback and the only root/commit authority.
- evaluator: every 20 minutes, give candid feedback and status-page guidance.
- dashboard updater: publish verified committed status snapshots to GitHub Pages.

## Workflow
1. Lane workers produce bounded feature slices.
2. Preflight workers reject broad handoffs or nominate exact sub-slices.
3. Isolation workers rebuild nominated sub-slices in clean detached worktrees.
4. Lane-group integrators may pre-review owned ready markers in parallel and queue small candidates; they do not run duplicate root harnesses or commit accepted progress.
5. Integrator accepts only small clean patches with focused evidence, then root verification when safe.
6. Dashboard updater publishes verified committed status.

## Quality Gates
- Focused lane tests must run clean for any accepted slice.
- `git diff --check` must pass on touched files.
- Root `php tools/run-tests.php` is serialized and run only from a stable snapshot.
- Support-library progress must have explicit denominators, evidence, and non-goals.

## Rejected Distractions
- Broad dirty lane dumps presented as integration-ready.
- Missing dependency/tooling claims without a bounded install attempt when install is reasonable.
- Provider/live-service tests that require credentials or unconstrained network state.
- Status pages that hide dependency gates or imply richer coverage than committed evidence supports.

## Libsqlite Generic API Rule 2026-05-30T13:20Z
- User priority update: the libsqlite port must have zero WordPress-specific
  classes, interfaces, traits, functions, or methods. Do not accept libsqlite
  patches that add declarations whose names contain `WordPress`, `wordpress`,
  `WP`, `Wp`, or `wp_`.
- New libsqlite handoffs should use generic application scenario names, not
  WordPress-specific smokes/examples. Existing fixture table strings are test
  data only and are not permission to expose WordPress-named APIs.
- Required gate for source-moving libsqlite batches: run the committed
  `SQLiteNoWordPressSpecificApiTest.php` guard or the equivalent declaration
  `rg` audit, plus focused slice tests and `git diff --check`.

## Final Acceptance Criteria
- Each lane has native PHP behavior mapped to upstream evidence, generic application scenarios, and status metadata.
- Required support libraries are tracked at bounded native component granularity.
- Public dashboard is current and evidence-based.
- No unreviewed broad dirty handoffs are treated as accepted progress.

## Libsqlite Numbered Helper Consolidation Rule 2026-05-29T10:08Z
- User priority update: remove numbered suffixes such as
  `CurrentSourceNext150Plan.php` from consolidated libsqlite source helpers
  and merge any remaining duplicate numbered helper families into stable
  unnumbered classes.
- Refills must not assign work that creates or extends a new numbered
  duplicate source helper. Workers may keep numbered test/example range names,
  but shared source helpers should have stable names.
- Initial grouped inventory from `1a570158f`: VFS has 55 numbered helpers,
  PRAGMA/FK 33, B-tree vacuum/freeblock 32, pager reader-cache 29, rowvalue
  savepoint 11, WAL hot-journal/savepoint/checkpoint 9, planner STAT4 6, and
  smaller pairs/triples including B-tree `CurrentSourceNext93/150Plan`.
- One visible lane is active on the B-tree `CurrentSourceNext93/150Plan`
  duplicate group. As range workers finish, refill windows with non-overlapping
  consolidation lanes until these suffix groups are gone.
