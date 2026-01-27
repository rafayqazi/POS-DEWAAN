<footer class="mt-2 py-1 text-center text-gray-400 text-[9px] border-t border-gray-50 opacity-80">
    <div class="flex flex-wrap justify-center gap-3 mb-1">
        <a href="<?= (basename(dirname($_SERVER['PHP_SELF'])) == 'pages') ? 'about_developer.php' : 'pages/about_developer.php' ?>" class="hover:text-teal-600 transition-colors">About Developer</a>
        <span class="text-gray-200">|</span>
        <a href="<?= (basename(dirname($_SERVER['PHP_SELF'])) == 'pages') ? 'license.php' : 'pages/license.php' ?>" class="hover:text-teal-600 transition-colors">Software License</a>
    </div>
    <div class="mb-0.5">
        Software by <span class="font-bold text-teal-600">Abdul Rafay</span> 
        <span class="mx-1.5 text-gray-200">|</span> 
        <i class="fab fa-whatsapp text-green-400 mr-1"></i>03000358189 / 03710273699
        <span class="mx-1.5 text-gray-200">|</span>
        &copy; <?= date('Y') ?> <strong>Fashion Shines POS</strong>.
    </div>
</footer>
