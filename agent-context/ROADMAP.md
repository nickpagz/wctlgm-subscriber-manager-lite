# Development Roadmap

> **Last updated:** 2026-03-02

Items are marked complete manually by the project owner.

---

## Phase 1: Agent Context & Testing Foundation

- [x] Create agent-context documentation files
- [x] Update CLAUDE.md to reference agent-context files
- [x] Set up PHPUnit testing infrastructure (composer dev deps, bootstrap, stubs, base test case)
- [x] Write initial unit tests for core classes (Subscriptions_Handler, Order_Handler, Bot_Interaction_Handler)

## Phase 2: Sync with Pro

- [x] Audit lite codebase against pro v1.7.0 changes
- [x] Port variable product support (per-variation channel settings, order processing, invite generation)
- [x] Identify remaining bug fixes or improvements from pro that apply to lite
- [x] Release v1.7.0

## Phase 3: Subscriber Table (v2.0.0)

- [x] Port subscriber table feature from pro plugin (Database, Users_List_Table, CSS, JS)
- [x] Add API methods (getChatMember, banChatMember, unbanChatMember)
- [x] Add settings page tabs (Settings, Subscribers) with modal and AJAX handlers
- [x] Add DB tracking to Bot_Interaction_Handler (join requests, chat_member updates, activation)
- [x] Add DB record creation to Order_Handler and Subscriptions_Handler
- [x] Add data migration from existing order meta on first install
- [x] Add sync functionality to populate user profile details (name, username) from Telegram
- [x] Update agent-context docs and CLAUDE.md
- [ ] E2E testing
- [ ] Release v2.0.0

## Phase 4: WordPress.org Maintenance

- [ ] Update README.txt for latest WordPress/WooCommerce compatibility
- [ ] Update screenshots for new subscriber table UI
- [ ] Address any WordPress.org plugin review feedback
- [ ] Release

## Phase 5: Testing Expansion

- [ ] Expand unit test coverage to Settings, API_Handler, and Database
- [ ] Set up E2E testing environment (staging site)
- [ ] Run first E2E test round
- [ ] Document results in E2E-TESTING.md

## Phase 6: Feature Consideration

- [ ] Evaluate adding simple product expiry to lite (currently pro-only)
- [ ] Evaluate multi-channel support in lite (currently pro-only)
- [ ] User feedback review and prioritization
