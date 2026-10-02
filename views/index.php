<?php

/* [AI:GPT-5.6 Sol | 2026-09-07 19:02:00 UTC] */

/**
 * Public Support module view.
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

$available = (bool) ($data['available'] ?? false);

?>

<main class="container py-5">
    <div style="max-width: 760px; margin: 0 auto;">
        <h1>Support</h1>

        <?php if (!$available): ?>

            <div class="alert alert-warning">
                Support is currently unavailable.
            </div>

        <?php else: ?>

            <p>
                Need help with STNC Chain?, The Chain, Stratum, Core or Miner?, or another issue? Open a support
                ticket below.
            </p>

            <?php if (($data['error'] ?? null) !== null): ?>
                <div class="alert alert-danger">
                    <?= $escape($data['error']); ?>
                </div>
            <?php endif; ?>

            <form method="post" action="/support">
                <?= $this->csrf_field(); ?>

                <div class="mb-3">
                    <label
                        for="support-name"
                        class="form-label"
                    >
                        Name
                    </label>

                    <input
                        id="support-name"
                        class="form-control"
                        type="text"
                        name="name"
                        maxlength="150"
                        required
                    >
                </div>

                <div class="mb-3">
                    <label
                        for="support-email"
                        class="form-label"
                    >
                        Email
                    </label>

                    <input
                        id="support-email"
                        class="form-control"
                        type="email"
                        name="email"
                        maxlength="255"
                        required
                    >
                </div>

                <div class="mb-3">
                    <label
                        for="support-type"
                        class="form-label"
                    >
                        Type
                    </label>

                    <select
                        id="support-type"
                        class="form-select"
                        name="type"
                    >
                        <option value="general">General</option>
                        <option value="chain_bug">Chain Bug</option>
						<option value="stratum_bug">Stratum Bug</option>
                        <option value="installation">
                            Installation
                        </option>
                        <option value="core">STNC Core</option>

                </div>

                <div class="mb-3">
                    <label
                        for="support-subject"
                        class="form-label"
                    >
                        Subject
                    </label>

                    <input
                        id="support-subject"
                        class="form-control"
                        type="text"
                        name="subject"
                        maxlength="255"
                        required
                    >
                </div>

                <div class="mb-3">
                    <label
                        for="support-description"
                        class="form-label"
                    >
                        What happened?
                    </label>

                    <textarea
                        id="support-description"
                        class="form-control"
                        name="description"
                        rows="8"
                        required
                    ></textarea>
                </div>

                <button
                    type="submit"
                    class="btn btn-primary"
                >
                    Open Ticket
                </button>
            </form>

        <?php endif; ?>
    </div>
</main>

<?php

if (!theme::render('foot', get_defined_vars())) {
    require APPROOT . '/views/inc/foot.php';
}

/* [End AI:GPT-5.6 Sol] */