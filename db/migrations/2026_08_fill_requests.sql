-- Missed-date fill requests.
-- An edit-request row can now also represent a request to FILL a day that was
-- never submitted (no existing header_id): header_id becomes NULL and the
-- target day is stored in target_date, scoped by department (+ condition for
-- checksheets that have conditions, e.g. Painting). Once an Admin approves it,
-- has_active_fill_unlock() (includes/edit_requests.php) lets the requester
-- create a record for that specific past date, the same way the one-day
-- "yesterday" grace window works — but for any approved missed day.

ALTER TABLE `t_edit_request`
  MODIFY `header_id` int(11) NULL DEFAULT NULL,
  ADD COLUMN `target_date` date NULL DEFAULT NULL AFTER `header_id`,
  ADD COLUMN `department_id` int(11) NULL DEFAULT NULL AFTER `target_date`,
  ADD COLUMN `condition_id` int(11) NULL DEFAULT NULL AFTER `department_id`,
  ADD KEY `idx_fill_lookup` (`checksheet_type`, `department_id`, `condition_id`, `target_date`, `status`);
