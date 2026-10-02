<?php

/* [AI:GPT-5.6 Sol | 2026-09-07 19:02:00 UTC] */

/**
 * Support administration queue and lifecycle controls.
 *
 * @var array<string, mixed> $data
 */

if (!theme::render('head', get_defined_vars())) {
    require APPROOT . '/views/inc/head.php';
}

$escape = static fn ($value): string => htmlspecialchars(
    (string) $value,
    ENT_QUOTES,
    'UTF-8'
);

$databaseState = (string) ($data['database_state'] ?? 'missing');
$tickets = $data['tickets'] ?? [];

?>

<main class="container py-4">
    <h1>Support</h1>

    <?php if (isset($_GET['installed'])): ?>
        <div class="alert alert-success">
            Support database installed.
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['deleted'])): ?>
        <div class="alert alert-success">
            Support data deleted.
        </div>
    <?php endif; ?>

    <?php if ($databaseState !== 'ready'): ?>

        <div class="alert alert-warning">
            <strong>Database not installed.</strong>
            Support cannot accept tickets until its database
            tables have been created.
        </div>

        <form method="post" action="/admin/support" class="mb-4">
            <?= $this->csrf_field(); ?>

            <input
                type="hidden"
                name="action"
                value="install_sql"
            >

            <button type="submit" class="btn btn-primary">
                Install SQL
            </button>
        </form>

    <?php else: ?>

        <p>
            Manage support tickets, assignment, support tier,
            escalation, and lifecycle.
        </p>

        <?php if ($tickets === []): ?>

            <div class="alert alert-secondary">
                No support tickets have been submitted.
            </div>

        <?php else: ?>

            <div class="table-responsive mb-5">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Ticket</th>
                            <th>Type</th>
                            <th>Subject</th>
                            <th>Status</th>
                            <th>Tier</th>
                            <th>Assigned</th>
                            <th>Reported</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($tickets as $ticket): ?>
                            <tr>
                                <td>
                                    <a
                                        href="/admin/support/<?= $escape(
                                            $ticket['ticket_id']
                                        ); ?>"
                                    >
                                        #<?= $escape(
                                            $ticket['ticket_id']
                                        ); ?>
                                    </a>
                                </td>

                                <td>
                                    <?= $escape(
                                        ucfirst(
                                            (string) $ticket['type']
                                        )
                                    ); ?>
                                </td>

                                <td>
                                    <?= $escape($ticket['subject']); ?>
                                </td>

                                <td>
                                    <?= $escape(
                                        strtoupper(
                                            str_replace(
                                                '_',
                                                ' ',
                                                (string) $ticket['status']
                                            )
                                        )
                                    ); ?>
                                </td>

                                <td>
                                    <?= $escape(
                                        strtoupper(
                                            (string) $ticket['tier']
                                        )
                                    ); ?>
                                </td>

                                <td>
                                    <?= $escape(
                                        $ticket['assigned_to']
                                            ?? 'Unassigned'
                                    ); ?>
                                </td>

                                <td>
                                    <?= $escape(
                                        $ticket['created_at']
                                    ); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        <?php endif; ?>

        <section class="border rounded p-4 mb-4">
            <h2 class="h4">Data</h2>

            <p>
                Delete all Support tickets, replies, and lifecycle
                history while preserving the Support database schema.
            </p>

            <form
                method="post"
                action="/admin/support"
                onsubmit="return confirm(
                    'Delete all Support data? This cannot be undone.'
                );"
            >
                <?= $this->csrf_field(); ?>

                <input
                    type="hidden"
                    name="action"
                    value="delete_data"
                >

                <button type="submit" class="btn btn-warning">
                    Delete Data
                </button>
            </form>
        </section>

    <?php endif; ?>

    <section class="border rounded p-4">
        <h2 class="h4">Lifecycle</h2>

        <p>
            Nuke removes the Support module through the
            ChAoS Core module lifecycle.
        </p>

        <form
            method="post"
            action="/admin/uninstall"
            onsubmit="return confirm(
                'Nuke the Support module? This cannot be undone.'
            );"
        >
            <?= $this->csrf_field(); ?>

            <input
                type="hidden"
                name="module"
                value="support"
            >

            <button type="submit" class="btn btn-danger">
                Nuke
            </button>
        </form>
    </section>
</main>

<?php

if (!theme::render('foot', get_defined_vars())) {
    require APPROOT . '/views/inc/foot.php';
}

/* [End AI:GPT-5.6 Sol] */