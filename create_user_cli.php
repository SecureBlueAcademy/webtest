<?php
/**
 * CLI User Creation Script for CYBRIXEN SIEM
 * Usage via SSH: php create_user_cli.php username password [role]
 */

require_once 'config.php';

function createUserCLI($username, $password, $role = 'analyst') {
    $users = readCSV(USERS_CSV);
    
    // Check if username already exists
    foreach ($users as $user) {
        if ($user[1] === $username) {
            return "Error: Username '$username' already exists!";
        }
    }
    
    // Generate new ID
    $id = count($users) + 1;
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    // Add new user
    $users[] = [$id, $username, $hashedPassword, $role];
    
    if (writeCSV(USERS_CSV, $users)) {
        return "User '$username' created successfully with ID $id and role '$role'";
    } else {
        return "Error creating user '$username'";
    }
}

// CLI execution
if (php_sapi_name() === 'cli') {
    if ($argc < 3) {
        echo "Usage: php create_user_cli.php username password [role]\n";
        echo "Roles: analyst, admin\n";
        echo "Example: php create_user_cli.php john secret123 admin\n";
        exit(1);
    }
    
    $username = $argv[1];
    $password = $argv[2];
    $role = $argv[3] ?? 'analyst';
    
    // Validate role
    if (!in_array($role, ['analyst', 'admin'])) {
        echo "Error: Role must be 'analyst' or 'admin'\n";
        exit(1);
    }
    
    $result = createUserCLI($username, $password, $role);
    echo $result . "\n";
    
    if (strpos($result, 'Error') === false) {
        exit(0); // Success
    } else {
        exit(1); // Error
    }
} else {
    echo "This script is for CLI use only.\n";
    exit(1);
}
?>