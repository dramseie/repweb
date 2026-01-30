<?php

require __DIR__ . '/../vendor/autoload.php';
$token = $argv[1] ?? null;
if (!$token) {
    fwrite(STDERR, "Usage: php dump_profiler.php <token>\n");
    exit(1);
}

$_SERVER['APP_ENV'] = 'dev';
$_SERVER['APP_DEBUG'] = '1';
$_SERVER['MIGRATION_MANAGER_ENABLED'] = $_SERVER['MIGRATION_MANAGER_ENABLED'] ?? 'false';
$_SERVER['BLAUE_ELISE_ENABLED'] = $_SERVER['BLAUE_ELISE_ENABLED'] ?? 'false';
$_SERVER['PROGRESS_INDICATORS_ENABLED'] = $_SERVER['PROGRESS_INDICATORS_ENABLED'] ?? 'false';
$_SERVER['GALLERY_STORAGE_PATH'] = $_SERVER['GALLERY_STORAGE_PATH'] ?? '/mnt/photo';

putenv('MIGRATION_MANAGER_ENABLED=' . ($_SERVER['MIGRATION_MANAGER_ENABLED']));
putenv('BLAUE_ELISE_ENABLED=' . ($_SERVER['BLAUE_ELISE_ENABLED']));
putenv('PROGRESS_INDICATORS_ENABLED=' . ($_SERVER['PROGRESS_INDICATORS_ENABLED']));
putenv('GALLERY_STORAGE_PATH=' . ($_SERVER['GALLERY_STORAGE_PATH']));

$kernel = new App\Kernel('dev', true);
$kernel->boot();

/** @var Symfony\Component\HttpKernel\Profiler\Profiler $profiler */
$profiler = $kernel->getContainer()->get('profiler');
$profile = $profiler->loadProfile($token);

if (!$profile) {
    fwrite(STDERR, "Profile not found\n");
    exit(1);
}

$collector = $profile->getCollector('exception');

if ($collector && $collector->hasException()) {
    $e = $collector->getException();
    printf("%s: %s\n", get_class($e), $e->getMessage());
    printf("%s:%d\n", $e->getFile(), $e->getLine());

    $trace = $e->getTraceAsString();
    echo "\nStack trace:\n" . $trace . "\n";
} else {
    echo "No exception recorded.\n";
}
