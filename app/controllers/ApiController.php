<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class ApiController extends Controller
{
    public function health()
    {
        $this->response->send_json_success(
            ['service' => 'lavalust-api'],
            'LavaLust API is reachable'
        );
    }

    public function register()
    {
        $api = $this->api();
        $api->require_method('POST');
        $api->rate_limit('register_' . $this->client_ip(), 5, 3600);
        $input = $api->body();

        $name = trim((string) ($input['full_name'] ?? ''));
        $age = filter_var($input['age'] ?? null, FILTER_VALIDATE_INT);
        $address = trim((string) ($input['address'] ?? ''));
        $email = strtolower(trim((string) ($input['email'] ?? '')));
        $password = (string) ($input['password'] ?? '');
        $errors = [];

        if ($name === '' || mb_strlen($name) > 160) $errors['full_name'] = 'Enter a name of up to 160 characters.';
        if ($age === false || $age < 1 || $age > 255) $errors['age'] = 'Enter a valid age.';
        if ($address === '') $errors['address'] = 'Enter an address.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) $errors['email'] = 'Enter a valid email address.';
        if (strlen($password) < 8 || strlen($password) > 72) $errors['password'] = 'Password must be 8 to 72 bytes.';
        if ($errors) return $this->response->send_json_error('Please correct the highlighted fields.', 422, ['errors' => $errors]);

        $db = $this->call->database();
        $existing = $db->raw('SELECT id FROM users WHERE email = ? LIMIT 1', [$email])->fetch(PDO::FETCH_ASSOC);
        if ($existing) {
            return $this->response->send_json_error('An account with this email already exists.', 409);
        }
        $db->transaction();
        try {
            $db->raw(
                "INSERT INTO users (email, password, role, account_status) VALUES (?, ?, 'employee', 'pending')",
                [$email, password_hash($password, PASSWORD_DEFAULT)]
            );
            $userId = $db->last_id();
            $db->raw(
                'INSERT INTO employees (user_id, full_name, age, address) VALUES (?, ?, ?, ?)',
                [$userId, $name, $age, $address]
            );
            $db->commit();
        } catch (Throwable $exception) {
            $db->roll_back();
            if ($exception instanceof PDOException && ($exception->errorInfo[1] ?? null) === 1062) {
                return $this->response->send_json_error('An account with this email already exists.', 409);
            }
            error_log('Employee registration failed (' . get_class($exception) . ').');
            return $this->response->send_json_error('Registration could not be completed.', 500);
        }

        return $this->response->send_json_success(
            ['account_status' => 'pending'],
            'Registration submitted. Your account is awaiting Admin review.',
            201
        );
    }

    public function login()
    {
        $api = $this->api();
        $api->require_method('POST');
        $api->rate_limit('login_' . $this->client_ip(), 10, 60);
        $input = $api->body();
        $email = strtolower(trim((string) ($input['email'] ?? '')));
        $password = (string) ($input['password'] ?? '');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
            return $this->response->send_json_error('Email or password is incorrect.', 401);
        }

        $db = $this->call->database();
        $statement = $db->raw(
            'SELECT id, email, password, role, account_status FROM users WHERE email = ? LIMIT 1',
            [$email]
        );
        $user = $statement->fetch(PDO::FETCH_ASSOC);
        if (!$user || !password_verify($password, $user['password'])) {
            return $this->response->send_json_error('Email or password is incorrect.', 401);
        }
        if ($user['account_status'] !== 'active' || !in_array($user['role'], ['admin', 'employee'], true)) {
            return $this->response->send_json_error('This account is not active.', 403);
        }

        $profile = $this->profile_for_user($user['id']);
        if (!$profile) return $this->response->send_json_error('This account is not active.', 403);

        $tokens = $api->issue_tokens(['id' => $user['id'], 'role' => $user['role']]);
        $this->set_refresh_cookie($tokens['refresh_token']);
        return $this->response->send_json_success([
            'access_token' => $tokens['access_token'],
            'expires_in' => $tokens['expires_in'],
            'token_type' => $tokens['token_type'],
            'user' => $profile,
        ], 'Signed in successfully.');
    }

    public function refresh()
    {
        $api = $this->api();
        $api->require_method('POST');
        $refreshToken = $_COOKIE['refresh_token'] ?? '';
        $payload = $api->validate_jwt($refreshToken, 'refresh');
        if (!$payload) {
            $this->clear_refresh_cookie();
            return $this->response->send_json_error('Sign in again to continue.', 401);
        }

        $db = $this->call->database();
        $tokenHash = hash_hmac('sha256', $refreshToken, config_item('refresh_token_key'));
        $db->transaction();
        try {
            $tokenStatement = $db->raw(
                'SELECT user_id FROM refresh_tokens WHERE token = ? AND expires_at > NOW() FOR UPDATE',
                [$tokenHash]
            );
            $storedToken = $tokenStatement->fetch(PDO::FETCH_ASSOC);
            if (!$storedToken || (string) $storedToken['user_id'] !== (string) $payload['sub']) {
                $db->roll_back();
                $this->clear_refresh_cookie();
                return $this->response->send_json_error('Sign in again to continue.', 401);
            }

            $userStatement = $db->raw('SELECT id, role, account_status FROM users WHERE id = ? LIMIT 1', [$payload['sub']]);
            $user = $userStatement->fetch(PDO::FETCH_ASSOC);
            if (!$user || $user['account_status'] !== 'active' || !in_array($user['role'], ['admin', 'employee'], true)) {
                $api->revoke_refresh_token($refreshToken);
                $db->commit();
                $this->clear_refresh_cookie();
                return $this->response->send_json_error('This account is not active.', 403);
            }

        $profile = $this->profile_for_user($user['id']);
        if (!$profile) {
            $api->revoke_refresh_token($refreshToken);
            $db->commit();
            $this->clear_refresh_cookie();
            return $this->response->send_json_error('This account is not active.', 403);
        }

            $api->revoke_refresh_token($refreshToken);
            $tokens = $api->issue_tokens(['id' => $user['id'], 'role' => $user['role']]);
            $db->commit();
        } catch (Throwable $exception) {
            $db->roll_back();
            error_log('Token refresh failed (' . get_class($exception) . ').');
            return $this->response->send_json_error('Session refresh failed.', 500);
        }

        $this->set_refresh_cookie($tokens['refresh_token']);
        return $this->response->send_json_success([
            'access_token' => $tokens['access_token'],
            'expires_in' => $tokens['expires_in'],
            'token_type' => $tokens['token_type'],
            'user' => $profile,
        ], 'Session refreshed.');
    }

    public function logout()
    {
        $api = $this->api();
        $api->require_method('POST');
        $refreshToken = $_COOKIE['refresh_token'] ?? '';
        if ($refreshToken !== '') {
            $this->call->database();
            $api->revoke_refresh_token($refreshToken);
        }
        $this->clear_refresh_cookie();
        return $this->response->send_json_success(NULL, 'Signed out successfully.');
    }

    public function me()
    {
        $api = $this->api();
        $claims = $this->authenticated_claims($api);
        $user = $this->profile_for_user($claims['sub']);
        if (!$user) {
            return $this->response->send_json_error('Unauthorized.', 401);
        }

        return $this->response->send_json_success(['user' => $user]);
    }

    public function departments()
    {
        $db = $this->call->database();
        $statement = $db->raw(
            "SELECT id, department_name, description FROM departments WHERE status = 'active' ORDER BY department_name"
        );
        return $this->response->send_json_success(['departments' => $statement->fetchAll(PDO::FETCH_ASSOC)]);
    }

    public function admin_departments()
    {
        $this->require_admin($this->api());
        $statement = $this->call->database()->raw(
            'SELECT id, department_name, description, status FROM departments ORDER BY department_name'
        );
        return $this->response->send_json_success(['departments' => $statement->fetchAll(PDO::FETCH_ASSOC)]);
    }

    public function create_department()
    {
        $this->require_admin($this->api());
        $input = $this->api->body();
        $name = trim((string) ($input['department_name'] ?? ''));
        $description = trim((string) ($input['description'] ?? ''));
        $errors = [];
        if ($name === '' || mb_strlen($name) > 150) $errors['department_name'] = 'Enter a department name of up to 150 characters.';
        if (mb_strlen($description) > 5000) $errors['description'] = 'Description is too long.';
        if ($errors) return $this->response->send_json_error('Please correct the highlighted fields.', 422, ['errors' => $errors]);

        $db = $this->call->database();
        $exists = $db->raw('SELECT id FROM departments WHERE department_name = ? LIMIT 1', [$name])->fetch(PDO::FETCH_ASSOC);
        if ($exists) return $this->response->send_json_error('A department with this name already exists.', 409);
        $db->raw('INSERT INTO departments (department_name, description, status) VALUES (?, ?, ?)', [$name, $description ?: NULL, 'active']);
        return $this->response->send_json_success(['id' => (int) $db->last_id()], 'Department created.', 201);
    }

    public function update_department($id)
    {
        $this->require_admin($this->api());
        $departmentId = $this->positive_id($id);
        $input = $this->api->body();
        $name = trim((string) ($input['department_name'] ?? ''));
        $description = trim((string) ($input['description'] ?? ''));
        $errors = [];
        if ($departmentId === NULL) return $this->response->send_json_error('Department not found.', 404);
        if ($name === '' || mb_strlen($name) > 150) $errors['department_name'] = 'Enter a department name of up to 150 characters.';
        if (mb_strlen($description) > 5000) $errors['description'] = 'Description is too long.';
        if ($errors) return $this->response->send_json_error('Please correct the highlighted fields.', 422, ['errors' => $errors]);

        $db = $this->call->database();
        $exists = $db->raw('SELECT id FROM departments WHERE department_name = ? AND id <> ? LIMIT 1', [$name, $departmentId])->fetch(PDO::FETCH_ASSOC);
        if ($exists) return $this->response->send_json_error('A department with this name already exists.', 409);
        $result = $db->raw('UPDATE departments SET department_name = ?, description = ?, updated_at = NOW() WHERE id = ?', [$name, $description ?: NULL, $departmentId]);
        if ($result->rowCount() === 0) {
            $found = $db->raw('SELECT id FROM departments WHERE id = ?', [$departmentId])->fetch(PDO::FETCH_ASSOC);
            if (!$found) return $this->response->send_json_error('Department not found.', 404);
        }
        return $this->response->send_json_success(NULL, 'Department updated.');
    }

    public function department_status($id)
    {
        $this->require_admin($this->api());
        $departmentId = $this->positive_id($id);
        if ($departmentId === NULL) return $this->response->send_json_error('Department not found.', 404);
        $input = $this->api->body();
        $status = $input['status'] ?? '';
        if (!in_array($status, ['active', 'inactive'], true)) {
            return $this->response->send_json_error('Status must be active or inactive.', 422);
        }
        $result = $this->call->database()->raw('UPDATE departments SET status = ?, updated_at = NOW() WHERE id = ?', [$status, $departmentId]);
        if ($result->rowCount() === 0) {
            $found = $this->call->database()->raw('SELECT id FROM departments WHERE id = ?', [$departmentId])->fetch(PDO::FETCH_ASSOC);
            if (!$found) return $this->response->send_json_error('Department not found.', 404);
        }
        return $this->response->send_json_success(['status' => $status], 'Department status updated.');
    }

    public function pending_registrations()
    {
        $this->require_admin($this->api());
        $statement = $this->call->database()->raw(
            "SELECT e.id, e.full_name, e.age, e.address, u.email, e.created_at "
            . "FROM employees e JOIN users u ON u.id = e.user_id "
            . "WHERE u.role = 'employee' AND u.account_status = 'pending' ORDER BY e.created_at ASC"
        );
        return $this->response->send_json_success(['registrations' => $statement->fetchAll(PDO::FETCH_ASSOC)]);
    }

    public function approve_registration($id)
    {
        $adminId = $this->require_admin($this->api());
        $employeeId = $this->positive_id($id);
        if ($employeeId === NULL) return $this->response->send_json_error('Registration not found.', 404);
        $input = $this->api->body();
        $departmentId = $this->positive_id($input['department_id'] ?? NULL);
        if ($departmentId === NULL) return $this->response->send_json_error('Select an active department.', 422);

        $db = $this->call->database();
        $db->transaction();
        try {
            $department = $db->raw(
                "SELECT id FROM departments WHERE id = ? AND status = 'active' FOR UPDATE",
                [$departmentId]
            )->fetch(PDO::FETCH_ASSOC);
            if (!$department) {
                $db->roll_back();
                return $this->response->send_json_error('Select an active department.', 422);
            }

            $account = $db->raw(
                "SELECT u.id FROM users u JOIN employees e ON e.user_id = u.id "
                . "WHERE e.id = ? AND u.role = 'employee' AND u.account_status = 'pending' FOR UPDATE",
                [$employeeId]
            )->fetch(PDO::FETCH_ASSOC);
            if (!$account) {
                $db->roll_back();
                return $this->response->send_json_error('Registration is no longer pending.', 409);
            }

            $db->raw(
                "UPDATE users SET account_status = 'active', updated_at = NOW() WHERE id = ? AND account_status = 'pending'",
                [$account['id']]
            );
            $db->raw(
                'UPDATE employees SET department_id = ?, approved_by = ?, approved_at = NOW(), updated_at = NOW() WHERE id = ?',
                [$departmentId, $adminId, $employeeId]
            );
            $db->commit();
        } catch (Throwable $exception) {
            $db->roll_back();
            error_log('Registration approval failed (' . get_class($exception) . ').');
            return $this->response->send_json_error('Registration approval could not be completed.', 500);
        }

        return $this->response->send_json_success(NULL, 'Registration approved.');
    }

    public function reject_registration($id)
    {
        $this->require_admin($this->api());
        $employeeId = $this->positive_id($id);
        if ($employeeId === NULL) return $this->response->send_json_error('Registration not found.', 404);
        $db = $this->call->database();
        $result = $db->raw(
            "UPDATE users u JOIN employees e ON e.user_id = u.id "
            . "SET u.account_status = 'rejected', u.updated_at = NOW() "
            . "WHERE e.id = ? AND u.role = 'employee' AND u.account_status = 'pending'",
            [$employeeId]
        );
        if ($result->rowCount() === 0) return $this->response->send_json_error('Registration is no longer pending.', 409);
        return $this->response->send_json_success(NULL, 'Registration rejected.');
    }

    public function employees()
    {
        $this->require_admin($this->api());
        $statement = $this->call->database()->raw(
            "SELECT e.id, e.full_name, e.age, e.address, u.email, u.account_status, e.department_id, d.department_name, e.approved_at "
            . "FROM employees e JOIN users u ON u.id = e.user_id LEFT JOIN departments d ON d.id = e.department_id "
            . "WHERE u.role = 'employee' ORDER BY e.full_name"
        );
        return $this->response->send_json_success(['employees' => $statement->fetchAll(PDO::FETCH_ASSOC)]);
    }

    public function employee_status($id)
    {
        $this->require_admin($this->api());
        $employeeId = $this->positive_id($id);
        if ($employeeId === NULL) return $this->response->send_json_error('Employee not found.', 404);
        $input = $this->api->body();
        $status = $input['account_status'] ?? '';
        if (!in_array($status, ['active', 'disabled'], true)) {
            return $this->response->send_json_error('Status must be active or disabled.', 422);
        }
        $result = $this->call->database()->raw(
            "UPDATE users u JOIN employees e ON e.user_id = u.id SET u.account_status = ?, u.updated_at = NOW() "
            . "WHERE e.id = ? AND u.role = 'employee' AND u.account_status IN ('active', 'disabled')",
            [$status, $employeeId]
        );
        if ($result->rowCount() === 0) {
            $existing = $this->call->database()->raw(
                "SELECT u.account_status FROM users u JOIN employees e ON e.user_id = u.id WHERE e.id = ? AND u.role = 'employee' LIMIT 1",
                [$employeeId]
            )->fetch(PDO::FETCH_ASSOC);
            if (!$existing || $existing['account_status'] !== $status) {
                return $this->response->send_json_error('Employee account was not found or cannot be changed.', 404);
            }
        }
        return $this->response->send_json_success(['account_status' => $status], 'Employee account status updated.');
    }

    public function admin_dashboard()
    {
        $this->require_admin($this->api());
        $db = $this->call->database();
        $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Manila'));
        $today = $now->format('Y-m-d');

        $counts = $db->raw(
            "SELECT COUNT(e.id) AS total_employees, "
            . "SUM(CASE WHEN u.account_status = 'active' THEN 1 ELSE 0 END) AS active_employees, "
            . "SUM(CASE WHEN u.account_status = 'pending' THEN 1 ELSE 0 END) AS pending_registrations "
            . "FROM employees e JOIN users u ON u.id = e.user_id WHERE u.role = 'employee'"
        )->fetch(PDO::FETCH_ASSOC);
        $recorded = $db->raw('SELECT COUNT(DISTINCT employee_id) AS recorded_today FROM attendance_records WHERE attendance_date = ?', [$today])
            ->fetch(PDO::FETCH_ASSOC);
        $activeCount = (int) ($counts['active_employees'] ?? 0);
        $attendanceCount = (int) ($recorded['recorded_today'] ?? 0);
        $rate = ((int) $now->format('N') < 6 && $activeCount > 0)
            ? round(($attendanceCount / $activeCount) * 100, 1)
            : NULL;

        $departmentStatement = $db->raw(
            'SELECT d.id, d.department_name, '
            . "COUNT(DISTINCT CASE WHEN u.account_status = 'active' AND u.role = 'employee' THEN e.id END) AS active_headcount, "
            . 'COUNT(DISTINCT a.employee_id) AS recorded_count '
            . 'FROM departments d LEFT JOIN employees e ON e.department_id = d.id '
            . 'LEFT JOIN users u ON u.id = e.user_id '
            . 'LEFT JOIN attendance_records a ON a.employee_id = e.id AND a.attendance_date = ? '
            . 'GROUP BY d.id, d.department_name ORDER BY d.department_name',
            [$today]
        );
        $recentStatement = $db->raw(
            "SELECT e.id, e.full_name, u.email, u.account_status, e.created_at "
            . "FROM employees e JOIN users u ON u.id = e.user_id WHERE u.role = 'employee' "
            . 'ORDER BY e.created_at DESC LIMIT 5'
        );

        return $this->response->send_json_success([
            'today' => $today,
            'is_working_day' => (int) $now->format('N') < 6,
            'total_employees' => (int) ($counts['total_employees'] ?? 0),
            'active_employees' => $activeCount,
            'pending_registrations' => (int) ($counts['pending_registrations'] ?? 0),
            'recorded_attendance_count' => $attendanceCount,
            'recorded_attendance_rate' => $rate,
            'departments' => $departmentStatement->fetchAll(PDO::FETCH_ASSOC),
            'recent_registrations' => $recentStatement->fetchAll(PDO::FETCH_ASSOC),
        ]);
    }

    public function admin_attendance()
    {
        $api = $this->api();
        $this->require_admin($api);
        $filters = $this->attendance_filters($api);
        if ($filters === NULL) return;
        $rows = $this->attendance_rows($filters);
        return $this->response->send_json_success(['filters' => $filters, 'attendance' => $rows]);
    }

    public function attendance_report()
    {
        $api = $this->api();
        $this->require_admin($api);
        $filters = $this->attendance_filters($api);
        if ($filters === NULL) return;
        $rows = $this->attendance_rows($filters);
        $workdays = $this->workdays_in_range($filters['from'], $filters['to']);
        $db = $this->call->database();
        $departmentStatement = $db->raw(
            'SELECT d.id, d.department_name, '
            . "COUNT(DISTINCT CASE WHEN u.account_status = 'active' AND u.role = 'employee' THEN e.id END) AS active_headcount, "
            . 'COUNT(DISTINCT a.id) AS recorded_employee_days '
            . 'FROM departments d LEFT JOIN employees e ON e.department_id = d.id '
            . 'LEFT JOIN users u ON u.id = e.user_id '
            . 'LEFT JOIN attendance_records a ON a.employee_id = e.id AND a.attendance_date BETWEEN ? AND ? '
            . 'WHERE (? IS NULL OR d.id = ?) '
            . 'GROUP BY d.id, d.department_name ORDER BY d.department_name',
            [$filters['from'], $filters['to'], $filters['department_id'], $filters['department_id']]
        );
        $summaries = $departmentStatement->fetchAll(PDO::FETCH_ASSOC);
        foreach ($summaries as &$summary) {
            $denominator = (int) $summary['active_headcount'] * $workdays;
            $summary['recorded_attendance_rate'] = $denominator > 0
                ? round(((int) $summary['recorded_employee_days'] / $denominator) * 100, 1)
                : NULL;
        }
        unset($summary);

        return $this->response->send_json_success([
            'filters' => $filters,
            'working_days_in_range' => $workdays,
            'attendance' => $rows,
            'department_summary' => $summaries,
        ]);
    }

    public function admin_analytics()
    {
        $this->require_admin($this->api());
        $db = $this->call->database();
        $since = (new DateTimeImmutable('now', new DateTimeZone('Asia/Manila')))->modify('-29 days')->format('Y-m-d');
        $dailyRows = $db->raw(
            'SELECT a.attendance_date, COUNT(DISTINCT a.employee_id) AS recorded_count, '
            . "SUM(CASE WHEN a.source = 'SIMULATED' THEN 1 ELSE 0 END) AS simulated_records, "
            . "SUM(CASE WHEN a.source = 'LIVE' THEN 1 ELSE 0 END) AS live_records "
            . 'FROM attendance_records a WHERE a.attendance_date >= ? '
            . 'GROUP BY a.attendance_date ORDER BY a.attendance_date',
            [$since]
        )->fetchAll(PDO::FETCH_ASSOC);
        $dailyByDate = [];
        foreach ($dailyRows as $row) $dailyByDate[$row['attendance_date']] = $row;
        $daily = [];
        $day = new DateTimeImmutable($since, new DateTimeZone('Asia/Manila'));
        $lastDay = new DateTimeImmutable('now', new DateTimeZone('Asia/Manila'));
        while ($day <= $lastDay) {
            if ((int) $day->format('N') < 6) {
                $date = $day->format('Y-m-d');
                $daily[] = $dailyByDate[$date] ?? [
                    'attendance_date' => $date, 'recorded_count' => 0,
                    'simulated_records' => 0, 'live_records' => 0,
                ];
            }
            $day = $day->modify('+1 day');
        }
        $summary = $db->raw(
            'SELECT COUNT(DISTINCT a.employee_id, a.attendance_date) AS recorded_employee_days, '
            . "SUM(CASE WHEN a.source = 'SIMULATED' THEN 1 ELSE 0 END) AS simulated_records, "
            . "SUM(CASE WHEN a.source = 'LIVE' THEN 1 ELSE 0 END) AS live_records "
            . 'FROM attendance_records a WHERE a.attendance_date >= ?', [$since]
        )->fetch(PDO::FETCH_ASSOC);
        $workdays = $this->workdays_in_range($since, (new DateTimeImmutable('now', new DateTimeZone('Asia/Manila')))->format('Y-m-d'));
        $active = $db->raw("SELECT COUNT(*) AS headcount FROM employees e JOIN users u ON u.id=e.user_id WHERE u.role='employee' AND u.account_status='active'")->fetch(PDO::FETCH_ASSOC);
        $denominator = (int) $active['headcount'] * $workdays;
        $summary['recorded_attendance_rate'] = $denominator > 0
            ? round(((int) $summary['recorded_employee_days'] / $denominator) * 100, 1) : NULL;
        $model = $db->raw(
            'SELECT model_version, dataset_type, training_completed_at, mae, rmse, wape '
            . 'FROM model_training_logs WHERE activation_status = 1 AND training_status = \'successful\' '
            . 'ORDER BY training_completed_at DESC LIMIT 1'
        )->fetch(PDO::FETCH_ASSOC);
        $latest = $db->raw(
            'SELECT model_version, dataset_type, training_started_at, training_completed_at, training_status, mae, rmse, wape, activation_status, failure_reason '
            . 'FROM model_training_logs ORDER BY id DESC LIMIT 1'
        )->fetch(PDO::FETCH_ASSOC);
        $forecasts = [];
        if ($model) {
            $forecastDates = [];
            $forecastDay = (new DateTimeImmutable('now', new DateTimeZone('Asia/Manila')))->modify('+1 day');
            while (count($forecastDates) < 5) {
                if ((int) $forecastDay->format('N') < 6) $forecastDates[] = $forecastDay->format('Y-m-d');
                $forecastDay = $forecastDay->modify('+1 day');
            }
            $forecasts = $db->raw(
                'SELECT f.forecast_date, f.predicted_attendance_count, f.generated_at, d.id AS department_id, d.department_name '
                . 'FROM model_forecasts f JOIN departments d ON d.id=f.department_id '
                . 'WHERE f.model_version = ? AND f.forecast_date IN (' . implode(',', array_fill(0, count($forecastDates), '?')) . ') '
                . 'ORDER BY f.forecast_date, d.department_name',
                array_merge([$model['model_version']], $forecastDates)
            )->fetchAll(PDO::FETCH_ASSOC);
        }
        return $this->response->send_json_success([
            'from' => $since, 'to' => (new DateTimeImmutable('now', new DateTimeZone('Asia/Manila')))->format('Y-m-d'),
            'working_days' => $workdays, 'active_headcount' => (int) $active['headcount'],
            'summary' => $summary, 'daily' => $daily, 'active_model' => $model ?: NULL,
            'latest_training' => $latest ?: NULL, 'forecasts' => $forecasts,
        ]);
    }

    public function export_attendance_report()
    {
        $api = $this->api();
        $this->require_admin($api);
        $filters = $this->attendance_filters($api);
        if ($filters === NULL) return;
        $rows = $this->attendance_rows($filters);

        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, ['Date', 'Employee', 'Email', 'Department', 'Time In', 'Time Out', 'Source'], ',', '"', '');
        foreach ($rows as $row) {
            fputcsv($stream, array_map([$this, 'csv_safe_value'], [
                $row['attendance_date'], $row['full_name'], $row['email'], $row['department_name'],
                $row['time_in'], $row['time_out'], $row['source'],
            ]), ',', '"', '');
        }
        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        $this->response->add_header('Content-Type', 'text/csv; charset=utf-8');
        $this->response->add_header('Content-Disposition', 'attachment; filename="attendance-report.csv"');
        $this->response->set_status_code(200);
        $this->response->set_content($csv);
        return $this->response->send();
    }

    public function employee_attendance()
    {
        $employee = $this->require_employee($this->api(), FALSE);
        $statement = $this->call->database()->raw(
            'SELECT attendance_date, time_in, time_out, source FROM attendance_records WHERE employee_id = ? ORDER BY attendance_date DESC',
            [$employee['id']]
        );
        return $this->response->send_json_success(['attendance' => $statement->fetchAll(PDO::FETCH_ASSOC)]);
    }

    public function today_attendance()
    {
        $employee = $this->require_employee($this->api(), TRUE);
        $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Manila'));
        $date = $now->format('Y-m-d');
        $statement = $this->call->database()->raw(
            'SELECT attendance_date, time_in, time_out, source FROM attendance_records WHERE employee_id = ? AND attendance_date = ? LIMIT 1',
            [$employee['id'], $date]
        );
        $record = $statement->fetch(PDO::FETCH_ASSOC);
        return $this->response->send_json_success([
            'date' => $date,
            'is_working_day' => (int) $now->format('N') < 6,
            'state' => !$record ? 'not_started' : ($record['time_out'] ? 'complete' : 'timed_in'),
            'attendance' => $record ?: NULL,
        ]);
    }

    public function time_in()
    {
        $this->api()->require_method('POST');
        $employee = $this->require_employee($this->api(), TRUE);
        $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Manila'));
        if ((int) $now->format('N') >= 6) {
            return $this->response->send_json_error('Time-in is available on weekdays only.', 422);
        }

        $date = $now->format('Y-m-d');
        $timestamp = $now->format('Y-m-d H:i:s');
        $db = $this->call->database();
        $existing = $db->raw(
            'SELECT id FROM attendance_records WHERE employee_id = ? AND attendance_date = ? LIMIT 1',
            [$employee['id'], $date]
        )->fetch(PDO::FETCH_ASSOC);
        if ($existing) return $this->response->send_json_error('Time-in has already been recorded for today.', 409);

        $db->raw(
            "INSERT INTO attendance_records (employee_id, attendance_date, time_in, source) VALUES (?, ?, ?, 'SIMULATED')",
            [$employee['id'], $date, $timestamp]
        );
        return $this->response->send_json_success(
            ['attendance_date' => $date, 'time_in' => $timestamp, 'source' => 'SIMULATED'],
            'Time-in recorded.'
        );
    }

    public function time_out()
    {
        $this->api()->require_method('POST');
        $employee = $this->require_employee($this->api(), TRUE);
        $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Manila'));
        $date = $now->format('Y-m-d');
        $timestamp = $now->format('Y-m-d H:i:s');
        $db = $this->call->database();
        $db->transaction();
        try {
            $statement = $db->raw(
                'SELECT id, time_in, time_out FROM attendance_records WHERE employee_id = ? AND attendance_date = ? FOR UPDATE',
                [$employee['id'], $date]
            );
            $record = $statement->fetch(PDO::FETCH_ASSOC);
            if (!$record) {
                $db->roll_back();
                return $this->response->send_json_error('Record time-in before time-out.', 409);
            }
            if ($record['time_out'] !== NULL) {
                $db->roll_back();
                return $this->response->send_json_error('Time-out has already been recorded for today.', 409);
            }
            if (strtotime($timestamp) <= strtotime($record['time_in'])) {
                $db->roll_back();
                return $this->response->send_json_error('Time-out must be later than time-in.', 422);
            }
            $db->raw('UPDATE attendance_records SET time_out = ?, updated_at = NOW() WHERE id = ? AND time_out IS NULL', [$timestamp, $record['id']]);
            $db->commit();
        } catch (Throwable $exception) {
            $db->roll_back();
            error_log('Attendance time-out failed (' . get_class($exception) . ').');
            return $this->response->send_json_error('Time-out could not be recorded.', 500);
        }

        return $this->response->send_json_success(
            ['attendance_date' => $date, 'time_out' => $timestamp],
            'Time-out recorded.'
        );
    }

    private function api()
    {
        $this->call->library('api');
        return $this->api;
    }

    private function client_ip()
    {
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }

    private function require_admin($api)
    {
        $claims = $this->authenticated_claims($api);
        $statement = $this->call->database()->raw(
            'SELECT role, account_status FROM users WHERE id = ? LIMIT 1',
            [$claims['sub']]
        );
        $user = $statement->fetch(PDO::FETCH_ASSOC);
        if (!$user || $user['account_status'] !== 'active') {
            $api->respond_error('Unauthorized.', 401);
        }
        if ($user['role'] !== 'admin') {
            $api->respond_error('Forbidden.', 403);
        }
        return (int) $claims['sub'];
    }

    private function require_employee($api, $require_department)
    {
        $claims = $this->authenticated_claims($api);
        $statement = $this->call->database()->raw(
            'SELECT u.role, u.account_status, e.id AS employee_id, e.department_id, d.status AS department_status '
            . 'FROM users u LEFT JOIN employees e ON e.user_id = u.id '
            . 'LEFT JOIN departments d ON d.id = e.department_id WHERE u.id = ? LIMIT 1',
            [$claims['sub']]
        );
        $user = $statement->fetch(PDO::FETCH_ASSOC);
        if (!$user || $user['account_status'] !== 'active') $api->respond_error('Unauthorized.', 401);
        if ($user['role'] !== 'employee' || $user['employee_id'] === NULL) $api->respond_error('Forbidden.', 403);
        if ($require_department && (!$user['department_id'] || $user['department_status'] !== 'active')) {
            $api->respond_error('An active department assignment is required.', 403);
        }
        return ['id' => (int) $user['employee_id'], 'department_id' => $user['department_id'] === NULL ? NULL : (int) $user['department_id']];
    }

    private function authenticated_claims($api)
    {
        $this->call->database();
        return $api->require_jwt();
    }

    private function attendance_filters($api)
    {
        $query = $api->get_query_params();
        $timezone = new DateTimeZone('Asia/Manila');
        $today = (new DateTimeImmutable('now', $timezone))->format('Y-m-d');
        $date = trim((string) ($query['date'] ?? ''));
        $from = trim((string) ($query['from'] ?? ''));
        $to = trim((string) ($query['to'] ?? ''));
        $departmentId = isset($query['department_id']) && $query['department_id'] !== ''
            ? $this->positive_id($query['department_id'])
            : NULL;

        if ($departmentId === NULL && isset($query['department_id']) && $query['department_id'] !== '') {
            $this->response->send_json_error('Department filter is invalid.', 422);
            return NULL;
        }
        if ($date !== '') {
            if ($from !== '' || $to !== '' || !$this->is_date($date)) {
                $this->response->send_json_error('Use a valid date or a complete date range.', 422);
                return NULL;
            }
            $from = $to = $date;
        } elseif ($from === '' && $to === '') {
            $from = $to = $today;
        } elseif (!$this->is_date($from) || !$this->is_date($to) || $from > $to) {
            $this->response->send_json_error('Use a valid date range with start on or before end.', 422);
            return NULL;
        }

        return ['from' => $from, 'to' => $to, 'department_id' => $departmentId];
    }

    private function is_date($value)
    {
        if (!is_string($value) || $value === '') return FALSE;
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('Asia/Manila'));
        return $date !== FALSE && $date->format('Y-m-d') === $value;
    }

    private function profile_for_user($userId)
    {
        $statement = $this->call->database()->raw(
            'SELECT u.id, u.email, u.role, u.account_status, e.full_name, e.department_id, d.department_name '
            . 'FROM users u LEFT JOIN employees e ON e.user_id = u.id '
            . 'LEFT JOIN departments d ON d.id = e.department_id WHERE u.id = ? LIMIT 1',
            [$userId]
        );
        $user = $statement->fetch(PDO::FETCH_ASSOC);
        if (!$user || $user['account_status'] !== 'active' || !in_array($user['role'], ['admin', 'employee'], true)) {
            return NULL;
        }

        unset($user['account_status']);
        $user['id'] = (int) $user['id'];
        $user['department_id'] = $user['department_id'] === NULL ? NULL : (int) $user['department_id'];
        return $user;
    }

    private function attendance_rows($filters)
    {
        $sql = 'SELECT a.attendance_date, e.full_name, u.email, d.department_name, a.time_in, a.time_out, a.source '
            . 'FROM attendance_records a JOIN employees e ON e.id = a.employee_id '
            . 'JOIN users u ON u.id = e.user_id LEFT JOIN departments d ON d.id = e.department_id '
            . 'WHERE a.attendance_date BETWEEN ? AND ?';
        $params = [$filters['from'], $filters['to']];
        if ($filters['department_id'] !== NULL) {
            $sql .= ' AND e.department_id = ?';
            $params[] = $filters['department_id'];
        }
        $sql .= ' ORDER BY a.attendance_date DESC, e.full_name';
        return $this->call->database()->raw($sql, $params)->fetchAll(PDO::FETCH_ASSOC);
    }

    private function workdays_in_range($from, $to)
    {
        $start = new DateTimeImmutable($from, new DateTimeZone('Asia/Manila'));
        $end = new DateTimeImmutable($to, new DateTimeZone('Asia/Manila'));
        $totalDays = (int) $start->diff($end)->days + 1;
        $workdays = intdiv($totalDays, 7) * 5;
        $remaining = $totalDays % 7;
        for ($offset = 0; $offset < $remaining; $offset++) {
            if ((int) $start->modify('+' . $offset . ' day')->format('N') < 6) $workdays++;
        }
        return $workdays;
    }

    private function csv_safe_value($value)
    {
        $value = (string) ($value ?? '');
        return preg_match('/^[=+@\-\t\r]/', $value) ? "'" . $value : $value;
    }

    private function positive_id($value)
    {
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        return $id === false ? NULL : $id;
    }

    private function set_refresh_cookie($token)
    {
        $secure = !empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off';
        $this->response->set_cookie('refresh_token', $token, 604800, '/', '', $secure, TRUE, 'Lax');
    }

    private function clear_refresh_cookie()
    {
        $secure = !empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off';
        setcookie('refresh_token', '', [
            'expires' => time() - 3600,
            'path' => '/',
            'secure' => $secure,
            'httponly' => TRUE,
            'samesite' => 'Lax',
        ]);
    }
}
