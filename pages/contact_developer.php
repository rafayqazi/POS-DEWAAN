<?php
// pages/contact_developer.php
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Critical Error - Contact Developer</title>
    <!-- Use Tailwind CSS for premium styling -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Outfit', sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 100%);
            min-height: 100-vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            overflow: hidden;
        }

        .glass-container {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 24px;
            padding: 3rem;
            max-width: 500px;
            width: 90%;
            text-align: center;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            animation: fadeIn 0.8s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .error-icon {
            font-size: 4rem;
            background: linear-gradient(to right, #f43f5e, #fb7185);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 1.5rem;
            filter: drop-shadow(0 0 10px rgba(244, 63, 94, 0.3));
        }

        .social-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(60px, 1fr));
            gap: 1rem;
            margin-top: 2rem;
        }

        .social-link {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 55px;
            height: 55px;
            border-radius: 16px;
            font-size: 1.5rem;
            color: white;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .social-link:hover {
            transform: translateY(-5px);
            background: rgba(255, 255, 255, 0.15);
            border-color: rgba(255, 255, 255, 0.3);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.3);
        }

        .linkedin:hover { color: #0077b5; border-color: #0077b5; }
        .facebook:hover { color: #1877f2; border-color: #1877f2; }
        .instagram:hover { color: #e4405f; border-color: #e4405f; }
        .github:hover { color: #fff; border-color: #fff; }
        .whatsapp:hover { color: #25d366; border-color: #25d366; }

        .pulsate {
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
    </style>
</head>
<body class="bg-slate-950">

    <div class="glass-container">
        <div class="error-icon pulsate">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        
        <h1 class="text-3xl font-bold text-white mb-2">System Restricted</h1>
        <p class="text-slate-400 text-lg mb-6">Critical system files are missing or the setup is incomplete. System access has been locked for security.</p>
        
        <div class="h-px bg-gradient-to-right from-transparent via-slate-700 to-transparent w-full mb-6"></div>
        
        <h2 class="text-white font-semibold mb-4">Contact Developer to Restore</h2>
        
        <div class="flex flex-col gap-3 mb-8">
             <div class="bg-slate-800/50 rounded-xl p-4 border border-slate-700 flex items-center justify-between group hover:border-slate-500 transition-colors">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-green-500/10 flex items-center justify-center text-green-500">
                        <i class="fab fa-whatsapp text-xl"></i>
                    </div>
                    <div class="text-left">
                        <p class="text-xs text-slate-500 uppercase tracking-wider font-bold">WhatsApp</p>
                        <p class="text-white font-medium">0300 0358189</p>
                    </div>
                </div>
                <a href="https://wa.me/923000358189" target="_blank" class="bg-green-500 hover:bg-green-600 text-white px-4 py-1.5 rounded-lg text-sm font-semibold transition-all">Chat</a>
            </div>
        </div>

        <div class="social-grid">
            <a href="https://www.linkedin.com/in/abdulrafayqazi/" target="_blank" class="social-link linkedin" title="LinkedIn">
                <i class="fab fa-linkedin-in"></i>
            </a>
            <a href="https://web.facebook.com/rafeH.QAZI" target="_blank" class="social-link facebook" title="Facebook">
                <i class="fab fa-facebook-f"></i>
            </a>
            <a href="https://www.instagram.com/abdulrafayqazi/" target="_blank" class="social-link instagram" title="Instagram">
                <i class="fab fa-instagram"></i>
            </a>
            <a href="https://github.com/rafayqazi" target="_blank" class="social-link github" title="GitHub">
                <i class="fab fa-github"></i>
            </a>
        </div>

        <p class="mt-8 text-slate-500 text-xs">
            &copy; <?= date('Y') ?> Abdul Rafay Development. All rights reserved.
        </p>
    </div>

</body>
</html>
