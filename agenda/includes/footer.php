<?php
/**
 * Pied de page commun pour le module Agenda
 * Utilise le système de design unifié de Pronote
 */
?>
      </div><!-- .content-container -->
    </div><!-- .main-content -->
  </div><!-- .app-container -->

  <script src="assets/js/calendar.js"></script>
  <script>
  document.addEventListener('DOMContentLoaded', function() {
    // Gestion des messages d'alerte
    document.querySelectorAll('.alert-close').forEach(button => {
      button.addEventListener('click', function() {
        const alert = this.parentElement;
        alert.style.opacity = '0';
        setTimeout(() => {
          alert.style.display = 'none';
        }, 300);
      });
    });
    
    // Auto-masquer les alertes après un délai
    document.querySelectorAll('.alert-banner:not(.alert-persistent)').forEach(alert => {
      setTimeout(() => {
        alert.style.opacity = '0';
        setTimeout(() => {
          alert.style.display = 'none';
        }, 300);
      }, 5000);
    });

    // Gestion des filtres de type d'événement
    document.querySelectorAll('.filter-checkbox[data-filter-type="type"]').forEach(checkbox => {
      checkbox.addEventListener('change', function() {
        // Collecter tous les types sélectionnés
        const selectedTypes = [];
        document.querySelectorAll('.filter-checkbox[data-filter-type="type"]:checked').forEach(cb => {
          selectedTypes.push(cb.value);
        });
        
        // Construire l'URL avec les filtres
        let url = window.location.href.split('?')[0] + '?filter_set=1';
        
        // Ajouter les paramètres de vue actuels
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('view')) {
          url += '&view=' + urlParams.get('view');
        }
        if (urlParams.has('month')) {
          url += '&month=' + urlParams.get('month');
        }
        if (urlParams.has('year')) {
          url += '&year=' + urlParams.get('year');
        }
        if (urlParams.has('date')) {
          url += '&date=' + urlParams.get('date');
        }
        
        // Ajouter les types sélectionnés
        selectedTypes.forEach(type => {
          url += '&types[]=' + type;
        });
        
        // Ajouter les classes sélectionnées si présentes
        const selectedClasses = [];
        document.querySelectorAll('.filter-checkbox[data-filter-type="class"]:checked').forEach(cb => {
          selectedClasses.push(cb.value);
        });
        selectedClasses.forEach(cls => {
          url += '&classes[]=' + encodeURIComponent(cls);
        });
        
        // Rediriger avec les filtres
        window.location.href = url;
      });
    });
    
    // Fonction pour filtrer les classes dans le dropdown
    window.filterClasses = function() {
      const searchText = document.getElementById('classSearch').value.toLowerCase();
      document.querySelectorAll('.dropdown-option').forEach(option => {
        const label = option.querySelector('label').textContent.toLowerCase();
        option.style.display = label.includes(searchText) ? 'block' : 'none';
      });
    };
    
    // Toggle du dropdown des classes
    const classesDropdownToggle = document.getElementById('classesDropdownToggle');
    const classesDropdown = document.getElementById('classesDropdown');
    
    if (classesDropdownToggle && classesDropdown) {
      classesDropdownToggle.addEventListener('click', function() {
        classesDropdown.classList.toggle('show');
      });
      
      // Fermer le dropdown si cliqué en dehors
      window.addEventListener('click', function(e) {
        if (!e.target.matches('.classes-dropdown-toggle') && !classesDropdown.contains(e.target)) {
          classesDropdown.classList.remove('show');
        }
      });
      
      // Sélectionner/désélectionner toutes les classes
      document.getElementById('selectAllClasses')?.addEventListener('click', function(e) {
        document.querySelectorAll('.filter-checkbox[data-filter-type="class"]').forEach(cb => {
          cb.checked = true;
        });
      });
      
      document.getElementById('deselectAllClasses')?.addEventListener('click', function(e) {
        document.querySelectorAll('.filter-checkbox[data-filter-type="class"]').forEach(cb => {
          cb.checked = false;
        });
      });
      
      // Appliquer les filtres de classes
      document.getElementById('applyClassesFilter')?.addEventListener('click', function(e) {
        // Collecter les types sélectionnés existants
        const selectedTypes = [];
        document.querySelectorAll('.filter-checkbox[data-filter-type="type"]:checked').forEach(cb => {
          selectedTypes.push(cb.value);
        });
        
        // Collecter les classes sélectionnées
        const selectedClasses = [];
        document.querySelectorAll('.filter-checkbox[data-filter-type="class"]:checked').forEach(cb => {
          selectedClasses.push(cb.value);
        });
        
        // Construire l'URL avec les filtres
        let url = window.location.href.split('?')[0] + '?filter_set=1';
        
        // Ajouter les paramètres de vue actuels
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('view')) {
          url += '&view=' + urlParams.get('view');
        }
        if (urlParams.has('month')) {
          url += '&month=' + urlParams.get('month');
        }
        if (urlParams.has('year')) {
          url += '&year=' + urlParams.get('year');
        }
        if (urlParams.has('date')) {
          url += '&date=' + urlParams.get('date');
        }
        
        // Ajouter les types sélectionnés
        selectedTypes.forEach(type => {
          url += '&types[]=' + type;
        });
        
        // Ajouter les classes sélectionnées
        selectedClasses.forEach(cls => {
          url += '&classes[]=' + encodeURIComponent(cls);
        });
        
        // Rediriger avec les filtres
        window.location.href = url;
      });
    }
    
    // Initialisation du calendrier
    if (typeof initCalendar === 'function') {
      initCalendar();
    }
  });
  
  // Fonctions de navigation exposées pour être utilisées dans les boutons HTML
  function navigateToPrevious() {
    const view = new URLSearchParams(window.location.search).get('view') || 'month';
    let url = '';
    
    if (view === 'month') {
      let month = parseInt(new URLSearchParams(window.location.search).get('month')) || new Date().getMonth() + 1;
      let year = parseInt(new URLSearchParams(window.location.search).get('year')) || new Date().getFullYear();
      
      if (month === 1) {
        month = 12;
        year--;
      } else {
        month--;
      }
      
      url = `?view=month&month=${month}&year=${year}`;
    } else if (view === 'day') {
      const currentDate = new Date(new URLSearchParams(window.location.search).get('date') || new Date());
      currentDate.setDate(currentDate.getDate() - 1);
      const newDate = currentDate.toISOString().split('T')[0];
      url = `?view=day&date=${newDate}`;
    } else if (view === 'week') {
      const currentDate = new Date(new URLSearchParams(window.location.search).get('date') || new Date());
      currentDate.setDate(currentDate.getDate() - 7);
      const newDate = currentDate.toISOString().split('T')[0];
      url = `?view=week&date=${newDate}`;
    }
    
    // Ajouter les filtres existants
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('filter_set')) {
      url += '&filter_set=1';
      
      // Ajouter les types d'événements filtrés
      urlParams.getAll('types[]').forEach(type => {
        url += `&types[]=${type}`;
      });
      
      // Ajouter les classes filtrées
      urlParams.getAll('classes[]').forEach(cls => {
        url += `&classes[]=${encodeURIComponent(cls)}`;
      });
    }
    
    window.location.href = url;
  }
  
  function navigateToNext() {
    const view = new URLSearchParams(window.location.search).get('view') || 'month';
    let url = '';
    
    if (view === 'month') {
      let month = parseInt(new URLSearchParams(window.location.search).get('month')) || new Date().getMonth() + 1;
      let year = parseInt(new URLSearchParams(window.location.search).get('year')) || new Date().getFullYear();
      
      if (month === 12) {
        month = 1;
        year++;
      } else {
        month++;
      }
      
      url = `?view=month&month=${month}&year=${year}`;
    } else if (view === 'day') {
      const currentDate = new Date(new URLSearchParams(window.location.search).get('date') || new Date());
      currentDate.setDate(currentDate.getDate() + 1);
      const newDate = currentDate.toISOString().split('T')[0];
      url = `?view=day&date=${newDate}`;
    } else if (view === 'week') {
      const currentDate = new Date(new URLSearchParams(window.location.search).get('date') || new Date());
      currentDate.setDate(currentDate.getDate() + 7);
      const newDate = currentDate.toISOString().split('T')[0];
      url = `?view=week&date=${newDate}`;
    }
    
    // Ajouter les filtres existants
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('filter_set')) {
      url += '&filter_set=1';
      
      // Ajouter les types d'événements filtrés
      urlParams.getAll('types[]').forEach(type => {
        url += `&types[]=${type}`;
      });
      
      // Ajouter les classes filtrées
      urlParams.getAll('classes[]').forEach(cls => {
        url += `&classes[]=${encodeURIComponent(cls)}`;
      });
    }
    
    window.location.href = url;
  }
  
  function navigateToToday() {
    const view = new URLSearchParams(window.location.search).get('view') || 'month';
    const today = new Date();
    const todayStr = today.toISOString().split('T')[0];
    
    let url = '';
    
    if (view === 'month') {
      url = `?view=month&month=${today.getMonth() + 1}&year=${today.getFullYear()}`;
    } else if (view === 'day' || view === 'week') {
      url = `?view=${view}&date=${todayStr}`;
    } else {
      url = `?view=${view}`;
    }
    
    // Ajouter les filtres existants
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('filter_set')) {
      url += '&filter_set=1';
      
      // Ajouter les types d'événements filtrés
      urlParams.getAll('types[]').forEach(type => {
        url += `&types[]=${type}`;
      });
      
      // Ajouter les classes filtrées
      urlParams.getAll('classes[]').forEach(cls => {
        url += `&classes[]=${encodeURIComponent(cls)}`;
      });
    }
    
    window.location.href = url;
  }
  
  // Fonctions pour les événements
  function openDayView(date) {
    let url = `?view=day&date=${date}`;
    
    // Ajouter les filtres existants
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('filter_set')) {
      url += '&filter_set=1';
      
      // Ajouter les types d'événements filtrés
      urlParams.getAll('types[]').forEach(type => {
        url += `&types[]=${type}`;
      });
      
      // Ajouter les classes filtrées
      urlParams.getAll('classes[]').forEach(cls => {
        url += `&classes[]=${encodeURIComponent(cls)}`;
      });
    }
    
    window.location.href = url;
  }
  
  function openEventDetails(eventId, e) {
    if (e) {
      e.stopPropagation(); // Empêcher la propagation au jour du calendrier
    }
    window.location.href = 'details_evenement.php?id=' + eventId;
  }
  </script>
</body>
</html>