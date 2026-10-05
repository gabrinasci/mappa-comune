-- Aggiunge la categoria Sanità. Eseguire una sola volta via phpMyAdmin sui database esistenti.

INSERT INTO categorie (nome, colore_hex, icona, ordine)
SELECT 'Sanità', '#003F85', 'sanita', 6
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM categorie WHERE icona = 'sanita');
