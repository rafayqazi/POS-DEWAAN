<footer class="mt-8 py-4 text-center text-gray-500 text-xs border-t border-gray-200 bg-gray-50/50">
    <div class="flex flex-wrap justify-center gap-4 mb-2">
        <a href="<?= (basename(dirname($_SERVER['PHP_SELF'])) == 'pages') ? 'about_developer.php' : 'pages/about_developer.php' ?>" class="hover:text-teal-700 font-semibold transition-colors">About Developer</a>
        <span class="text-gray-300">|</span>
        <a href="<?= (basename(dirname($_SERVER['PHP_SELF'])) == 'pages') ? 'license.php' : 'pages/license.php' ?>" class="hover:text-teal-700 font-semibold transition-colors">Software License</a>
    </div>
    <div class="mb-1">
        Software by <span class="font-bold text-teal-700">Abdul Rafay</span> 
        <span class="mx-2 text-gray-300">|</span> 
        <i class="fab fa-whatsapp text-green-500 text-sm mr-1"></i><span class="font-medium text-gray-700">03000358189 / 03710273699</span>
        <span class="mx-2 text-gray-300">|</span>
        &copy; <?= date('Y') ?> <strong class="text-gray-700"><?= getSetting('business_name', 'Fashion Shines POS') ?></strong>.
    </div>
</footer>
