<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

requireLogin();

$pageTitle = "About Developer";
include '../includes/header.php';
?>

<style>
    .profile-card {
        background: linear-gradient(135deg, #0f766e 0%, #134e4a 100%);
        border-radius: 2rem;
        overflow: hidden;
    }
    .skill-badge {
        display: inline-block;
        padding: 0.5rem 1rem;
        margin: 0.25rem;
        background: rgba(20, 184, 166, 0.1);
        border: 1px solid rgba(20, 184, 166, 0.3);
        border-radius: 0.75rem;
        font-size: 0.75rem;
        font-weight: 600;
        color: #0f766e;
        transition: all 0.2s;
    }
    .skill-badge:hover {
        background: rgba(20, 184, 166, 0.2);
        transform: translateY(-2px);
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }
    .experience-card {
        position: relative;
        padding-left: 2rem;
        border-left: 3px solid #0f766e;
        margin-bottom: 2rem;
    }
    .experience-card::before {
        content: '';
        position: absolute;
        left: -0.5rem;
        top: 0;
        width: 1rem;
        height: 1rem;
        background: #0f766e;
        border-radius: 50%;
        border: 3px solid white;
    }
    .social-link {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 3rem;
        height: 3rem;
        border-radius: 50%;
        background: #f0fdfa;
        color: #0f766e;
        transition: all 0.3s;
        font-size: 1.25rem;
    }
    .social-link:hover {
        background: #0f766e;
        color: white;
        transform: translateY(-4px);
        box-shadow: 0 8px 16px rgba(15, 118, 110, 0.3);
    }
</style>

<div class="max-w-6xl mx-auto">
    <!-- Header Section with Profile -->
    <div class="profile-card mb-8 text-white p-8 shadow-2xl">
        <div class="flex flex-col md:flex-row items-center gap-8">
            <div class="w-32 h-32 rounded-full bg-white/20 backdrop-blur-sm flex items-center justify-center text-6xl font-bold">
                AR
            </div>
            <div class="flex-1 text-center md:text-left">
                <h1 class="text-4xl font-bold mb-2">Abdul Rafay</h1>
                <p class="text-xl text-teal-100 mb-4">Primary Educator | Web Developer | Content Writer</p>
                <div class="flex flex-wrap gap-4 justify-center md:justify-start text-sm">
                    <span><i class="fas fa-phone mr-2"></i>0300-0358189 / 0371-0273699</span>
                    <span><i class="fas fa-envelope mr-2"></i>abdulrafehqazi@gmail.com</span>
                    <span><i class="fas fa-map-marker-alt mr-2"></i>Tando Allahyar, Sindh, Pakistan</span>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Left Column -->
        <div class="lg:col-span-2 space-y-8">
            <!-- Personal Profile -->
            <div class="bg-white rounded-2xl shadow-lg p-6 border border-gray-100">
                <h2 class="text-2xl font-bold text-gray-800 mb-4 flex items-center">
                    <i class="fas fa-user-circle text-teal-600 mr-3"></i>
                    Personal Profile
                </h2>
                <p class="text-gray-600 leading-relaxed mb-4">
                    Passionate primary educator with a strong foundation in teaching young learners (ages 5-11) and robust computing skills. Experienced in creating engaging lesson plans, fostering inclusive classrooms, and integrating educational technology to enhance student learning.
                </p>
                <p class="text-gray-600 leading-relaxed">
                    <strong>Objective:</strong> Highly motivated and ambitious individual seeking a challenging position in a reputable organization where I can utilize my skills to make a positive contribution. Eager to learn, grow, and take on new challenges while delivering high-quality work. A proactive and detail-oriented professional with excellent communication and problem-solving skills, dedicated to continuous learning and improvement.
                </p>
            </div>

            <!-- Professional Experience -->
            <div class="bg-white rounded-2xl shadow-lg p-6 border border-gray-100">
                <h2 class="text-2xl font-bold text-gray-800 mb-6 flex items-center">
                    <i class="fas fa-briefcase text-teal-600 mr-3"></i>
                    Professional Experience
                </h2>
                
                <div class="space-y-6">
                    <div class="experience-card">
                        <div class="flex justify-between items-start mb-2">
                            <h3 class="text-lg font-bold text-gray-800">Primary School Teacher</h3>
                            <span class="text-sm font-semibold text-teal-600">2022 – Present</span>
                        </div>
                        <p class="text-sm text-gray-500 mb-2">Government Boys Primary School Ali Bux Jarwar</p>
                        <ul class="list-disc list-inside text-sm text-gray-600 space-y-1">
                            <li>Educating young learners with focus on core curriculum and digital literacy</li>
                            <li>Developing engaging lesson plans and fostering inclusive classroom environment</li>
                            <li>Integrating modern educational tools to improve student engagement</li>
                        </ul>
                    </div>

                    <div class="experience-card">
                        <div class="flex justify-between items-start mb-2">
                            <h3 class="text-lg font-bold text-gray-800">Student Brand Ambassador (Intern)</h3>
                            <span class="text-sm font-semibold text-teal-600">2022 (6-Month)</span>
                        </div>
                        <p class="text-sm text-gray-500 mb-2">State Bank of Pakistan (SBP) | NFLP-Y</p>
                        <ul class="list-disc list-inside text-sm text-gray-600 space-y-1">
                            <li>Completed virtual internship with "Gold" distinction</li>
                            <li>Promoted financial literacy through 'PomPak – Learn to Earn' initiative</li>
                        </ul>
                    </div>

                    <div class="experience-card">
                        <div class="flex justify-between items-start mb-2">
                            <h3 class="text-lg font-bold text-gray-800">Web & WordPress Developer</h3>
                            <span class="text-sm font-semibold text-teal-600">2019 – Present</span>
                        </div>
                        <p class="text-sm text-gray-500 mb-2">Freelance Developer</p>
                        <ul class="list-disc list-inside text-sm text-gray-600 space-y-1">
                            <li>Developed 20+ functional websites for diverse clients</li>
                            <li>Implemented custom themes and plugins for business requirements</li>
                        </ul>
                    </div>

                    <div class="experience-card">
                        <div class="flex justify-between items-start mb-2">
                            <h3 class="text-lg font-bold text-gray-800">Content Writer</h3>
                            <span class="text-sm font-semibold text-teal-600">2018 – Present</span>
                        </div>
                        <p class="text-sm text-gray-500 mb-2">Digital Media Specialist</p>
                        <ul class="list-disc list-inside text-sm text-gray-600 space-y-1">
                            <li>Contributing to The Daws, Neerpear.org, Orator Magazine, Visual Paradigm Magazine</li>
                            <li>Authoring instructional materials for online platforms</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Final Year Project -->
            <div class="bg-white rounded-2xl shadow-lg p-6 border border-gray-100">
                <h2 class="text-2xl font-bold text-gray-800 mb-4 flex items-center">
                    <i class="fas fa-robot text-teal-600 mr-3"></i>
                    Final Year Project
                </h2>
                <h3 class="text-lg font-bold text-gray-800 mb-2">COVID-19 Inspection Robot</h3>
                <p class="text-sm text-gray-600 mb-3">
                    Developed a fully functional COVID-19 inspection robot using integrated IoT, Data Science, AI, Machine Learning, and Computer Vision technologies.
                </p>
                <div class="bg-teal-50 rounded-xl p-4 mb-3">
                    <h4 class="text-sm font-bold text-teal-800 mb-2">Robot Features:</h4>
                    <ul class="space-y-1 text-xs text-gray-700">
                        <li class="flex items-start"><i class="fas fa-check-circle text-teal-600 mr-2 mt-0.5"></i>Automatic mask detection on human face</li>
                        <li class="flex items-start"><i class="fas fa-check-circle text-teal-600 mr-2 mt-0.5"></i>Vaccination card verification system</li>
                        <li class="flex items-start"><i class="fas fa-check-circle text-teal-600 mr-2 mt-0.5"></i>Touch-less body temperature sensor</li>
                        <li class="flex items-start"><i class="fas fa-check-circle text-teal-600 mr-2 mt-0.5"></i>Automatic hand sanitization</li>
                        <li class="flex items-start"><i class="fas fa-check-circle text-teal-600 mr-2 mt-0.5"></i>Automated entrance door control after SOP verification</li>
                    </ul>
                </div>
                <p class="text-xs text-gray-500 italic">Got position in Final Year Project at SAU University</p>
            </div>

            <!-- Achievements -->
            <div class="bg-white rounded-2xl shadow-lg p-6 border border-gray-100">
                <h2 class="text-2xl font-bold text-gray-800 mb-4 flex items-center">
                    <i class="fas fa-trophy text-teal-600 mr-3"></i>
                    Achievements & Workshops
                </h2>
                <ul class="space-y-2 text-sm text-gray-600">
                    <li class="flex items-start">
                        <i class="fas fa-star text-amber-500 mr-3 mt-1"></i>
                        <span><strong>Organizer:</strong> STEAM EXHIBITION 2025 held at Gym Khanna</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-medal text-amber-500 mr-3 mt-1"></i>
                        <span><strong>1st Position:</strong> District Induction Training Phase-1, Mirpur Khas</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-award text-amber-500 mr-3 mt-1"></i>
                        <span><strong>First Prize:</strong> Software Project Exhibition at SST Rashidabad (Project Leader)</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-certificate text-amber-500 mr-3 mt-1"></i>
                        <span><strong>Highest Marks:</strong> Microsoft Word Specialist (2016) Exam</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-hands-helping text-amber-500 mr-3 mt-1"></i>
                        <span><strong>Volunteer:</strong> ITC Talent Hunt Program at SAU</span>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Right Column -->
        <div class="space-y-8">
            <!-- Education -->
            <div class="bg-white rounded-2xl shadow-lg p-6 border border-gray-100">
                <h2 class="text-2xl font-bold text-gray-800 mb-4 flex items-center">
                    <i class="fas fa-graduation-cap text-teal-600 mr-3"></i>
                    Education
                </h2>
                <div class="space-y-4">
                    <div>
                        <h3 class="font-bold text-gray-800">BSIT (Hons) - Bachelor of Science in IT</h3>
                        <p class="text-sm text-gray-500">Sindh Agriculture University, Tandojam</p>
                        <p class="text-xs text-teal-600 font-semibold">2018 – 2022</p>
                    </div>
                    <div>
                        <h3 class="font-bold text-gray-800">Intermediate (Pre-Engineering)</h3>
                        <p class="text-sm text-gray-500">BISE Hyderabad</p>
                        <p class="text-xs text-teal-600 font-semibold">2015 – 2017</p>
                    </div>
                    <div>
                        <h3 class="font-bold text-gray-800">Matriculation</h3>
                        <p class="text-sm text-gray-500">BISE Hyderabad</p>
                        <p class="text-xs text-teal-600 font-semibold">2013 – 2015</p>
                    </div>
                </div>
            </div>

            <!-- Skills -->
            <div class="bg-white rounded-2xl shadow-lg p-6 border border-gray-100">
                <h2 class="text-2xl font-bold text-gray-800 mb-4 flex items-center">
                    <i class="fas fa-code text-teal-600 mr-3"></i>
                    Skills & Technologies
                </h2>
                <div class="mb-4">
                    <h3 class="text-sm font-bold text-gray-700 mb-2">Programming</h3>
                    <div class="flex flex-wrap">
                        <span class="skill-badge">PHP</span>
                        <span class="skill-badge">JavaScript</span>
                        <span class="skill-badge">HTML5</span>
                        <span class="skill-badge">CSS3</span>
                        <span class="skill-badge">SQL</span>
                    </div>
                </div>
                <div class="mb-4">
                    <h3 class="text-sm font-bold text-gray-700 mb-2">Frameworks & Tools</h3>
                    <div class="flex flex-wrap">
                        <span class="skill-badge">WordPress</span>
                        <span class="skill-badge">Bootstrap</span>
                        <span class="skill-badge">XAMPP/WAMPP</span>
                        <span class="skill-badge">GitHub</span>
                        <span class="skill-badge">VS Code</span>
                        <span class="skill-badge">Sublime Text</span>
                    </div>
                </div>
                <div class="mb-4">
                    <h3 class="text-sm font-bold text-gray-700 mb-2">Teaching & Education</h3>
                    <div class="flex flex-wrap">
                        <span class="skill-badge">Lesson Planning</span>
                        <span class="skill-badge">Classroom Management</span>
                        <span class="skill-badge">Student Mentorship</span>
                        <span class="skill-badge">Interactive Tools</span>
                    </div>
                </div>
                <div class="mb-4">
                    <h3 class="text-sm font-bold text-gray-700 mb-2">Microsoft Office</h3>
                    <div class="flex flex-wrap">
                        <span class="skill-badge">MS Word</span>
                        <span class="skill-badge">PowerPoint</span>
                        <span class="skill-badge">Excel</span>
                    </div>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-gray-700 mb-2">Soft Skills</h3>
                    <div class="flex flex-wrap">
                        <span class="skill-badge">Leadership</span>
                        <span class="skill-badge">Management</span>
                        <span class="skill-badge">Communication</span>
                        <span class="skill-badge">Problem Solving</span>
                        <span class="skill-badge">Creative Thinking</span>
                        <span class="skill-badge">Interpersonal</span>
                    </div>
                </div>
            </div>

            <!-- Certifications -->
            <div class="bg-white rounded-2xl shadow-lg p-6 border border-gray-100">
                <h2 class="text-2xl font-bold text-gray-800 mb-4 flex items-center">
                    <i class="fas fa-certificate text-teal-600 mr-3"></i>
                    Certifications
                </h2>
                <ul class="space-y-2 text-sm text-gray-600">
                    <li class="flex items-start">
                        <i class="fas fa-check-circle text-teal-500 mr-2 mt-1"></i>
                        <span>Microsoft Word Specialist (2016)</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-check-circle text-teal-500 mr-2 mt-1"></i>
                        <span>Digital Literacy (Digiskills)</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-check-circle text-teal-500 mr-2 mt-1"></i>
                        <span>Digital Marketing (Digiskills)</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-check-circle text-teal-500 mr-2 mt-1"></i>
                        <span>Content Writing (Digiskills)</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-check-circle text-teal-500 mr-2 mt-1"></i>
                        <span>SEO (Digiskills)</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-check-circle text-teal-500 mr-2 mt-1"></i>
                        <span>WordPress Development (Digiskills)</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-check-circle text-teal-500 mr-2 mt-1"></i>
                        <span>Diploma in Information Technology (DIT)</span>
                    </li>
                </ul>
            </div>

            <!-- Social Links -->
            <div class="bg-white rounded-2xl shadow-lg p-6 border border-gray-100">
                <h2 class="text-2xl font-bold text-gray-800 mb-4 flex items-center">
                    <i class="fas fa-share-alt text-teal-600 mr-3"></i>
                    Digital Presence
                </h2>
                <div class="space-y-2 text-sm">
                    <a href="https://linkedin.com/in/abdulrafayqazi" target="_blank" class="flex items-center text-gray-700 hover:text-teal-600 transition">
                        <i class="fab fa-linkedin w-8 text-lg"></i>
                        <span>linkedin.com/in/abdulrafayqazi</span>
                    </a>
                    <a href="https://github.com/rafayqazi" target="_blank" class="flex items-center text-gray-700 hover:text-teal-600 transition">
                        <i class="fab fa-github w-8 text-lg"></i>
                        <span>github.com/rafayqazi</span>
                    </a>
                    <a href="https://www.knowledgeshout.com" target="_blank" class="flex items-center text-gray-700 hover:text-teal-600 transition">
                        <i class="fas fa-globe w-8 text-lg"></i>
                        <span>knowledgeshout.com</span>
                    </a>
                    <a href="https://www.quora.com/profile/Abdul-Rafay-584" target="_blank" class="flex items-center text-gray-700 hover:text-teal-600 transition">
                        <i class="fab fa-quora w-8 text-lg"></i>
                        <span>Quora: Abdul-Rafay-584</span>
                    </a>
                    <a href="https://youtube.com/@freelancing_with_rafay" target="_blank" class="flex items-center text-gray-700 hover:text-teal-600 transition">
                        <i class="fab fa-youtube w-8 text-lg"></i>
                        <span>@freelancing_with_rafay</span>
                    </a>
                    <a href="https://instagram.com/freelancing_with_rafay" target="_blank" class="flex items-center text-gray-700 hover:text-teal-600 transition">
                        <i class="fab fa-instagram w-8 text-lg"></i>
                        <span>@freelancing_with_rafay</span>
                    </a>
                    <a href="https://web.facebook.com/rafeH.QAZI" target="_blank" class="flex items-center text-gray-700 hover:text-teal-600 transition">
                        <i class="fab fa-facebook w-8 text-lg"></i>
                        <span>facebook.com/rafeH.QAZI</span>
                    </a>
                </div>
            </div>

            <!-- Languages -->
            <div class="bg-white rounded-2xl shadow-lg p-6 border border-gray-100">
                <h2 class="text-2xl font-bold text-gray-800 mb-4 flex items-center">
                    <i class="fas fa-language text-teal-600 mr-3"></i>
                    Languages
                </h2>
                <div class="space-y-3">
                    <div>
                        <div class="flex justify-between mb-1">
                            <span class="text-sm font-semibold text-gray-700">Urdu</span>
                            <span class="text-xs text-gray-500">Native</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="bg-teal-600 h-2 rounded-full" style="width: 100%"></div>
                        </div>
                    </div>
                    <div>
                        <div class="flex justify-between mb-1">
                            <span class="text-sm font-semibold text-gray-700">English</span>
                            <span class="text-xs text-gray-500">Fluent</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="bg-teal-600 h-2 rounded-full" style="width: 90%"></div>
                        </div>
                    </div>
                    <div>
                        <div class="flex justify-between mb-1">
                            <span class="text-sm font-semibold text-gray-700">Sindhi</span>
                            <span class="text-xs text-gray-500">Professional</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="bg-teal-600 h-2 rounded-full" style="width: 80%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php 
include '../includes/footer.php';
echo '</main></div></body></html>'; 
?>
