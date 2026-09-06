<header class="bg-blue-900 text-white">
  <div class="max-w-5xl mx-auto px-4 py-3 flex flex-wrap items-center gap-4">
    <a href="/admin/dashboard.php" class="font-semibold">📍 Admin Mappa Servizi</a>
    <a href="/admin/punti.php" class="text-sm text-blue-100 hover:text-white">Punti servizio</a>
    <?php if (isSuperadmin($utente)): ?>
      <a href="/admin/categorie.php" class="text-sm text-blue-100 hover:text-white">Categorie</a>
    <?php endif; ?>
    <a href="/admin/import.php" class="text-sm text-blue-100 hover:text-white">Importa CSV</a>
    <?php if (isSuperadmin($utente)): ?>
      <a href="/admin/utenti.php" class="text-sm text-blue-100 hover:text-white">Utenti</a>
      <a href="/admin/impostazioni_smtp.php" class="text-sm text-blue-100 hover:text-white">Impostazioni SMTP</a>
    <?php endif; ?>
    <span class="ml-auto text-sm text-blue-200"><a href="/admin/profilo.php" class="underline"><?= h($utente['nome']) ?></a> · <?= h($utente['ruolo']) ?></span>
    <a href="/admin/logout.php" class="text-sm underline">Esci</a>
  </div>
</header>
