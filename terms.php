<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Terms & Conditions - DEWAAN</title>
    <script src="assets/js/tailwind.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#0f766e',
                        secondary: '#134e4a',
                        accent: '#f59e0b',
                    }
                }
            }
        }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/all.min.css">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; }
        .license-card {
            background: white;
            border-radius: 2rem;
            overflow: hidden;
            border: 1px solid #e2e8f0;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }
        .legal-section {
            border-left: 4px solid #0f766e;
            padding-left: 1.5rem;
            margin-bottom: 2rem;
        }
        .developer-box {
            background: linear-gradient(135deg, #0f766e 0%, #134e4a 100%);
            color: white;
            border-radius: 1.5rem;
        }
        .alert-banner {
            background: #fef2f2;
            border: 1px solid #fee2e2;
            color: #991b1b;
            border-radius: 1rem;
        }
    </style>
</head>
<body class="bg-gray-50 text-gray-800 p-4 md:p-8">

<div class="max-w-5xl mx-auto space-y-8">
    <!-- Header -->
    <div class="text-center mb-12">
        <h1 class="text-4xl font-black text-teal-800 tracking-tighter">DEWAAN</h1>
        <p class="text-gray-500 font-bold uppercase tracking-widest text-xs mt-2">Software Terms & Legal Policy</p>
    </div>

    <!-- Restriction Alert -->
    <div class="alert-banner p-6 flex items-start gap-4">
        <div class="w-12 h-12 rounded-full bg-red-100 flex items-center justify-center flex-shrink-0 text-red-600">
            <i class="fas fa-exclamation-triangle text-xl"></i>
        </div>
        <div>
            <h2 class="text-xl font-bold mb-1">Ownership & Usage Warning</h2>
            <p class="text-sm opacity-90">This software is NOT free and is NOT public property. Unauthorized use or distribution without the developer's explicit consent is strictly prohibited and illegal.</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Main Content -->
        <div class="lg:col-span-2 space-y-6">
            <div class="license-card p-8">
                <h2 class="text-2xl font-bold text-gray-800 mb-6 flex items-center">
                    <i class="fas fa-file-contract text-teal-600 mr-3"></i>
                    Agreement Terms
                </h2>

                <div class="legal-section">
                    <h3 class="font-bold text-gray-800 text-lg mb-2">1. Intellectual Property</h3>
                    <p class="text-gray-600 text-sm leading-relaxed">
                        All source code, designs, and logical structures of "POS DEWAAN" are the exclusive property of <strong>Abdul Rafay (The Developer)</strong>. Under copyright law, you are granted a license to USE the software, not own its code.
                    </p>
                </div>

                <div class="legal-section">
                    <h3 class="font-bold text-gray-800 text-lg mb-2">2. Mandatory Purchase</h3>
                    <p class="text-gray-600 text-sm leading-relaxed">
                        To use this software for commercial purposes, you must purchase a valid license from the developer. Using cracked or unauthorized copies puts your business data at risk and is a violation of the developer's rights.
                    </p>
                </div>

                <div class="legal-section">
                    <h3 class="font-bold text-gray-800 text-lg mb-2">3. Contact for Purchase</h3>
                    <p class="text-gray-600 text-sm leading-relaxed">
                        If you have obtained this software through a third party or wish to formalize your license, please contact the developer immediately using the information provided on this page.
                    </p>
                </div>
            </div>
        </div>

        <!-- Developer Info Sidebar -->
        <div class="space-y-6">
            <div class="developer-box p-6 shadow-xl">
                 <div class="flex flex-col items-center mb-6">
                    <img src="developer.jpg" alt="Developer" class="w-24 h-24 rounded-full border-4 border-white/20 mb-4 object-cover object-top">
                    <h3 class="font-bold text-lg">Abdul Rafay</h3>
                    <p class="text-teal-300 text-xs uppercase font-bold">Software Architect</p>
                </div>
                <div class="space-y-4 text-sm">
                    <div class="flex items-center gap-3 bg-white/10 p-3 rounded-xl border border-white/10">
                        <i class="fas fa-phone-alt text-accent"></i>
                        <span>0300-0358189</span>
                    </div>
                    <div class="flex items-center gap-3 bg-white/10 p-3 rounded-xl border border-white/10">
                        <i class="fas fa-envelope text-accent"></i>
                        <span class="truncate text-xs">abdulrafehqazi@gmail.com</span>
                    </div>
                </div>

                <div class="mt-8 pt-6 border-t border-white/10 text-center">
                    <p class="text-[10px] text-teal-300 uppercase tracking-widest font-bold mb-4">Contact Now</p>
                    <div class="flex justify-center gap-4">
                        <a href="https://wa.me/923000358189" class="w-10 h-10 bg-green-500 rounded-lg flex items-center justify-center hover:scale-110 transition-transform">
                            <i class="fab fa-whatsapp text-lg"></i>
                        </a>
                        <a href="mailto:abdulrafehqazi@gmail.com" class="w-10 h-10 bg-blue-500 rounded-lg flex items-center justify-center hover:scale-110 transition-transform">
                            <i class="fas fa-envelope text-lg"></i>
                        </a>
                    </div>
                </div>
            </div>
            
            <a href="login.php" class="block w-full py-4 bg-gray-800 text-white text-center rounded-2xl font-bold hover:bg-black transition-all shadow-lg active:scale-95">
                <i class="fas fa-arrow-left mr-2"></i> Back to Login
            </a>
        </div>
    </div>
</div>

</body>
</html>
