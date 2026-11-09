<?php

class Create_mvp_schema
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

        // Preserve the starter accounts while making room for the MVP roles.
        if ($dbforge->table_exists('users')) {
            if ($dbforge->column_exists('users', 'role')) {
                $dbforge->modify_column('users', [
                    'role' => [
                        'type'       => 'VARCHAR',
                        'constraint' => 20,
                        'null'       => FALSE,
                        'default'    => 'employee',
                    ],
                ]);
                $this->_lava->db->raw("UPDATE users SET role = 'employee' WHERE role = 'user'");
            }

            if (!$dbforge->column_exists('users', 'account_status')) {
                $dbforge->add_column('users', [
                    'account_status' => [
                        'type'       => 'VARCHAR',
                        'constraint' => 20,
                        'null'       => FALSE,
                        'default'    => 'active',
                    ],
                ]);

                if ($dbforge->column_exists('users', 'is_active')) {
                    $this->_lava->db->raw("UPDATE users SET account_status = IF(is_active = 1, 'active', 'disabled')");
                }
            }

            if (!$this->index_exists('users', 'account_status_idx')) {
                $this->_lava->db->raw(
                    "ALTER TABLE `users` ADD INDEX `account_status_idx` (`account_status`)"
                );
            }
        }

        if (!$dbforge->table_exists('departments')) {
            $dbforge
                ->add_field([
                    'id' => ['type' => 'INT', 'unsigned' => TRUE, 'auto_increment' => TRUE, 'null' => FALSE],
                    'department_name' => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => FALSE],
                    'description' => ['type' => 'TEXT', 'null' => TRUE],
                    'status' => ['type' => 'ENUM', 'constraint' => "'active','inactive'", 'null' => FALSE, 'default' => 'active'],
                    'created_at' => ['type' => 'DATETIME', 'null' => FALSE, 'default' => 'CURRENT_TIMESTAMP'],
                    'updated_at' => ['type' => 'DATETIME', 'null' => TRUE, 'default' => NULL],
                ])
                ->add_key('id', primary: TRUE)
                ->add_key('department_name', unique: TRUE, name: 'department_name_unique')
                ->add_key('status', name: 'department_status_idx')
                ->create_table('departments');
        }

        if (!$dbforge->table_exists('employees')) {
            $dbforge
                ->add_field([
                    'id' => ['type' => 'INT', 'unsigned' => TRUE, 'auto_increment' => TRUE, 'null' => FALSE],
                    'user_id' => ['type' => 'INT', 'unsigned' => TRUE, 'null' => FALSE],
                    'department_id' => ['type' => 'INT', 'unsigned' => TRUE, 'null' => TRUE],
                    'full_name' => ['type' => 'VARCHAR', 'constraint' => 160, 'null' => FALSE],
                    'age' => ['type' => 'TINYINT', 'unsigned' => TRUE, 'null' => FALSE],
                    'address' => ['type' => 'TEXT', 'null' => FALSE],
                    'approved_by' => ['type' => 'INT', 'unsigned' => TRUE, 'null' => TRUE],
                    'approved_at' => ['type' => 'DATETIME', 'null' => TRUE],
                    'created_at' => ['type' => 'DATETIME', 'null' => FALSE, 'default' => 'CURRENT_TIMESTAMP'],
                    'updated_at' => ['type' => 'DATETIME', 'null' => TRUE, 'default' => NULL],
                ])
                ->add_key('id', primary: TRUE)
                ->add_key('user_id', unique: TRUE, name: 'employee_user_unique')
                ->add_key('department_id', name: 'employee_department_idx')
                ->add_foreign_key('user_id', 'users', 'id', 'CASCADE')
                ->add_foreign_key('department_id', 'departments', 'id', 'RESTRICT')
                ->add_foreign_key('approved_by', 'users', 'id', 'SET NULL')
                ->create_table('employees');
        }

        if (!$dbforge->table_exists('attendance_records')) {
            $dbforge
                ->add_field([
                    'id' => ['type' => 'INT', 'unsigned' => TRUE, 'auto_increment' => TRUE, 'null' => FALSE],
                    'employee_id' => ['type' => 'INT', 'unsigned' => TRUE, 'null' => FALSE],
                    'attendance_date' => ['type' => 'DATE', 'null' => FALSE],
                    'time_in' => ['type' => 'DATETIME', 'null' => FALSE],
                    'time_out' => ['type' => 'DATETIME', 'null' => TRUE],
                    'source' => ['type' => 'ENUM', 'constraint' => "'SIMULATED','LIVE'", 'null' => FALSE, 'default' => 'SIMULATED'],
                    'created_at' => ['type' => 'DATETIME', 'null' => FALSE, 'default' => 'CURRENT_TIMESTAMP'],
                    'updated_at' => ['type' => 'DATETIME', 'null' => TRUE, 'default' => NULL],
                ])
                ->add_key('id', primary: TRUE)
                ->add_key(['employee_id', 'attendance_date'], unique: TRUE, name: 'employee_attendance_date_unique')
                ->add_key('attendance_date', name: 'attendance_date_idx')
                ->add_foreign_key('employee_id', 'employees', 'id', 'CASCADE')
                ->create_table('attendance_records');
        }

        if (!$dbforge->table_exists('model_training_logs')) {
            $dbforge
                ->add_field([
                    'id' => ['type' => 'INT', 'unsigned' => TRUE, 'auto_increment' => TRUE, 'null' => FALSE],
                    'model_version' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => FALSE],
                    'dataset_type' => ['type' => 'ENUM', 'constraint' => "'SIMULATED','LIVE'", 'null' => FALSE],
                    'training_started_at' => ['type' => 'DATETIME', 'null' => FALSE],
                    'training_completed_at' => ['type' => 'DATETIME', 'null' => TRUE],
                    'training_status' => ['type' => 'ENUM', 'constraint' => "'pending','successful','failed'", 'null' => FALSE, 'default' => 'pending'],
                    'mae' => ['type' => 'DECIMAL', 'constraint' => '10,4', 'null' => TRUE],
                    'rmse' => ['type' => 'DECIMAL', 'constraint' => '10,4', 'null' => TRUE],
                    'wape' => ['type' => 'DECIMAL', 'constraint' => '10,4', 'null' => TRUE],
                    'activation_status' => ['type' => 'TINYINT', 'constraint' => 1, 'unsigned' => TRUE, 'null' => FALSE, 'default' => 0],
                    'failure_reason' => ['type' => 'TEXT', 'null' => TRUE],
                ])
                ->add_key('id', primary: TRUE)
                ->add_key(['training_status', 'training_started_at'], name: 'training_status_started_idx')
                ->add_key('activation_status', name: 'training_activation_idx')
                ->create_table('model_training_logs');
        }

        if (!$dbforge->table_exists('model_forecasts')) {
            $dbforge
                ->add_field([
                    'id' => ['type' => 'INT', 'unsigned' => TRUE, 'auto_increment' => TRUE, 'null' => FALSE],
                    'department_id' => ['type' => 'INT', 'unsigned' => TRUE, 'null' => FALSE],
                    'forecast_date' => ['type' => 'DATE', 'null' => FALSE],
                    'predicted_attendance_count' => ['type' => 'INT', 'unsigned' => TRUE, 'null' => FALSE],
                    'model_version' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => FALSE],
                    'generated_at' => ['type' => 'DATETIME', 'null' => FALSE],
                ])
                ->add_key('id', primary: TRUE)
                ->add_key(['department_id', 'forecast_date', 'model_version'], unique: TRUE, name: 'department_forecast_model_unique')
                ->add_key('forecast_date', name: 'forecast_date_idx')
                ->add_foreign_key('department_id', 'departments', 'id', 'CASCADE')
                ->create_table('model_forecasts');
        }
    }

    public function down()
    {
        $dbforge = $this->_lava->dbforge;

        foreach (['model_forecasts', 'model_training_logs', 'attendance_records', 'employees', 'departments'] as $table) {
            if ($dbforge->table_exists($table)) {
                $dbforge->drop_table($table);
            }
        }

        if ($dbforge->table_exists('users') && $dbforge->column_exists('users', 'account_status')) {
            if ($this->index_exists('users', 'account_status_idx')) {
                $this->_lava->db->raw("ALTER TABLE `users` DROP INDEX `account_status_idx`");
            }
            $dbforge->drop_column('users', 'account_status');
        }
    }

    private function index_exists($table, $index)
    {
        $result = $this->_lava->db->raw(
            'SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = :table AND index_name = :index',
            ['table' => $table, 'index' => $index]
        );

        return (int) $result->fetchColumn() > 0;
    }
}
