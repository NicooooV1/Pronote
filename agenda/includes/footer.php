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

    // Gestion des filtres
    document.querySelectorAll('.filter-checkbox[data-filter-type="type"]').forEach(checkbox => {
      checkbox.addEventListener('change', function() {
        applyFilters();
      });
    });

    // Activation des liens de la sidebar - détection du module actuel
    const currentPath = window.location.pathname;
    
    // Identifier le module actif en fonction du chemin
    let activeModule = 'agenda'; // Par défaut
    if (currentPath.includes('/notes/')) {
      activeModule = 'notes';
    } else if (currentPath.includes('/cahierdetextes/')) {
      activeModule = 'cahierdetextes';
    } else if (currentPath.includes('/messagerie/')) {
      activeModule = 'messagerie';
    } else if (currentPath.includes('/absences/')) {
      activeModule = 'absences';
    } else if (currentPath.includes('/accueil/')) {
      activeModule = 'accueil';
    }
    
    // Ajouter la classe active au module correspondant
    document.querySelectorAll('.module-link').forEach(link => {
      const href = link.getAttribute('href');
      if (href && href.includes(`/${activeModule}/`)) {
        link.classList.add('active');
      }
    });

    // Gestion des dropdowns
    document.querySelectorAll('.classes-dropdown-toggle').forEach(toggle => {
      toggle.addEventListener('click', function(e) {
        e.preventDefault();
        const dropdown = this.nextElementSibling;
        dropdown.classList.toggle('show');
        
        // Fermer le dropdown si on clique ailleurs
        if (dropdown.classList.contains('show')) {
          document.addEventListener('click', function closeDropdown(e) {
            if (!toggle.contains(e.target) && !dropdown.contains(e.target)) {
              dropdown.classList.remove('show');
              document.removeEventListener('click', closeDropdown);
            }
          });
        }
      });
    });
  });

  function applyFilters() {
    let url = `?view=<?= $view ?? 'month' ?>`;
    
    // Ajouter les paramètres selon la vue
    <?php if (isset($view) && $view === 'month'): ?>
    url += `&month=<?= $month ?? date('n') ?>&year=<?= $year ?? date('Y') ?>`;
    <?php elseif (isset($view) && ($view === 'day' || $view === 'week')): ?>
    url += `&date=<?= $date ?? date('Y-m-d') ?>`;
    <?php endif; ?>
    
    // Ajouter le flag de filtre
    url += '&filter_set=1';
    
    // Ajouter les filtres par type
    const typeCheckboxes = document.querySelectorAll('.filter-checkbox[data-filter-type="type"]:checked');
    typeCheckboxes.forEach(checkbox => {
      url += `&types[]=${checkbox.value}`;
    });
    
    // Ajouter les filtres par classe
    const classCheckboxes = document.querySelectorAll('.filter-checkbox[data-filter-type="class"]:checked');
    classCheckboxes.forEach(checkbox => {
      url += `&classes[]=${encodeURIComponent(checkbox.value)}`;
    });
    
    // Rediriger vers l'URL avec les filtres
    window.location.href = url;
  }
  </script>
</body>
</html>