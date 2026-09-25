USE lavidaloca;

ALTER TABLE users
    ADD COLUMN attacks_won INT DEFAULT 0,
    ADD COLUMN attacks_lost INT DEFAULT 0,
    ADD COLUMN times_hospitalized INT DEFAULT 0,
    ADD COLUMN hospital_until TIMESTAMP NULL,
    ADD COLUMN last_attack TIMESTAMP NULL;