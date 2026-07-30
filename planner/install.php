<?php
/**
 * Installation Script for Make Your Plan Today
 * Run this script once to set up the database
 */

// Security check - only allow if not installed
if (file_exists('install.lock')) {
    die('System already installed. Delete install.lock to reinstall.');
}

require_once 'includes/config.php';

echo "<h1>Make Your Plan Today - Installation</h1>";

try {
    $db = db();

    // Read SQL file
    $sql = file_get_contents('install.sql');

    // Execute SQL
    $db->exec($sql);

    // Create install lock
    file_put_contents('install.lock', 'installed');

    echo "<p style='color:green;'>✓ Installation completed successfully!</p>";
    echo "<p>You can now <a href='login.php'>login</a> with the default admin credentials:</p>";
    echo "<ul>
            <li><strong>Username:</strong> admin</li>
            <li><strong>Password:</strong> admin123</li>
          </ul>";
    echo "<p><strong>Important:</strong> Please change the default admin password immediately!</p>";

} catch (PDOException $e) {
    echo "<p style='color:red;'>Error: " . $e->getMessage() . "</p>";
    echo "<p>Please check your database configuration in includes/config.php</p>";
}
?>
