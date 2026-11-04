ALTER TABLE campaigns
  ADD COLUMN mailbox_id BIGINT UNSIGNED NULL AFTER status,
  ADD COLUMN subject VARCHAR(255) NULL AFTER mailbox_id,
  ADD COLUMN body TEXT NULL AFTER subject,
  ADD CONSTRAINT fk_campaigns_mailbox FOREIGN KEY (mailbox_id) REFERENCES mailboxes (id);

CREATE TABLE contact_tokens (
  contact_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
  token CHAR(32) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_contact_tokens_token (token),
  CONSTRAINT fk_contact_tokens_contact FOREIGN KEY (contact_id) REFERENCES contacts (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
