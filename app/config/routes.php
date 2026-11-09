<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
/**
 * ------------------------------------------------------------------
 * LavaLust - an opensource lightweight PHP MVC Framework
 * ------------------------------------------------------------------
 *
 * MIT License
 *
 * Copyright (c) 2020 Ronald M. Marasigan
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 *
 * @package LavaLust
 * @author Ronald M. Marasigan <ronald.marasigan@yahoo.com>
 * @since Version 1
 * @link https://github.com/ronmarasigan/LavaLust
 * @license https://opensource.org/licenses/MIT MIT License
 */

/*
| -------------------------------------------------------------------
| URI ROUTING
| -------------------------------------------------------------------
| Here is where you can register web routes for your application.
|
|
*/
/** @var object $router **/

$router->get('/', 'Welcome::index');
$router->get('api/health', 'ApiController::health');
$router->get('cli/provision-admin', 'ProvisionAdmin::create');
$router->post('api/auth/register', 'ApiController::register');
$router->post('api/auth/login', 'ApiController::login');
$router->post('api/auth/refresh', 'ApiController::refresh');
$router->post('api/auth/logout', 'ApiController::logout');
$router->get('api/auth/me', 'ApiController::me');
$router->get('api/departments', 'ApiController::departments');
$router->get('api/admin/departments', 'ApiController::admin_departments');
$router->post('api/admin/departments', 'ApiController::create_department');
$router->patch('api/admin/departments/{id}', 'ApiController::update_department');
$router->patch('api/admin/departments/{id}/status', 'ApiController::department_status');
$router->get('api/admin/registrations', 'ApiController::pending_registrations');
$router->patch('api/admin/registrations/{id}/approve', 'ApiController::approve_registration');
$router->patch('api/admin/registrations/{id}/reject', 'ApiController::reject_registration');
$router->get('api/admin/employees', 'ApiController::employees');
$router->patch('api/admin/employees/{id}/status', 'ApiController::employee_status');
$router->get('api/employee/attendance', 'ApiController::employee_attendance');
$router->get('api/employee/attendance/today', 'ApiController::today_attendance');
$router->post('api/attendance/time-in', 'ApiController::time_in');
$router->post('api/attendance/time-out', 'ApiController::time_out');
$router->get('api/admin/dashboard', 'ApiController::admin_dashboard');
$router->get('api/admin/analytics', 'ApiController::admin_analytics');
$router->get('api/admin/attendance', 'ApiController::admin_attendance');
$router->get('api/admin/reports/attendance', 'ApiController::attendance_report');
$router->get('api/admin/reports/attendance/export', 'ApiController::export_attendance_report');

// Migration Routes
$router->get('create-migration/{migration_class}', 'MigrationController::create_migration');
$router->get('migrate', 'MigrationController::migrate');
$router->get('rollback', 'MigrationController::rollback');
$router->get('rollback-all', 'MigrationController::rollback_all');
$router->get('refresh', 'MigrationController::refresh');
$router->get('status', 'MigrationController::status');
