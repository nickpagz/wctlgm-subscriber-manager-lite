# Development Roadmap

> **Last updated:** 2026-02-25

Items are marked complete manually by the project owner.

---

## Phase 1: Agent Context & Testing Foundation (current)

- [x] Create agent-context documentation files
- [ ] Update CLAUDE.md to reference agent-context files
- [ ] Set up PHPUnit testing infrastructure (composer dev deps, bootstrap, stubs, base test case)
- [ ] Write initial unit tests for core classes (Subscriptions_Handler, Order_Handler, Bot_Interaction_Handler)

## Phase 2: Sync with Pro

- [x] Audit lite codebase against pro v1.7.0 changes
- [x] Port variable product support (per-variation channel settings, order processing, invite generation)
- [ ] Identify remaining bug fixes or improvements from pro that apply to lite
- [ ] Release

## Phase 3: WordPress.org Maintenance

- [ ] Update README.txt for latest WordPress/WooCommerce compatibility
- [ ] Update screenshots if UI has changed
- [ ] Address any WordPress.org plugin review feedback
- [ ] Release

## Phase 4: Testing Expansion

- [ ] Expand unit test coverage to Settings and API_Handler
- [ ] Set up E2E testing environment (staging site)
- [ ] Run first E2E test round
- [ ] Document results in E2E-TESTING.md

## Phase 5: Feature Consideration

- [ ] Evaluate adding simple product expiry to lite (currently pro-only)
- [ ] Evaluate multi-channel support in lite (currently pro-only)
- [ ] User feedback review and prioritization
