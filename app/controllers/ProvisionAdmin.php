<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class ProvisionAdmin extends Controller
{
    public function create()
    {
        if (PHP_SAPI !== 'cli') {
            http_response_code(404);
            exit;
        }

        $email = strtolower(trim((string) getenv('ADMIN_SETUP_EMAIL')));
        $passwordHash = (string) getenv('ADMIN_SETUP_PASSWORD_HASH');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $passwordHash === '') {
            fwrite(STDERR, "Admin setup values were not provided.\n");
            exit(1);
        }

        $db = $this->call->database();
        $existing = $db->raw('SELECT role FROM users WHERE email = ? LIMIT 1', [$email])->fetch(PDO::FETCH_ASSOC);
        if ($existing) {
            fwrite(STDERR, $existing['role'] === 'admin'
                ? "An Admin account with this email already exists.\n"
                : "This email is already assigned to another account.\n");
            exit(1);
        }

        $db->raw(
            "INSERT INTO users (email, password, role, account_status) VALUES (?, ?, 'admin', 'active')",
            [$email, $passwordHash]
        );
        echo "Admin account provisioned.\n";
    }
}
