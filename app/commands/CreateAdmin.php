<?php

class CreateAdmin
{
    public static $command = 'admin:create';
    public static $description = 'Provision an Admin account for local setup';
    public static $arguments = [
        'email' => 'Email address for the initial Admin account',
        '[ADMIN_SETUP_PASSWORD]' => 'Set this environment variable before running; never pass a password as an argument',
    ];

    public function handle($email = null, array $flags = [])
    {
        $email = strtolower(trim((string) $email));
        $password = getenv('ADMIN_SETUP_PASSWORD') ?: '';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            fwrite(STDERR, "Usage: set ADMIN_SETUP_PASSWORD, then run php lava admin:create admin@example.local\n");
            exit(1);
        }
        if (strlen($password) < 8 || strlen($password) > 72) {
            fwrite(STDERR, "ADMIN_SETUP_PASSWORD must be 8 to 72 bytes.\n");
            exit(1);
        }

        putenv('ADMIN_SETUP_EMAIL=' . $email);
        putenv('ADMIN_SETUP_PASSWORD_HASH=' . password_hash($password, PASSWORD_DEFAULT));
        putenv('ADMIN_SETUP_PASSWORD');

        $index = PUBLIC_DIR . 'index.php';
        $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($index) . ' ' . escapeshellarg('cli/provision-admin');
        passthru($command, $exitCode);

        putenv('ADMIN_SETUP_EMAIL');
        putenv('ADMIN_SETUP_PASSWORD_HASH');
        exit($exitCode);
    }
}
