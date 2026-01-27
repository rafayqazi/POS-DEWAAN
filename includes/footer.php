<footer class="mt-4 py-3 text-center text-gray-500 text-[10px] border-t border-gray-100">
    <div class="flex flex-wrap justify-center gap-4 mb-2">
        <a href="<?= (basename(dirname($_SERVER['PHP_SELF'])) == 'pages') ? 'about_developer.php' : 'pages/about_developer.php' ?>" class="hover:text-teal-600 transition-colors">About Developer</a>
        <span class="text-gray-300">|</span>
        <a href="<?= (basename(dirname($_SERVER['PHP_SELF'])) == 'pages') ? 'license.php' : 'pages/license.php' ?>" class="hover:text-teal-600 transition-colors">Software License</a>
    </div>
    <div class="mb-1 text-gray-400">
        Software by <span class="font-bold text-teal-700">Abdul Rafay</span> 
        <span class="mx-2 text-gray-300">|</span> 
        <i class="fab fa-whatsapp text-green-500 mr-1"></i>03000358189 / 03710273699
    </div>
    &copy; <?= date('Y') ?> <strong>Fashion Shines POS</strong>. All rights reserved.
</footer>
