<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Validation Debug Tool</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        .debug-output {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
            padding: 1rem;
            font-family: 'Courier New', monospace;
            font-size: 0.875rem;
            white-space: pre-wrap;
            max-height: 500px;
            overflow-y: auto;
        }
        .status-pass { color: #198754; font-weight: bold; }
        .status-fail { color: #dc3545; font-weight: bold; }
        .status-warning { color: #fd7e14; font-weight: bold; }
        .test-section { 
            border: 1px solid #dee2e6; 
            border-radius: 0.375rem; 
            padding: 1rem; 
            margin-bottom: 1rem; 
        }
        .step-indicator {
            display: inline-block;
            width: 25px;
            height: 25px;
            border-radius: 50%;
            color: white;
            text-align: center;
            line-height: 25px;
            font-size: 12px;
            font-weight: bold;
            margin-right: 10px;
        }
        .step-running { background-color: #0d6efd; }
        .step-success { background-color: #198754; }
        .step-error { background-color: #dc3545; }
        .step-pending { background-color: #6c757d; }
    </style>
</head>
<body>
    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-bug"></i> Email Validation Debug Tool</h3>
                        <p class="mb-0 text-muted">Comprehensive email validation testing and diagnostics</p>
                    </div>
                    <div class="card-body">
                        <!-- Email Test Form -->
                        <div class="row mb-4">
                            <div class="col-md-8">
                                <div class="input-group">
                                    <input type="email" id="test-email" class="form-control" 
                                           placeholder="Enter email to test" value="test@gmail.com">
                                    <button id="run-debug" class="btn btn-primary">
                                        <i class="fas fa-play"></i> Run Full Debug
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="verbose-mode">
                                    <label class="form-check-label" for="verbose-mode">
                                        Verbose Mode
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Test Progress -->
                        <div id="test-progress" class="d-none">
                            <h5>Test Progress</h5>
                            <div class="row">
                                <div class="col-md-3">
                                    <div id="step1" class="mb-2">
                                        <span class="step-indicator step-pending">1</span>
                                        <span>System Check</span>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div id="step2" class="mb-2">
                                        <span class="step-indicator step-pending">2</span>
                                        <span>Format Validation</span>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div id="step3" class="mb-2">
                                        <span class="step-indicator step-pending">3</span>
                                        <span>Domain Check</span>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div id="step4" class="mb-2">
                                        <span class="step-indicator step-pending">4</span>
                                        <span>SMTP Test</span>
                                    </div>
                                </div>
                            </div>
                            <hr>
                        </div>

                        <!-- Results Tabs -->
                        <ul class="nav nav-tabs" id="results-tabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="summary-tab" data-bs-toggle="tab" 
                                        data-bs-target="#summary" type="button">
                                    <i class="fas fa-chart-pie"></i> Summary
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="system-tab" data-bs-toggle="tab" 
                                        data-bs-target="#system" type="button">
                                    <i class="fas fa-server"></i> System Info
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="validation-tab" data-bs-toggle="tab" 
                                        data-bs-target="#validation" type="button">
                                    <i class="fas fa-check-circle"></i> Validation Details
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="smtp-tab" data-bs-toggle="tab" 
                                        data-bs-target="#smtp" type="button">
                                    <i class="fas fa-envelope"></i> SMTP Test
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="recommendations-tab" data-bs-toggle="tab" 
                                        data-bs-target="#recommendations" type="button">
                                    <i class="fas fa-lightbulb"></i> Recommendations
                                </button>
                            </li>
                        </ul>

                        <div class="tab-content mt-3" id="results-content">
                            <!-- Summary Tab -->
                            <div class="tab-pane fade show active" id="summary" role="tabpanel">
                                <div class="row" id="summary-cards">
                                    <div class="col-md-3">
                                        <div class="card text-center">
                                            <div class="card-body">
                                                <i class="fas fa-envelope fa-2x mb-2 text-primary"></i>
                                                <h5>Format</h5>
                                                <span id="format-status" class="badge bg-secondary">Not tested</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="card text-center">
                                            <div class="card-body">
                                                <i class="fas fa-globe fa-2x mb-2 text-info"></i>
                                                <h5>Domain</h5>
                                                <span id="domain-status" class="badge bg-secondary">Not tested</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="card text-center">
                                            <div class="card-body">
                                                <i class="fas fa-server fa-2x mb-2 text-warning"></i>
                                                <h5>SMTP</h5>
                                                <span id="smtp-status" class="badge bg-secondary">Not tested</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="card text-center">
                                            <div class="card-body">
                                                <i class="fas fa-check-circle fa-2x mb-2 text-success"></i>
                                                <h5>Overall</h5>
                                                <span id="overall-status" class="badge bg-secondary">Not tested</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-4">
                                    <h6>Test Summary</h6>
                                    <div id="summary-text" class="alert alert-info">
                                        Run a test to see detailed results and recommendations.
                                    </div>
                                </div>
                            </div>

                            <!-- System Info Tab -->
                            <div class="tab-pane fade" id="system" role="tabpanel">
                                <div class="test-section">
                                    <h6><i class="fas fa-info-circle"></i> PHP Environment</h6>
                                    <div id="system-info" class="debug-output">
                                        Click "Run Full Debug" to check system information...
                                    </div>
                                </div>
                            </div>

                            <!-- Validation Details Tab -->
                            <div class="tab-pane fade" id="validation" role="tabpanel">
                                <div class="test-section">
                                    <h6><i class="fas fa-search"></i> Validation Steps</h6>
                                    <div id="validation-details" class="debug-output">
                                        Click "Run Full Debug" to see detailed validation steps...
                                    </div>
                                </div>
                            </div>

                            <!-- SMTP Test Tab -->
                            <div class="tab-pane fade" id="smtp" role="tabpanel">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="test-section">
                                            <h6><i class="fas fa-cog"></i> Current SMTP Configuration</h6>
                                            <div id="smtp-config" class="debug-output">
                                                Click "Run Full Debug" to load SMTP configuration...
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="test-section">
                                            <h6><i class="fas fa-vial"></i> SMTP Connection Test</h6>
                                            <div id="smtp-test-results" class="debug-output">
                                                Click "Run Full Debug" to test SMTP connection...
                                            </div>
                                            <button id="test-smtp-config" class="btn btn-outline-primary btn-sm mt-2">
                                                <i class="fas fa-play"></i> Test SMTP Only
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Recommendations Tab -->
                            <div class="tab-pane fade" id="recommendations" role="tabpanel">
                                <div class="test-section">
                                    <h6><i class="fas fa-lightbulb"></i> Recommendations & Fixes</h6>
                                    <div id="recommendations-content">
                                        <div class="alert alert-info">
                                            <h6><i class="fas fa-info-circle"></i> How to Use This Tool</h6>
                                            <ol>
                                                <li>Enter an email address you want to test</li>
                                                <li>Click "Run Full Debug" to perform comprehensive testing</li>
                                                <li>Review results in each tab</li>
                                                <li>Follow recommendations to fix any issues</li>
                                            </ol>
                                        </div>
                                        
                                        <div class="alert alert-warning">
                                            <h6><i class="fas fa-exclamation-triangle"></i> Common Issues</h6>
                                            <ul>
                                                <li><strong>Format validation failing:</strong> Check regex patterns and PHP filter functions</li>
                                                <li><strong>Domain validation failing:</strong> DNS functions may not be available</li>
                                                <li><strong>SMTP validation failing:</strong> Hosting provider may block SMTP ports</li>
                                                <li><strong>All validations failing:</strong> Check PHP configuration and hosting restrictions</li>
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
        let debugResults = {};

        document.getElementById('run-debug').addEventListener('click', function() {
            const email = document.getElementById('test-email').value.trim();
            
            if (!email) {
                alert('Please enter an email address to test');
                return;
            }
            
            runFullDebug(email);
        });

        document.getElementById('test-smtp-config').addEventListener('click', function() {
            testSmtpConfiguration();
        });

        async function runFullDebug(email) {
            const button = document.getElementById('run-debug');
            button.disabled = true;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Running Tests...';
            
            // Show progress
            document.getElementById('test-progress').classList.remove('d-none');
            
            try {
                // Step 1: System Check
                updateStepStatus('step1', 'running');
                const systemInfo = await getSystemInfo();
                debugResults.systemInfo = systemInfo;
                displaySystemInfo(systemInfo);
                updateStepStatus('step1', 'success');
                
                // Step 2: Email Validation
                updateStepStatus('step2', 'running');
                const validationResult = await validateEmailDetailed(email);
                debugResults.validation = validationResult;
                displayValidationDetails(validationResult);
                updateStepStatus('step2', validationResult.is_valid ? 'success' : 'error');
                
                // Step 3: Domain Check (detailed)
                updateStepStatus('step3', 'running');
                // Domain check is part of validation, just update status
                updateStepStatus('step3', validationResult.domain_valid ? 'success' : 'error');
                
                // Step 4: SMTP Test
                updateStepStatus('step4', 'running');
                await testSmtpConfiguration();
                updateStepStatus('step4', validationResult.smtp_valid ? 'success' : 'error');
                
                // Update summary
                updateSummary(validationResult);
                
                // Generate recommendations
                generateRecommendations();
                
            } catch (error) {
                console.error('Debug test failed:', error);
                displayError('Debug test failed: ' + error.message);
            } finally {
                button.disabled = false;
                button.innerHTML = '<i class="fas fa-play"></i> Run Full Debug';
            }
        }

        async function getSystemInfo() {
            try {
                const response = await fetch('api/validate-email.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ get_system_info: true })
                });
                
                const data = await response.json();
                return data.system_info;
            } catch (error) {
                return { error: error.message };
            }
        }

        async function validateEmailDetailed(email) {
            try {
                const response = await fetch('api/validate-email.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ 
                        email: email,
                        sendVerification: false 
                    })
                });
                
                return await response.json();
            } catch (error) {
                return { error: error.message };
            }
        }

        async function testSmtpConfiguration() {
            try {
                // First get SMTP settings
                const smtpResponse = await fetch('api/smtp-settings.php');
                const smtpConfig = await smtpResponse.json();
                
                displaySmtpConfig(smtpConfig);
                
                if (smtpConfig && smtpConfig.provider_type) {
                    // Test the configuration
                    const testResponse = await fetch('api/test-smtp.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(smtpConfig)
                    });
                    
                    const testResult = await testResponse.json();
                    debugResults.smtpTest = testResult;
                    displaySmtpTestResults(testResult);
                } else {
                    displaySmtpTestResults({
                        success: false,
                        message: 'No SMTP configuration found'
                    });
                }
            } catch (error) {
                displaySmtpTestResults({
                    success: false,
                    error: error.message
                });
            }
        }

        function updateStepStatus(stepId, status) {
            const step = document.getElementById(stepId);
            const indicator = step.querySelector('.step-indicator');
            
            // Remove all status classes
            indicator.classList.remove('step-pending', 'step-running', 'step-success', 'step-error');
            
            // Add new status class
            indicator.classList.add('step-' + status);
        }

        function displaySystemInfo(systemInfo) {
            const container = document.getElementById('system-info');
            
            if (systemInfo.error) {
                container.textContent = 'Error loading system info: ' + systemInfo.error;
                return;
            }
            
            let output = 'SYSTEM INFORMATION\n';
            output += '==================\n\n';
            output += 'PHP Version: ' + systemInfo.php_version + '\n\n';
            
            output += 'EXTENSIONS:\n';
            Object.entries(systemInfo.extensions || {}).forEach(([ext, loaded]) => {
                output += `  ${ext}: ${loaded ? 'LOADED' : 'NOT LOADED'}\n`;
            });
            
            output += '\nFUNCTIONS:\n';
            Object.entries(systemInfo.functions || {}).forEach(([func, available]) => {
                output += `  ${func}(): ${available ? 'AVAILABLE' : 'NOT AVAILABLE'}\n`;
            });
            
            output += '\nPHP SETTINGS:\n';
            Object.entries(systemInfo.php_settings || {}).forEach(([setting, value]) => {
                output += `  ${setting}: ${value}\n`;
            });
            
            container.textContent = output;
        }

        function displayValidationDetails(result) {
            const container = document.getElementById('validation-details');
            
            let output = 'EMAIL VALIDATION DETAILS\n';
            output += '========================\n\n';
            output += `Email: ${result.email}\n`;
            output += `Format Valid: ${result.format_valid ? 'PASS' : 'FAIL'}\n`;
            output += `Domain Valid: ${result.domain_valid ? 'PASS' : 'FAIL'}\n`;
            output += `SMTP Valid: ${result.smtp_valid ? 'PASS' : 'FAIL'}\n`;
            output += `Overall Valid: ${result.is_valid ? 'PASS' : 'FAIL'}\n`;
            output += `Cached Result: ${result.cached ? 'YES' : 'NO'}\n\n`;
            
            if (result.debug_info) {
                output += 'DEBUG INFORMATION:\n';
                output += '==================\n\n';
                
                if (result.debug_info.steps) {
                    output += 'VALIDATION STEPS:\n';
                    result.debug_info.steps.forEach((step, index) => {
                        output += `  ${index + 1}. ${step}\n`;
                    });
                    output += '\n';
                }
                
                if (result.debug_info.format_check) {
                    output += 'FORMAT CHECK:\n';
                    output += `  Original: ${result.debug_info.format_check.original}\n`;
                    output += `  Sanitized: ${result.debug_info.format_check.sanitized}\n`;
                    output += `  Valid: ${result.debug_info.format_check.valid}\n\n`;
                }
                
                if (result.debug_info.domain) {
                    output += 'DOMAIN CHECK:\n';
                    output += `  Domain: ${result.debug_info.domain}\n`;
                    if (result.debug_info.mx_check !== undefined) {
                        output += `  MX Record: ${result.debug_info.mx_check ? 'FOUND' : 'NOT FOUND'}\n`;
                    }
                    if (result.debug_info.a_check !== undefined) {
                        output += `  A Record: ${result.debug_info.a_check ? 'FOUND' : 'NOT FOUND'}\n`;
                    }
                    if (result.debug_info.mx_records) {
                        output += `  MX Records: ${result.debug_info.mx_records.join(', ')}\n`;
                    }
                    output += '\n';
                }
                
                if (result.debug_info.smtp_domain) {
                    output += 'SMTP CHECK:\n';
                    output += `  Domain: ${result.debug_info.smtp_domain}\n`;
                    if (result.debug_info.smtp_attempts) {
                        output += '  Attempts:\n';
                        result.debug_info.smtp_attempts.forEach(attempt => {
                            output += `    ${attempt}\n`;
                        });
                    }
                    if (result.debug_info.smtp_success_port) {
                        output += `  Success: ${result.debug_info.smtp_success_port}\n`;
                    }
                    output += '\n';
                }
                
                // Show any errors
                ['format_error', 'domain_error', 'smtp_error'].forEach(errorType => {
                    if (result.debug_info[errorType]) {
                        output += `${errorType.toUpperCase()}:\n`;
                        output += `  ${result.debug_info[errorType]}\n\n`;
                    }
                });
            }
            
            if (result.error) {
                output += `ERROR: ${result.error}\n`;
            }
            
            container.textContent = output;
        }

        function displaySmtpConfig(config) {
            const container = document.getElementById('smtp-config');
            
            if (!config || !config.provider_type) {
                container.textContent = 'No SMTP configuration found.\n\nPlease configure SMTP settings in the application.';
                return;
            }
            
            let output = 'SMTP CONFIGURATION\n';
            output += '==================\n\n';
            output += `Provider Type: ${config.provider_type}\n`;
            
            if (config.smtp_host) {
                output += `SMTP Host: ${config.smtp_host}\n`;
            }
            if (config.smtp_port) {
                output += `SMTP Port: ${config.smtp_port}\n`;
            }
            if (config.smtp_username) {
                output += `SMTP Username: ${config.smtp_username}\n`;
            }
            if (config.smtp_password && config.smtp_password !== '••••••••') {
                output += `SMTP Password: [Configured]\n`;
            }
            if (config.api_key && config.api_key !== '••••••••') {
                output += `API Key: [Configured]\n`;
            }
            
            output += `From Email: ${config.from_email}\n`;
            if (config.from_name) {
                output += `From Name: ${config.from_name}\n`;
            }
            
            container.textContent = output;
        }

        function displaySmtpTestResults(result) {
            const container = document.getElementById('smtp-test-results');
            
            let output = 'SMTP TEST RESULTS\n';
            output += '=================\n\n';
            
            if (result.error) {
                output += `ERROR: ${result.error}\n`;
            } else {
                output += `Provider: ${result.provider || 'Unknown'}\n`;
                output += `Success: ${result.success ? 'YES' : 'NO'}\n`;
                output += `Message: ${result.message || 'No message'}\n\n`;
                
                if (result.tests) {
                    output += 'TEST DETAILS:\n';
                    Object.entries(result.tests).forEach(([test, passed]) => {
                        output += `  ${test}: ${passed ? 'PASS' : 'FAIL'}\n`;
                    });
                    output += '\n';
                }
                
                if (result.details && result.details.length > 0) {
                    output += 'DETAILED LOG:\n';
                    result.details.forEach(detail => {
                        output += `  ${detail}\n`;
                    });
                }
            }
            
            container.textContent = output;
        }

        function updateSummary(validationResult) {
            // Update status badges
            document.getElementById('format-status').className = 
                `badge ${validationResult.format_valid ? 'bg-success' : 'bg-danger'}`;
            document.getElementById('format-status').textContent = 
                validationResult.format_valid ? 'Valid' : 'Invalid';
                
            document.getElementById('domain-status').className = 
                `badge ${validationResult.domain_valid ? 'bg-success' : 'bg-danger'}`;
            document.getElementById('domain-status').textContent = 
                validationResult.domain_valid ? 'Valid' : 'Invalid';
                
            document.getElementById('smtp-status').className = 
                `badge ${validationResult.smtp_valid ? 'bg-success' : 'bg-danger'}`;
            document.getElementById('smtp-status').textContent = 
                validationResult.smtp_valid ? 'Valid' : 'Invalid';
                
            document.getElementById('overall-status').className = 
                `badge ${validationResult.is_valid ? 'bg-success' : 'bg-danger'}`;
            document.getElementById('overall-status').textContent = 
                validationResult.is_valid ? 'Valid' : 'Invalid';
            
            // Update summary text
            const summaryContainer = document.getElementById('summary-text');
            summaryContainer.className = `alert ${validationResult.is_valid ? 'alert-success' : 'alert-danger'}`;
            
            let summaryText = `Email validation for "${validationResult.email}" `;
            summaryText += validationResult.is_valid ? 'PASSED' : 'FAILED';
            summaryText += '\n\n';
            
            if (!validationResult.format_valid) {
                summaryText += '❌ Format validation failed - Email format is invalid\n';
            }
            if (!validationResult.domain_valid) {
                summaryText += '❌ Domain validation failed - Domain does not exist or is unreachable\n';
            }
            if (!validationResult.smtp_valid) {
                summaryText += '❌ SMTP validation failed - SMTP server rejected the email\n';
            }
            
            if (validationResult.is_valid) {
                summaryText += '✅ All validation checks passed successfully!';
            }
            
            summaryContainer.textContent = summaryText;
        }

        function generateRecommendations() {
            const container = document.getElementById('recommendations-content');
            const systemInfo = debugResults.systemInfo;
            const validationResult = debugResults.validation;
            const smtpTest = debugResults.smtpTest;
            
            let recommendations = '';
            
            // Check for system issues
            if (systemInfo && systemInfo.functions) {
                if (!systemInfo.functions.checkdnsrr) {
                    recommendations += createRecommendation(
                        'warning',
                        'DNS Functions Not Available',
                        'The checkdnsrr() function is not available. This may cause domain validation to fail.',
                        ['Contact your hosting provider to enable DNS functions',
                         'Consider using alternative validation methods',
                         'Use fallback validation for common domains']
                    );
                }
                
                if (!systemInfo.functions.fsockopen) {
                    recommendations += createRecommendation(
                        'warning',
                        'Socket Functions Not Available',
                        'The fsockopen() function is not available. This will prevent SMTP validation.',
                        ['Contact your hosting provider to enable socket functions',
                         'Use API-based email validation services',
                         'Consider upgrading to a VPS or dedicated server']
                    );
                }
            }
            
            // Check validation issues
            if (validationResult && !validationResult.is_valid) {
                if (!validationResult.format_valid) {
                    recommendations += createRecommendation(
                        'danger',
                        'Email Format Validation Failing',
                        'Basic email format validation is failing. This suggests fundamental issues.',
                        ['Check PHP filter extension is loaded',
                         'Verify input sanitization is working',
                         'Test with known valid email formats']
                    );
                }
                
                if (!validationResult.domain_valid) {
                    recommendations += createRecommendation(
                        'warning',
                        'Domain Validation Issues',
                        'Domain validation is failing. This could be due to DNS restrictions.',
                        ['Check if DNS queries are allowed by hosting provider',
                         'Verify domain actually exists using external tools',
                         'Implement fallback validation for known domains']
                    );
                }
                
                if (!validationResult.smtp_valid) {
                    recommendations += createRecommendation(
                        'warning',
                        'SMTP Validation Issues',
                        'SMTP validation is failing. This is common on shared hosting.',
                        ['Check if SMTP ports (25, 587, 465) are blocked',
                         'Contact hosting provider about outbound SMTP restrictions',
                         'Consider using API-based validation services',
                         'Implement tiered validation (format → domain → SMTP fallback)']
                    );
                }
            }
            
            // Check SMTP configuration
            if (smtpTest && !smtpTest.success) {
                recommendations += createRecommendation(
                    'info',
                    'SMTP Configuration Issues',
                    'SMTP configuration test failed. Email sending may not work.',
                    ['Verify SMTP credentials are correct',
                     'Check API keys for third-party services',
                     'Test SMTP settings with external tools',
                     'Consider using alternative email providers']
                );
            }
            
            // Success case
            if (validationResult && validationResult.is_valid && smtpTest && smtpTest.success) {
                recommendations += createRecommendation(
                    'success',
                    'All Tests Passed',
                    'Your email validation system is working correctly!',
                    ['Monitor validation success rates',
                     'Consider implementing caching for better performance',
                     'Set up regular health checks',
                     'Document your configuration for future reference']
                );
            }
            
            if (recommendations) {
                container.innerHTML = recommendations;
            }
        }

        function createRecommendation(type, title, description, actions) {
            const alertClass = {
                'success': 'alert-success',
                'info': 'alert-info',
                'warning': 'alert-warning',
                'danger': 'alert-danger'
            };
            
            const icon = {
                'success': 'fas fa-check-circle',
                'info': 'fas fa-info-circle',
                'warning': 'fas fa-exclamation-triangle',
                'danger': 'fas fa-times-circle'
            };
            
            let html = `<div class="alert ${alertClass[type]}">`;
            html += `<h6><i class="${icon[type]}"></i> ${title}</h6>`;
            html += `<p>${description}</p>`;
            
            if (actions && actions.length > 0) {
                html += '<ul>';
                actions.forEach(action => {
                    html += `<li>${action}</li>`;
                });
                html += '</ul>';
            }
            
            html += '</div>';
            
            return html;
        }

        function displayError(message) {
            const container = document.getElementById('summary-text');
            container.className = 'alert alert-danger';
            container.textContent = message;
        }

        // Allow Enter key to trigger test
        document.getElementById('test-email').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                document.getElementById('run-debug').click();
            }
        });
    </script>
</body>
</html>