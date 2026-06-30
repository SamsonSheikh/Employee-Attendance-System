# TODO

## Admin Users fixes
- [x] Fix malformed JavaScript in `pages/user-admin/adminusers.php` (Role Assignment area).
- [x] Wire Role Assignment form to backend: `adminusers.php?action=update_user_role`.
- [x] Align POST field names (`new_role_id`, and include `reason`).
- [x] Implement Role Assignment backend to update `users.role_id` and best-effort audit logging.
- [ ] Verify in browser: role assignment submit + change password modal still works.

## QR identifiers for newly created users
- [x] Update `pages/user-admin/adminusers.php` to set `users.qr_identifier` for every newly created user (single add + bulk add).

