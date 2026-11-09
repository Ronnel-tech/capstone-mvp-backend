<?php

class Seed_demo_employees
{
    private $_lava;

    public function __construct()
    {
        $this->_lava = lava_instance();
    }

    public function up()
    {
        $admin = $this->_lava->db->raw(
            "SELECT id FROM users WHERE role = 'admin' AND account_status = 'active' ORDER BY id LIMIT 1"
        )->fetch(PDO::FETCH_ASSOC);
        if (!$admin) {
            throw new RuntimeException('Provision an active Admin before seeding demo employees.');
        }

        $departments = $this->_lava->db->raw(
            "SELECT id, department_name FROM departments WHERE status = 'active' AND department_name IN (?, ?, ?) ORDER BY department_name",
            ['Administration Office', 'Finance Office', 'Public Services Office']
        )->fetchAll(PDO::FETCH_ASSOC);
        if (count($departments) !== 3) {
            throw new RuntimeException('The three initial active departments must exist before seeding demo employees.');
        }

        $index = 1;
        foreach ($departments as $department) {
            for ($withinDepartment = 0; $withinDepartment < 5; $withinDepartment++, $index++) {
                $email = sprintf('mvp.demo%03d@example.local', $index);
                $existing = $this->_lava->db->raw('SELECT id FROM users WHERE email = ? LIMIT 1', [$email])
                    ->fetch(PDO::FETCH_ASSOC);
                if ($existing) continue;

                $this->_lava->db->raw(
                    "INSERT INTO users (email, password, role, account_status) VALUES (?, ?, 'employee', 'active')",
                    [$email, password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT)]
                );
                $userId = $this->_lava->db->last_id();
                $this->_lava->db->raw(
                    'INSERT INTO employees (user_id, department_id, full_name, age, address, approved_by, approved_at) '
                    . 'VALUES (?, ?, ?, ?, ?, ?, NOW())',
                    [
                        $userId,
                        $department['id'],
                        sprintf('MVP Sample Employee %03d', $index),
                        24 + (($index * 7) % 25),
                        'SIMULATED SAMPLE DATA',
                        $admin['id'],
                    ]
                );
            }
        }
    }

    public function down()
    {
        $emails = [];
        for ($index = 1; $index <= 15; $index++) $emails[] = sprintf('mvp.demo%03d@example.local', $index);
        $placeholders = implode(',', array_fill(0, count($emails), '?'));
        $this->_lava->db->raw('DELETE FROM users WHERE email IN (' . $placeholders . ')', $emails);
    }
}
