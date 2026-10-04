-- Aggiunge il flag "servizio sovracomunale": punti che, pur avendo una sede fisica in un
-- comune, sono offerti a residenti di tutti i comuni (es. servizi in convenzione/unione).
-- Un punto sovracomunale compare sulla mappa indipendentemente dal filtro comune attivo.
-- Eseguire una sola volta via phpMyAdmin sui database creati prima di questa modifica.

ALTER TABLE punti_servizio
  ADD COLUMN sovracomunale TINYINT(1) NOT NULL DEFAULT 0 AFTER orario_testo;
