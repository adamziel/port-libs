# Support Follow-up Integration Hold - 2026-05-24T11:17:41Z

Scope: integration decision for the dependency-audit follow-up slice covering
`git-wire-protocol-core`, `quadrable-proof-transport-codec-core`, and
Difftastic-first ownership for `tree-sitter-grammar-subset`.

## Decision

Hold. No files were staged or committed.

The independent review artifact
`audits/essential-dependency-followup-review-20260524T110609Z.md` is present,
but its decision is not a pass. It reports one required tracker correction:
`quadrable-proof-transport-codec-core` needs explicit secret/credential
exclusions, preferably in both `scopeBoundary` and `testExpectation`, covering
secret-bearing transport captures, credential material, and secret-bearing
configs.

Because the review failed for a required tracker correction, the integration
rules forbid staging or committing the follow-up slice.

A second independent hold condition also occurred: two scoped owned-file state
polls were taken at least 20 seconds apart, and `progress.md` moved between
them (`501206` bytes / hash
`37c3f2305254ecb5244213c7197e9a500a50c983b1b3a2e239d3582f65305899` to
`501384` bytes / hash
`116ccb56bbca3831329b94703c87bb96cff7318c16b424599e2b8c661e45b838`).
This also forbids staging or committing under the integration rules.

## Reviewer Decision

- `git-wire-protocol-core`: accepted by review.
- `quadrable-proof-transport-codec-core`: failed pending required
  secret/credential exclusion correction.
- `tree-sitter-grammar-subset`: accepted by review as Difftastic-first with
  `neededBy` limited to `["difftastic"]`.

## Validation Status

Not fully run for commit eligibility because the reviewer decision and scoped
file movement are blocking. No staging validation was run.

Read-only validation completed:

- `jq empty dependency-backlog.json`
- duplicate dependency id check
- required dependency key check for `id`, `name`, `status`, `priority`,
  `neededBy`, `activationGate`, `scopeBoundary`, `source`,
  `essentialCapability`, `testExpectation`, `reuseNotes`, and `blocker`
- count/status/priority summary: 36 rows; `blocked: 1`, `candidate: 24`,
  `deferred: 11`; active `0`; priorities `critical: 4`, `high: 26`,
  `medium: 6`
- targeted checks for inactive high-priority candidate rows
  `git-wire-protocol-core` and `quadrable-proof-transport-codec-core`, and
  `tree-sitter-grammar-subset.neededBy == ["difftastic"]`
- `git diff --check -- dependency-backlog.json progress.md audits/support-library-essential-audit-followup-20260524T110535Z.md audits/essential-dependency-followup-review-20260524T110609Z.md`
- no-index whitespace checks for the untracked owned audit artifacts,
  including this hold artifact

## Unresolved Gate

Dashboard/publication artifacts remain intentionally untouched and stale until
a later serialized publication slice.
