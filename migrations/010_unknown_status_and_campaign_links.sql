-- A recipient whose send was interrupted has an unknown outcome. It is kept apart from real failures
-- so that "queue failed again" can never resend it automatically.
ALTER TABLE campaign_recipients
  MODIFY COLUMN status ENUM('pending','sending','sent','failed','skipped','unknown') NOT NULL DEFAULT 'pending';

-- Rows the old worker marked as failed with "Outcome unknown" were interrupted sends; give them the new status.
UPDATE campaign_recipients SET status = 'unknown' WHERE status = 'failed' AND error LIKE 'Outcome unknown%';

-- Personal details links ({{details_link}}) are per campaign and contact and expire.
-- The unsubscribe token in contact_tokens stays separate and only allows unsubscribing.
-- The token is unique per campaign (not globally) so links already sent with the old shared token can be kept below.
CREATE TABLE campaign_links (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  campaign_id BIGINT UNSIGNED NOT NULL,
  contact_id BIGINT UNSIGNED NOT NULL,
  token CHAR(32) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at DATETIME NOT NULL,
  UNIQUE KEY uq_campaign_link (campaign_id, contact_id),
  UNIQUE KEY uq_campaign_link_token (campaign_id, token),
  CONSTRAINT fk_link_campaign FOREIGN KEY (campaign_id) REFERENCES campaigns (id) ON DELETE CASCADE,
  CONSTRAINT fk_link_contact FOREIGN KEY (contact_id) REFERENCES contacts (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Mail sent before this migration contains details links built from the contact's unsubscribe token.
-- Keep those links working for 90 days after they were sent; new mail gets its own token.
INSERT IGNORE INTO campaign_links (campaign_id, contact_id, token, created_at, expires_at)
SELECT r.campaign_id, r.contact_id, t.token, COALESCE(r.sent_at, NOW()), DATE_ADD(COALESCE(r.sent_at, NOW()), INTERVAL 90 DAY)
FROM campaign_recipients r
JOIN contact_tokens t ON t.contact_id = r.contact_id
WHERE r.status = 'sent';
