ALTER TABLE campaigns
  ADD COLUMN send_state ENUM('draft','sending','paused','done') NOT NULL DEFAULT 'draft' AFTER body,
  ADD COLUMN test_sent_at TIMESTAMP NULL DEFAULT NULL AFTER send_state,
  ADD COLUMN approved_by VARCHAR(254) NULL AFTER test_sent_at,
  ADD COLUMN approved_at TIMESTAMP NULL DEFAULT NULL AFTER approved_by,
  ADD COLUMN pause_reason VARCHAR(500) NULL AFTER approved_at;

ALTER TABLE campaign_recipients
  MODIFY COLUMN status ENUM('pending','sending','sent','failed','skipped') NOT NULL DEFAULT 'pending',
  ADD COLUMN claimed_at TIMESTAMP NULL DEFAULT NULL AFTER status,
  ADD COLUMN sent_at TIMESTAMP NULL DEFAULT NULL AFTER claimed_at,
  ADD COLUMN error VARCHAR(500) NULL AFTER sent_at;
