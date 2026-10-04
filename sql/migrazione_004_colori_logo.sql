-- Allinea i colori delle categorie alla palette del logo Punti in comune.
-- Eseguire una sola volta via phpMyAdmin sui database già esistenti.

UPDATE categorie SET colore_hex = '#009BDF' WHERE icona = 'anagrafe';
UPDATE categorie SET colore_hex = '#FFCB03' WHERE icona = 'tributi';
UPDATE categorie SET colore_hex = '#0BB14B' WHERE icona = 'sociale';
UPDATE categorie SET colore_hex = '#F06798' WHERE icona = 'istruzione';
UPDATE categorie SET colore_hex = '#F05202' WHERE icona = 'biblioteca';
