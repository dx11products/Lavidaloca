USE lavidaloca;

ALTER TABLE families
    ADD COLUMN crime_success_level INT DEFAULT 1,
    ADD COLUMN heist_success_level INT DEFAULT 1;