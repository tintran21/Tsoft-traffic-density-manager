<?php
echo "<h1>Server Check</h1>";
echo "PHP Version: " . phpversion() . "<br>";
echo "Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "<br>";
echo "Current Dir: " . __DIR__ . "<br>";
echo "imageluuluong exists: " . (is_dir(__DIR__ . '/imageluuluong') ? 'YES' : 'NO') . "<br>";

$folders = ['bac', 'nam', 'dong', 'tay'];
foreach ($folders as $folder) {
    $path = __DIR__ . '/imageluuluong/' . $folder;
    echo "Folder $folder: " . (is_dir($path) ? 'EXISTS' : 'MISSING') . "<br>";
    if (is_dir($path)) {
        $images = glob($path . '/*.{jpg,jpeg,png}', GLOB_BRACE);
        echo " - Images: " . count($images) . "<br>";
    }
}
?>