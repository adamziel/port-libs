# pandoc-opc-xml-relationships-core-current-base-20260609T071750Z

Base accepted HEAD: `606e24ec818a38feb2a796c2f2b7d182ce531afd`.

Implemented a bounded OPC XML signature digest-policy summary in `OpcRelationshipGraph`.
The new `digitalSignatureDigestPolicySummary()` aggregates SignedInfo and Object Manifest
reference digest metadata, preserving raw reference validity while surfacing importer
policy issues for unknown digest algorithms and decoded digest-value length mismatches.

Focused test movement:

- Baseline: `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  passed with `1 test files, 3522 assertions, 0 failures`.
- Final: `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  passed with `1 test files, 3569 assertions, 0 failures`.
- Delta: `+1` focused PHP PASS case and `+47` focused assertions.
- Example smoke: `php lanes/pandoc/examples/wordpress-docx-opc-preflight.php --self-test`
  passed with `opc docx preflight self-test ok`.

Dependency closure:

- No new native support component is needed. This reuses the existing OPC package
  graph, DOM-backed XML signature metadata parser, digest algorithm profile table,
  and WordPress DOCX OPC preflight example.
- Full XML canonicalization, cryptographic digest recomputation, certificate trust
  validation, and external signature validators remain out of scope for this lane.

Non-overlap:

- Avoids the accepted relationship-role policy, package part relationship coverage,
  source closure coverage, package consistency, signature relationship transform,
  and enveloped-signature transform slices.
- This slice focuses on digest-method policy review, matching the prior OPC follow-up
  recommendation without changing lower-level raw digest metadata compatibility.

Suggested follow-up:

- Signed relationship type allowlists, relationship part canonicalization edge cases,
  or higher-level DOCX import-gate wiring for this digest-policy summary.
