# CMCP Orchestration Journal

## engine-20260912082143-delivering-cf8545

### Iteration 1 — reconnaissance and baseline

- Workspace: `D:\PhpstormProjects\www\Delivering`.
- Branch: `refactor/delivering-canonicalization-20260905`.
- Pre-existing worktree state: untracked `.gating/`; it is treated as pre-existing runner/gate material and is not removed or overwritten blindly.
- Read: repository `README.md`, `composer.json`, `phpunit.xml.dist`, source/test namespace inventory, and the mandatory Objecting, Cruding, Viewing, Interfacing, Gating, and Canonization contracts.
- Canonization rules consulted: Canon001, Canon008, Canon017, Canon018, Canon019, Canon020, Canon022, Canon023, Canon024, Canon026, Canon029, Canon039, and Canon040.
- Target mapping: `delivering/delivery` maps the component root to `App\\Delivering\\`, while Canon018 maps the subject token to `Delivery*`; the implementation predominantly uses `Delivering*`, so subject naming requires explicit remediation rather than acceptance as a local convention.
- Dependency contour: no foreign `App\\<Sibling>\\` runtime imports were found in tracked source. Canon022 applies only if standalone Symfony boot surfaces are present; this repository is documented and packaged as a reusable bundle.
- RC-critical workstream: executable canon/quality hardening and correction of deterministic hard failures without expanding provider/product scope.
- Growth workstream (post-RC): additional delivery channels/providers, richer operational UX, and provider analytics only after correctness/packaging gates are green.
- Material risks: broad `Delivering*` to `Delivery*` migration touches public PHP API, Messenger class names, DI references, tests, and downstream consumers; it must be atomic and verified. Existing `.gating/` may contain copied tooling not owned by this task.
- Gates to run: executable Gating checks when available, Composer validation, PHPUnit, PHPStan/PHP-CS-Fixer after their canonical execution contracts are present, and Git cleanliness/diff review.

### What do we have? What remains?

We have a verified local baseline, normative canon mapping, and a bounded RC workstream. Remaining work is executable gate evidence, material remediation, verification/fixes, Git integration, and final acceptance.

### Iteration 2 — material implementation

- Added the canonical PHPStan dependency/configuration and Composer scripts for static analysis, PHP-CS-Fixer checks/fixes, PHPUnit coverage, and aggregate quality execution.
- Extended PHPUnit configuration to declare `src/` as the production coverage population.
- Added `composer.prod.json` as a packaged production manifest with identity parity and no local path/symlink repositories.
- Updated `composer.lock` through scoped Composer updates only.

### Iteration 3 — verification and fix

- Initial PHPStan level 8 run exposed 24 findings.
- Added `phpstan/phpstan-doctrine` rather than suppressing Doctrine-managed entity properties.
- Fixed iterable PHPDoc typing, removed redundant `method_exists()` guards on the `ManagerRegistry` contract, and made telemetry-test mutation typing explicit.
- Re-ran gates: PHPStan PASS (0 errors), PHPUnit PASS (36 tests, 117 assertions, 2 skipped), PHP-CS-Fixer dry-run PASS (0 fixable files).

### Iteration 4 — debt closure and integration

- Composer validate PASS and tracked-PHP syntax lint PASS.
- Canon000/Canon018 still identify a repository-wide subject-prefix migration from `Delivering*` to `Delivery*`. This cannot be completed safely as a partial local rename because the affected public Messenger/API class names have cross-repository consumers, while this task authorizes writes only inside Delivering. Canon010 requires callers/config/docs to migrate atomically, so this tail is explicitly deferred to a separately authorized cross-repository migration rather than hidden behind aliases or partial renames.
- Pre-existing untracked `.gating/` remains untouched and excluded from this task's commit.

### Canon000/Canon018 subject-prefix closure

- Follow-up authorization executed the deferred `Delivering*` to `Delivery*` subject migration while preserving the component namespace `App\\Delivering\\` and framework bootstrap `DeliveringBundle`.
- Renamed PHP subject types, filenames, tests, DI references, Messenger routing references, and README code examples consistently; no compatibility aliases were introduced.
- Residual tracked `Delivering[A-Z]` references are limited to `DeliveringBundle` and references to that bundle, which is the framework-bootstrap exception.
- `composer quality` after the rename: PASS; PHP-CS-Fixer 0 files, PHPStan 0 errors, PHPUnit 36/36 with 117 assertions and 2 skipped tests.
- Host `App` consumers were found in phone verification and notification dispatch. They used a stale pre-canonical message namespace and were updated to `App\\Delivering\\Message\\Command\\Delivery\\DeliverySendSms` / `DeliverySendPush`; host deployment documentation was updated too.
- Host changed-PHP lint: PASS. Host Composer autoload refresh: PASS.
- Host full PHPUnit currently fails on unrelated pre-existing sibling debt in Facting, Cruding, Commissioning, and Vendoring; no reported failure references Delivering.
- Host Symfony command discovery is blocked before `lint:container` can run, so container acceptance is externally blocked at application bootstrap.
- Host repository carries pre-existing dirty `.gating/` changes; those remain untouched and must not be mixed into this migration.

## 2026-09-13 Delivering RC hardening

### Iteration 1 — reconnaissance and baseline

- Re-read Delivering documentation, manifests, source/test/config surfaces and the local dependency contour: Objecting, Cruding, Viewing, Interfacing, Gating, and Canonization.
- Normative Canonization material consulted for this pass: `Canon010ArchitectureMigrationCompletenessRule`, `Canon018ComposerIdentityMappingRule`, `Canon022StandaloneApplicationDependencyBaselineRule`, `Canon039PhpTestToolingRule`, `Canon040PhpTestCoverageRule`, and `GUARD_MATRIX.md`.
- Target-to-canon mapping: `delivering/delivery` remains `App\\Delivering\\` + `Delivery*`; generic lifecycle audit fields belong to Objecting; generic CRUD belongs to Cruding; presentation/shell concerns remain in Viewing/Interfacing; Delivering owns provider-neutral outbound delivery, provider adapters, receipts, retry/failure semantics, status and diagnostics.
- Market/enterprise baseline reviewed: at-least-once delivery/idempotency, failure transports/retry semantics, provider delivery-status diagnostics, device-token lifecycle, and webhook/event deduplication. RC-critical scope was separated from post-RC provider/channel/UI growth.
- Concrete RC work selected: repair mandatory dependency wiring, remove duplicated lifecycle ownership, make coverage execution reproducible, and close HIGH_TEST_DEBT through delivery-boundary tests.

### Iteration 2 — material implementation

- Declared the required `cruding/crud`, `interfacing/interface`, `objecting/object`, and `viewing/view` application dependencies in development and production manifests.
- Added development-only local `path` repositories with `symlink: true`; Collectioning and Tabling are exposed only as root Composer repository sources required by Cruding's transitive package graph.
- Added `minimum-stability: dev` with `prefer-stable: true` for the current SmartResponsor dev-branch package graph; production manifest remains free of local path repositories.
- Replaced Delivering's local `created_at` ownership in `DeliveryDelivery` with Objecting's canonical `ObjectAuditedInterface` + `ObjectAuditEmbeddableTrait` and initialized the canonical audit pack at creation.
- Scoped Composer update completed and locked the sibling package contour; Composer reported no security advisories.

### Iteration 3 — verification and fix

- `composer validate --strict --check-lock`: PASS.
- `composer quality`: PASS after dependency/Objecting integration.
- Canon039 execution defect found: PHPUnit 11.5 rejects `--branch-coverage`; changed the coverage contract to supported `--path-coverage` and made the script create `var/coverage` before writing the persistent text summary.
- First valid coverage evidence exposed Canon040 HIGH_TEST_DEBT: Methods 28.44%, Branches 54.37%, Lines 38.92%.

### Iteration 4 — debt closure and integration

- Added focused APNs/FCM provider validation tests without external network calls.
- Added operational contract tests for push routing, unavailable token resolution, queue health/status commands, push readiness, subscription invalidation, and structured telemetry.
- Added exhaustive message/receipt boundary validation, Messenger queue-count, push orchestration success/failure/idempotency, and Doctrine receipt persistence tests.
- Final quality suite: PASS — 61 tests, 184 assertions, 2 environment-dependent skips; PHPStan 0 errors; PHP-CS-Fixer 0 fixable files.
- Final Canon040 evidence: Methods 50.46% (55/109), Branches 67.01% (449/670), Lines 69.59% (627/901). `HIGH_TEST_DEBT` is cleared in all three dimensions. Canonical 80%/80%/70% targets remain bounded post-RC coverage debt, with branch coverage closest to target.

### Iteration 5 — final acceptance and handoff

- RC-critical dependency ownership, Objecting lifecycle ownership, reproducible coverage tooling, idempotency/receipt persistence, provider configuration boundaries, telemetry classifications, and operational status contracts are materially verified.
- Growth remains deliberately outside RC: additional providers/channels, richer delivery analytics/dashboard UX, and broader provider-path coverage toward Canon040 target thresholds.
- Final acceptance completed: the bounded RC change set was reviewed, committed with a signed Git commit, pushed to `origin/refactor/delivering-canonicalization-20260905`, and the post-push branch was clean with `ahead=0` / `behind=0`.

## 2026-09-13 Canon040 post-RC coverage closure

### Iteration 1 — baseline and targeting

- Started from merged `origin/master` on `test/delivering-canon040-coverage-20260913` after PR #5 landed.
- Baseline: Methods 50.46% (55/109), Branches 67.01% (449/670), Lines 69.59% (627/901).
- Selected branch-dense delivery boundaries and failure exits rather than expanding provider/channel scope.

### Iteration 2 — boundary coverage and correctness

- Expanded Telnyx receipt/conversation parser validation, Messenger telemetry lifecycle coverage, SMS orchestration failure/idempotency coverage, handler delegation, push-router selection, delivery identity, APNs/FCM validation, Telnyx sender/signature validation, and push-delivery failure recovery.
- Found and fixed a real webhook routing defect: a valid Telnyx AI notification was parsed successfully and then incorrectly forced through the receipt parser, causing `400 invalid_payload`. AI notifications now dispatch and return accepted before receipt parsing; a regression suite covers invalid signatures, malformed payloads, AI notifications, receipts, and ignored events.

### Iteration 3 — final bounded verification

- PHP lint for all changed PHP: PASS.
- Composer validation: PASS. PHPStan level 8: PASS with 0 errors. PHPUnit: PASS — 89 tests, 249 assertions, 2 environment-dependent skips. Coverage execution: PASS.
- Final evidence: Methods 66.06% (72/109), Branches 75.69% (520/687), Lines 78.60% (709/902).
- Canon040 branch target (>=70%) is exceeded. Lines are 13 covered lines short of 80%; methods remain below 80% because PHPUnit path coverage marks several combinatorial DTO/parser/provider methods incomplete despite 89–100% line/branch coverage in those classes.
- Remaining debt is bounded primarily to cryptographic/external-provider APNs/FCM success/OAuth flows and combinatorial path completion. Artificial path permutations are not added solely to inflate the method metric; future work should use deterministic provider fixtures/crypto-capable CI to close that debt.

## 2026-09-14 RC ingress and package-contract hardening

### Reconnaissance and baseline

- Workspace: `D:\PhpstormProjects\www\Delivering`; branch baseline `fix/delivering-webhook-coverage-20260913`, clean and synchronized with its upstream before changes.
- Re-read Delivering `README.md`, `composer.json`, `composer.prod.json`, PHPUnit/PHPStan configuration, prior CMCP journal, Telnyx webhook controller/parsers/signature verifier, focused webhook tests, and the mandatory Objecting, Cruding, Viewing, Interfacing, Gating, and Canonization reference contracts.
- Canonization textual rules consulted and mapped: Canon000/Canon018 (`delivering/delivery` => `App\\Delivering\\` + `Delivery*`), Canon001/Canon020 (role-first Symfony tree), Canon007 (literal PSR-4 identity), Canon008 (Composer/runtime dependency parity), Canon009 (standalone host boundary), Canon017 (runtime/docs parity), Canon019 (no alternate Domain/Port/Adapter taxonomy), and Canon021 (generic CRUD remains in Cruding). Root `AGENTS.md` additionally requires first-party sibling constraints and path-repository `options.versions` to pin `dev-master`.
- Dependency contour verified: Objecting, Cruding, Viewing, and Interfacing are real runtime dependencies; Collectioning and Tabling remain path-repository visibility for Cruding's reachable local dependency graph rather than direct Delivering runtime dependencies.
- Market/enterprise benchmark: mature webhook delivery systems emphasize signature verification, replay-window protection, idempotency, bounded retries/backoff, dead-letter/replay capability, and structured observability. Multi-provider portals, broad destination catalogs, and richer analytics remain growth scope rather than RC blockers.
- Baseline gates: `composer validate --strict --check-lock` PASS; `composer quality` PASS with PHPStan 0 errors and PHPUnit 89 tests / 249 assertions / 2 environment-dependent skips.
- RC-critical workstream selected: harden inbound Telnyx trust/shape boundary and package reproducibility. Concrete findings: unconditional Sodium API use was not declared as `ext-sodium`; sibling Composer constraints allowed `dev-main` contrary to local canon; local path repositories lacked canonical `dev-master` version pins; AI notification parsing accepted any signed JSON object without `data`, including payloads with no recognized lead field.
- Growth workstream: additional delivery channels/providers, customer-facing replay/analytics UX, broader destination support, and deterministic external-provider success fixtures remain post-RC.

### Material implementation

- Declared `ext-sodium` in development and production manifests so Ed25519 verification cannot become an undeclared runtime capability.
- Canonicalized first-party sibling requirements to exact `dev-master` and pinned every local first-party path repository through `options.versions`.
- Tightened the Telnyx AI notification parser so at least one recognized non-empty string lead field is required before creating and dispatching `DeliverySendSms`.
- Added endpoint/parser regression coverage and documented the Sodium runtime requirement.

### What do we have? What remains?

We have a material RC hardening patch grounded in current code, textual canon, dependency contracts, and webhook reliability practice. Remaining work is Composer lock reconciliation, complete quality/coverage verification, diff review, Git integration, push, and post-push cleanliness/upstream verification.

### Verification and integration readiness

- Scoped Composer reconciliation completed. The lock now resolves first-party path packages through canonical `dev-master`; the refresh also moved `doctrine/orm` from 3.7.0 to the compatible 3.7.1 patch release. Composer reported no security advisories.
- `composer validate --strict --check-lock`: PASS.
- `composer quality`: PASS — PHP-CS-Fixer 0 fixable files, PHPStan 0 errors, PHPUnit 90 tests / 250 assertions / 2 environment-dependent skips.
- `composer test:coverage`: PASS. Coverage evidence: Methods 66.06% (72/109), Branches 75.76% (522/689), Lines 78.70% (713/906). The Telnyx webhook controller remains 100% methods/paths/branches/lines; the AI notification parser remains 100% lines with 95.65% branch coverage.
- `composer audit`: PASS — no security vulnerability advisories found.
- Code Memory reconnaissance: Delivering has no repository-local `memory:scope:resolve` script; Console MCP resolves the active graph to the repository-local Delivering project with the workspace graph as read-only navigation. No tracked AsciiDoc documentation was found in this repository.
- Final diff review confirms all source mutations remain inside Delivering responsibility: package metadata/lock, repository documentation/journal, Telnyx ingress parser, and focused tests. No generic CRUD, Viewing, Interfacing, Objecting, Gating, Canonization, or Navigating implementation was modified.

### Что имеем? Что осталось?

RC-critical ingress/package hardening is verified. The original signed change set was pushed, then replayed onto a fresh branch from current `origin/master`; Git correctly skipped the already-merged prior coverage commit. PR #7 was closed unmerged because it repeated prior history; clean PR #8 contains only this bounded change set and is mergeable. No pull-request workflow runs are registered for the current head. Remaining authorized tail: merge PR #8 and verify final repository/upstream state.

## 2026-09-14 deterministic push-provider fixture closure

### Provider contract materialization

- Continued from current `origin/master` after ingress/package hardening was already merged; isolated this pass on `fix/delivering-provider-fixtures-20260914`.
- Added deterministic offline APNs and FCM HTTP contract coverage using Symfony `MockHttpClient` and fixed test-only EC/RSA PEM fixtures. No production credentials or external provider calls are used.
- APNs coverage now executes sandbox endpoint selection, provider message-id success, permanent invalid-recipient classification, and transient retry-delay handling.
- FCM coverage now executes OAuth token acquisition, access-token caching across sends, successful send response parsing, permanent invalid-recipient classification, transient send retry handling, and OAuth failure normalization.

### Production defect found and repaired

- Deterministic FCM execution exposed a service-account parsing defect: `DELIVERING_FCM_SERVICE_ACCOUNT_JSON` was normalized with `str_replace('\\n', "\n", ...)` before `json_decode`, which can corrupt otherwise valid JSON containing an escaped PEM private key.
- `DeliveryFcmPushProvider` now decodes and validates the JSON first, then normalizes escaped newlines only on the decoded `private_key` field before JWT signing.

### Verification

- `composer quality`: PASS — PHP-CS-Fixer 0 fixable files, PHPStan 0 errors, PHPUnit 95 tests / 267 assertions / 2 pre-existing environment-dependent skips.
- `composer test:coverage`: PASS.
- Coverage moved from Methods 66.06% / Branches 75.76% / Lines 78.70% to Methods 67.89% (74/109) / Branches 84.46% (614/727) / Lines 94.71% (859/907).
- Canon040 branch and line targets are now exceeded with material provider-path evidence. Remaining method-percentage debt is dominated by path-completeness accounting in highly combinatorial methods rather than uncovered production lines; no artificial path-permutation tests are added solely to inflate that metric.

## 2026-09-14 deterministic crypto and journal boundary closure

- Replaced environment-dependent JWT key generation in `DeliveryJwtSignerTest` with fixed test-only RSA/EC fixtures already representative of provider signing contracts; both RS256 and ES256 now execute deterministically on every run.
- Added public-contract tests for invalid RSA/EC private keys so signing configuration failures are normalized as `DeliveryPermanentTransportException` rather than OpenSSL-specific behavior.
- Added receipt journal filesystem-boundary coverage for an invalid parent path and hardened `DeliveryReceiptJournalRecorder` so directory-creation failures are converted to its documented `RuntimeException` without leaking a native PHP warning.
- `composer quality`: PASS — PHP-CS-Fixer 0 fixable files, PHPStan 0 errors, PHPUnit 98/98 tests, 277 assertions, 0 skips, 0 warnings.
- `composer test:coverage`: PASS. Final evidence: Methods 67.89% (74/109), Branches 84.87% (617/727), Lines 94.93% (861/907).
- The unchanged method percentage despite additional executed crypto and filesystem paths further confirms that the remaining Canon040 method debt is path-completeness accounting in combinatorial methods, not missing line execution. No reflection/private-method probing or synthetic permutation padding was introduced.

