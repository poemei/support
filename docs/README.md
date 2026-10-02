# Support

The Support module provides public support-ticket submission and administrative ticket management for ChAoS MVC installations.

## Ticket Lifecycle

Tickets begin at:

    Status: OPEN
    Tier: T1

Supported statuses:

    OPEN
    IN_PROGRESS
    WAITING
    RESOLVED
    CLOSED

Supported tiers:

    T1
    T2
    T3
    DEV

Tier changes are retained in the ticket event history.

## Ticket IDs

Public ticket identifiers are six-character hexadecimal values.

Example:

    #563067

The identifier is generated independently of the internal database primary key.

## Replies

Administrative users can create public replies or internal notes.

Public replies are visible on the public ticket page.

Internal notes are visible only through the administrative interface.

## Data Ownership

The module owns:

    support_tickets
    support_replies
    support_events

Normal ticket CRUD is owned by the Support module.

Schema installation, migrations, module replacement, rollback, and Nuke remain Core lifecycle responsibilities.

## Mail

Core Mailer integration is intended for ticket creation, public replies, escalation, and ticket-resolution notifications.

The Support module does not implement its own SMTP transport.