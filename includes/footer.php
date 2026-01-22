<footer class="mt-10 py-6 text-center text-gray-500 text-xs border-t border-gray-100">
    <div class="flex flex-wrap justify-center gap-4 mb-2">
        <a href="<?= (basename(dirname($_SERVER['PHP_SELF'])) == 'pages') ? 'about_developer.php' : 'pages/about_developer.php' ?>" class="hover:text-teal-600 transition-colors">About Developer</a>
        <span class="text-gray-300">|</span>
        <a href="<?= (basename(dirname($_SERVER['PHP_SELF'])) == 'pages') ? 'license.php' : 'pages/license.php' ?>" class="hover:text-teal-600 transition-colors">Software License</a>
    </div>
    &copy; <?= date('Y') ?> <strong>POS DEWAAN</strong>. Developed with <i class="fas fa-heart text-red-500"></i> by 
    <a href="<?= (basename(dirname($_SERVER['PHP_SELF'])) == 'pages') ? 'about_developer.php' : 'pages/about_developer.php' ?>" class="text-teal-600 hover:text-teal-800 font-bold transition-colors">
        Abdul Rafay
    </a>
</footer>
