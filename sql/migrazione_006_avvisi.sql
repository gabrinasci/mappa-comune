-- Aggiunge la tabella degli avvisi (flash news) mostrati nel banner della home.
-- Eseguire una sola volta via phpMyAdmin sui database esistenti.

CREATE TABLE IF NOT EXISTS avvisi (
  id INT AUTO_INCREMENT PRIMARY KEY,
  comune_id INT NULL COMMENT 'NULL = avviso valido per tutti i comuni',
  tipo ENUM('info','orari','novita','urgente') NOT NULL DEFAULT 'info',
  testo VARCHAR(255) NOT NULL,
  link_url VARCHAR(255) NULL,
  data_inizio DATE NULL,
  data_fine DATE NULL,
  attivo TINYINT(1) NOT NULL DEFAULT 1,
  ordine INT NOT NULL DEFAULT 0,
  creato_da INT NULL,
  creato_il DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (comune_id) REFERENCES comuni(id) ON DELETE CASCADE,
  FOREIGN KEY (creato_da) REFERENCES utenti(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
