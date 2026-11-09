# Municipal Employee Attendance and Workforce Analytics System
## MVP Software Requirements and Implementation Specification

**Document status:** MVP baseline  
**Development status:** Local development only; deployment is out of scope  
**Specification rule:** Requirements in this document define the MVP. Suggestions marked as implementation guidance may be adapted to the installed Lavalust version, but must not change the required system behavior.

---

## 1. Project Overview

The system is a web-based Municipal Employee Attendance and Workforce Analytics System. It allows municipal employees to register, receive Admin approval, and record their daily time-in and time-out. The Admin manages employee registrations and departments, monitors attendance, and views attendance analytics and department-level attendance forecasts.

The MVP demonstrates an end-to-end attendance workflow using simulated biometric-style attendance actions. It does **not** connect to actual biometric hardware.

The system also demonstrates machine-learning-based forecasting using historical attendance data. It begins with generated historical records so the forecasting feature can be demonstrated before sufficient live records have accumulated.

### 1.1 MVP Objectives

1. Provide employee self-registration.
2. Allow an Admin to review and approve or reject registrations.
3. Allow the Admin to assign an approved employee to a municipal department.
4. Allow approved and active employees to record one time-in and one time-out per working day.
5. Allow employees to view their own attendance history.
6. Allow the Admin to monitor employee and department attendance.
7. Calculate attendance statistics from stored attendance records.
8. Generate department-level attendance forecasts for the next five working days.
9. Display understandable, data-grounded analytics and forecast information.
10. Demonstrate a controlled transition from simulated historical data to live attendance data for model training.

### 1.2 Scope Boundary

This is an MVP, not a complete municipal HR or payroll platform. Implement only the features defined here. Do not add modules merely because they are common in HR systems.

---

## 2. Technology Stack and Architecture

### 2.1 Required Stack

| Layer | Technology |
|---|---|
| Frontend | React |
| Styling | Tailwind CSS |
| Backend | Lavalust PHP framework |
| Database | MySQL |
| Machine learning | Python and scikit-learn |
| Initial forecasting model | Random Forest Regressor |
| Authentication | JWT using Lavalust's supported API/authentication facilities |
| Development | Local environment |

### 2.2 Architecture

Use a straightforward frontend/backend/database architecture:

- React provides the user interface.
- React communicates with Lavalust through JSON HTTP API endpoints.
- Lavalust handles authentication, authorization, validation, business rules, database access, and API responses.
- MySQL stores user accounts, employee information, departments, attendance records, model-training logs, and forecasts.
- Python scripts prepare attendance data, train and evaluate the forecasting model, and generate forecasts.
- Python scripts use the same MySQL database or a controlled data exchange mechanism supported by the project. They must not become a second web backend.
- Lavalust remains the main application backend and the authority for application data and business rules.

### 2.3 Lavalust Framework Rules

Database Schema Management: All database tables, columns, indexes, foreign keys, unique constraints, and schema modifications must be created and maintained using Lavalust's native migration system and conventions. Do not manually create tables through phpMyAdmin or raw SQL scripts as the primary schema-management method. Inspect the installed Lavalust version to determine the correct migration commands, file structure, and syntax. Seed initial departments and simulated attendance data using Lavalust-compatible seeders or controlled seed scripts.

**The installed Lavalust framework is the backend source of truth.**

1. Follow Lavalust's existing MVC structure, routing, controllers, models, configuration, middleware, database conventions, and API response patterns.
2. Consult the official documentation: https://lavalust.netlify.app/docs/
3. Inspect the actual installed Lavalust version and existing project files before implementing framework-specific code.
4. Do not assume Laravel conventions, commands, classes, helpers, or directory structures are available in Lavalust.
5. Do not replace Lavalust with Laravel, Node.js, Express, or another backend framework.
6. Do not create a parallel backend architecture.
7. Use the framework's supported facilities for JWT, password hashing, validation, database operations, and JSON responses wherever available.
8. If a documented feature differs from the installed version, follow the installed version and verify its implementation before using it.
9. Keep business logic out of React. The backend must enforce all important rules even if the frontend already validates them.

### 2.4 Local Development Only

Deployment, production hosting, public domain configuration, production email delivery, and production biometric-device connectivity are not MVP requirements. Configure the application for local development and document any environment variables needed to run it.

---

## 3. User Roles and Permissions

The MVP has exactly two application roles.

### 3.1 Employee

An Employee can:

- Register for an account.
- Sign in after the account has been approved and activated.
- View their own account and registration status where applicable.
- View their assigned department.
- Record their own time-in.
- Record their own time-out after recording time-in.
- View their own attendance history.
- View their own attendance summary.

An Employee cannot:

- Approve or reject registrations.
- Assign employees to departments.
- View another employee's personal information or attendance records.
- Access Admin pages or Admin API actions.
- Change their own role, approval status, account status, or department assignment.

### 3.2 Admin

An Admin can:

- Sign in to the Admin area.
- View the Admin dashboard.
- View pending employee registrations.
- Approve or reject employee registrations.
- Assign a department when approving an employee.
- View and manage employee account status.
- View employee and department information.
- Monitor attendance records across the municipality.
- Filter attendance records by date and department.
- View attendance analytics and department-level forecasts.
- View model training and forecast status.
- Generate attendance reports and export report data to CSV.

An Admin cannot use public employee registration to create an Admin account. Admin accounts must be created through a controlled setup or seeding process.

### 3.3 Authorization Requirements

- Authentication identifies the signed-in user.
- Authorization checks the user's role on the backend.
- Hiding an Admin button or route in React is not sufficient authorization.
- Employee data access must be restricted to the authenticated Employee.
- Admin-only endpoints must reject Employee accounts.
- Use `401 Unauthorized` for unauthenticated requests and `403 Forbidden` for authenticated users without permission.

---

## 4. Employee Registration and Approval

### 4.1 Registration Fields

The public registration form collects:

- Full name
- Age
- Address
- Email address
- Password

The registration form must not allow the user to choose an application role, approval status, account status, or department.

### 4.2 Registration Process

1. The visitor submits the registration form.
2. The backend validates the submitted fields.
3. The backend checks that the email address is not already registered.
4. The backend securely hashes the password.
5. The backend creates the `users` and `employees` records in one database transaction.
6. The account is assigned the Employee role and a `pending` account status.
7. The employee's department remains unassigned until Admin approval.
8. The user receives a clear registration-submitted/pending confirmation.
9. The registration appears in the Admin's pending-registration list.

If either database insert fails, the transaction must roll back so that an incomplete account is not created.

### 4.3 Admin Approval

To approve a registration, the Admin must select a department.

On approval:

- Change the account status to `active`.
- Assign the selected department to the employee.
- Store the approving Admin's user ID.
- Store the approval timestamp.
- Make the employee eligible to sign in and record attendance.

Approval and department assignment must be performed as one consistent backend operation.

### 4.4 Admin Rejection

On rejection:

- Change the account status to `rejected`.
- Keep the registration record for administrative reference.
- Do not allow the account to record attendance or access Employee features.

A rejection-reason workflow is not required for the MVP.

### 4.5 Account Statuses

Use these account statuses:

| Status | Meaning |
|---|---|
| `pending` | Registration submitted and awaiting Admin review |
| `active` | Approved account allowed to use Employee features |
| `rejected` | Registration rejected by the Admin |
| `disabled` | Previously created account disabled by the Admin |

Only an active Employee with an assigned department may record attendance.

---

## 5. Department Management

The system must support multiple municipal departments/offices.

The Admin must be able to:

- View the department list.
- Add a department.
- Edit a department's name or description.
- Set a department as active or inactive.

Department names must be unique.

Only active departments should be selectable when approving a new Employee. Existing employee records and historical attendance must remain intact if a department is made inactive.

For the MVP, an employee has one current department assignment. Department transfer history is not included. Historical seeded attendance must therefore use department assignments that remain consistent throughout the seeded historical period.

---

## 6. Attendance Recording

### 6.1 Attendance Schedule

The municipality's standard schedule for the MVP is:

- Time-in: 8:00 AM
- Time-out: 5:00 PM
- Working days: Monday to Friday
- Non-working days: Saturday and Sunday
- Timezone: `Asia/Manila`

The schedule provides the standard workday reference. The MVP records the actual server-generated time of each action. It does not calculate late minutes, overtime, undertime, payroll deductions, or other employment consequences.

### 6.2 Simulated Attendance

The MVP simulates biometric-style attendance actions through the Employee interface.

The Employee uses a Time In action and a Time Out action. The system records these actions in MySQL. These are persistent attendance records, not temporary frontend demonstrations.

Every attendance record must have a source value:

- `SIMULATED` for MVP attendance actions.
- `LIVE` reserved for a future real attendance-device integration.

Do not build biometric-device integration in this MVP.

### 6.3 Time-In Rules

The backend must:

1. Confirm that the request belongs to an authenticated Employee.
2. Confirm that the account is active and approved.
3. Confirm that the Employee has an assigned active department.
4. Determine the attendance date and timestamp using the backend clock and `Asia/Manila`.
5. Reject time-in on Saturday or Sunday.
6. Create no more than one attendance record for that Employee and attendance date.
7. Reject a duplicate time-in, including duplicate or concurrent requests.
8. Store the time-in timestamp and `SIMULATED` source.

The browser must not be trusted to provide the authoritative attendance date or timestamp.

### 6.4 Time-Out Rules

The backend must:

1. Confirm the same Employee eligibility requirements as time-in.
2. Find the Employee's attendance record for the current attendance date.
3. Require an existing time-in.
4. Require that time-out is later than time-in.
5. Reject a duplicate time-out.
6. Store the backend-generated time-out timestamp.

A time-out cannot create a new attendance record by itself.

### 6.5 Record Uniqueness and Data Integrity

- There must be no more than one attendance record per Employee per attendance date.
- Enforce a database unique constraint on `(employee_id, attendance_date)`.
- `time_out` is nullable until the Employee records time-out.
- Use database transactions and appropriate constraints to protect against duplicate or concurrent actions.
- The backend, not React, enforces attendance rules.
- Use a consistent `Asia/Manila` interpretation for attendance dates and displayed times.

### 6.6 Attendance Classification

The MVP does not classify employees as late, absent, undertime, or overtime. It must not infer that an employee is officially absent merely because no time-in record is present.

Use the term **Recorded Attendance Rate** for attendance percentages. For example, if 26 of 30 active employees have a valid time-in record for a date, the Recorded Attendance Rate is 86.7%. The other four employees must not automatically be labelled officially absent.

### 6.7 Attendance Record Corrections

The MVP does not include an attendance correction, manual time editing, exception-resolution, or dispute workflow. Do not presume that incomplete or incorrect attendance records are expected operational cases. The system should prevent invalid actions through its validation and data-integrity rules.

---

## 7. Employee Interface

After signing in, an Employee should see a simple dashboard containing:

1. A welcome/header area with the Employee's name.
2. Account status and assigned department.
3. Today's attendance status.
4. Time In or Time Out action, depending on the current attendance state.
5. The recorded time after a successful action.
6. A recent attendance history section.
7. A link to the Employee's attendance history.

The Employee attendance history should show:

- Attendance date
- Time-in
- Time-out
- Record source, where useful

The Employee must only receive their own data from the backend.

The frontend must clearly disable or hide an action that has already been completed, but the backend must still reject duplicate requests.

---

## 8. Admin Interface

The Admin interface should be organized around the MVP tasks rather than an oversized HR administration suite.

### 8.1 Admin Dashboard

Display useful high-level information:

- Total registered Employees
- Total active Employees
- Pending registrations
- Today's recorded attendance count
- Today's Recorded Attendance Rate
- Department-level attendance overview
- A short view of recent registrations or recent system activity

Counts and percentages must be calculated from database records and must use clearly defined denominators. Do not label the number of recorded time-ins as an official absence or presence determination.

### 8.2 Registration Management

Provide a pending-registration page where the Admin can:

- View registration details.
- Review the submitted name, age, address, and email.
- Select a department when approving.
- Approve the registration.
- Reject the registration.

The page should show the current status and prevent repeated approval/rejection actions from silently creating inconsistent state.

### 8.3 Employee Management

Provide an Employee list with:

- Name
- Email
- Department
- Account status
- Registration/approval information where useful

The Admin can view an Employee and enable or disable an account. Do not provide payroll, leave, recruitment, or full personnel-file management features.

### 8.4 Department Management

Provide a basic department list and create/edit/activate/deactivate actions as described in Section 5.

### 8.5 Attendance Monitoring

Provide a municipality-wide attendance view with:

- Attendance date
- Employee name
- Department
- Time-in
- Time-out
- Record source

Allow filtering by date or date range and department. The Admin can view attendance records but does not edit their timestamps in the MVP.

### 8.6 Analytics and Forecasts

Provide a dedicated analytics view containing:

- Historical Recorded Attendance Rate.
- Department-level attendance trends.
- Daily recorded attendance counts.
- Forecasted attendance counts for the next five working days.
- Model status and latest successful training information.
- A clear indication when forecasts are unavailable or historical data is insufficient.

Keep historical analytics and future forecasts visually distinct.

---

## 9. Attendance Reports

The MVP should provide practical attendance reports without becoming a full reporting platform.

### 9.1 Required Reports

1. **Daily attendance report:** attendance records for a selected date.
2. **Date-range attendance report:** attendance records over a selected date range.
3. **Department attendance summary:** recorded attendance counts and Recorded Attendance Rate by department for a selected date or range.

### 9.2 Filters

Support:

- Date or date range
- Department
- All departments

### 9.3 Export

Provide CSV export for the currently selected report and filters.

Exported data must reflect the same backend filters and values shown in the report. Do not include password hashes, tokens, or other authentication secrets.

PDF export is not required for the MVP.

---

## 10. Attendance Analytics and Machine Learning

### 10.1 Purpose

The analytics feature demonstrates how historical attendance data can be used to summarize recorded attendance patterns and forecast department-level attendance.

The model must not evaluate, rank, score, or make disciplinary recommendations about individual employees.

### 10.2 Analytics Versus Forecasting

Keep these concepts separate:

- **Descriptive analytics:** calculates what is present in the stored attendance records.
- **Predictive analytics:** uses the trained machine-learning model to estimate future department-level recorded attendance counts.
- **Automated insights:** explains calculated trends and forecast results using grounded data.

Descriptive metrics must be calculated directly from database records. Do not use a machine-learning prediction as a substitute for an actual attendance count.

### 10.3 Forecast Target

The model predicts, for each department and each of the next five working days:

**The expected count of distinct active Employees in that department who will have a valid time-in record.**

The forecast is a count, not an individual employee prediction and not an official attendance or absence determination.

### 10.4 Model

Use a Python machine-learning module with scikit-learn and a Random Forest Regressor.

The model should use department-level daily observations. Aggregate attendance into one row per department per working date. Count distinct Employees with valid time-in records; do not count repeated scans or requests as separate attendance.

Potential input features:

- Department identifier or encoded department
- Day of week
- Active employee headcount
- Previous working-day recorded attendance count
- Recent department attendance averages
- Historical department-date attendance values

Only information available at the forecast generation time may be used. Do not leak future attendance values into training features.

The model output must be a non-negative attendance count and must not exceed the department's applicable active headcount. Apply sensible output bounds after prediction if necessary.

### 10.5 Initial Historical Dataset

The MVP must include a reproducible seed-data process that creates approximately 12 weeks of simulated historical attendance records.

The generated dataset should:

- Cover the active municipal departments.
- Use working days only.
- Include a realistic number of Employees per department.
- Have plausible day-to-day variation.
- Include weekday patterns and modest trends.
- Avoid making every department and date identical.
- Maintain consistent employee-to-department assignments throughout the seeded historical period.
- Mark every generated attendance record as `SIMULATED`.

Seed data must be generated by a repeatable script or process. It must not be manually hardcoded into dashboard components.

### 10.6 Simulated-to-Live Data Transition

Use a **live-transition** dataset policy:

1. Initially train and demonstrate the model using simulated historical attendance data.
2. Continue to identify simulated and live records separately.
3. Do not silently mix simulated and live records.
4. Once at least eight weeks of eligible live historical data are available, transition model training to live-only data.
5. After transition, train using all available eligible live historical data, with the dataset growing over time.
6. Do not return to simulated data as a substitute for missing live data after the transition.
7. Clearly label whether the active model was trained using simulated or live data.

An eligible historical observation is one unique department-working-day row, not one individual attendance action.

### 10.7 Retraining Schedule and Threshold

- Check for retraining once per week, every Sunday.
- For local development, schedule the check at 2:00 AM `Asia/Manila`, provided the development machine and scheduler are running.
- A new training attempt requires at least 20 additional eligible department-working-day observations since the last successful model activation.
- Do not count individual scans, duplicate requests, or multiple employees as separate observations for this threshold.
- Before live transition, the model may use the simulated dataset for the initial demonstration and subsequent eligible simulated-data retraining.
- After live transition, use live-only observations and the eight-week minimum live-history requirement.

### 10.8 Model Evaluation

Use chronological data splitting. Do not randomly shuffle time-series observations.

Evaluate using:

- Mean Absolute Error (MAE)
- Root Mean Squared Error (RMSE)
- Weighted Absolute Percentage Error (WAPE), with safe handling when the actual-value denominator is zero

Compare the candidate model against:

1. A weekday-average baseline.
2. The currently active model, if one exists.

Use the same chronological test dates for the comparisons.

MAE is the primary evaluation metric. A candidate can be activated only if it improves on the baseline and does not regress against the previous active model on the same evaluation window. If no previous active model exists, compare against the baseline.

Do not invent an accuracy percentage or claim that the model is reliable merely because training completed. Display evaluation metrics and the training data type.

### 10.9 Failed Training

- If training or evaluation fails, record the failure in the training log.
- Keep the previous active model.
- Do not replace a working model with a failed candidate.
- Retry at the next scheduled Sunday check.
- Do not reset the new-observation counter after a failed attempt.
- Reset the counter only after a candidate model is successfully activated.

### 10.10 Forecast Generation

After a model is successfully activated, generate forecasts for the next five working days for each active department.

Store forecast results in the database with:

- Department
- Forecast date
- Predicted attendance count
- Model version
- Generation timestamp

Do not present stale forecasts as newly generated. Display the forecast generation time and model version.

If there is no active model, the data is insufficient, or forecast generation fails, show a clear unavailable state rather than fabricated forecast values.

### 10.11 Automated Insights

For the MVP, generate short, data-grounded explanations from calculated analytics and stored forecasts. A deterministic/template-based insight layer is sufficient; an external LLM service is not required.

Examples of valid insight types:

- A department's recorded attendance count increased or decreased compared with its recent historical average.
- A department's forecast is higher or lower than its recent average.
- A forecast is unavailable because the required historical data has not accumulated.

Every insight must be traceable to actual calculated metrics or stored forecast results. Do not invent causes, employee behavior, official absence, or disciplinary conclusions.

---

## 11. Authentication and Application Security

### 11.1 JWT Authentication

Use Lavalust's supported JWT/API authentication facilities. Do not write a custom JWT implementation if the installed framework already provides the required functionality.

Proposed MVP token settings:

- Access token lifetime: 15 minutes.
- Refresh token lifetime: 7 days.
- Signing algorithm: HS256, if supported by the installed Lavalust implementation.
- Send access tokens using the `Authorization: Bearer <token>` header.
- Rotate refresh tokens when refreshed if supported by the framework's refresh-token implementation.

If the installed Lavalust version uses a different supported configuration, follow its actual documented implementation while preserving short-lived access tokens and secure refresh handling.

### 11.2 Secret Management

Keep JWT secrets and refresh-token keys in backend environment configuration. Never place them in React source code or commit real secrets to Git.

Use separate, sufficiently strong values for the access-token signing secret and refresh-token key when the framework requires both.

### 11.3 Frontend Token Handling

- Prefer keeping the short-lived access token in memory.
- Prefer a Secure, HttpOnly refresh-token cookie when supported by the local application origin and Lavalust configuration.
- Do not store long-lived refresh tokens in localStorage.
- If an access token expires, attempt one refresh operation.
- If refresh fails, clear the authenticated frontend state and return the user to login.
- Never log tokens or passwords.

### 11.4 Other Security Requirements

- Hash passwords using the framework's supported secure password-hashing facility.
- Validate and authorize all sensitive actions on the backend.
- Restrict CORS to the local React development origin during development.
- Apply reasonable rate limits to login and public registration if supported by the installed framework.
- Use parameterized database operations or the framework's safe query facilities.
- Do not expose SQL errors, filesystem paths, stack traces, secrets, or internal configuration in API responses.
- Validate all user input on the backend.
- Prevent mass assignment of protected fields such as role, account status, approval details, and department assignment.
- Use database foreign keys and unique constraints where appropriate.

---

## 12. Database Requirements

Database Schema Management: All database tables, columns, indexes, foreign keys, unique constraints, and schema modifications must be created and maintained using Lavalust's native migration system and conventions. Do not manually create tables through phpMyAdmin or raw SQL scripts as the primary schema-management method. Inspect the installed Lavalust version to determine the correct migration commands, file structure, and syntax. Seed initial departments and simulated attendance data using Lavalust-compatible seeders or controlled seed scripts.

Use MySQL with appropriate primary keys, foreign keys, indexes, unique constraints, and timestamps. Match naming and migration conventions to the existing Lavalust project.

The following tables represent the MVP logical data model. Adapt physical naming only when necessary to follow the installed project's conventions.

### 12.1 `users`

Stores login accounts.

| Field | Purpose |
|---|---|
| `id` | Primary key |
| `email` | Unique login email |
| `password` | Secure password hash |
| `role` | `admin` or `employee` |
| `account_status` | `pending`, `active`, `rejected`, or `disabled` |
| `created_at` | Creation timestamp |
| `updated_at` | Last update timestamp |

Public registration must always create `role = employee` and `account_status = pending`.

### 12.2 `employees`

Stores Employee profile information.

| Field | Purpose |
|---|---|
| `id` | Primary key |
| `user_id` | Unique foreign key to `users.id` |
| `department_id` | Foreign key to `departments.id`, nullable until approval |
| `full_name` | Employee's submitted name |
| `age` | Employee's submitted age |
| `address` | Employee's submitted address |
| `approved_by` | Nullable foreign key to the approving Admin's `users.id` |
| `approved_at` | Nullable approval timestamp |
| `created_at` | Creation timestamp |
| `updated_at` | Last update timestamp |

An Admin account does not require an `employees` record.

### 12.3 `departments`

Stores municipal departments/offices.

| Field | Purpose |
|---|---|
| `id` | Primary key |
| `department_name` | Unique department name |
| `description` | Optional description |
| `status` | Active or inactive |
| `created_at` | Creation timestamp |
| `updated_at` | Last update timestamp |

### 12.4 `attendance_records`

Stores Employee attendance actions.

| Field | Purpose |
|---|---|
| `id` | Primary key |
| `employee_id` | Foreign key to `employees.id` |
| `attendance_date` | Attendance date in `Asia/Manila` |
| `time_in` | Time-in timestamp |
| `time_out` | Nullable time-out timestamp |
| `source` | `SIMULATED` or `LIVE` |
| `created_at` | Creation timestamp |
| `updated_at` | Last update timestamp |

Required constraint: unique `(employee_id, attendance_date)`.

### 12.5 `model_training_logs`

Stores model training and evaluation history.

| Field | Purpose |
|---|---|
| `id` | Primary key |
| `model_version` | Candidate or activated model version |
| `dataset_type` | `SIMULATED` or `LIVE` |
| `training_started_at` | Training start timestamp |
| `training_completed_at` | Nullable completion timestamp |
| `training_status` | Pending, successful, or failed |
| `mae` | Evaluation MAE |
| `rmse` | Evaluation RMSE |
| `wape` | Evaluation WAPE |
| `activation_status` | Whether the candidate became active |
| `failure_reason` | Nullable safe failure summary |

Do not store credentials, tokens, or sensitive employee data in training logs.

### 12.6 `model_forecasts`

Stores department-level forecast results.

| Field | Purpose |
|---|---|
| `id` | Primary key |
| `department_id` | Foreign key to `departments.id` |
| `forecast_date` | Future working date being forecast |
| `predicted_attendance_count` | Predicted distinct Employee count |
| `model_version` | Model version used |
| `generated_at` | Forecast generation timestamp |

### 12.7 Relationships

- One `users` record may have zero or one associated `employees` record.
- One `department` may have many Employees.
- One Admin `users` record may approve many Employee records.
- One Employee may have many attendance records.
- One department may have many forecast records.
- Model training logs record training attempts and are not directly dependent on an individual Employee.

### 12.8 Database Integrity

- Use unique email addresses.
- Use a unique `employees.user_id`.
- Use a unique department name.
- Use unique `(employee_id, attendance_date)` for attendance.
- Use foreign keys for relationships.
- Index common filters such as attendance date, employee, department, account status, and forecast date.
- Use transactions for multi-table registration and approval operations.
- Avoid storing duplicate aggregate analytics when those values can be reliably calculated from source records.

---

## 13. API Requirements

The API is a logical contract for the frontend/backend interaction. These paths are proposed examples, not a claim that they already exist in Lavalust. Implement them using Lavalust's actual routing and controller conventions.

### 13.1 Authentication

| Method | Example path | Purpose |
|---|---|---|
| `POST` | `/api/auth/register` | Employee registration |
| `POST` | `/api/auth/login` | Login |
| `POST` | `/api/auth/refresh` | Refresh access token |
| `POST` | `/api/auth/logout` | Logout/revoke refresh session where supported |
| `GET` | `/api/auth/me` | Current authenticated user |

### 13.2 Departments

| Method | Example path | Purpose |
|---|---|---|
| `GET` | `/api/departments` | List selectable or visible departments |
| `POST` | `/api/admin/departments` | Create department |
| `PATCH` | `/api/admin/departments/{id}` | Update department |
| `PATCH` | `/api/admin/departments/{id}/status` | Activate/deactivate department |

### 13.3 Employee

| Method | Example path | Purpose |
|---|---|---|
| `GET` | `/api/employee/profile` | View own profile |
| `GET` | `/api/employee/attendance` | View own attendance history |
| `POST` | `/api/attendance/time-in` | Record own time-in |
| `POST` | `/api/attendance/time-out` | Record own time-out |

### 13.4 Admin

| Method | Example path | Purpose |
|---|---|---|
| `GET` | `/api/admin/dashboard` | Dashboard summary |
| `GET` | `/api/admin/registrations` | List registrations |
| `PATCH` | `/api/admin/registrations/{id}/approve` | Approve and assign department |
| `PATCH` | `/api/admin/registrations/{id}/reject` | Reject registration |
| `GET` | `/api/admin/employees` | List Employees |
| `PATCH` | `/api/admin/employees/{id}/status` | Enable/disable Employee account |
| `GET` | `/api/admin/attendance` | Municipality-wide attendance |
| `GET` | `/api/admin/analytics` | Descriptive analytics |
| `GET` | `/api/admin/forecasts` | Department forecasts |
| `GET` | `/api/admin/model-training` | Model training history/status |
| `GET` | `/api/admin/reports/attendance` | Attendance report data |
| `GET` | `/api/admin/reports/attendance/export` | CSV export |

### 13.5 API Response Behavior

Use consistent JSON response structures following Lavalust's supported conventions.

Expected HTTP status behavior:

- `200`: successful read or update.
- `201`: successfully created resource.
- `400`: malformed or invalid request.
- `401`: missing or invalid authentication.
- `403`: authenticated user lacks permission.
- `404`: resource not found.
- `409`: conflicting action, such as duplicate time-in.
- `422`: field validation failure, if supported by the framework's conventions.
- `429`: rate limit exceeded.
- `500`: unexpected server error.

Return clear user-safe error messages. Never expose stack traces, SQL statements, secrets, or server paths.

---

## 14. Frontend Requirements

### 14.1 General

- Use React for the frontend.
- Use Tailwind CSS for styling.
- Use reusable components for repeated interface patterns.
- Keep Employee and Admin navigation clearly separated.
- Provide loading, empty, success, and error states for data-driven screens.
- Use responsive layouts suitable for desktop and smaller screens.
- Use accessible labels, buttons, focus states, and readable contrast.
- Do not hardcode database records or analytics values into UI components.
- Fetch application data from the Lavalust API.

### 14.2 Route Protection

- Provide separate Employee and Admin route areas.
- Redirect unauthenticated users to login.
- Prevent Employee users from entering Admin pages.
- Do not rely on frontend route protection as the only security measure; backend authorization is mandatory.
- After login, route users according to their authenticated role.

### 14.3 Attendance Interaction

- Show the action appropriate to the Employee's current attendance state.
- Prevent accidental repeated clicks while a request is processing.
- Show the server-confirmed timestamp after a successful action.
- If the backend rejects an action, display the returned safe error message.
- Refresh or update the displayed attendance state using the server response.

---

## 15. Suggested Project Organization

The exact directory structure must follow the existing project and Lavalust conventions. The following is a logical separation, not a mandate to create these exact folders.

### Frontend

- Authentication pages
- Employee pages
- Admin pages
- Shared layout and navigation
- Reusable UI components
- API client and authentication state
- Feature-specific data fetching and validation

### Backend

- Authentication endpoints and services
- Employee registration and profile handling
- Admin registration approval and employee management
- Department management
- Attendance actions and queries
- Analytics and reporting
- Forecast retrieval
- Authorization middleware or equivalent Lavalust facilities

### Machine Learning

Suggested Python scripts:

- `seed_attendance.py` — creates reproducible simulated historical records.
- `prepare_dataset.py` — builds department-date observations and model features.
- `train_model.py` — trains a candidate model.
- `evaluate_model.py` — evaluates the candidate against the baseline and active model.
- `generate_forecasts.py` — generates and stores five-working-day forecasts.

These are suggested responsibilities. Use a simple structure and avoid unnecessary packages, services, or abstractions.

The weekly schedule may be run through the local operating system scheduler. It will only execute when the development machine and scheduler are running.

---

## 16. MVP Acceptance Criteria

The MVP is considered functionally complete when the following can be demonstrated.

### 16.1 Registration and Approval

- [ ] A visitor can register using the required fields.
- [ ] Public registration always creates an Employee account, never an Admin account.
- [ ] Duplicate email registration is rejected.
- [ ] User and Employee records are created transactionally.
- [ ] New registrations begin in `pending` status.
- [ ] The Admin can view pending registrations.
- [ ] The Admin can approve a registration only with a valid active department selected.
- [ ] Approval stores the Admin ID and approval timestamp.
- [ ] The Admin can reject a registration.
- [ ] Rejected and pending accounts cannot record attendance.
- [ ] Admin accounts are provisioned through a controlled setup process.

### 16.2 Authentication and Authorization

- [ ] Employees and Admins can sign in using valid credentials.
- [ ] Invalid credentials are rejected safely.
- [ ] Protected endpoints require valid authentication.
- [ ] Employee users cannot access Admin endpoints.
- [ ] Employees can only view their own profile and attendance.
- [ ] Protected fields cannot be changed through public registration or Employee requests.
- [ ] Logout/refresh behavior follows the implemented Lavalust authentication facilities.

### 16.3 Attendance

- [ ] An active approved Employee with a department can record time-in on a weekday.
- [ ] The server determines the authoritative timestamp and attendance date.
- [ ] Duplicate time-in is rejected.
- [ ] Time-out requires an existing time-in.
- [ ] Time-out must be later than time-in.
- [ ] Duplicate time-out is rejected.
- [ ] Weekend time-in is rejected.
- [ ] A unique database constraint prevents multiple records for one Employee on one date.
- [ ] Attendance actions are stored with the `SIMULATED` source.
- [ ] The Employee sees the server-confirmed attendance result.
- [ ] The Employee can view only their own attendance history.

### 16.4 Admin Operations and Reports

- [ ] The Admin can view dashboard counts based on stored records.
- [ ] The Admin can manage departments.
- [ ] The Admin can view active Employees and their departments.
- [ ] The Admin can enable or disable an Employee account.
- [ ] The Admin can view attendance across departments.
- [ ] Attendance can be filtered by date/date range and department.
- [ ] Daily, date-range, and department summary reports display database-derived values.
- [ ] CSV export matches the selected report filters.
- [ ] The system uses “Recorded Attendance Rate” and does not label unrecorded time-ins as official absences.

### 16.5 Analytics and Machine Learning

- [ ] The seed process can generate approximately 12 weeks of simulated weekday attendance records.
- [ ] Seeded records are marked `SIMULATED`.
- [ ] Attendance is aggregated into unique department-working-day observations.
- [ ] The Random Forest model can train from the initial simulated dataset.
- [ ] Evaluation uses chronological splitting without shuffling.
- [ ] MAE, RMSE, and WAPE are recorded.
- [ ] Candidate evaluation compares against the weekday-average baseline and, when available, the active model.
- [ ] A failed candidate does not replace the active model.
- [ ] Weekly retraining checks follow the Sunday schedule.
- [ ] The 20-new-observation threshold is measured in unique department-working-day rows.
- [ ] The live transition requires at least eight weeks of eligible live history.
- [ ] After transition, training uses live-only data.
- [ ] Forecasts cover the next five working days for active departments.
- [ ] Forecast values are bounded by zero and applicable active headcount.
- [ ] Forecasts show generation time and model version.
- [ ] The interface shows an unavailable state instead of inventing forecasts when data/model requirements are not met.
- [ ] Automated insights are grounded in computed metrics and stored forecasts.

---

## 17. Explicit MVP Exclusions

Do not implement the following as part of this MVP unless the project scope is formally revised:

- Actual fingerprint, facial recognition, or biometric-device integration.
- Hardware SDKs, device APIs, or device synchronization.
- Payroll computation, payslips, salary records, deductions, loans, overtime pay, or disbursement.
- Leave filing or leave approval.
- Holiday calendars or holiday-based attendance rules.
- Late, undertime, overtime, or official absence classification.
- Attendance editing, correction requests, or exception resolution.
- Recruitment, job applications, or applicant tracking.
- Individual employee performance scoring, ranking, or disciplinary recommendations.
- Predictive analytics for individual employees.
- Department transfer history.
- Mobile application.
- Public production deployment.
- External LLM integration as a required dependency.
- Email/SMS notification integration.
- PDF report export.
- Additional application roles beyond Employee and Admin.

---

## 18. Implementation Priorities

Implement the MVP in this order so that each stage has a working foundation.

1. **Project foundation:** verify Lavalust installation, database connection, React setup, and local API communication.
2. **Database:** create the required tables, relationships, constraints, and initial department data.
3. **Authentication:** implement Employee registration, controlled Admin provisioning, login, JWT handling, and role authorization.
4. **Registration workflow:** implement pending registration, Admin review, approval, rejection, and department assignment.
5. **Attendance:** implement time-in/time-out rules, server timestamps, uniqueness, and Employee attendance history.
6. **Admin operations:** implement Employee/department management and attendance monitoring.
7. **Reports and descriptive analytics:** implement database-derived metrics, filters, and CSV export.
8. **ML data preparation:** generate the 12-week simulated dataset and department-date training observations.
9. **ML forecasting:** implement Random Forest training, chronological evaluation, model activation, weekly retraining checks, and five-working-day forecasts.
10. **Integration and validation:** connect forecasts to the Admin interface and verify all acceptance criteria.

Do not begin with machine learning before the underlying registration, department, and attendance data are working correctly.

---

## 19. Development Agent Rules

Any AI coding agent or developer working on this project must follow these rules:

1. Read this entire `specs.md` before making changes.
2. Inspect the existing repository and preserve working code.
3. Do not delete, replace, or restructure the Lavalust backend without a clear technical requirement.
4. Follow the installed Lavalust version and its documentation; do not assume Laravel compatibility.
5. Implement only the MVP scope defined in this document.
6. Do not add extra roles, modules, tables, integrations, or business rules without approval.
7. Do not invent attendance rules for grace periods, late arrivals, holidays, overtime, absences, or corrections.
8. Do not treat missing time-in records as official absences.
9. Enforce authorization, validation, and business rules on the backend.
10. Use database constraints and transactions for data integrity.
11. Do not hardcode demo attendance, analytics, or forecast values into the React interface.
12. Keep simulated and live attendance data distinguishable at all times.
13. Never silently mix simulated and live training datasets.
14. Do not activate an ML candidate that fails the evaluation requirements.
15. Do not fabricate forecast values or AI-generated explanations.
16. Keep the implementation understandable and appropriate for an MVP. Prefer the simplest reliable solution over unnecessary abstraction.
17. After implementing a feature, test its success cases, validation failures, permission boundaries, and relevant database effects.
18. Update this specification only when the project owner approves a scope or requirement change.

---

## 20. Source of Truth and Clarification Policy

This document is the consolidated MVP implementation baseline.

When a technical detail is not explicitly specified:

1. Prefer the simplest implementation consistent with the requirements.
2. Follow the installed Lavalust framework's actual conventions.
3. Preserve data integrity and backend security.
4. Do not invent new business rules that change employee attendance outcomes.
5. If a decision would materially change scope, data structure, or user permissions, ask the project owner before implementing it.

The MVP is complete when the acceptance criteria in Section 16 are satisfied. Features listed in Section 17 are not required for MVP completion.
