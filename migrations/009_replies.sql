-- Replies fetched from the approved mailboxes. Only mail from known contacts is stored.
CREATE TABLE replies (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  mailbox_id BIGINT UNSIGNED NOT NULL,
  graph_id VARCHAR(255) NOT NULL,
  contact_id BIGINT UNSIGNED NOT NULL,
  campaign_id BIGINT UNSIGNED NULL,
  from_email VARCHAR(254) NOT NULL,
  subject VARCHAR(500) NOT NULL DEFAULT '',
  preview VARCHAR(500) NOT NULL DEFAULT '',
  body_text MEDIUMTEXT NULL,
  received_at DATETIME NOT NULL,
  stage ENUM('new','interested','needs_call','quote_sent','won','declined','done') NOT NULL DEFAULT 'new',
  note TEXT NULL,
  next_followup DATE NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_reply_message (mailbox_id, graph_id),
  INDEX idx_reply_stage (stage, received_at),
  INDEX idx_reply_campaign (campaign_id),
  INDEX idx_reply_contact (contact_id),
  CONSTRAINT fk_reply_mailbox FOREIGN KEY (mailbox_id) REFERENCES mailboxes (id),
  CONSTRAINT fk_reply_contact FOREIGN KEY (contact_id) REFERENCES contacts (id) ON DELETE CASCADE,
  CONSTRAINT fk_reply_campaign FOREIGN KEY (campaign_id) REFERENCES campaigns (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE reply_stage_history (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  reply_id BIGINT UNSIGNED NOT NULL,
  old_stage VARCHAR(20) NOT NULL,
  new_stage VARCHAR(20) NOT NULL,
  changed_by VARCHAR(254) NOT NULL,
  changed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_history_reply (reply_id),
  CONSTRAINT fk_history_reply FOREIGN KEY (reply_id) REFERENCES replies (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE mailbox_sync (
  mailbox_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
  last_received DATETIME NULL,
  last_run_at DATETIME NULL,
  last_error VARCHAR(500) NULL,
  last_seen INT UNSIGNED NOT NULL DEFAULT 0,
  last_stored INT UNSIGNED NOT NULL DEFAULT 0,
  last_unknown INT UNSIGNED NOT NULL DEFAULT 0,
  CONSTRAINT fk_sync_mailbox FOREIGN KEY (mailbox_id) REFERENCES mailboxes (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
