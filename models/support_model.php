<?php

declare(strict_types=1);

/* [AI:GPT-5.6 Sol | 2026-09-07 18:42:00 UTC] */

/**
 * Support module data model.
 *
 * Owns normal CRUD operations for support tickets, replies,
 * and lifecycle events.
 */
final class support_model extends model
{
    /**
     * Module-owned tables required by Support.
     *
     * @var array<int, string>
     */
    private const TABLES = [
        'support_tickets',
        'support_replies',
        'support_events',
    ];

    /**
     * Return the current Support database state.
     *
     * @return string
     */
    public function databaseState(): string
    {
        foreach (self::TABLES as $table) {
            if (!$this->tableExists($table)) {
                return 'missing';
            }
        }

        return 'ready';
    }

    /**
     * Install the Support schema from the module-owned schema file.
     */
    public function installSchema(): void
    {
        $file = __DIR__ . '/../sql/schema.sql';

        if (!is_file($file)) {
            throw new RuntimeException('Support schema file was not found.');
        }

        $sql = file_get_contents($file);

        if ($sql === false || trim($sql) === '') {
            throw new RuntimeException('Support schema file is empty or unreadable.');
        }

        $statements = preg_split('/;\s*(?:\r?\n|$)/', $sql);

        if (!is_array($statements)) {
            throw new RuntimeException('Support schema could not be parsed.');
        }

        foreach ($statements as $statement) {
            $statement = trim($statement);

            if ($statement === '') {
                continue;
            }

            $this->query($statement);
        }
    }

    /**
     * Delete all Support-owned records while preserving schema.
     */
    public function deleteData(): void
    {
        $this->query('DELETE FROM `support_events`');
        $this->query('DELETE FROM `support_replies`');
        $this->query('DELETE FROM `support_tickets`');
    }

    /**
     * Create a new support ticket.
     *
     * @param array<string, string> $data Ticket data.
     *
     * @return string Public ticket identifier.
     */
    public function createTicket(array $data): string
    {
        $ticketId = $this->generateTicketId();

        $this->query(
            'INSERT INTO support_tickets
                (
                    ticket_id,
                    email,
                    name,
                    type,
                    subject,
                    description,
                    status,
                    tier
                )
             VALUES
                (
                    :ticket_id,
                    :email,
                    :name,
                    :type,
                    :subject,
                    :description,
                    :status,
                    :tier
                )',
            [
                ':ticket_id' => $ticketId,
                ':email' => $data['email'],
                ':name' => $data['name'],
                ':type' => $data['type'],
                ':subject' => $data['subject'],
                ':description' => $data['description'],
                ':status' => 'open',
                ':tier' => 't1',
            ]
        );

        $ticket = $this->findTicket($ticketId);

        if ($ticket !== null) {
            $this->addEvent(
                (int) $ticket['id'],
                'created',
                null,
                'open',
                $data['name'],
                'Support ticket created.'
            );
        }

        return $ticketId;
    }

    /**
     * Find a ticket by its public identifier.
     *
     * @param string $ticketId Public ticket identifier.
     *
     * @return array<string, mixed>|null
     */
    public function findTicket(string $ticketId): ?array
    {
        $ticket = $this->fetch(
            'SELECT *
             FROM support_tickets
             WHERE ticket_id = :ticket_id
             LIMIT 1',
            [
                ':ticket_id' => strtolower($ticketId),
            ]
        );

        return is_array($ticket) ? $ticket : null;
    }

    /**
     * Return all support tickets.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getTickets(): array
    {
        $tickets = $this->fetchAll(
            'SELECT *
             FROM support_tickets
             ORDER BY
                CASE status
                    WHEN \'open\' THEN 1
                    WHEN \'in_progress\' THEN 2
                    WHEN \'waiting\' THEN 3
                    WHEN \'resolved\' THEN 4
                    WHEN \'closed\' THEN 5
                    ELSE 6
                END,
                created_at DESC'
        );

        return is_array($tickets) ? $tickets : [];
    }

    /**
     * Return all replies and internal notes for a ticket.
     *
     * @param int $ticketId Internal ticket ID.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getReplies(int $ticketId): array
    {
        $replies = $this->fetchAll(
            'SELECT *
             FROM support_replies
             WHERE ticket_id = :ticket_id
             ORDER BY created_at ASC, id ASC',
            [
                ':ticket_id' => $ticketId,
            ]
        );

        return is_array($replies) ? $replies : [];
    }

    /**
     * Return only public replies for a ticket.
     *
     * @param int $ticketId Internal ticket ID.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getPublicReplies(int $ticketId): array
    {
        $replies = $this->fetchAll(
            'SELECT *
             FROM support_replies
             WHERE ticket_id = :ticket_id
               AND is_internal = 0
             ORDER BY created_at ASC, id ASC',
            [
                ':ticket_id' => $ticketId,
            ]
        );

        return is_array($replies) ? $replies : [];
    }

    /**
     * Return lifecycle events for a ticket.
     *
     * @param int $ticketId Internal ticket ID.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getEvents(int $ticketId): array
    {
        $events = $this->fetchAll(
            'SELECT *
             FROM support_events
             WHERE ticket_id = :ticket_id
             ORDER BY created_at ASC, id ASC',
            [
                ':ticket_id' => $ticketId,
            ]
        );

        return is_array($events) ? $events : [];
    }

    /**
     * Add a public reply or internal note.
     *
     * @param int    $ticketId Internal ticket ID.
     * @param string $author   Reply author.
     * @param string $message  Reply content.
     * @param bool   $internal Whether this is an internal note.
     */
    public function addReply(
        int $ticketId,
        string $author,
        string $message,
        bool $internal = false
    ): void {
        $this->query(
            'INSERT INTO support_replies
                (
                    ticket_id,
                    author,
                    message,
                    is_internal
                )
             VALUES
                (
                    :ticket_id,
                    :author,
                    :message,
                    :is_internal
                )',
            [
                ':ticket_id' => $ticketId,
                ':author' => $author,
                ':message' => $message,
                ':is_internal' => $internal ? 1 : 0,
            ]
        );

        $this->addEvent(
            $ticketId,
            $internal ? 'internal_note' : 'reply',
            null,
            null,
            $author,
            null
        );
    }

    /**
     * Change ticket status.
     *
     * @param array<string, mixed> $ticket Current ticket.
     * @param string               $status New status.
     * @param string               $actor  Actor making the change.
     */
    public function setStatus(
        array $ticket,
        string $status,
        string $actor
    ): void {
        $closedAt = $status === 'closed'
            ? date('Y-m-d H:i:s')
            : null;

        $this->query(
            'UPDATE support_tickets
             SET status = :status,
                 closed_at = :closed_at
             WHERE id = :id',
            [
                ':status' => $status,
                ':closed_at' => $closedAt,
                ':id' => (int) $ticket['id'],
            ]
        );

        $this->addEvent(
            (int) $ticket['id'],
            'status',
            (string) $ticket['status'],
            $status,
            $actor,
            null
        );
    }

    /**
     * Change ticket tier.
     *
     * @param array<string, mixed> $ticket Current ticket.
     * @param string               $tier   New tier.
     * @param string               $actor  Actor making the change.
     * @param string|null          $note   Optional escalation note.
     */
    public function setTier(
        array $ticket,
        string $tier,
        string $actor,
        ?string $note = null
    ): void {
        $this->query(
            'UPDATE support_tickets
             SET tier = :tier
             WHERE id = :id',
            [
                ':tier' => $tier,
                ':id' => (int) $ticket['id'],
            ]
        );

        $this->addEvent(
            (int) $ticket['id'],
            'tier',
            (string) $ticket['tier'],
            $tier,
            $actor,
            $note
        );
    }

    /**
     * Assign or unassign a ticket.
     *
     * @param array<string, mixed> $ticket   Current ticket.
     * @param string|null          $assignee New assignee.
     * @param string               $actor    Actor making the change.
     */
    public function assignTicket(
        array $ticket,
        ?string $assignee,
        string $actor
    ): void {
        $this->query(
            'UPDATE support_tickets
             SET assigned_to = :assigned_to
             WHERE id = :id',
            [
                ':assigned_to' => $assignee,
                ':id' => (int) $ticket['id'],
            ]
        );

        $this->addEvent(
            (int) $ticket['id'],
            'assignment',
            isset($ticket['assigned_to'])
                ? (string) $ticket['assigned_to']
                : null,
            $assignee,
            $actor,
            null
        );
    }

    /**
     * Add a lifecycle event.
     *
     * @param int         $ticketId  Internal ticket ID.
     * @param string      $eventType Event classification.
     * @param string|null $fromValue Previous value.
     * @param string|null $toValue   New value.
     * @param string|null $actor     Actor responsible.
     * @param string|null $note      Optional event note.
     */
    private function addEvent(
        int $ticketId,
        string $eventType,
        ?string $fromValue,
        ?string $toValue,
        ?string $actor,
        ?string $note
    ): void {
        $this->query(
            'INSERT INTO support_events
                (
                    ticket_id,
                    event_type,
                    from_value,
                    to_value,
                    actor,
                    note
                )
             VALUES
                (
                    :ticket_id,
                    :event_type,
                    :from_value,
                    :to_value,
                    :actor,
                    :note
                )',
            [
                ':ticket_id' => $ticketId,
                ':event_type' => $eventType,
                ':from_value' => $fromValue,
                ':to_value' => $toValue,
                ':actor' => $actor,
                ':note' => $note,
            ]
        );
    }

    /**
     * Generate an unused six-character hexadecimal ticket identifier.
     */
    /**
     * Determine whether a module-owned table exists.
     *
     * @param string $table Table name.
     *
     * @return bool
     */
    private function tableExists(string $table): bool
    {
        return (bool) $this->fetch(
            'SELECT 1 FROM information_schema.tables WHERE table_schema = :schema AND table_name = :table_name LIMIT 1',
            ['schema' => DB_NAME, 'table_name' => $table]
        );
    }

    private function generateTicketId(): string
    {
        do {
            $ticketId = bin2hex(random_bytes(3));
        } while ($this->findTicket($ticketId) !== null);

        return $ticketId;
    }
}

/* [End AI:GPT-5.6 Sol] */