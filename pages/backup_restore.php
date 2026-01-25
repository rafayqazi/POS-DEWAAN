<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

requireLogin();

$pageTitle = "Backup & Restore";
include '../includes/header.php';
?>

<div class="max-w-4xl mx-auto">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
        <!-- Backup Card -->
        <div class="bg-white rounded-[2.5rem] shadow-sm p-8 border border-gray-100 card-hover transition-all duration-300 glass flex flex-col items-center text-center relative overflow-hidden">
            <div id="backup-loading" class="absolute inset-0 bg-white/90 backdrop-blur-sm z-10 hidden flex-col items-center justify-center p-8 animate-in fade-in duration-300">
                <div class="w-16 h-16 border-4 border-teal-100 border-t-teal-600 rounded-full animate-spin mb-4"></div>
                <p class="font-bold text-teal-800">Preparing Database...</p>
                <p class="text-xs text-teal-600 mt-2">Compressing files, please wait</p>
            </div>
            
            <div class="w-20 h-20 bg-teal-50 text-teal-600 rounded-3xl flex items-center justify-center mb-6 shadow-inner ring-1 ring-teal-100">
                <i class="fas fa-file-archive text-3xl"></i>
            </div>
            <h3 class="text-2xl font-bold text-gray-800 mb-2">Backup Database</h3>
            <p class="text-gray-500 text-sm mb-8 leading-relaxed">
                Download a complete copy of your database in a compressed ZIP format. 
                Keep this file safe to restore your data later.
            </p>
            <button onclick="startBackup()" class="w-full flex items-center justify-center gap-3 px-8 py-4 bg-teal-600 text-white rounded-2xl font-bold hover:bg-teal-700 transition-all shadow-lg shadow-teal-900/10 active:scale-95 group">
                <i class="fas fa-download group-hover:animate-bounce"></i>
                Download Zip Backup
            </button>
        </div>

        <!-- Restore Card -->
        <div class="bg-white rounded-[2.5rem] shadow-sm p-8 border border-gray-100 card-hover transition-all duration-300 glass flex flex-col items-center text-center relative overflow-hidden">
            <div id="restore-progress-container" class="absolute inset-0 bg-white/95 backdrop-blur-sm z-10 hidden flex-col items-center justify-center p-8 animate-in fade-in duration-300">
                <div class="w-full bg-gray-100 h-4 rounded-full overflow-hidden mb-4 shadow-inner">
                    <div id="restore-progress-bar" class="w-0 h-full bg-gradient-to-r from-amber-400 to-orange-500 transition-all duration-300"></div>
                </div>
                <p id="restore-status-text" class="font-bold text-amber-800">Uploading: 0%</p>
                <p class="text-xs text-amber-600 mt-2" id="restore-subtext">Optimizing database tables...</p>
            </div>

            <div class="w-20 h-20 bg-amber-50 text-amber-600 rounded-3xl flex items-center justify-center mb-6 shadow-inner ring-1 ring-amber-100">
                <i class="fas fa-file-upload text-3xl"></i>
            </div>
            <h3 class="text-2xl font-bold text-gray-800 mb-2">Restore Database</h3>
            <p class="text-gray-500 text-sm mb-8 leading-relaxed">
                Upload a previously downloaded ZIP backup to restore your data. 
                <span class="text-red-500 font-bold underline italic">Warning: This will overwrite all current data!</span>
            </p>
            
            <form id="restoreForm" class="w-full">
                <div class="relative mb-4 group">
                    <input type="file" name="backup_file" id="backup_file" class="hidden" accept=".zip" required onchange="updateFileName(this)">
                    <label for="backup_file" id="drop-zone" class="flex flex-col items-center justify-center gap-3 px-8 py-8 bg-gray-50 text-gray-700 rounded-2xl font-bold border-2 border-dashed border-gray-200 hover:border-amber-400 hover:bg-amber-50/30 transition-all cursor-pointer">
                        <div class="w-12 h-12 bg-white rounded-full flex items-center justify-center shadow-sm mb-1 group-hover:scale-110 transition-transform">
                            <i class="fas fa-cloud-upload-alt text-amber-500 text-xl"></i>
                        </div>
                        <div class="flex flex-col">
                            <span id="file-name-display">Drag & Drop ZIP File</span>
                            <span class="text-[10px] text-gray-400 font-normal uppercase tracking-widest mt-1">or click to browse</span>
                        </div>
                    </label>
                </div>
                <button type="button" onclick="handleRestore()" class="w-full flex items-center justify-center gap-3 px-8 py-4 bg-amber-500 text-white rounded-2xl font-bold hover:bg-amber-600 transition-all shadow-lg shadow-amber-900/10 active:scale-95">
                    <i class="fas fa-sync-alt"></i>
                    Upload & Restore
                </button>
            </form>
        </div>
    </div>

    <!-- Danger Zone / Reset -->
    <div class="mt-8 bg-red-50/50 backdrop-blur-sm rounded-[2.5rem] p-8 border border-red-100 flex flex-col md:flex-row items-center justify-between gap-6 group">
        <div class="flex items-center gap-6">
            <div class="w-16 h-16 bg-red-100 text-red-600 rounded-2xl flex items-center justify-center flex-shrink-0 shadow-sm group-hover:bg-red-600 group-hover:text-white transition-all duration-300">
                <i class="fas fa-trash-alt text-2xl"></i>
            </div>
            <div>
                <h4 class="text-xl font-bold text-gray-800">System Reset</h4>
                <p class="text-sm text-gray-500 font-medium leading-relaxed">
                    Erase all data and start fresh. This will clear inventory, sales, customers, and transactions. 
                    <br><span class="text-red-600 font-bold uppercase tracking-wider text-[10px]">Passwords are required for security</span>
                </p>
            </div>
        </div>
        <button onclick="openResetModal()" class="px-8 py-4 bg-white text-red-600 border-2 border-red-200 rounded-2xl font-bold hover:bg-red-600 hover:text-white hover:border-red-600 transition-all active:scale-95 shadow-lg shadow-red-900/5">
            Reset Application
        </button>
    </div>

    <!-- Alert History / Info ... -->
</div>

<!-- Reset Password Modal -->
<div id="resetModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm hidden z-[9999] items-center justify-center p-4">
    <div class="bg-white rounded-[2.5rem] shadow-2xl max-w-md w-full transform transition-all animate-in fade-in zoom-in duration-200 overflow-hidden">
        <div class="p-8 text-center bg-red-50/30 border-b border-red-50">
            <div class="w-20 h-20 bg-red-100 text-red-600 rounded-full flex items-center justify-center mx-auto mb-4 border-4 border-white shadow-lg">
                <i class="fas fa-user-shield text-3xl"></i>
            </div>
            <h3 class="text-2xl font-bold text-gray-900 mb-2">Admin Required</h3>
            <p class="text-gray-500 text-sm">Please enter your password to confirm identity and authorize the system reset.</p>
        </div>
        <div class="p-8">
            <input type="password" id="reset_password" placeholder="Enter your password" class="w-full px-6 py-4 bg-gray-50 border-2 border-gray-100 rounded-2xl focus:border-red-500 focus:bg-white transition-all outline-none font-medium text-center mb-6">
            <div class="flex gap-4">
                <button onclick="closeResetModal()" class="flex-1 bg-gray-100 text-gray-600 font-bold py-4 rounded-2xl hover:bg-gray-200 transition-colors active:scale-95">
                    Cancel
                </button>
                <button onclick="confirmReset()" class="flex-1 bg-red-600 text-white font-bold py-4 rounded-2xl hover:bg-red-700 transition-all shadow-lg shadow-red-900/20 active:scale-95">
                    Proceed Reset
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    function updateFileName(input) {
        const file = input.files[0];
        if (!file) {
            document.getElementById('file-name-display').innerText = 'Drag & Drop ZIP File';
            document.getElementById('file-name-display').classList.remove('text-amber-600');
            return;
        }

        if (!file.name.endsWith('.zip')) {
            showAlert("Please upload a valid ZIP file.", "Invalid File Type");
            input.value = '';
            document.getElementById('file-name-display').innerText = 'Drag & Drop ZIP File';
            return;
        }

        document.getElementById('file-name-display').innerText = file.name;
        document.getElementById('file-name-display').classList.add('text-amber-600');
    }

    // Drag and Drop Logic
    const dropZone = document.getElementById('drop-zone');
    const fileInput = document.getElementById('backup_file');

    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, preventDefaults, false);
    });

    function preventDefaults (e) {
        e.preventDefault();
        e.stopPropagation();
    }

    ['dragenter', 'dragover'].forEach(eventName => {
        dropZone.addEventListener(eventName, () => {
            dropZone.classList.add('border-amber-400', 'bg-amber-50');
        }, false);
    });

    ['dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, () => {
            dropZone.classList.remove('border-amber-400', 'bg-amber-50');
        }, false);
    });

    dropZone.addEventListener('drop', handleDrop, false);

    function handleDrop(e) {
        const dt = e.dataTransfer;
        const files = dt.files;

        if (files.length > 0) {
            fileInput.files = files;
            updateFileName(fileInput);
        }
    }

    function startBackup() {
        const loading = document.getElementById('backup-loading');
        loading.classList.remove('hidden');
        loading.classList.add('flex');
        
        setTimeout(() => {
            window.location.href = '../actions/backup_process.php';
        }, 500);

        setTimeout(() => {
            loading.classList.add('hidden');
            loading.classList.remove('flex');
        }, 5000);
    }

    function handleRestore() {
        const fileInput = document.getElementById('backup_file');
        const file = fileInput.files[0];
        
        if (!file) {
            showAlert("Please select a ZIP file first.", "No File Selected");
            return;
        }

        showConfirm("WARNING: Restoring will delete all current data and replace it with the backup content. This action cannot be undone. Are you absolutely sure?", () => {
            const formData = new FormData();
            formData.append('backup_file', file);

            const container = document.getElementById('restore-progress-container');
            const progressBar = document.getElementById('restore-progress-bar');
            const statusText = document.getElementById('restore-status-text');
            const subtext = document.getElementById('restore-subtext');

            container.classList.remove('hidden');
            container.classList.add('flex');

            const xhr = new XMLHttpRequest();
            
            xhr.upload.onprogress = function(e) {
                if (e.lengthComputable) {
                    const percent = Math.round((e.loaded / e.total) * 100);
                    progressBar.style.width = percent + '%';
                    statusText.innerText = 'Uploading: ' + percent + '%';
                    if (percent === 100) {
                        subtext.innerText = 'Extracting and restoring database...';
                    }
                }
            };

            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4) {
                    container.classList.add('hidden');
                    container.classList.remove('flex');
                    
                    try {
                        const response = JSON.parse(xhr.responseText);
                        if (response.success) {
                            showAlert(response.message, "Success");
                            fileInput.value = '';
                            document.getElementById('file-name-display').innerText = 'Choose ZIP File';
                            document.getElementById('file-name-display').classList.remove('text-amber-600');
                        } else {
                            showAlert(response.message, "Restoration Failed");
                        }
                    } catch (e) {
                        showAlert("An unexpected error occurred during restoration.", "System Error");
                    }
                }
            };

            xhr.open('POST', '../actions/restore_process.php', true);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.send(formData);
        }, "Confirm Restore");
    }

    function openResetModal() {
        const modal = document.getElementById('resetModal');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        document.getElementById('reset_password').focus();
    }

    function closeResetModal() {
        const modal = document.getElementById('resetModal');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        document.getElementById('reset_password').value = '';
    }

    function confirmReset() {
        const password = document.getElementById('reset_password').value;
        if (!password) {
            showAlert("Password is required to proceed.", "Required");
            return;
        }

        closeResetModal();
        
        showConfirm("CRITICAL WARNING: This will permanently delete ALL data. Only user accounts will be preserved. This cannot be undone. Proceed?", () => {
            const formData = new FormData();
            formData.append('password', password);

            const xhr = new XMLHttpRequest();
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        if (response.success) {
                            showAlert(response.message, "System Reset Done");
                            setTimeout(() => window.location.reload(), 3000);
                        } else {
                            showAlert(response.message, "Reset Failed");
                        }
                    } catch (e) {
                        showAlert("An error occurred during system reset.", "Error");
                    }
                }
            };

            xhr.open('POST', '../actions/reset_process.php', true);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.send(formData);
        }, "Final Warning");
    }
</script>

<?php 
// Show success/error messages from session
if (isset($_SESSION['success'])) {
    echo "<script>showAlert('" . $_SESSION['success'] . "', 'Success');</script>";
    unset($_SESSION['success']);
}
if (isset($_SESSION['error'])) {
    echo "<script>showAlert('" . $_SESSION['error'] . "', 'Error');</script>";
    unset($_SESSION['error']);
}

include '../includes/footer.php';
echo '</main></div></body></html>'; 
?>
