# Agent Context

These files provide detailed context for AI agents working on the **Subscriber Manager Lite for Telegram** codebase. They are not deployed with the plugin.

## Files

| File | Purpose |
|------|---------|
| [ARCHITECTURE.md](ARCHITECTURE.md) | Full project structure, class roles, WordPress options, meta keys, hooks, constants |
| [CONVENTIONS.md](CONVENTIONS.md) | Naming conventions, code style, architectural patterns, file load order |
| [DATA-FLOW.md](DATA-FLOW.md) | Mermaid diagrams for all major flows: orders, webhooks, activation, join requests |
| [TESTING.md](TESTING.md) | Current test status, proposed stack, test plan, writing patterns |
| [E2E-TESTING.md](E2E-TESTING.md) | Semi-automated E2E testing playbook for staging site validation |
| [ROADMAP.md](ROADMAP.md) | Development roadmap with phases and completion status |

## Keeping These Files Updated

Update the relevant files when:

- A new class is added or an existing class changes responsibilities
- Data flows change (new hooks, different processing order)
- New hooks or filters are introduced
- Meta keys or WordPress options are added/changed
- Test infrastructure is set up or test coverage expands
- E2E testing procedures change

## Relationship to Pro Version

This is the **lite/free** version of the plugin. The pro version lives at `wctlgm-subscriber-manager` and has its own `agent-context/` folder. When syncing features between lite and pro, consult both sets of documentation. Key differences are documented in [ARCHITECTURE.md](ARCHITECTURE.md#lite-vs-pro-limitations).
