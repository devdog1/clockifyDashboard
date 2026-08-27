<?php
if (!defined('CLOCKIFY_PLUGIN_DIR')) {
    require_once __DIR__ . '/../clockify-lib.php';
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (function_exists('csrf_verify')) {
        csrf_verify();
    }

    if (isset($_POST['action'])) {
        $action = $_POST['action'];

        if ($action === 'create_role') {
            $roleName = trim($_POST['role_name'] ?? '');
            $description = trim($_POST['description'] ?? '');

            if (!empty($roleName)) {
                $roleId = ClockifyModel::createRole($roleName, $description);
                if ($roleId) {
                    $message = "Role '" . htmlspecialchars($roleName) . "' created successfully!";
                } else {
                    $error = "Failed to create role or role name already exists.";
                }
            } else {
                $error = "Role name cannot be empty.";
            }
        } elseif ($action === 'edit_role') {
            $roleId = (int)($_POST['role_id'] ?? 0);
            $roleName = trim($_POST['role_name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $isDisabled = isset($_POST['is_disabled']) ? 1 : 0;

            if ($roleId > 0 && !empty($roleName)) {
                if (ClockifyModel::updateRole($roleId, $roleName, $description, $isDisabled)) {
                    $message = "Role updated successfully.";
                } else {
                    $error = "Failed to update role.";
                }
            }
        } elseif ($action === 'toggle_role') {
            $roleId = (int)($_POST['role_id'] ?? 0);
            $currentStatus = (int)($_POST['current_status'] ?? 0);
            $newStatus = $currentStatus === 1 ? 0 : 1;

            if ($roleId > 0) {
                if (ClockifyModel::toggleRoleStatus($roleId, $newStatus)) {
                    $message = "Role status updated (" . ($newStatus === 1 ? 'Disabled' : 'Active') . ").";
                } else {
                    $error = "Failed to toggle role status.";
                }
            }
        } elseif ($action === 'update_permissions') {
            $roleId = (int)($_POST['role_id'] ?? 0);
            $permissionIds = $_POST['permissions'] ?? [];

            if ($roleId > 0) {
                if (ClockifyModel::updateRolePermissions($roleId, $permissionIds)) {
                    $message = "Role permissions updated successfully.";
                } else {
                    $error = "Failed to update role permissions.";
                }
            }
        } elseif ($action === 'delete_role') {
            $roleId = (int)($_POST['role_id'] ?? 0);

            if ($roleId > 0) {
                if (ClockifyModel::deleteRole($roleId)) {
                    $message = "Role deleted successfully.";
                } else {
                    $error = "Cannot delete system default roles (admin, manager, user).";
                }
            }
        }
    }
}

$allRoles = ClockifyModel::getAllRoles();
$allPermissions = ClockifyModel::getAllPermissions();
?>

<div class="my-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2><i class="fa-solid fa-user-shield text-primary me-2"></i>Roles & Permissions Management</h2>
            <p class="text-muted">Create, edit, disable permission roles, and manage permissions assigned to each role.</p>
        </div>
        <div>
            <a href="index.php?route=clockify_dashboard" class="btn btn-outline-secondary">
                <i class="fa-solid fa-arrow-left me-1"></i> Dashboard
            </a>
        </div>
    </div>

    <?php if (!empty($message)): ?>
        <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
            <i class="fa-solid fa-circle-check me-2"></i> <?= htmlspecialchars($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
            <i class="fa-solid fa-triangle-exclamation me-2"></i> <?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- Create Role Form -->
        <div class="col-md-4 mb-4">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white fw-bold">
                    <i class="fa-solid fa-plus-circle me-2"></i>Create New Role
                </div>
                <div class="card-body">
                    <form method="POST" action="index.php?route=clockify_manage_roles">
                        <?php if (function_exists('csrf_field')) csrf_field(); ?>
                        <input type="hidden" name="action" value="create_role">

                        <div class="mb-3">
                            <label for="role_name" class="form-label fw-bold">Role Name</label>
                            <input type="text" name="role_name" id="role_name" class="form-control" placeholder="e.g. clockify_auditor" required>
                            <div class="form-text">Will be formatted into a clean identifier string.</div>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label fw-bold">Description</label>
                            <textarea name="description" id="description" class="form-control" rows="2" placeholder="Brief description of role responsibilities"></textarea>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fa-solid fa-plus me-1"></i> Add Role
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Role List & Permissions Matrix -->
        <div class="col-md-8">
            <?php if (empty($allRoles)): ?>
                <div class="alert alert-info shadow-sm">
                    <i class="fa-solid fa-circle-info me-2"></i>No roles found in system.
                </div>
            <?php else: ?>
                <?php foreach ($allRoles as $role): ?>
                    <?php
                    $isSystemRole = in_array($role['role_name'], ['admin', 'manager', 'user']);
                    $isDisabled = (int)($role['is_disabled'] ?? 0) === 1;
                    ?>
                    <div class="card shadow-sm mb-4 border-start border-4 <?= $isDisabled ? 'border-danger' : 'border-success' ?>">
                        <div class="card-header d-flex justify-content-between align-items-center bg-dark text-white">
                            <div class="d-flex align-items-center gap-2">
                                <strong><i class="fa-solid fa-user-gear me-1 text-info"></i> <?= htmlspecialchars($role['role_name']) ?></strong>
                                <?php if ($isSystemRole): ?>
                                    <span class="badge bg-secondary">System Role</span>
                                <?php endif; ?>
                                <span class="badge <?= $isDisabled ? 'bg-danger' : 'bg-success' ?>">
                                    <?= $isDisabled ? 'Disabled' : 'Active' ?>
                                </span>
                            </div>

                            <div class="d-flex gap-2">
                                <!-- Enable/Disable Role Form -->
                                <form method="POST" action="index.php?route=clockify_manage_roles" style="display:inline;">
                                    <?php if (function_exists('csrf_field')) csrf_field(); ?>
                                    <input type="hidden" name="action" value="toggle_role">
                                    <input type="hidden" name="role_id" value="<?= $role['id'] ?>">
                                    <input type="hidden" name="current_status" value="<?= $isDisabled ? 1 : 0 ?>">
                                    <button type="submit" class="btn btn-sm <?= $isDisabled ? 'btn-success' : 'btn-warning' ?>">
                                        <i class="fa-solid <?= $isDisabled ? 'fa-check-circle' : 'fa-ban' ?> me-1"></i>
                                        <?= $isDisabled ? 'Enable' : 'Disable' ?>
                                    </button>
                                </form>

                                <?php if (!$isSystemRole): ?>
                                    <!-- Delete Role Form -->
                                    <form method="POST" action="index.php?route=clockify_manage_roles" style="display:inline;" onsubmit="return confirm('Delete this role?');">
                                        <?php if (function_exists('csrf_field')) csrf_field(); ?>
                                        <input type="hidden" name="action" value="delete_role">
                                        <input type="hidden" name="role_id" value="<?= $role['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">
                                            <i class="fa-solid fa-trash me-1"></i> Delete
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="card-body">
                            <p class="small text-muted mb-3"><?= htmlspecialchars($role['description'] ?: 'No description provided.') ?></p>

                            <!-- Role Permissions Form -->
                            <form method="POST" action="index.php?route=clockify_manage_roles">
                                <?php if (function_exists('csrf_field')) csrf_field(); ?>
                                <input type="hidden" name="action" value="update_permissions">
                                <input type="hidden" name="role_id" value="<?= $role['id'] ?>">

                                <h6 class="fw-bold mb-2"><i class="fa-solid fa-key text-secondary me-1"></i>Assigned Permissions</h6>

                                <?php if (empty($allPermissions)): ?>
                                    <div class="text-muted small">No permissions registered in system.</div>
                                <?php else: ?>
                                    <div class="row g-2 mb-3">
                                        <?php foreach ($allPermissions as $perm): ?>
                                            <?php
                                            $isAssigned = in_array($perm['id'], $role['permission_ids'] ?? []);
                                            ?>
                                            <div class="col-md-6">
                                                <div class="form-check p-2 bg-light rounded border">
                                                    <input class="form-check-input ms-0 me-2" type="checkbox"
                                                           name="permissions[]"
                                                           value="<?= $perm['id'] ?>"
                                                           id="perm_<?= $role['id'] ?>_<?= $perm['id'] ?>"
                                                           <?= $isAssigned ? 'checked' : '' ?>>
                                                    <label class="form-check-label fw-bold small text-dark" for="perm_<?= $role['id'] ?>_<?= $perm['id'] ?>">
                                                        <?= htmlspecialchars($perm['permission_name']) ?>
                                                    </label>
                                                    <?php if (!empty($perm['description'])): ?>
                                                        <div class="small text-muted font-monospace" style="font-size: 0.75rem;">
                                                            <?= htmlspecialchars($perm['description']) ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>

                                <button type="submit" class="btn btn-sm btn-primary">
                                    <i class="fa-solid fa-floppy-disk me-1"></i> Update Role Permissions
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
