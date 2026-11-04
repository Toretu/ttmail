-- Answers from the public "update details" page. One row per contact and campaign (the latest answer replaces the earlier one).
-- changes holds proposed edits as JSON {"company":{...},"contact":{...}}; nothing touches the master data until an editor applies it.
CREATE TABLE campaign_responses (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  campaign_id BIGINT UNSIGNED NOT NULL,
  contact_id BIGINT UNSIGNED NOT NULL,
  changes JSON NULL,
  product_ids JSON NULL,
  comment TEXT NULL,
  status ENUM('new','applied','dismissed') NOT NULL DEFAULT 'new',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_response (campaign_id, contact_id),
  INDEX idx_response_status (campaign_id, status),
  CONSTRAINT fk_response_campaign FOREIGN KEY (campaign_id) REFERENCES campaigns (id) ON DELETE CASCADE,
  CONSTRAINT fk_response_contact FOREIGN KEY (contact_id) REFERENCES contacts (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
