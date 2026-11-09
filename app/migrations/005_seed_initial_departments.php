<?php

class Seed_initial_departments
{
    private $_lava;

    public function __construct()
    {
        $this->_lava = lava_instance();
    }

    public function up()
    {
        $departments = [
            ['Administration Office', 'Initial municipal administration department for the MVP.'],
            ['Finance Office', 'Initial municipal finance department for the MVP.'],
            ['Public Services Office', 'Initial municipal public services department for the MVP.'],
        ];

        foreach ($departments as [$name, $description]) {
            $existing = $this->_lava->db->raw(
                'SELECT id FROM departments WHERE department_name = ? LIMIT 1',
                [$name]
            )->fetch(PDO::FETCH_ASSOC);
            if (!$existing) {
                $this->_lava->db->raw(
                    "INSERT INTO departments (department_name, description, status) VALUES (?, ?, 'active')",
                    [$name, $description]
                );
            }
        }
    }

    public function down()
    {
        $names = ['Administration Office', 'Finance Office', 'Public Services Office'];
        $placeholders = implode(',', array_fill(0, count($names), '?'));
        $this->_lava->db->raw(
            'DELETE FROM departments WHERE department_name IN (' . $placeholders . ')',
            $names
        );
    }
}
