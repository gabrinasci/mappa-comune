-- Foto dei punti servizio (galleria nel dettaglio della mappa). Eseguire una sola volta via phpMyAdmin.
-- I file stanno in public/uploads/punti/<id punto>/: la cartella deve essere scrivibile da PHP.

CREATE TABLE IF NOT EXISTS foto_punti (
  id INT AUTO_INCREMENT PRIMARY KEY,
  punto_id INT NOT NULL,
  file_grande VARCHAR(255) NOT NULL,
  file_medio VARCHAR(255) NOT NULL,
  larghezza INT NOT NULL,
  altezza INT NOT NULL,
  alt_testo VARCHAR(255) NULL,
  ordine INT NOT NULL DEFAULT 0,
  creato_da INT NULL,
  creato_il DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (punto_id) REFERENCES punti_servizio(id) ON DELETE CASCADE,
  FOREIGN KEY (creato_da) REFERENCES utenti(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
