<?php
/**
 * Diagnóstico de SAP Woo Sync - Verificación de Update Checker
 * 
 * IMPORTANTE: Eliminar este archivo después de usar
 * Acceder via: /wp-content/plugins/sap-woo/sapwc-debug.php
 */

// Mostrar errores
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Header
header('Content-Type: text/plain; charset=utf-8');

echo "=== SAPWC Debug Info ===\n\n";

// Leer versión del plugin
$plugin_file = __DIR__ . '/sap-wc-sync.php';
if (file_exists($plugin_file)) {
    $content = file_get_contents($plugin_file);
    if (preg_match('/Version:\s*(.+)/i', $content, $matches)) {
        echo "Plugin Version: " . trim($matches[1]) . "\n";
    }
    
    // Verificar URL del update checker
    if (preg_match('/buildUpdateChecker\s*\(\s*[\'"]([^\'"]+)[\'"]/i', $content, $matches)) {
        echo "GitHub URL configured: " . $matches[1] . "\n";
    } else {
        echo "GitHub URL: NOT FOUND in code\n";
    }
    
    // Verificar setBranch
    if (preg_match('/setBranch\s*\(\s*[\'"]([^\'"]+)[\'"]/i', $content, $matches)) {
        echo "Branch: " . $matches[1] . "\n";
    }
} else {
    echo "ERROR: sap-wc-sync.php NOT FOUND\n";
}

echo "\n--- Token Check ---\n";
// Verificar si el token está definido (sin mostrar el valor completo)
if (defined('SAPWC_GITHUB_TOKEN')) {
    $token = SAPWC_GITHUB_TOKEN;
    echo "Token defined: YES\n";
    echo "Token length: " . strlen($token) . " chars\n";
    echo "Token starts with: " . substr($token, 0, 15) . "...\n";
} else {
    // Intentar cargar wp-config
    $wp_config = dirname(__DIR__, 3) . '/wp-config.php';
    if (file_exists($wp_config)) {
        $config_content = file_get_contents($wp_config);
        if (strpos($config_content, 'SAPWC_GITHUB_TOKEN') !== false) {
            echo "Token in wp-config: YES (but not loaded - wrong position?)\n";
        } else {
            echo "Token in wp-config: NOT FOUND\n";
        }
    }
}

echo "\n--- Files Check ---\n";
$critical_files = [
    'sap-wc-sync.php',
    'vendor/autoload.php',
    'vendor/yahnis-elsts/plugin-update-checker/plugin-update-checker.php',
    'admin/class-selective-import-page.php',
];

foreach ($critical_files as $file) {
    $path = __DIR__ . '/' . $file;
    if (file_exists($path)) {
        echo "✓ $file (exists, " . filesize($path) . " bytes)\n";
    } else {
        echo "✗ $file (MISSING)\n";
    }
}

echo "\n--- Library Version ---\n";
$installed_json = __DIR__ . '/vendor/composer/installed.json';
if (file_exists($installed_json)) {
    $json = json_decode(file_get_contents($installed_json), true);
    if (isset($json['packages'])) {
        foreach ($json['packages'] as $pkg) {
            if ($pkg['name'] === 'yahnis-elsts/plugin-update-checker') {
                echo "Update Checker: " . $pkg['version'] . "\n";
            }
        }
    }
}

echo "\n--- Test GitHub API ---\n";
$token = defined('SAPWC_GITHUB_TOKEN') ? SAPWC_GITHUB_TOKEN : '';
$headers = [
    'User-Agent: SAPWC-Debug',
    'Accept: application/vnd.github.v3+json',
];
if ($token) {
    $headers[] = 'Authorization: token ' . $token;
}

$ch = curl_init('https://api.github.com/repos/replantadev/sapwoo');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "GitHub API Response Code: $http_code\n";
if ($http_code === 200) {
    $data = json_decode($response, true);
    echo "Repo found: " . ($data['full_name'] ?? 'unknown') . "\n";
    echo "Default branch: " . ($data['default_branch'] ?? 'unknown') . "\n";
} else {
    echo "Error: " . substr($response, 0, 200) . "\n";
}

echo "\n=== END DEBUG ===\n";
echo "\n⚠️  DELETE THIS FILE AFTER USE!\n";
