<header class="bg-white border-b-4 border-primary text-navy">
  <div class="max-w-5xl mx-auto px-4 py-3 flex flex-wrap items-center gap-x-4 gap-y-2">
    <a href="/admin/dashboard.php" class="font-semibold">Amministrazione</a>
    <a href="/admin/punti.php" class="text-sm text-gray-700 hover:text-primary">Punti servizio</a>
    <a href="/admin/avvisi.php" class="text-sm text-gray-700 hover:text-primary">Avvisi</a>
    <?php if (isSuperadmin($utente)): ?>
      <a href="/admin/categorie.php" class="text-sm text-gray-700 hover:text-primary">Categorie</a>
    <?php endif; ?>
    <a href="/admin/import.php" class="text-sm text-gray-700 hover:text-primary">Importa CSV</a>
    <?php if (isSuperadmin($utente)): ?>
      <a href="/admin/utenti.php" class="text-sm text-gray-700 hover:text-primary">Utenti</a>
      <a href="/admin/impostazioni_mappa.php" class="text-sm text-gray-700 hover:text-primary">Impostazioni mappa</a>
      <a href="/admin/impostazioni_smtp.php" class="text-sm text-gray-700 hover:text-primary">Impostazioni SMTP</a>
    <?php endif; ?>
    <span class="ml-auto text-sm text-gray-600"><a href="/admin/profilo.php" class="underline"><?= h($utente['nome']) ?></a> · <?= h($utente['ruolo']) ?></span>
    <form method="post" action="/admin/logout.php" class="inline">
      <?= csrfCampo() ?>
      <button type="submit" class="text-sm underline text-primary">Esci</button>
    </form>
  </div>
</header>
