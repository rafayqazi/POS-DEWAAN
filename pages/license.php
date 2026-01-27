<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

requireLogin();

$pageTitle = "Software License";
include '../includes/header.php';
?>

<style>
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

<div class="max-w-5xl mx-auto space-y-8">
    <!-- Developer Portfolio Section (Top Centered) -->
    <div class="flex flex-col items-center justify-center pt-4 pb-8">
        <div class="relative group">
            <div class="absolute -inset-1 bg-gradient-to-r from-teal-500 to-accent rounded-full blur opacity-25 group-hover:opacity-50 transition duration-1000 group-hover:duration-200"></div>
            <div class="relative">
                <img src="../developer.jpg" alt="Developer" class="w-40 h-40 rounded-full object-cover object-top border-4 border-white shadow-2xl">
                <div class="absolute bottom-1 right-2 bg-accent text-white w-10 h-10 rounded-full flex items-center justify-center border-4 border-white shadow-lg">
                    <i class="fas fa-check-circle text-lg"></i>
                </div>
            </div>
        </div>
        <div class="text-center mt-6">
            <h1 class="text-3xl font-extrabold text-gray-900 tracking-tight">Abdul Rafay</h1>
            <p class="text-teal-600 font-semibold text-lg">Lead Developer & Lead Architect</p>
            <div class="flex items-center justify-center gap-4 mt-2 text-gray-500 text-sm">
                <span><i class="fas fa-code mr-1"></i> Full Stack</span>
                <span class="w-1 h-1 bg-gray-300 rounded-full"></span>
                <span><i class="fas fa-terminal mr-1"></i> Solution Architect</span>
            </div>
        </div>
    </div>

    <!-- License Header & Restriction Alert -->
    <div class="alert-banner p-6 flex items-start gap-4">
        <div class="w-12 h-12 rounded-full bg-red-100 flex items-center justify-center flex-shrink-0 text-red-600">
            <i class="fas fa-exclamation-triangle text-xl"></i>
        </div>
        <div>
            <h2 class="text-xl font-bold mb-1">Strict Prohibition of Unauthorized Resale</h2>
            <p class="text-sm opacity-90">This software is licensed exclusively to the original purchaser. Any attempt to sell, distribute, or modify this software without the express written permission of the developer is a violation of international copyright laws and the laws of Pakistan.</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Main Legal Content -->
        <div class="lg:col-span-2 space-y-6">
            <div class="license-card p-8">
                <h2 class="text-2xl font-bold text-gray-800 mb-6 flex items-center">
                    <i class="fas fa-file-contract text-teal-600 mr-3"></i>
                    License Terms & Conditions
                </h2>

                <div class="legal-section">
                    <h3 class="font-bold text-gray-800 text-lg mb-2">1. Ownership & Copyright</h3>
                    <p class="text-gray-600 text-sm leading-relaxed">
                        This software "Fashion Shines POS" and all its source code, designs, and documentation are the intellectual property of <strong>Abdul Rafay (The Developer)</strong>. Under the <strong>Copyright Act, 1962 (Pakistan)</strong>, the developer holds all exclusive rights to this work. No part of this software may be reproduced or transmitted in any form without prior permission.
                    </p>
                </div>

                <div class="legal-section">
                    <h3 class="font-bold text-gray-800 text-lg mb-2">2. Usage Rights</h3>
                    <p class="text-gray-600 text-sm leading-relaxed">
                        The user is granted a non-exclusive, non-transferable license to use this software for their own business operations. You are strictly prohibited from sharing, hosting for others, or selling this software as your own product.
                    </p>
                </div>

                <div class="legal-section">
                    <h3 class="font-bold text-gray-800 text-lg mb-2">3. Prevention of Electronic Crimes</h3>
                    <p class="text-gray-600 text-sm leading-relaxed">
                        Unauthorized access, modification, or distribution of this software's source code may constitute an offense under the <strong>Prevention of Electronic Crimes Act (PECA), 2016</strong>. Piracy and digital theft are subject to heavy fines and imprisonment under Pakistani law.
                    </p>
                </div>

                <div class="legal-section">
                    <h3 class="font-bold text-gray-800 text-lg mb-2">4. Digital Signatures & Validation</h3>
                    <p class="text-gray-600 text-sm leading-relaxed">
                        In accordance with the <strong>Electronic Transactions Ordinance, 2002</strong>, these digital license terms carry the same legal weight as a physical contract. By using this software, you agree to abide by all terms mentioned herein.
                    </p>
                </div>
            </div>
        </div>

        <!-- Developer Info Sidebar -->
        <div class="space-y-6">
            <div class="developer-box p-6 shadow-xl">
                <div class="space-y-4 text-sm mt-2">
                    <div class="flex items-center gap-3 bg-white/10 p-3 rounded-xl border border-white/10">
                        <i class="fas fa-phone-alt text-accent"></i>
                        <span>0300-0358189</span>
                    </div>
                    <div class="flex items-center gap-3 bg-white/10 p-3 rounded-xl border border-white/10">
                        <i class="fas fa-envelope text-accent"></i>
                        <span class="truncate">abdulrafehqazi@gmail.com</span>
                    </div>
                    <div class="flex items-center gap-3 bg-white/10 p-3 rounded-xl border border-white/10">
                        <i class="fas fa-map-marker-alt text-accent"></i>
                        <span>Tando Allahyar, Sindh</span>
                    </div>
                </div>

                <div class="mt-8 pt-6 border-t border-white/10 text-center">
                    <p class="text-[10px] text-teal-300 uppercase tracking-widest font-bold mb-4">Contact for Permissions</p>
                    <div class="flex justify-center gap-4">
                        <a href="https://wa.me/923000358189" class="w-10 h-10 bg-green-500 rounded-lg flex items-center justify-center hover:scale-110 transition-transform">
                            <i class="fab fa-whatsapp text-lg"></i>
                        </a>
                        <a href="https://github.com/rafayqazi" class="w-10 h-10 bg-gray-800 rounded-lg flex items-center justify-center hover:scale-110 transition-transform">
                            <i class="fab fa-github text-lg"></i>
                        </a>
                        <a href="mailto:abdulrafehqazi@gmail.com" class="w-10 h-10 bg-blue-500 rounded-lg flex items-center justify-center hover:scale-110 transition-transform">
                            <i class="fas fa-envelope text-lg"></i>
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="bg-amber-50 border border-amber-200 p-4 rounded-2xl">
                <p class="text-amber-800 text-xs font-medium leading-relaxed italic text-center">
                    "Protecting intellectual property ensures continuous innovation and quality support for your business."
                </p>
            </div>
        </div>
    </div>
</div>

<?php 
include '../includes/footer.php';
echo '</main></div></body></html>'; 
?>
