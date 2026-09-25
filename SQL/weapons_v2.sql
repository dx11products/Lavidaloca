USE lavidaloca;

-- Voeg quantity kolom toe
ALTER TABLE user_click_weapons ADD COLUMN quantity INT DEFAULT 1;

-- Verwijder oude unique key (elke user kan nu meerdere van hetzelfde wapen hebben in één rij)
-- MariaDB ondersteunt geen DROP INDEX IF EXISTS, dus we proberen en negeren fouten
-- Als dit faalt, kun je het overslaan
ALTER TABLE user_click_weapons DROP INDEX user_id;

-- Nieuwe unique key
ALTER TABLE user_click_weapons ADD UNIQUE KEY user_weapon_unique (user_id, weapon_key);

-- Update bestaande rijen
UPDATE user_click_weapons SET quantity = 1 WHERE quantity IS NULL;

-- Update prijzen/attack van bestaande wapens
UPDATE click_weapons SET click_cost = 10,    attack_bonus = 1   WHERE `key` = 'knuppel';
UPDATE click_weapons SET click_cost = 25,    attack_bonus = 2   WHERE `key` = 'mes';
UPDATE click_weapons SET click_cost = 50,    attack_bonus = 3   WHERE `key` = 'honkbalknuppel';
UPDATE click_weapons SET click_cost = 100,   attack_bonus = 5   WHERE `key` = 'pistool';
UPDATE click_weapons SET click_cost = 250,   attack_bonus = 8   WHERE `key` = 'revolver';
UPDATE click_weapons SET click_cost = 500,   attack_bonus = 15  WHERE `key` = 'uzi';
UPDATE click_weapons SET click_cost = 1000,  attack_bonus = 25  WHERE `key` = 'shotgun';
UPDATE click_weapons SET click_cost = 2500,  attack_bonus = 50  WHERE `key` = 'ak47';
UPDATE click_weapons SET click_cost = 5000,  attack_bonus = 100 WHERE `key` = 'sniper';
UPDATE click_weapons SET click_cost = 10000, attack_bonus = 200 WHERE `key` = 'rpg';