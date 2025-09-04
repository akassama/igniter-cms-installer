<?php

/*
 *---------------------------------------------------------------
 * INSTALLER REDIRECT AND CLEANUP
 *---------------------------------------------------------------
 */
$installPath = __DIR__ . '/install';

if (is_dir($installPath)) {
    // If install just completed, allow cleanup
    if (isset($_GET['installed']) && $_GET['installed'] == 1) {
        // Recursively delete install folder
        function rrmdir($dir) {
            foreach (array_diff(scandir($dir), ['.', '..']) as $file) {
                $path = "$dir/$file";
                if (is_dir($path)) {
                    rrmdir($path);
                } else {
                    unlink($path);
                }
            }
            rmdir($dir);
        }
        rrmdir($installPath);

        // Redirect to base URL without query string
        $baseUrl = strtok(
            (isset($_SERVER['HTTPS']) ? "https://" : "http://") .
            $_SERVER['HTTP_HOST'] .
            $_SERVER['REQUEST_URI'],
            '?'
        );
        header("Location: $baseUrl");
        exit;
    }

    // Otherwise, redirect into installer
    header("Location: install/");
    exit;
}

/*
 *---------------------------------------------------------------
 * CHECK PHP VERSION
 *---------------------------------------------------------------
 */
$minPhpVersion = '8.1'; // If you update this, don't forget to update `spark`.
if (version_compare(PHP_VERSION, $minPhpVersion, '<')) {
    $message = sprintf(
        'Your PHP version must be %s or higher to run CodeIgniter. Current version: %s',
        $minPhpVersion,
        PHP_VERSION,
    );

    header('HTTP/1.1 503 Service Unavailable.', true, 503);
    echo $message;

    exit(1);
}

/*
 *---------------------------------------------------------------
 * SET THE CURRENT DIRECTORY
 *---------------------------------------------------------------
 */
// Path to the front controller (this file)
define('FCPATH', __DIR__ . DIRECTORY_SEPARATOR);

// Ensure the current directory is pointing to the front controller's directory
if (getcwd() . DIRECTORY_SEPARATOR !== FCPATH) {
    chdir(FCPATH);
}

/*
 *---------------------------------------------------------------
 * BOOTSTRAP THE APPLICATION
 *---------------------------------------------------------------
 * This process sets up the path constants, loads and registers
 * our autoloader, along with Composer's, loads our constants
 * and fires up an environment-specific bootstrapping.
 */

// LOAD OUR PATHS CONFIG FILE
// This is the line that might need to be changed, depending on your folder structure.
require FCPATH . 'app/Config/Paths.php';
// ^^^ Change this line if you move your application folder

$paths = new Config\Paths();

// LOAD THE FRAMEWORK BOOTSTRAP FILE
require $paths->systemDirectory . '/Boot.php';

exit(CodeIgniter\Boot::bootWeb($paths));
