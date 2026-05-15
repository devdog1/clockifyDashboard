<?php
require_once "clockify-lib.php";

$settings = loadSettings();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'update_status') {
            $settings['reports_enabled'] = isset($_POST['reports_enabled']);
        } elseif ($_POST['action'] === 'add_recipient') {
            $email = trim($_POST['email']);
            if (filter_var($email, FILTER_VALIDATE_EMAIL) && !in_array($email, $settings['recipients'])) {
                $settings['recipients'][] = $email;
            }
        } elseif ($_POST['action'] === 'remove_recipient') {
            $email = $_POST['email'];
            $settings['recipients'] = array_values(array_filter($settings['recipients'], function($r) use ($email) {
                return $r !== $email;
            }));
        }
        saveSettings($settings);
    }
}

$pageTitle = "Automated Report Settings";
include "header.php";
?>

<div class="my-4">
    <h2 class="mb-4">Automated Report Settings</h2>

    <div class="row">
        <!-- Status Toggle -->
        <div class="col-md-5">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white"><strong>Report Status</strong></div>
                <div class="card-body">
                    <form method="post">
                        <input type="hidden" name="action" value="update_status">
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" id="reports_enabled" name="reports_enabled" <?= $settings['reports_enabled'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="reports_enabled">Automated Reports Enabled</label>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm">Save Status</button>
                    </form>
                </div>
            </div>

            <div class="card shadow-sm mb-4">
                <div class="card-header bg-dark text-white"><strong>Add Recipient</strong></div>
                <div class="card-body">
                    <form method="post">
                        <input type="hidden" name="action" value="add_recipient">
                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" name="email" id="email" class="form-control" placeholder="user@example.com" required>
                        </div>
                        <button type="submit" class="btn btn-success btn-sm">Add Recipient</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Recipients List -->
        <div class="col-md-7">
            <div class="card shadow-sm">
                <div class="card-header bg-secondary text-white"><strong>Report Recipients</strong></div>
                <div class="card-body p-0">
                    <table class="table table-striped mb-0">
                        <thead>
                            <tr>
                                <th>Email</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($settings['recipients'])): ?>
                                <tr><td colspan="2" class="text-center">No recipients configured.</td></tr>
                            <?php else: ?>
                                <?php foreach ($settings['recipients'] as $email): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($email) ?></td>
                                        <td class="text-end">
                                            <form method="post" style="display:inline;" onsubmit="return confirm('Remove this recipient?');">
                                                <input type="hidden" name="action" value="remove_recipient">
                                                <input type="hidden" name="email" value="<?= htmlspecialchars($email) ?>">
                                                <button type="submit" class="btn btn-danger btn-sm">Remove</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="mt-3 small text-muted">
                Note: Recipients defined in <code>clockify-config.php</code> are also included automatically.
            </div>
        </div>
    </div>
</div>

<?php include "footer.php"; ?>
