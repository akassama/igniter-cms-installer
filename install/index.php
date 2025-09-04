<?php
// /install/index.php
session_start();

$step = $_GET['step'] ?? 1;

/**
 * Run composer install (tries composer.phar first, then global composer).
 */
function runComposer() {
    $output = [];
    $return_var = 0;

    $rootPath = realpath(__DIR__ . "/..");

    // Try local composer.phar
    if (file_exists($rootPath . "/composer.phar")) {
        exec("php " . escapeshellarg($rootPath . "/composer.phar") . " install -d " . escapeshellarg($rootPath) . " 2>&1", $output, $return_var);
    } else {
        // Try global composer
        exec("composer install -d " . escapeshellarg($rootPath) . " 2>&1", $output, $return_var);
    }

    return [$return_var, implode("\n", $output)];
}

/**
 * System requirements check.
 */
function checkRequirements() {
    $errors = [];

    // PHP version
    if (PHP_VERSION_ID < 80000) {
        $errors[] = "PHP 8.0+ required, you have " . PHP_VERSION;
    }

    // Required extensions
    if (!extension_loaded('zip')) $errors[] = "ZIP extension not enabled.";
    if (!extension_loaded('gd')) $errors[] = "GD extension not enabled.";
    if (!extension_loaded('intl')) $errors[] = "INTL extension not enabled.";

    // Execution time
    $maxExec = (int) ini_get("max_execution_time");
    if ($maxExec !== 0 && $maxExec < 60) {
        $errors[] = "max_execution_time should be at least 60 seconds (current: {$maxExec})";
    }

    // File system
    if (!is_writable(__DIR__ . "/../")) {
        $errors[] = "Project root not writable.";
    }

    return $errors;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step == 2) {
    $db_host = trim($_POST['db_host']);
    $db_name = trim($_POST['db_name']);
    $db_user = trim($_POST['db_user']);
    $db_pass = trim($_POST['db_pass']);
    $app_url = trim($_POST['app_url']);

    // Try DB connection
    $mysqli = @new mysqli($db_host, $db_user, $db_pass, $db_name);
    if ($mysqli->connect_error) {
        $_SESSION['error'] = "Database connection failed: " . $mysqli->connect_error;
        header("Location: index.php?step=2");
        exit;
    }

    // Generate .env
    $envTemplate = file_get_contents(__DIR__ . "/../env");
    $appKey = bin2hex(random_bytes(32));

    $replacements = [
        "# database.default.hostname = localhost" => "database.default.hostname = {$db_host}",
        "# database.default.database = igniter_cms_pro_db" => "database.default.database = {$db_name}",
        "# database.default.username = root" => "database.default.username = {$db_user}",
        "# database.default.password =" => "database.default.password = {$db_pass}",
        "# app.baseURL = ''" => "app.baseURL = '{$app_url}'",
    ];

    foreach ($replacements as $search => $replace) {
        $envTemplate = str_replace($search, $replace, $envTemplate);
    }

    $envTemplate .= "\nAPP_KEY={$appKey}\n";

    file_put_contents(__DIR__ . "/../.env", $envTemplate);

    // Import database.sql if exists
    $sqlFile = __DIR__ . "/database.sql";
    if (file_exists($sqlFile)) {
        $sql = file_get_contents($sqlFile);
        if ($mysqli->multi_query($sql)) {
            do { } while ($mysqli->more_results() && $mysqli->next_result());
        }
    }

    $mysqli->close();

    // Run composer install
    list($code, $composerOutput) = runComposer();

    // Swap index.php
    $rootPath = realpath(__DIR__ . "/..");
    $redirectIndex = $rootPath . "/index.php";
    $realIndex = $rootPath . "/index.renamed.php";

    if (file_exists($redirectIndex)) {
        unlink($redirectIndex);
    }
    if (file_exists($realIndex)) {
        rename($realIndex, $rootPath . "/index.php");
    }

    // Save composer logs to session for step 3
    $_SESSION['composer_status'] = $code === 0 ? "success" : "fail";
    $_SESSION['composer_output'] = $composerOutput;

    header("Location: index.php?step=3");
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Igniter CMS Installer</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5">
  <div class="card shadow-lg rounded-4">
    <div class="card-body p-5">

      <?php if ($step == 1): ?>
        <h2><i class="bi bi-gear"></i> Requirements Check</h2>
        <?php $errors = checkRequirements(); ?>
        <?php if ($errors): ?>
          <div class="alert alert-danger mt-3">
            <ul>
              <?php foreach($errors as $e) echo "<li>$e</li>"; ?>
            </ul>
          </div>
        <?php else: ?>
          <div class="alert alert-success mt-3">✅ All requirements satisfied</div>
          <a href="index.php?step=2" class="btn btn-primary mt-3">
            Next <i class="bi bi-arrow-right-circle"></i>
          </a>
        <?php endif; ?>
      <?php endif; ?>

      <?php if ($step == 2): ?>
        <h2><i class="bi bi-database"></i> Database Configuration</h2>
        <?php if (!empty($_SESSION['error'])): ?>
          <div class="alert alert-danger"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>
        <form method="post" class="mt-3">
          <div class="mb-3">
            <label class="form-label">DB Host</label>
            <input type="text" name="db_host" class="form-control" required value="localhost">
          </div>
          <div class="mb-3">
            <label class="form-label">DB Name</label>
            <input type="text" name="db_name" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">DB User</label>
            <input type="text" name="db_user" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">DB Password</label>
            <input type="password" name="db_pass" class="form-control">
          </div>
          <div class="mb-3">
            <label class="form-label">Base URL</label>
            <input type="url" name="app_url" class="form-control" required value="http://localhost/igniter-cms/">
          </div>
          <button type="submit" class="btn btn-success">
            Continue <i class="bi bi-arrow-right-circle"></i>
          </button>
        </form>
      <?php endif; ?>

      <?php if ($step == 3): ?>
        <h2><i class="bi bi-check-circle"></i> Installation Complete</h2>
        <div class="alert alert-success mt-3">
          Igniter CMS has been successfully installed 🎉
        </div>

        <?php if (!empty($_SESSION['composer_status'])): ?>
          <?php if ($_SESSION['composer_status'] === "success"): ?>
            <div class="alert alert-success">Composer dependencies installed successfully ✅</div>
          <?php else: ?>
            <div class="alert alert-warning">
              ⚠️ Composer install failed. Please run manually:<br>
              <code>composer install</code>
            </div>
            <pre class="bg-dark text-light p-2 small rounded"><?= htmlspecialchars($_SESSION['composer_output']) ?></pre>
          <?php endif; ?>
          <?php unset($_SESSION['composer_status'], $_SESSION['composer_output']); ?>
        <?php endif; ?>

        <p>You can now access your site:</p>
        <a href="../public/" class="btn btn-primary">
          Go to Site <i class="bi bi-box-arrow-up-right"></i>
        </a>
        <a href="../public/admin" class="btn btn-secondary">
          Go to Admin <i class="bi bi-person-lock"></i>
        </a>
      <?php endif; ?>

    </div>
  </div>
</div>
</body>
</html>
