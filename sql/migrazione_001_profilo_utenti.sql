-- Migrazione per database già esistenti (creati prima dell'introduzione di profilo utente,
-- log accessi, reset password e impostazioni SMTP). Eseguire una sola volta via phpMyAdmin.
-- Chi parte da zero non ne ha bisogno: sql/schema.sql è già aggiornato.

ALTER TABLE utenti
  ADD COLUMN reset_token_hash VARCHAR(64) NULL,
  ADD COLUMN reset_token_scadenza DATETIME NULL,
  ADD COLUMN ultimo_accesso DATETIME NULL;

CREATE TABLE IF NOT EXISTS log_accessi (
  id INT AUTO_INCREMENT PRIMARY KEY,
  utente_id INT NULL,
  email_tentativo VARCHAR(190) NOT NULL,
  esito ENUM('successo', 'fallito') NOT NULL,
  ip VARCHAR(45) NULL,
  user_agent VARCHAR(255) NULL,
  creato_il DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (utente_id) REFERENCES utenti(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS impostazioni (
  chiave VARCHAR(100) PRIMARY KEY,
  valore TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
