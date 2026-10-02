<?php

declare(strict_types=1);

/* [AI:GPT-5.6 Sol | 2026-09-07 19:55:00 UTC] */

/**
 * Support module controller.
 *
 * Provides public support ticket submission and lookup together with
 * protected administrative ticket and lifecycle management.
 */
final class support extends controller
{
    /**
     * Administrative actions explicitly owned by this module.
     */
    private const ADMIN_ACTIONS = [
        'install_sql',
        'status',
        'tier',
        'assign',
        'reply',
        'delete_data',
    ];

    /**
     * Public Support entry point.
     *
     * @param array<int, string> $params Route parameters.
     */
    public function index(array $params = []): void
    {
        /** @var support_model $model */
        $model = $this->model('support_model');

        if ($model->databaseState() !== 'ready') {
            http_response_code(503);

            $data = [
                'available' => false,
                'error' => null,
            ];

            $this->view('index', $data);

            return;
        }

        $data = [
            'available' => true,
            'error' => null,
        ];

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $this->require_csrf();

            $name = trim((string) ($_POST['name'] ?? ''));
            $email = trim((string) ($_POST['email'] ?? ''));
            $type = strtolower(
                trim((string) ($_POST['type'] ?? 'general'))
            );
            $subject = trim((string) ($_POST['subject'] ?? ''));
            $description = trim(
                (string) ($_POST['description'] ?? '')
            );

            $allowedTypes = [
                'bug',
                'installation',
                'module',
                'theme',
                'account',
                'general',
            ];

            if (!in_array($type, $allowedTypes, true)) {
                $type = 'general';
            }

            if (
                $name === ''
                || $subject === ''
                || $description === ''
                || filter_var($email, FILTER_VALIDATE_EMAIL) === false
            ) {
                $data['error'] = 'Please complete all required fields.';
            } else {
                $ticketId = $model->createTicket(
                    [
                        'name' => $name,
                        'email' => $email,
                        'type' => $type,
                        'subject' => $subject,
                        'description' => $description,
                    ]
                );

                $ticket = $model->findTicket($ticketId);

                if ($ticket !== null) {
                    $this->sendNewTicketNotifications($ticket);
                }

                header(
                    'Location: /support/ticket/'
                    . rawurlencode($ticketId)
                );
                exit;
            }
        }

        $this->view('index', $data);
    }

    /**
     * Display a public support ticket.
     *
     * @param array<int, string> $params Route parameters.
     */
    public function ticket(array $params = []): void
    {
        /** @var support_model $model */
        $model = $this->model('support_model');

        if ($model->databaseState() !== 'ready') {
            http_response_code(503);

            $data = [
                'available' => false,
                'ticket' => null,
                'replies' => [],
            ];

            $this->view('ticket', $data);

            return;
        }

        $ticketId = strtolower(
            trim((string) ($params[0] ?? ''))
        );

        if (!preg_match('/^[a-f0-9]{6}$/', $ticketId)) {
            http_response_code(404);

            $data = [
                'available' => true,
                'ticket' => null,
                'replies' => [],
            ];

            $this->view('ticket', $data);

            return;
        }

        $ticket = $model->findTicket($ticketId);

        if ($ticket === null) {
            http_response_code(404);

            $data = [
                'available' => true,
                'ticket' => null,
                'replies' => [],
            ];

            $this->view('ticket', $data);

            return;
        }

        $data = [
            'available' => true,
            'ticket' => $ticket,
            'replies' => $model->getPublicReplies(
                (int) $ticket['id']
            ),
        ];

        $this->view('ticket', $data);
    }

    /**
     * Administrative Support entry point.
     *
     * @param array<int, string> $params Administrative route parameters.
     */
    public function admin(array $params = []): void
    {
        $this->require_admin(7);

        /** @var support_model $model */
        $model = $this->model('support_model');

        $state = $model->databaseState();

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $this->require_csrf();

            $action = trim(
                (string) ($_POST['action'] ?? '')
            );

            if (!in_array($action, self::ADMIN_ACTIONS, true)) {
                http_response_code(400);
                $this->error_page(
                    'Invalid Support administrative action.'
                );
            }

            if ($action === 'install_sql') {
                if ($state !== 'missing') {
                    http_response_code(409);
                    $this->error_page(
                        'The Support schema is already installed.'
                    );
                }

                $model->installSchema();

                if ($model->databaseState() !== 'ready') {
                    throw new RuntimeException(
                        'Support schema installation did not complete.'
                    );
                }

                header('Location: /admin/support?installed=1');
                exit;
            }

            if ($state !== 'ready') {
                http_response_code(409);
                $this->error_page(
                    'The Support database is not available.'
                );
            }

            if ($action === 'delete_data') {
                $model->deleteData();

                header('Location: /admin/support?deleted=1');
                exit;
            }

            $ticketId = strtolower(
                trim((string) ($_POST['ticket_id'] ?? ''))
            );

            if (!preg_match('/^[a-f0-9]{6}$/', $ticketId)) {
                http_response_code(400);
                $this->error_page(
                    'Invalid Support ticket identifier.'
                );
            }

            $ticket = $model->findTicket($ticketId);

            if ($ticket === null) {
                http_response_code(404);
                $this->error_page(
                    'The requested Support ticket was not found.'
                );
            }

            $this->handleTicketAction(
                $model,
                $ticket,
                $action
            );

            header(
                'Location: /admin/support/'
                . rawurlencode($ticketId)
            );
            exit;
        }

        if ($state !== 'ready') {
            $data = [
                'database_state' => $state,
                'tickets' => [],
            ];

            $this->view('admin/index', $data);

            return;
        }

        $ticketId = strtolower(
            trim((string) ($params[1] ?? ''))
        );

        if ($ticketId === '') {
            $data = [
                'database_state' => $state,
                'tickets' => $model->getTickets(),
            ];

            $this->view('admin/index', $data);

            return;
        }

        if (!preg_match('/^[a-f0-9]{6}$/', $ticketId)) {
            http_response_code(404);

            $data = [
                'database_state' => $state,
                'ticket' => null,
                'replies' => [],
                'events' => [],
            ];

            $this->view('admin/ticket', $data);

            return;
        }

        $ticket = $model->findTicket($ticketId);

        if ($ticket === null) {
            http_response_code(404);

            $data = [
                'database_state' => $state,
                'ticket' => null,
                'replies' => [],
                'events' => [],
            ];

            $this->view('admin/ticket', $data);

            return;
        }

        $data = [
            'database_state' => $state,
            'ticket' => $ticket,
            'replies' => $model->getReplies(
                (int) $ticket['id']
            ),
            'events' => $model->getEvents(
                (int) $ticket['id']
            ),
        ];

        $this->view('admin/ticket', $data);
    }

    /**
     * Handle an explicitly allowed ticket administration action.
     *
     * @param support_model        $model  Support model.
     * @param array<string, mixed> $ticket Current ticket.
     * @param string               $action Requested action.
     */
    private function handleTicketAction(
        support_model $model,
        array $ticket,
        string $action
    ): void {
        $actor = $this->adminActor();

        switch ($action) {
            case 'status':
                $status = strtolower(
                    trim((string) ($_POST['status'] ?? ''))
                );

                $allowedStatuses = [
                    'open',
                    'in_progress',
                    'waiting',
                    'resolved',
                    'closed',
                ];

                if (!in_array($status, $allowedStatuses, true)) {
                    http_response_code(400);
                    $this->error_page(
                        'Invalid Support ticket status.'
                    );
                }

                $model->setStatus(
                    $ticket,
                    $status,
                    $actor
                );

                if (in_array($status, ['resolved', 'closed'], true)) {
                    $ticket['status'] = $status;
                    $this->sendStatusNotification($ticket);
                }
                break;

            case 'tier':
                $tier = strtolower(
                    trim((string) ($_POST['tier'] ?? ''))
                );

                $allowedTiers = [
                    't1',
                    't2',
                    't3',
                    'dev',
                ];

                if (!in_array($tier, $allowedTiers, true)) {
                    http_response_code(400);
                    $this->error_page(
                        'Invalid Support tier.'
                    );
                }

                $note = trim(
                    (string) ($_POST['note'] ?? '')
                );

                $model->setTier(
                    $ticket,
                    $tier,
                    $actor,
                    $note !== '' ? $note : null
                );

                if ($tier !== strtolower((string) ($ticket['tier'] ?? ''))) {
                    $ticket['tier'] = $tier;
                    $this->sendTierNotification(
                        $ticket,
                        $actor,
                        $note !== '' ? $note : null
                    );
                }
                break;

            case 'assign':
                $assignee = trim(
                    (string) ($_POST['assigned_to'] ?? '')
                );

                $model->assignTicket(
                    $ticket,
                    $assignee !== '' ? $assignee : null,
                    $actor
                );
                break;

            case 'reply':
                $message = trim(
                    (string) ($_POST['message'] ?? '')
                );

                if ($message === '') {
                    http_response_code(400);
                    $this->error_page(
                        'A Support reply cannot be empty.'
                    );
                }

                $isInternal = isset($_POST['is_internal']);

                $model->addReply(
                    (int) $ticket['id'],
                    $actor,
                    $message,
                    $isInternal
                );

                if (!$isInternal) {
                    $this->sendReplyNotification(
                        $ticket,
                        $actor,
                        $message
                    );
                }
                break;

            default:
                http_response_code(400);
                $this->error_page(
                    'Invalid Support ticket action.'
                );
        }
    }


    /**
     * Notify the Support team and submitter about a newly created ticket.
     *
     * Mail delivery is deliberately nonfatal. Ticket creation remains
     * successful if the configured mail transport is unavailable.
     *
     * @param array<string, mixed> $ticket Ticket record.
     */
    private function sendNewTicketNotifications(array $ticket): void
    {
        $ticketId = (string) $ticket['ticket_id'];
        $subject = (string) $ticket['subject'];
        $name = (string) $ticket['name'];
        $email = (string) $ticket['email'];
        $type = strtoupper((string) $ticket['type']);
        $description = nl2br(
            htmlspecialchars(
                (string) $ticket['description'],
                ENT_QUOTES,
                'UTF-8'
            )
        );

        $adminUrl = URLROOT . '/admin/support/' . rawurlencode($ticketId);
        $ticketUrl = URLROOT . '/support/ticket/' . rawurlencode($ticketId);

        $this->sendMail(
            'support@stn-chain.org',
            'Support Team',
            '[STNC Chain Support #' . $ticketId . '] ' . $subject,
            "
                <div style='font-family: sans-serif; padding: 20px; color: #333;'>
                    <h2>New Support Ticket #{$ticketId}</h2>
                    <p><strong>From:</strong> " . $this->escapeHtml($name) . " (" . $this->escapeHtml($email) . ")</p>
                    <p><strong>Type:</strong> {$type}</p>
                    <p><strong>Subject:</strong> " . $this->escapeHtml($subject) . "</p>
                    <hr>
                    <div>{$description}</div>
                    <p><a href='{$adminUrl}'>Open ticket in Admin</a></p>
                </div>"
        );

        $this->sendMail(
            $email,
            $name,
            '[ChAoS Support #' . $ticketId . '] Ticket received',
            "
                <div style='font-family: sans-serif; padding: 20px; color: #333;'>
                    <p>Hello " . $this->escapeHtml($name) . ",</p>
                    <p>Your Support ticket <strong>#{$ticketId}</strong> has been received.</p>
                    <p><strong>Subject:</strong> " . $this->escapeHtml($subject) . "</p>
                    <p><a href='{$ticketUrl}'>View your Support ticket</a></p>
                </div>"
        );
    }

    /**
     * Notify the submitter when a public administrative reply is posted.
     *
     * @param array<string, mixed> $ticket  Ticket record.
     * @param string               $actor   Reply author.
     * @param string               $message Public reply.
     */
    private function sendReplyNotification(
        array $ticket,
        string $actor,
        string $message
    ): void {
        $ticketId = (string) $ticket['ticket_id'];
        $ticketUrl = URLROOT . '/support/ticket/' . rawurlencode($ticketId);
        $reply = nl2br($this->escapeHtml($message));

        $this->sendMail(
            (string) $ticket['email'],
            (string) $ticket['name'],
            '[STNC Chain Support #' . $ticketId . '] Support response',
            "
                <div style='font-family: sans-serif; padding: 20px; color: #333;'>
                    <p>Hello " . $this->escapeHtml((string) $ticket['name']) . ",</p>
                    <p>" . $this->escapeHtml($actor) . " responded to your Support ticket.</p>
                    <div style='background: #f9f9f9; padding: 15px; border-left: 4px solid #0056b3;'>{$reply}</div>
                    <p><a href='{$ticketUrl}'>View ticket #{$ticketId}</a></p>
                </div>"
        );
    }

    /**
     * Notify the Support team when a ticket changes support tier.
     *
     * @param array<string, mixed> $ticket Ticket record.
     * @param string               $actor  Administrative actor.
     * @param string|null          $note   Optional escalation note.
     */
    private function sendTierNotification(
        array $ticket,
        string $actor,
        ?string $note
    ): void {
        $ticketId = (string) $ticket['ticket_id'];
        $tier = strtoupper((string) $ticket['tier']);
        $adminUrl = URLROOT . '/admin/support/' . rawurlencode($ticketId);
        $noteHtml = $note !== null
            ? '<p><strong>Note:</strong> ' . $this->escapeHtml($note) . '</p>'
            : '';

        $this->sendMail(
            'support@dtn-chain.org',
            'Support Team',
            '[STN Chain Support #' . $ticketId . '] Tier changed to ' . $tier,
            "
                <div style='font-family: sans-serif; padding: 20px; color: #333;'>
                    <p>Ticket <strong>#{$ticketId}</strong> was moved to <strong>{$tier}</strong> by " . $this->escapeHtml($actor) . ".</p>
                    {$noteHtml}
                    <p><a href='{$adminUrl}'>Open ticket in Admin</a></p>
                </div>"
        );
    }

    /**
     * Notify the submitter when a ticket is resolved or closed.
     *
     * @param array<string, mixed> $ticket Ticket record.
     */
    private function sendStatusNotification(array $ticket): void
    {
        $ticketId = (string) $ticket['ticket_id'];
        $status = strtoupper(
            str_replace('_', ' ', (string) $ticket['status'])
        );
        $ticketUrl = URLROOT . '/support/ticket/' . rawurlencode($ticketId);

        $this->sendMail(
            (string) $ticket['email'],
            (string) $ticket['name'],
            '[ChAoS Support #' . $ticketId . '] ' . $status,
            "
                <div style='font-family: sans-serif; padding: 20px; color: #333;'>
                    <p>Hello " . $this->escapeHtml((string) $ticket['name']) . ",</p>
                    <p>Your Support ticket <strong>#{$ticketId}</strong> is now <strong>{$status}</strong>.</p>
                    <p><a href='{$ticketUrl}'>View your Support ticket</a></p>
                </div>"
        );
    }

    /**
     * Send a Support email through the ChAoS Core Mailer.
     *
     * Mail failures do not alter ticket state or interrupt the request.
     */
    private function sendMail(
        string $address,
        string $name,
        string $subject,
        string $body
    ): void {
        try {
            $mailObj = new mailer();
            $mail = $mailObj->create();
            $mail->addAddress($address, $name);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $body;
            $mail->send();
        } catch (Throwable $e) {
            // Support workflow remains valid when mail delivery fails.
        }
    }

    /**
     * Escape untrusted content before inserting it into an HTML email.
     */
    private function escapeHtml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Resolve a human-readable administrative actor.
     */
    private function adminActor(): string
    {
        $displayName = trim(
            (string) ($_SESSION['display_name'] ?? '')
        );

        if ($displayName !== '') {
            return $displayName;
        }

        $username = trim(
            (string) ($_SESSION['username'] ?? '')
        );

        if ($username !== '') {
            return $username;
        }

        return 'Admin';
    }
}

/* [End AI:GPT-5.6 Sol] */