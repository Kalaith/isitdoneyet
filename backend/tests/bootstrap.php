<?php

declare(strict_types=1);

$autoloadCandidates = [
    __DIR__ . '/../../../../vendor/autoload.php',
    __DIR__ . '/../vendor/autoload.php',
];

$autoloader = null;
foreach ($autoloadCandidates as $candidate) {
    if (file_exists($candidate)) {
        $autoloader = $candidate;
        break;
    }
}

if ($autoloader === null) {
    throw new RuntimeException('Composer autoload.php not found for tests.');
}

$loader = require $autoloader;
$projectSrc = realpath(__DIR__ . '/../src') ?: (__DIR__ . '/../src');
$buildLocalAppClassMap = static function (string $srcPath): array {
    $classMap = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($srcPath, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if (!$file instanceof SplFileInfo || $file->getExtension() !== 'php') {
            continue;
        }

        $relativePath = substr($file->getPathname(), strlen($srcPath) + 1);
        $className = 'App\\' . str_replace([DIRECTORY_SEPARATOR, '.php'], ['\\', ''], $relativePath);
        $classMap[$className] = $file->getPathname();
    }

    return $classMap;
};

if (is_object($loader) && method_exists($loader, 'addPsr4')) {
    $loader->addPsr4('App\\', rtrim($projectSrc, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR, true);
}
if (is_object($loader) && method_exists($loader, 'addClassMap')) {
    $loader->addClassMap($buildLocalAppClassMap($projectSrc));
}
