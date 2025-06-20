<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Validator - Debug Interface</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        .debug-section {
            margin-bottom: 2rem;
        }
        .debug-output {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
            padding: 1rem;
            font-family: 'Courier New', monospace;
            font-size: 0.875rem;
            white-space: pre-wrap;
            max-height: 400px;
            overflow-y: auto;
        }
        .status-check {
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-weight: bold;
        }
        .status-pass {
            background-color: #d1e7dd;
            color: #0f5132;
        }
        .status-fail {
            background-color: #f8d7da;
            color: #842029;
        }
        .status-warning {
            background-color: #fff3cd;
            color: #664d03;
        }
    </style>
</head>
<body>
    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-bug"></i> Email Validator Debug Interface</h3>
                        <p class="mb-0 text-muted">This tool helps diagnose issues with email validation</p>
                    </div>
                    <div class="card-body">
                        <!-- System Information -->
                        <div class="debug-section">
                            <h5><i class="fas fa-server"></i> System Information</h5>
                            <button id="check-system" class="btn btn-primary mb-3">
                                <i class="fas fa-search"></i> Check System Configuration
                            </button>
                            <div id="system-info" class="debug-output" style="display: none;"></div>
                        </div>

                        <!-- Test Email Validation -->
                        <div class="debug-section">
                            <h5><i class="fas fa-envelope"></i> Test Email Validation</h5>
                            <div class="row">
                                <div class="col-md-8">
                                    <div class="input-group mb-3">
                                        <input type="email" id="test-email" class="form-control" placeholder="Enter email to test" value="test@gmail.com">
                                        <button id="test-validation" class="btn btn-success">
                                            <i class="fas fa-play"></i> Test Validation
                                        </button>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="enable-debug">
                                        <label class="form-check-label" for="enable-debug">
                                            Enable Debug Mode
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div id="validation-result" class="debug-output" style="display: none;"></div>
                        </div>

                        <!-- Quick Tests -->
                        <div class="debug-section">
                            <h5><i class="fas fa-bolt"></i> Quick Tests</h5>
                            <div class="row">
                                <div class="col-md-3">
                                    <button class="btn btn-outline-primary w-100 mb-2" onclick="testEmail('test@gmail.com')">
                                        <i class="fas fa-envelope"></i> Gmail Test
                                    </button>
                                </div>
                                <div class="col-md-3">
                                    <button class="btn btn-outline-primary w-100 mb-2" onclick="testEmail('test@yahoo.com')">
                                        <i class="fas fa-envelope"></i> Yahoo Test
                                    </button>
                                </div>
                                <div class="col-md-3">
                                    <button class="btn btn-outline-primary w-100 mb-2" onclick="testEmail('test@outlook.com')">
                                        <i class="fas fa-envelope"></i> Outlook Test
                                    </button>
                                </div>
                                <div class="col-md-3">
                                    <button class="btn btn-outline-danger w-100 mb-2" onclick="testEmail('invalid@nonexistentdomain12345.com')">
                                        <i class="fas fa-times"></i> Invalid Test
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Troubleshooting Guide -->
                        <div class="debug-section">
                            <h5><i class="fas fa-question-circle"></i> Common Issues & Solutions</h5>
                            <div class="accordion" id="troubleshootingAccordion">
                                <div class="accordion-item">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#dns-issues">
                                            DNS Resolution Issues
                                        </button>
                                    </h2>
                                    <div id="dns-issues" class="accordion-collapse collapse" data-bs-parent="#troubleshootingAccordion">
                                        <div class="accordion-body">
                                            <strong>Symptoms:</strong> Domain validation always fails<br>
                                            <strong>Causes:</strong>
                                            <ul>
                                                <li>Missing <code>checkdnsrr()</code> function (Windows servers)</li>
                                                <li>DNS server configuration issues</li>
                                                <li>Firewall blocking DNS queries</li>
                                            </ul>
                                            <strong>Solutions:</strong>
                                            <ul>
                                                <li>Enable PHP's standard extensions</li>
                                                <li>Check DNS server configuration</li>
                                                <li>Use fallback methods (implemented in enhanced validator)</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="accordion-item">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#smtp-issues">
                                            SMTP Connection Issues
                                        </button>
                                    </h2>
                                    <div id="smtp-issues" class="accordion-collapse collapse" data-bs-parent="#troubleshootingAccordion">
                                        <div class="accordion-body">
                                            <strong>Symptoms:</strong> SMTP validation always fails<br>
                                            <strong>Causes:</strong>
                                            <ul>
                                                <li>Port 25 blocked by hosting provider</li>
                                                <li>Firewall restrictions</li>
                                                <li>SMTP servers rejecting connections</li>
                                            </ul>
                                            <strong>Solutions:</strong>
                                            <ul>
                                                <li>Try alternative ports (587, 465)</li>
                                                <li>Contact hosting provider about SMTP restrictions</li>
                                                <li>Use API-based validation services</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="accordion-item">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#hosting-issues">
                                            Hosting Environment Issues
                                        </button>
                                    </h2>
                                    <div id="hosting-issues" class="accordion-collapse collapse" data-bs-parent="#troubleshootingAccordion">
                                        <div class="accordion-body">
                                            <strong>Common hosting restrictions:</strong>
                                            <ul>
                                                <li><strong>Shared hosting:</strong> Often blocks outbound SMTP connections</li>
                                                <li><strong>Cloud hosting:</strong> May require specific security group rules</li>
                                                <li><strong>Windows servers:</strong> May lack certain PHP functions</li>
                                            </ul>
                                            <strong>Recommendations:</strong>
                                            <ul>
                                                <li>Use VPS or dedicated servers for full SMTP validation</li>
                                                <li>Implement tiered validation (format → domain → SMTP)</li>
                                                <li>Consider third-party validation APIs</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('check-system').addEventListener('click', function() {
            this.disabled = true;
            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Checking...';
            
            fetch('api/validate-email.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    get_system_info: true
                })
            })
            .then(response => response.json())
            .then(data => {
                displaySystemInfo(data.system_info);
                this.disabled = false;
                this.innerHTML = '<i class="fas fa-search"></i> Check System Configuration';
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('system-info').textContent = 'Error checking system info: ' + error.message;
                document.getElementById('system-info').style.display = 'block';
                this.disabled = false;
                this.innerHTML = '<i class="fas fa-search"></i> Check System Configuration';
            });
        });

        document.getElementById('test-validation').addEventListener('click', function() {
            const email = document.getElementById('test-email').value;
            if (!email) {
                alert('Please enter an email address');
                return;
            }
            testEmail(email);
        });

        function testEmail(email) {
            document.getElementById('test-email').value = email;
            
            const button = document.getElementById('test-validation');
            button.disabled = true;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Testing...';
            
            fetch('api/validate-email.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    email: email,
                    sendVerification: false
                })
            })
            .then(response => response.json())
            .then(data => {
                displayValidationResult(data);
                button.disabled = false;
                button.innerHTML = '<i class="fas fa-play"></i> Test Validation';
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('validation-result').textContent = 'Error: ' + error.message;
                document.getElementById('validation-result').style.display = 'block';
                button.disabled = false;
                button.innerHTML = '<i class="fas fa-play"></i> Test Validation';
            });
        }

        function displaySystemInfo(systemInfo) {
            const container = document.getElementById('system-info');
            
            let html = 'PHP VERSION: ' + systemInfo.php_version + '\n\n';
            
            html += 'PHP EXTENSIONS:\n';
            for (const [ext, loaded] of Object.entries(systemInfo.extensions)) {
                const status = loaded ? 'PASS' : 'FAIL';
                html += `  ${ext}: ${status}\n`;
            }
            
            html += '\nPHP FUNCTIONS:\n';
            for (const [func, available] of Object.entries(systemInfo.functions)) {
                const status = available ? 'PASS' : 'FAIL';
                html += `  ${func}(): ${status}\n`;
            }
            
            html += '\nCONFIGURATION:\n';
            html += `  allow_url_fopen: ${systemInfo.allow_url_fopen ? 'ENABLED' : 'DISABLED'}\n`;
            html += `  dns_get_record: ${systemInfo.dns_get_record ? 'AVAILABLE' : 'NOT AVAILABLE'}\n`;
            
            if (systemInfo.disabled_functions) {
                html += `  disabled_functions: ${systemInfo.disabled_functions}\n`;
            }
            
            container.textContent = html;
            container.style.display = 'block';
        }

        function displayValidationResult(result) {
            const container = document.getElementById('validation-result');
            
            let html = `EMAIL VALIDATION RESULT\n`;
            html += `========================\n\n`;
            html += `Email: ${result.email}\n`;
            html += `Format Valid: ${result.format_valid ? 'PASS' : 'FAIL'}\n`;
            html += `Domain Valid: ${result.domain_valid ? 'PASS' : 'FAIL'}\n`;
            html += `SMTP Valid: ${result.smtp_valid ? 'PASS' : 'FAIL'}\n`;
            html += `Overall Valid: ${result.is_valid ? 'PASS' : 'FAIL'}\n`;
            html += `Cached: ${result.cached ? 'YES' : 'NO'}\n\n`;
            
            if (result.debug_info) {
                html += `DEBUG INFORMATION:\n`;
                html += `==================\n`;
                
                if (result.debug_info.steps) {
                    html += `\nValidation Steps:\n`;
                    result.debug_info.steps.forEach((step, index) => {
                        html += `  ${index + 1}. ${step}\n`;
                    });
                }
                
                if (result.debug_info.format_check) {
                    html += `\nFormat Check Details:\n`;
                    html += `  Original: ${result.debug_info.format_check.email}\n`;
                    html += `  Sanitized: ${result.debug_info.format_check.sanitized}\n`;
                    html += `  Valid: ${result.debug_info.format_check.valid}\n`;
                }
                
                if (result.debug_info.domain) {
                    html += `\nDomain Check Details:\n`;
                    html += `  Domain: ${result.debug_info.domain}\n`;
                    if (result.debug_info.mx_check !== undefined) {
                        html += `  MX Record: ${result.debug_info.mx_check ? 'FOUND' : 'NOT FOUND'}\n`;
                    }
                    if (result.debug_info.a_check !== undefined) {
                        html += `  A Record: ${result.debug_info.a_check ? 'FOUND' : 'NOT FOUND'}\n`;
                    }
                }
                
                if (result.debug_info.smtp_domain) {
                    html += `\nSMTP Check Details:\n`;
                    html += `  Domain: ${result.debug_info.smtp_domain}\n`;
                    if (result.debug_info.mx_records) {
                        if (Array.isArray(result.debug_info.mx_records)) {
                            html += `  MX Records: ${result.debug_info.mx_records.join(', ')}\n`;
                        } else {
                            html += `  MX Records: ${result.debug_info.mx_records}\n`;
                        }
                    }
                    if (result.debug_info.smtp_attempts) {
                        html += `  SMTP Attempts:\n`;
                        result.debug_info.smtp_attempts.forEach(attempt => {
                            html += `    ${attempt}\n`;
                        });
                    }
                    if (result.debug_info.smtp_success_port) {
                        html += `  Success Port: ${result.debug_info.smtp_success_port}\n`;
                    }
                }
                
                if (result.debug_info.fallback_domain_check) {
                    html += `\nFallback Domain Check:\n`;
                    html += `  Domain: ${result.debug_info.fallback_domain_check.domain}\n`;
                    html += `  Resolved IP: ${result.debug_info.fallback_domain_check.resolved_ip}\n`;
                    html += `  Valid: ${result.debug_info.fallback_domain_check.valid}\n`;
                }
                
                if (result.debug_info.fallback_smtp_check) {
                    html += `\nFallback SMTP Check:\n`;
                    html += `  Domain: ${result.debug_info.fallback_smtp_check.domain}\n`;
                    html += `  Resolved: ${result.debug_info.fallback_smtp_check.resolved}\n`;
                    html += `  Note: ${result.debug_info.fallback_smtp_check.note}\n`;
                }
                
                // Show any errors
                ['format_error', 'domain_error', 'smtp_error', 'verification_error'].forEach(errorType => {
                    if (result.debug_info[errorType]) {
                        html += `\n${errorType.toUpperCase()}:\n`;
                        html += `  ${result.debug_info[errorType]}\n`;
                    }
                });
            }
            
            if (result.error) {
                html += `\nERROR: ${result.error}\n`;
            }
            
            container.textContent = html;
            container.style.display = 'block';
        }

        // Allow Enter key to trigger test
        document.getElementById('test-email').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                document.getElementById('test-validation').click();
            }
        });
    </script>
</body>
</html>