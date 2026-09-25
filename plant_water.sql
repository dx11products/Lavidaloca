USE lavidaloca;

ALTER TABLE user_plants
    ADD COLUMN water_count INT DEFAULT 0,
    ADD COLUMN last_water_at TIMESTAMP NULL;