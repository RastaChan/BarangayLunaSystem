<div class="complaint-search">
  <label>Search complaints
    <input type="search" data-complaint-search-input placeholder="Search ID, name, subject or status…" autocomplete="off">
  </label>
</div>
<p class="complaint-search-empty" data-complaint-search-empty role="status" hidden>No complaints match your search.</p>
<script src="js/complaint-list.js?v=<?= filemtime(__DIR__ . '/../js/complaint-list.js') ?>" defer></script>
