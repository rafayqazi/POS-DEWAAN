<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

requireLogin();

$update_status = getUpdateStatus();
if (!$update_status['available'] || !$update_status['overdue']) {
    redirect('../index.php');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Locked - Update Required</title>
    <link rel="icon" type="image/png" href="../<?= getSetting('business_favicon', 'assets/img/favicon.png') ?>">
    <script src="../assets/js/tailwind.js"></script>
    <link rel="stylesheet" href="../assets/css/all.min.css">
    <style>
        .animate-float { animation: float 6s ease-in-out infinite; }
        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-20px); }
        }
    </style>
</head>
<body class="bg-slate-900 flex items-center justify-center min-h-screen p-6 overflow-hidden">
    <div class="max-w-xl w-full text-center relative">
        <!-- Background Glow -->
        <div class="absolute inset-0 bg-teal-500/20 blur-[120px] rounded-full -z-10 animate-pulse"></div>

        <div class="bg-white/5 backdrop-blur-2xl border border-white/10 rounded-[3rem] p-12 shadow-2xl">
            <div class="w-32 h-32 bg-red-500/10 text-red-500 rounded-[2.5rem] flex items-center justify-center mx-auto mb-10 border border-red-500/20 animate-float shadow-lg shadow-red-500/10">
                <i class="fas fa-user-lock text-5xl"></i>
            </div>

            <h1 class="text-4xl font-black text-white mb-4 tracking-tighter">System Locked</h1>
            <p class="text-slate-400 font-medium mb-10 leading-relaxed text-lg">
                The 24-hour grace period for the software update has expired. <br>
                <span class="text-red-400 font-bold uppercase tracking-widest text-sm">Action Required:</span> <br>
                Please update your software to the latest version to restore full functionality.
            </p>

            <div class="space-y-4">
                <button onclick="startLockdownUpdate()" id="updateBtn" class="w-full py-5 bg-teal-600 hover:bg-teal-500 text-white rounded-2xl font-black text-lg transition-all active:scale-[0.98] shadow-xl shadow-teal-900/20 flex items-center justify-center gap-3">
                    <i class="fas fa-cloud-download-alt"></i> Update & Unlock Now
                </button>
                <p class="text-slate-500 text-xs font-bold uppercase tracking-widest">Database will be backed up automatically</p>
            </div>
        </div>

        <div class="mt-12">
            <p class="text-slate-600 text-[10px] font-black uppercase tracking-[0.3em]"><?= getSetting('business_name', 'Fashion Shines') ?> Security Shield</p>
        </div>
    </div>

    <!-- Update Overlay (Copied from index.php but modified for lockdown) -->
    <div id="updateOverlay" class="fixed inset-0 bg-black/80 backdrop-blur-xl hidden z-[9999] flex-col items-center justify-center text-center p-6">
        <div class="bg-white rounded-[3rem] p-10 max-w-sm w-full shadow-2xl">
            <i class="fas fa-cog fa-spin text-5xl text-teal-600 mb-6"></i>
            <h3 class="text-2xl font-black text-gray-800 mb-2">Restoring Access</h3>
            <p id="statusText" class="text-gray-500 text-sm mb-6 font-medium">Downloading backup and installing core updates...</p>
            <div class="w-full bg-gray-100 h-2 rounded-full overflow-hidden">
                <div id="progressBar" class="h-full bg-teal-500 w-0 transition-all duration-1000"></div>
            </div>
        </div>
    </div>
    
    <iframe id="backupFrame" style="display:none;"></iframe>

    <script>
    async function startLockdownUpdate() {
        const btn = document.getElementById('updateBtn');
        const overlay = document.getElementById('updateOverlay');
        const bar = document.getElementById('progressBar');
        const status = document.getElementById('statusText');
        const backupFrame = document.getElementById('backupFrame');

        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Initializing...';
        overlay.classList.remove('hidden');
        overlay.classList.add('flex');

        try {
            status.innerText = "Securing your data (Backup)...";
            bar.style.width = "30%";
            backupFrame.src = '../actions/backup_process.php';
            
            await new Promise(r => setTimeout(r, 3000));
            
            status.innerText = "Applying latest version...";
            bar.style.width = "70%";
            
            const response = await fetch('../pages/settings.php', {
                method: 'POST',
                body: new URLSearchParams({ 'action': 'do_update' })
            });
            
            const data = await response.json();
            if(data.status === 'success') {
                bar.style.width = "100%";
                status.innerText = "Unlock Successful! Restarting...";
                setTimeout(() => window.location.href = '../logout.php', 2000);
            } else {
                throw new Error(data.message);
            }
        } catch (e) {
            overlay.classList.add('hidden');
            alert("Lockdown Update Failed: " + e.message);
            btn.disabled = false;
        }
    }
    </script>
</body>
</html>
