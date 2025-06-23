// Validation specific JavaScript functions
class EmailValidation {
    constructor() {
        this.init();
    }

    init() {
        this.bindEvents();
        this.setupRealTimeValidation();
    }

    bindEvents() {
        // Single email validation
        const validateBtn = document.getElementById('validateEmailBtn');
        if (validateBtn) {
            validateBtn.addEventListener('click', (e) => this.validateSingleEmail(e));
        }

        // Bulk validation progress tracking
        if (window.currentJobId) {
            this.trackBulkProgress(window.currentJobId);
        }

        // Real-time email format validation
        const emailInputs = document.querySelectorAll('input[type="email"]');
        emailInputs.forEach(input => {
            input.addEventListener('input', (e) => this.validateEmailFormat(e.target));
        });
    }

    setupRealTimeValidation() {
        // Add real-time validation indicators
        const emailInputs = document.querySelectorAll('input[type="email"]');
        emailInputs.forEach(input => {
            const feedback = document.createElement('div');
            feedback.className = 'invalid-feedback';
            input.parentNode.insertBefore(feedback, input.nextSibling);
        });
    }

    validateEmailFormat(input) {
        const email = input.value.trim();
        const feedback = input.parentNode.querySelector('.invalid-feedback');
        
        if (!email) {
            input.classList.remove('is-valid', 'is-invalid');
            return;
        }

        const emailRegex = /^[a-zA-Z0-9.!#$%&'*+/=?^_`{|}~-]+@[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)*$/;
        
        if (emailRegex.test(email)) {
            input.classList.remove('is-invalid');
            input.classList.add('is-valid');
            feedback.textContent = '';
        } else {
            input.classList.remove('is-valid');
            input.classList.add('is-invalid');
            feedback.textContent = 'Please enter a valid email address';
        }
    }

    async validateSingleEmail(event) {
        event.preventDefault();
        
        const form = event.target.closest('form');
        const emailInput = form.querySelector('input[name="email"]');
        const submitBtn = event.target;
        const resultDiv = document.getElementById('validationResult');
        
        const email = emailInput.value.trim();
        
        if (!email) {
            this.showError('Please enter an email address');
            return;
        }

        // Show loading state
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Validating...';
        
        if (resultDiv) {
            resultDiv.innerHTML = '<div class="text-center"><i class="fas fa-spinner fa-spin fa-2x"></i></div>';
        }

        try {
            const response = await fetch('../api/validate_email.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ email: email })
            });

            const data = await response.json();

            if (data.success) {
                this.displayValidationResult(data.result, resultDiv);
            } else {
                this.showError(data.message);
            }
        } catch (error) {
            this.showError('An error occurred during validation');
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-check me-2"></i>Validate Email';
        }
    }

    displayValidationResult(result, container) {
        if (!container) return;

        let statusClass = 'info';
        let statusIcon = 'question-circle';
        let statusText = 'Unknown';

        switch(result.status) {
            case 'valid':
                statusClass = 'success';
                statusIcon = 'check-circle';
                statusText = 'Valid';
                break;
            case 'invalid':
                statusClass = 'danger';
                statusIcon = 'times-circle';
                statusText = 'Invalid';
                break;
            case 'risky':
                statusClass = 'warning';
                statusIcon = 'exclamation-triangle';
                statusText = 'Risky';
                break;
        }

        let html = `
            <div class="alert alert-${statusClass}">
                <div class="d-flex align-items-center mb-3">
                    <i class="fas fa-${statusIcon} fa-2x me-3"></i>
                    <div>
                        <h5 class="mb-1">Email Status: ${statusText}</h5>
                        <p class="mb-0">${result.email}</p>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <h6>Validation Checks</h6>
                        <ul class="list-unstyled">
                            <li><i class="fas fa-${result.format_valid ? 'check text-success' : 'times text-danger'} me-2"></i>Format Valid</li>
                            <li><i class="fas fa-${result.dns_valid ? 'check text-success' : 'times text-danger'} me-2"></i>DNS Valid</li>
                            <li><i class="fas fa-${result.smtp_valid ? 'check text-success' : 'times text-danger'} me-2"></i>SMTP Valid</li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <h6>Risk Assessment</h6>
                        <ul class="list-unstyled">
                            <li><i class="fas fa-${result.is_disposable ? 'exclamation-triangle text-warning' : 'check text-success'} me-2"></i>
                                ${result.is_disposable ? 'Disposable Email' : 'Not Disposable'}</li>
                            <li><i class="fas fa-${result.is_role_based ? 'info-circle text-info' : 'check text-success'} me-2"></i>
                                ${result.is_role_based ? 'Role-based Email' : 'Personal Email'}</li>
                            <li><i class="fas fa-${result.is_catch_all ? 'exclamation-triangle text-warning' : 'check text-success'} me-2"></i>
                                ${result.is_catch_all ? 'Catch-all Domain' : 'Standard Domain'}</li>
                        </ul>
                    </div>
                </div>
        `;

        if (result.risk_score > 0) {
            html += `
                <div class="mt-3">
                    <h6>Risk Score: ${result.risk_score}/100</h6>
                    <div class="progress">
                        <div class="progress-bar bg-${result.risk_score > 70 ? 'danger' : result.risk_score > 40 ? 'warning' : 'success'}" 
                             style="width: ${result.risk_score}%"></div>
                    </div>
                </div>
            `;
        }

        if (result.details && result.details.length > 0) {
            html += `
                <div class="mt-3">
                    <h6>Additional Details</h6>
                    <ul class="list-unstyled small">
                        ${result.details.map(detail => `<li><i class="fas fa-info-circle text-info me-2"></i>${detail}</li>`).join('')}
                    </ul>
                </div>
            `;
        }

        html += '</div>';
        container.innerHTML = html;
    }

    async trackBulkProgress(jobId) {
        const progressContainer = document.getElementById('bulkProgress');
        if (!progressContainer) return;

        const updateProgress = async () => {
            try {
                const response = await fetch(`../api/progress.php?job_id=${jobId}`);
                const data = await response.json();

                if (data.success) {
                    const job = data.job;
                    this.updateProgressDisplay(job);

                    if (job.status === 'completed' || job.status === 'failed') {
                        clearInterval(progressInterval);
                        this.onBulkValidationComplete(job);
                    }
                }
            } catch (error) {
                console.error('Progress tracking error:', error);
            }
        };

        // Update immediately and then every 2 seconds
        updateProgress();
        const progressInterval = setInterval(updateProgress, 2000);
    }

    updateProgressDisplay(job) {
        const progressBar = document.getElementById('progressBar');
        const progressText = document.getElementById('progressText');
        const processedCount = document.getElementById('processedCount');
        const validCount = document.getElementById('validCount');
        const invalidCount = document.getElementById('invalidCount');
        const statusBadge = document.getElementById('statusBadge');

        if (progressBar && progressText) {
            progressBar.style.width = job.progress + '%';
            progressText.textContent = job.progress + '%';
        }

        if (processedCount) processedCount.textContent = this.formatNumber(job.processed_emails);
        if (validCount) validCount.textContent = this.formatNumber(job.valid_emails);
        if (invalidCount) invalidCount.textContent = this.formatNumber(job.invalid_emails);

        if (statusBadge) {
            statusBadge.className = `badge bg-${this.getStatusColor(job.status)}`;
            statusBadge.textContent = job.status.charAt(0).toUpperCase() + job.status.slice(1);
        }
    }

    onBulkValidationComplete(job) {
        const message = job.status === 'completed' 
            ? 'Bulk validation completed successfully!' 
            : 'Bulk validation failed. Please try again.';
        
        this.showAlert(message, job.status === 'completed' ? 'success' : 'danger');

        // Show download button if completed
        if (job.status === 'completed') {
            const downloadBtn = document.getElementById('downloadResults');
            if (downloadBtn) {
                downloadBtn.style.display = 'block';
                downloadBtn.href = `../api/export.php?job_id=${job.id}`;
            }
        }
    }

    getStatusColor(status) {
        const colors = {
            'pending': 'secondary',
            'processing': 'primary',
            'completed': 'success',
            'failed': 'danger'
        };
        return colors[status] || 'secondary';
    }

    formatNumber(num) {
        return new Intl.NumberFormat().format(num);
    }

    showError(message) {
        this.showAlert(message, 'danger');
    }

    showAlert(message, type = 'info') {
        const alertContainer = document.getElementById('alertContainer') || document.body;
        
        const alert = document.createElement('div');
        alert.className = `alert alert-${type} alert-dismissible fade show`;
        alert.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        alertContainer.appendChild(alert);
        
        // Auto-hide success alerts
        if (type === 'success') {
            setTimeout(() => {
                if (alert.parentNode) {
                    alert.remove();
                }
            }, 5000);
        }
    }
}

// Initialize validation when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    new EmailValidation();
});

// Export for use in other modules
window.EmailValidation = EmailValidation;