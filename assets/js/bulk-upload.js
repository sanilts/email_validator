document.addEventListener('DOMContentLoaded', function() {
    const uploadArea = document.getElementById('uploadArea');
    const fileInput = document.getElementById('csv_file');
    const bulkForm = document.getElementById('bulkForm');
    const progressCard = document.getElementById('progressCard');
    
    // File upload drag and drop
    if (uploadArea && fileInput) {
        uploadArea.addEventListener('click', () => fileInput.click());
        
        uploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadArea.classList.add('dragover');
        });
        
        uploadArea.addEventListener('dragleave', () => {
            uploadArea.classList.remove('dragover');
        });
        
        uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadArea.classList.remove('dragover');
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                fileInput.files = files;
                updateUploadAreaText(files[0]);
            }
        });
        
        fileInput.addEventListener('change', (e) => {
            if (e.target.files.length > 0) {
                updateUploadAreaText(e.target.files[0]);
            }
        });
    }
    
    function updateUploadAreaText(file) {
        const icon = uploadArea.querySelector('i');
        const title = uploadArea.querySelector('h5');
        const subtitle = uploadArea.querySelector('p');
        
        icon.className = 'fas fa-file-csv fa-3x text-success mb-3';
        title.textContent = file.name;
        subtitle.textContent = `Size: ${formatFileSize(file.size)}`;
    }
    
    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
    
    // Start bulk processing if there's a current job
    const currentJobId = getCurrentJobId();
    if (currentJobId && progressCard) {
        startBulkValidation(currentJobId);
    }
    
    function getCurrentJobId() {
        // This would normally come from PHP session or be passed to the page
        const params = new URLSearchParams(window.location.search);
        return params.get('job_id');
    }
    
    function startBulkValidation(jobId) {
        let startIndex = 0;
        
        function processNext() {
            makeRequest('../api/bulk_process.php', {
                method: 'POST',
                body: JSON.stringify({
                    job_id: jobId,
                    start_index: startIndex
                })
            })
            .then(response => {
                if (response.success) {
                    updateProgress(response.processed, response.total);
                    
                    if (!response.complete) {
                        startIndex = response.next_index;
                        setTimeout(processNext, 1000); // 1 second delay between batches
                    } else {
                        onValidationComplete();
                    }
                } else {
                    showAlert('Validation failed: ' + response.message, 'danger');
                }
            })
            .catch(error => {
                console.error('Validation error:', error);
                showAlert('An error occurred during validation', 'danger');
            });
        }
        
        processNext();
    }
    
    function updateProgress(processed, total) {
        const progressBar = document.getElementById('progressBar');
        const progressText = document.getElementById('progressText');
        const processedCount = document.getElementById('processedCount');
        
        const percentage = Math.round((processed / total) * 100);
        
        if (progressBar) progressBar.style.width = percentage + '%';
        if (progressText) progressText.textContent = percentage + '%';
        if (processedCount) processedCount.textContent = formatNumber(processed);
        
        // Update valid/invalid counts via separate API call
        updateValidationCounts();
    }
    
    function updateValidationCounts() {
        const jobId = getCurrentJobId();
        if (!jobId) return;
        
        makeRequest(`../api/progress.php?job_id=${jobId}`)
            .then(response => {
                if (response.success) {
                    const job = response.job;
                    const validCount = document.getElementById('validCount');
                    const invalidCount = document.getElementById('invalidCount');
                    
                    if (validCount) validCount.textContent = formatNumber(job.valid_emails);
                    if (invalidCount) invalidCount.textContent = formatNumber(job.invalid_emails);
                }
            })
            .catch(error => console.error('Progress update error:', error));
    }
    
    function onValidationComplete() {
        showAlert('Bulk validation completed successfully!', 'success');
        
        // Hide progress card
        if (progressCard) {
            progressCard.style.display = 'none';
        }
        
        // Refresh the page after a delay
        setTimeout(() => {
            window.location.reload();
        }, 2000);
    }
    
    // Cancel button
    const cancelBtn = document.getElementById('cancelBtn');
    if (cancelBtn) {
        cancelBtn.addEventListener('click', function() {
            if (confirm('Are you sure you want to cancel the validation?')) {
                // In a real implementation, you'd send a cancel request to the server
                showAlert('Validation cancelled', 'warning');
                if (progressCard) {
                    progressCard.style.display = 'none';
                }
            }
        });
    }
});