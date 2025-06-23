<?php
// ===================================================================
// ADMIN/BACKUP.PHP - Backup Management Interface
// ===================================================================
?>
<?php
require_once '../config/config.php';
require_once '../includes/auth.php';

// Admin only
if ($_SESSION['role'] !== 'admin') {
    redirect('../user/dashboard.php');
}

$page_title = 'Database Backup';
include '../templates/header.php';
?>

<div class="main-wrapper">
    <?php include '../templates/sidebar.php'; ?>
    
    <div class="main-content">
        <?php include '../templates/nav.php'; ?>
        
        <div class="content-area">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="mb-0">Database Backup</h2>
                <button class="btn btn-primary" onclick="createBackup()">
                    <i class="fas fa-database me-2"></i>Create Backup
                </button>
            </div>

            <div id="alertContainer"></div>

            <!-- Backup List -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Available Backups</h5>
                </div>
                <div class="card-body">
                    <div id="backupsList">
                        <div class="text-center py-4">
                            <div class="spinner-border" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-2">Loading backups...</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Backup Information -->
            <div class="row mt-4">
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Backup Information</h5>
                        </div>
                        <div class="card-body">
                            <ul class="list-unstyled">
                                <li><i class="fas fa-info-circle text-primary me-2"></i>Backups include all database tables and data</li>
                                <li><i class="fas fa-shield-alt text-success me-2"></i>Backups are stored securely on the server</li>
                                <li><i class="fas fa-download text-info me-2"></i>You can download backups for external storage</li>
                                <li><i class="fas fa-clock text-warning me-2"></i>Regular backups are recommended</li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Restoration Notes</h5>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-warning">
                                <h6><i class="fas fa-exclamation-triangle me-2"></i>Important</h6>
                                <ul class="mb-0 small">
                                    <li>Restoration must be done manually through database administration tools</li>
                                    <li>Always test backups before relying on them</li>
                                    <li>Consider external backup solutions for critical data</li>
                                    <li>Keep backups in multiple locations</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    loadBackups();
});

function loadBackups() {
    fetch('../api/backup.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({action: 'list_backups'})
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displayBackups(data.backups);
        } else {
            showAlert('Failed to load backups: ' + data.message, 'danger');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('Error loading backups', 'danger');
    });
}

function displayBackups(backups) {
    const container = document.getElementById('backupsList');
    
    if (backups.length === 0) {
        container.innerHTML = `
            <div class="text-center py-4">
                <i class="fas fa-database fa-3x text-muted mb-3"></i>
                <h5>No Backups Found</h5>
                <p class="text-muted">Create your first backup to get started</p>
                <button class="btn btn-primary" onclick="createBackup()">
                    <i class="fas fa-database me-2"></i>Create First Backup
                </button>
            </div>
        `;
        return;
    }
    
    let html = `
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Filename</th>
                        <th>Size</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
    `;
    
    backups.forEach(backup => {
        html += `
            <tr>
                <td>
                    <i class="fas fa-file-archive text-primary me-2"></i>
                    ${backup.filename}
                </td>
                <td>${backup.size}</td>
                <td class="text-muted">${backup.created}</td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-primary" onclick="downloadBackup('${backup.filename}')" title="Download">
                            <i class="fas fa-download"></i>
                        </button>
                        <button class="btn btn-outline-danger" onclick="deleteBackup('${backup.filename}')" title="Delete">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    });
    
    html += `
                </tbody>
            </table>
        </div>
    `;
    
    container.innerHTML = html;
}

function createBackup() {
    const btn = event.target;
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Creating...';
    btn.disabled = true;
    
    fetch('../api/backup.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({action: 'create_backup'})
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert(`Backup created successfully: ${data.filename} (${data.size})`, 'success');
            loadBackups();
        } else {
            showAlert('Failed to create backup: ' + data.message, 'danger');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('Error creating backup', 'danger');
    })
    .finally(() => {
        btn.innerHTML = originalText;
        btn.disabled = false;
    });
}

function downloadBackup(filename) {
    window.location.href = `../api/backup.php?action=download&filename=${encodeURIComponent(filename)}`;
}

function deleteBackup(filename) {
    if (confirm(`Are you sure you want to delete the backup "${filename}"? This action cannot be undone.`)) {
        fetch('../api/backup.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({action: 'delete_backup', filename: filename})
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert('Backup deleted successfully', 'success');
                loadBackups();
            } else {
                showAlert('Failed to delete backup: ' + data.message, 'danger');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('Error deleting backup', 'danger');
        });
    }
}

function showAlert(message, type) {
    const alertContainer = document.getElementById('alertContainer');
    const alert = document.createElement('div');
    alert.className = `alert alert-${type} alert-dismissible fade show`;
    alert.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    alertContainer.appendChild(alert);
    
    if (type === 'success') {
        setTimeout(() => {
            alert.remove();
        }, 5000);
    }
}
</script>

<?php include '../templates/footer.php'; ?>