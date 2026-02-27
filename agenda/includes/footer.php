<?php
/**
 * Pied de page commun pour le module Agenda
 * Utilise les templates partagés Pronote
 */
?>
      </div><!-- .content-container -->

<?php
$extraJs = ['assets/js/calendar.js'];
ob_start();
?>
<script>
  document.addEventListener('DOMContentLoaded', function() {
    // Gestion des filtres de type d'événement
    document.querySelectorAll('.filter-checkbox[data-filter-type="type"]').forEach(checkbox => {
      checkbox.addEventListener('change', function() {
        const selectedTypes = [];
        document.querySelectorAll('.filter-checkbox[data-filter-type="type"]:checked').forEach(cb => {
          selectedTypes.push(cb.value);
        });
        
        let url = window.location.href.split('?')[0] + '?filter_set=1';
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('view')) url += '&view=' + urlParams.get('view');
        if (urlParams.has('month')) url += '&month=' + urlParams.get('month');
        if (urlParams.has('year')) url += '&year=' + urlParams.get('year');
        if (urlParams.has('date')) url += '&date=' + urlParams.get('date');
        
        selectedTypes.forEach(type => { url += '&types[]=' + type; });
        
        const selectedClasses = [];
        document.querySelectorAll('.filter-checkbox[data-filter-type="class"]:checked').forEach(cb => {
          selectedClasses.push(cb.value);
        });
        selectedClasses.forEach(cls => { url += '&classes[]=' + encodeURIComponent(cls); });
        
        window.location.href = url;
      });
    });
    
    window.filterClasses = function() {
      const searchText = document.getElementById('classSearch').value.toLowerCase();
      document.querySelectorAll('.dropdown-option').forEach(option => {
        const label = option.querySelector('label').textContent.toLowerCase();
        option.style.display = label.includes(searchText) ? 'block' : 'none';
      });
    };
    
    const classesDropdownToggle = document.getElementById('classesDropdownToggle');
    const classesDropdown = document.getElementById('classesDropdown');
    
    if (classesDropdownToggle && classesDropdown) {
      classesDropdownToggle.addEventListener('click', function() {
        classesDropdown.classList.toggle('show');
      });
      
      window.addEventListener('click', function(e) {
        if (!e.target.matches('.classes-dropdown-toggle') && !classesDropdown.contains(e.target)) {
          classesDropdown.classList.remove('show');
        }
      });
      
      document.getElementById('selectAllClasses')?.addEventListener('click', function() {
        document.querySelectorAll('.filter-checkbox[data-filter-type="class"]').forEach(cb => { cb.checked = true; });
      });
      
      document.getElementById('deselectAllClasses')?.addEventListener('click', function() {
        document.querySelectorAll('.filter-checkbox[data-filter-type="class"]').forEach(cb => { cb.checked = false; });
      });
      
      document.getElementById('applyClassesFilter')?.addEventListener('click', function() {
        const selectedTypes = [];
        document.querySelectorAll('.filter-checkbox[data-filter-type="type"]:checked').forEach(cb => { selectedTypes.push(cb.value); });
        const selectedClasses = [];
        document.querySelectorAll('.filter-checkbox[data-filter-type="class"]:checked').forEach(cb => { selectedClasses.push(cb.value); });
        
        let url = window.location.href.split('?')[0] + '?filter_set=1';
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('view')) url += '&view=' + urlParams.get('view');
        if (urlParams.has('month')) url += '&month=' + urlParams.get('month');
        if (urlParams.has('year')) url += '&year=' + urlParams.get('year');
        if (urlParams.has('date')) url += '&date=' + urlParams.get('date');
        
        selectedTypes.forEach(type => { url += '&types[]=' + type; });
        selectedClasses.forEach(cls => { url += '&classes[]=' + encodeURIComponent(cls); });
        
        window.location.href = url;
      });
    }
    
    if (typeof initCalendar === 'function') {
      initCalendar();
    }
  });
  
  function navigateToPrevious() {
    const view = new URLSearchParams(window.location.search).get('view') || 'month';
    let url = '';
    if (view === 'month') {
      let month = parseInt(new URLSearchParams(window.location.search).get('month')) || new Date().getMonth() + 1;
      let year = parseInt(new URLSearchParams(window.location.search).get('year')) || new Date().getFullYear();
      if (month === 1) { month = 12; year--; } else { month--; }
      url = `?view=month&month=${month}&year=${year}`;
    } else if (view === 'day') {
      const d = new Date(new URLSearchParams(window.location.search).get('date') || new Date());
      d.setDate(d.getDate() - 1);
      url = `?view=day&date=${d.toISOString().split('T')[0]}`;
    } else if (view === 'week') {
      const d = new Date(new URLSearchParams(window.location.search).get('date') || new Date());
      d.setDate(d.getDate() - 7);
      url = `?view=week&date=${d.toISOString().split('T')[0]}`;
    }
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('filter_set')) {
      url += '&filter_set=1';
      urlParams.getAll('types[]').forEach(t => { url += `&types[]=${t}`; });
      urlParams.getAll('classes[]').forEach(c => { url += `&classes[]=${encodeURIComponent(c)}`; });
    }
    window.location.href = url;
  }
  
  function navigateToNext() {
    const view = new URLSearchParams(window.location.search).get('view') || 'month';
    let url = '';
    if (view === 'month') {
      let month = parseInt(new URLSearchParams(window.location.search).get('month')) || new Date().getMonth() + 1;
      let year = parseInt(new URLSearchParams(window.location.search).get('year')) || new Date().getFullYear();
      if (month === 12) { month = 1; year++; } else { month++; }
      url = `?view=month&month=${month}&year=${year}`;
    } else if (view === 'day') {
      const d = new Date(new URLSearchParams(window.location.search).get('date') || new Date());
      d.setDate(d.getDate() + 1);
      url = `?view=day&date=${d.toISOString().split('T')[0]}`;
    } else if (view === 'week') {
      const d = new Date(new URLSearchParams(window.location.search).get('date') || new Date());
      d.setDate(d.getDate() + 7);
      url = `?view=week&date=${d.toISOString().split('T')[0]}`;
    }
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('filter_set')) {
      url += '&filter_set=1';
      urlParams.getAll('types[]').forEach(t => { url += `&types[]=${t}`; });
      urlParams.getAll('classes[]').forEach(c => { url += `&classes[]=${encodeURIComponent(c)}`; });
    }
    window.location.href = url;
  }
  
  function navigateToToday() {
    const view = new URLSearchParams(window.location.search).get('view') || 'month';
    const today = new Date();
    const todayStr = today.toISOString().split('T')[0];
    let url = '';
    if (view === 'month') { url = `?view=month&month=${today.getMonth()+1}&year=${today.getFullYear()}`; }
    else if (view === 'day' || view === 'week') { url = `?view=${view}&date=${todayStr}`; }
    else { url = `?view=${view}`; }
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('filter_set')) {
      url += '&filter_set=1';
      urlParams.getAll('types[]').forEach(t => { url += `&types[]=${t}`; });
      urlParams.getAll('classes[]').forEach(c => { url += `&classes[]=${encodeURIComponent(c)}`; });
    }
    window.location.href = url;
  }
  
  function openDayView(date) {
    let url = `?view=day&date=${date}`;
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('filter_set')) {
      url += '&filter_set=1';
      urlParams.getAll('types[]').forEach(t => { url += `&types[]=${t}`; });
      urlParams.getAll('classes[]').forEach(c => { url += `&classes[]=${encodeURIComponent(c)}`; });
    }
    window.location.href = url;
  }
  
  function openEventDetails(eventId, e) {
    if (e) e.stopPropagation();
    window.location.href = 'details_evenement.php?id=' + eventId;
  }
</script>
<?php
$extraScriptHtml = ob_get_clean();
include __DIR__ . '/../../templates/shared_footer.php';
?>