-- Aggiunge il campo orario in testo libero, necessario perché i dati reali dei comuni
-- arrivano con orari descritti liberamente (es. "su appuntamento", "da ottobre a maggio")
-- e non nel formato strutturato giorno-per-giorno inizialmente previsto.
-- Eseguire una sola volta via phpMyAdmin sui database creati prima di questa modifica.

ALTER TABLE punti_servizio
  ADD COLUMN orario_testo TEXT NULL AFTER lng;
