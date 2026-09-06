-- Mappa Servizi Comunali - schema database
-- Charset utf8mb4 per supportare correttamente lettere accentate ed emoji eventuali.

CREATE TABLE comuni (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(100) NOT NULL,
  slug VARCHAR(100) NOT NULL UNIQUE,
  colore_tema VARCHAR(7) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE categorie (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(100) NOT NULL,
  colore_hex VARCHAR(7) NOT NULL,
  icona VARCHAR(50) NOT NULL,
  ordine INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE utenti (
  id INT AUTO_INCREMENT PRIMARY KEY,
  comune_id INT NULL,
  nome VARCHAR(150) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  ruolo ENUM('admin_comune','superadmin') NOT NULL DEFAULT 'admin_comune',
  attivo TINYINT(1) NOT NULL DEFAULT 1,
  reset_token_hash VARCHAR(64) NULL,
  reset_token_scadenza DATETIME NULL,
  ultimo_accesso DATETIME NULL,
  creato_il DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (comune_id) REFERENCES comuni(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE log_accessi (
  id INT AUTO_INCREMENT PRIMARY KEY,
  utente_id INT NULL,
  email_tentativo VARCHAR(190) NOT NULL,
  esito ENUM('successo', 'fallito') NOT NULL,
  ip VARCHAR(45) NULL,
  user_agent VARCHAR(255) NULL,
  creato_il DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (utente_id) REFERENCES utenti(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE impostazioni (
  chiave VARCHAR(100) PRIMARY KEY,
  valore TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE punti_servizio (
  id INT AUTO_INCREMENT PRIMARY KEY,
  comune_id INT NOT NULL,
  categoria_id INT NOT NULL,
  nome VARCHAR(200) NOT NULL,
  indirizzo VARCHAR(255) NOT NULL,
  lat DECIMAL(10,7) NOT NULL,
  lng DECIMAL(10,7) NOT NULL,
  descrizione TEXT NULL,
  telefono VARCHAR(50) NULL,
  email VARCHAR(190) NULL,
  sito_web VARCHAR(255) NULL,
  immagine_url VARCHAR(255) NULL,
  creato_da INT NULL,
  creato_il DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  aggiornato_il DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (comune_id) REFERENCES comuni(id) ON DELETE CASCADE,
  FOREIGN KEY (categoria_id) REFERENCES categorie(id),
  FOREIGN KEY (creato_da) REFERENCES utenti(id) ON DELETE SET NULL,
  FULLTEXT KEY ft_ricerca (nome, indirizzo, descrizione)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE orari_apertura (
  id INT AUTO_INCREMENT PRIMARY KEY,
  punto_id INT NOT NULL,
  giorno_settimana TINYINT NOT NULL COMMENT '1=lunedi ... 7=domenica',
  apertura TIME NULL,
  chiusura TIME NULL,
  chiuso TINYINT(1) NOT NULL DEFAULT 0,
  FOREIGN KEY (punto_id) REFERENCES punti_servizio(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE import_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  utente_id INT NULL,
  nome_file VARCHAR(255) NOT NULL,
  righe_importate INT NOT NULL DEFAULT 0,
  righe_errore INT NOT NULL DEFAULT 0,
  dettagli_errore TEXT NULL,
  eseguito_il DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (utente_id) REFERENCES utenti(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Dati iniziali: 3 comuni (nomi da confermare/correggere), 5 categorie con colore+icona distinti.
INSERT INTO comuni (nome, slug) VALUES
  ('Farra di Soligo', 'farra-di-soligo'),
  ('Pieve di Soligo', 'pieve-di-soligo'),
  ('Sernaglia della Battaglia', 'sernaglia-della-battaglia');

INSERT INTO categorie (nome, colore_hex, icona, ordine) VALUES
  ('Anagrafe', '#2563eb', 'anagrafe', 1),
  ('Tributi', '#b45309', 'tributi', 2),
  ('Sociale', '#059669', 'sociale', 3),
  ('Istruzione', '#7c3aed', 'istruzione', 4),
  ('Biblioteca', '#dc2626', 'biblioteca', 5);
