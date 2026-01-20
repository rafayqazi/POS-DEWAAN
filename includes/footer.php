<footer class="mt-10 py-6 text-center text-gray-500 text-xs border-t border-gray-100">
    &copy; <?= date('Y') ?> <strong>POS DEWAAN</strong>. Developed with <i class="fas fa-heart text-red-500"></i> by 
    <a href="<?= (basename(dirname($_SERVER['PHP_SELF'])) == 'pages') ? 'about_developer.php' : 'pages/about_developer.php' ?>" class="text-teal-600 hover:text-teal-800 font-bold transition-colors">
        Abdul Rafay
    </a>
</footer>
