# TODO - User Admin: Master Users (User List + Add User backend)

- [x] Implement DB-backed User List in `pages/user-admin/adminusers.php` (join users with roles/departments/shifts as needed)
- [x] Add backend handler in `adminusers.php` for creating a new user (`?action=add_user`)
- [x] Add Add New User form/modal markup in `adminusers.php` (admin inputs first/last/email/password + role/optional dept/shift)

- [x] Validate server-side inputs (required fields, email uniqueness, role_id integrity)
- [x] Hash password with `password_hash()` before inserting into `users.password_hash`
- [x] On successful add, redirect back and refresh the list
- [x] Basic flash message display (success/error)
- [x] Verify in browser: user list loads from DB + new user appears after submit

- [x] Bulk add accounts in `pages/user-admin/adminusers.php` (paste CSV/TSV, per-row validation + partial success)

