-- Adds invitation support for account registration.
-- This is needed because the current schema supports user-to-user connections,
-- but it does not store invite emails/tokens for someone who does not yet have an account.

CREATE TABLE IF NOT EXISTS user_invitations (
    invitation_id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    inviter_user_id INT(10) UNSIGNED NOT NULL,
    invitee_email VARCHAR(255) NOT NULL,
    invitation_token VARCHAR(64) NOT NULL,
    status ENUM('PENDING','REGISTERED','EXPIRED','CANCELLED') NOT NULL DEFAULT 'PENDING',
    expires_at DATETIME NOT NULL,
    accepted_user_id INT(10) UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (invitation_id),
    UNIQUE KEY uq_invitation_token (invitation_token),
    UNIQUE KEY uq_pending_invite (inviter_user_id, invitee_email, status),
    KEY idx_inviter_user_id (inviter_user_id),
    KEY idx_accepted_user_id (accepted_user_id),
    CONSTRAINT fk_invitation_inviter
        FOREIGN KEY (inviter_user_id) REFERENCES users(user_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_invitation_accepted_user
        FOREIGN KEY (accepted_user_id) REFERENCES users(user_id)
        ON DELETE SET NULL ON UPDATE CASCADE
);
