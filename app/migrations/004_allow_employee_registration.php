<?php

class Allow_employee_registration
{
    private $_lava;

    public function __construct()
    {
        $this->_lava = lava_instance();
        $this->_lava->call->dbforge();
    }

    public function up()
    {
        $dbforge = $this->_lava->dbforge;
        if ($dbforge->table_exists('users') && $dbforge->column_exists('users', 'username')) {
            $dbforge->modify_column('users', [
                'username' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 100,
                    'null'       => TRUE,
                    'default'    => NULL,
                ],
            ]);
        }
    }

    public function down()
    {
        // Keep nullable usernames so registrations without a username remain valid.
    }
}
