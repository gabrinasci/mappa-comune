-- Aggiunge il comune di Refrontolo. Eseguire una sola volta via phpMyAdmin sui database esistenti.

INSERT INTO comuni (nome, slug)
SELECT 'Refrontolo', 'refrontolo'
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM comuni WHERE slug = 'refrontolo');
