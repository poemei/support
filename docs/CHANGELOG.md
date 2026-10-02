# Changelog

## 1.0.3 — 2026-10-02

### Added
- Support for Apache2 along with LightSpeed HTTPD

## 1.0.2 — 2026-09-07

### Added

- Core Mailer integration using the established ChAoS `mailer` service.
- New-ticket notification to the Support team.
- New-ticket confirmation to the submitter.
- Submitter notification for public administrative replies.
- Support-team notification when a ticket changes support tier.
- Submitter notification when a ticket is resolved or closed.
- Direct links to public and administrative ticket views in notifications.

### Changed

- Mail delivery failures are nonfatal and do not interrupt ticket persistence or administrative actions.
- Internal administrative notes do not generate submitter email.

## 1.0.0 — 2026-09-07

### Added

- Public support ticket submission.
- Six-character hexadecimal ticket identifiers.
- Public ticket status and reply view.
- Administrative support queue.
- Ticket types for bugs, installation issues, modules, themes, accounts, and general support.
- T1, T2, T3, and DEV support tiers.
- Ticket assignment.
- Tier escalation and de-escalation.
- OPEN, IN_PROGRESS, WAITING, RESOLVED, and CLOSED lifecycle states.
- Public administrative replies.
- Internal administrative notes.
- Persistent ticket lifecycle event history.
- Module-owned support ticket, reply, and event tables.

### Changed

- Replaced the generated administrative scaffold with the Support management interface.
- Removed the module-owned Nuke control; destructive module lifecycle remains Core-owned.

### Pending

- Core Mailer integration for ticket creation, replies, escalation, and resolution notifications.