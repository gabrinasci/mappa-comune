-- Completa con https:// i siti web salvati senza protocollo (es. "www.comune.it"),
-- che sulla mappa diventavano link a pagine inesistenti. Eseguire una sola volta via phpMyAdmin.

UPDATE punti_servizio
SET sito_web = CONCAT('https://', sito_web)
WHERE sito_web IS NOT NULL AND sito_web <> '' AND sito_web NOT REGEXP '^https?://';
