/**
 * Fonctionnalités JavaScript pour le calendrier de l'agenda
 * Version harmonisée avec le design système Pronote
 */

document.addEventListener('DOMContentLoaded', function() {
  // Initialisation du calendrier
  initCalendar();
  
  // Ajout des écouteurs d'événements pour la navigation
  setupCalendarNavigation();
  
  // Initialiser les interactions avec les événements
  setupEventInteractions();
});

/**
 * Initialise le calendrier et ses fonctionnalités de base
 */
function initCalendar() {
  // Récupérer les références aux éléments du DOM
  const calendarDays = document.querySelectorAll('.calendar-day');
  const calendarEvents = document.querySelectorAll('.calendar-event');
  
  // Définir la hauteur des jours du calendrier pour une meilleure apparence
  adjustCalendarDayHeight();
  
  // Ajouter des écouteurs d'événements pour les événements du calendrier
  calendarEvents.forEach(event => {
    event.addEventListener('click', function(e) {
      e.stopPropagation(); // Empêcher la propagation au jour du calendrier
      const eventId = this.getAttribute('data-event-id');
      if (eventId) {
        openEventDetails(eventId, e);
      }
    });
  });
  
  // Ajouter des écouteurs pour les jours du calendrier
  calendarDays.forEach(day => {
    if (!day.classList.contains('other-month')) {
      day.addEventListener('click', function() {
        const date = this.getAttribute('data-date');
        if (date) {
          openDayView(date);
        }
      });
    }
  });
  
  // Initialiser les interactions avec le mini-calendrier
  setupMiniCalendar();
}

/**
 * Configure les interactions avec le mini-calendrier
 */
function setupMiniCalendar() {
  // Gérer les clics sur les jours du mini-calendrier
  document.querySelectorAll('.mini-calendar-day').forEach(day => {
    if (!day.classList.contains('other-month')) {
      day.addEventListener('click', function() {
        const date = this.getAttribute('data-date');
        if (date) {
          openDayView(date);
        }
      });
    }
  });
  
  // Navigation du mini-calendrier
  document.querySelectorAll('.mini-calendar-nav-btn').forEach(btn => {
    btn.addEventListener('click', function() {
      const month = this.getAttribute('data-month');
      const year = this.getAttribute('data-year');
      if (month && year) {
        let url = `?view=month&month=${month}&year=${year}`;
        
        // Ajouter les filtres s'ils existent
        const filterParams = this.getAttribute('data-filters');
        if (filterParams) {
          url += filterParams;
        }
        
        window.location.href = url;
      }
    });
  });
}

/**
 * Configure les interactions avec les événements du calendrier
 */
function setupEventInteractions() {
  // Ajouter des interactions pour les événements (clic, survol, etc.)
  const calendarEvents = document.querySelectorAll('.calendar-event');
  
  calendarEvents.forEach(event => {
    // Ajouter une classe au survol pour l'effet visuel
    event.addEventListener('mouseenter', function() {
      this.classList.add('event-hover');
    });
    
    event.addEventListener('mouseleave', function() {
      this.classList.remove('event-hover');
    });
  });
}

/**
 * Configure la navigation du calendrier (mois précédent, suivant)
 */
function setupCalendarNavigation() {
  // Gérer le changement de vue (jour, semaine, mois, liste)
  const viewOptions = document.querySelectorAll('.view-toggle a');
  
  viewOptions.forEach(option => {
    option.addEventListener('click', function(e) {
      // Les options de vue ont des liens href qui gèrent déjà la navigation
      // On peut ajouter ici des fonctionnalités supplémentaires si nécessaire
    });
  });
  
  // Boutons de navigation (précédent, suivant, aujourd'hui)
  const prevButton = document.querySelector('.prev-button');
  const nextButton = document.querySelector('.next-button');
  const todayButton = document.querySelector('.today-button');
  
  if (prevButton) {
    prevButton.addEventListener('click', navigateToPrevious);
  }
  
  if (nextButton) {
    nextButton.addEventListener('click', navigateToNext);
  }
  
  if (todayButton) {
    todayButton.addEventListener('click', navigateToToday);
  }
}

/**
 * Ajuste la hauteur des jours du calendrier pour une meilleure apparence
 */
function adjustCalendarDayHeight() {
  const calendarDays = document.querySelectorAll('.calendar-day:not(.other-month)');
  
  // Déterminer la hauteur maximale nécessaire
  let maxEventsCount = 0;
  
  calendarDays.forEach(day => {
    const eventsCount = day.querySelectorAll('.calendar-event').length;
    maxEventsCount = Math.max(maxEventsCount, eventsCount);
  });
  
  // Définir une hauteur minimale basée sur le nombre maximum d'événements
  if (maxEventsCount > 0) {
    const minHeight = 100 + (maxEventsCount * 26); // 100px de base + 26px par événement
    
    calendarDays.forEach(day => {
      day.style.minHeight = minHeight + 'px';
    });
  }
}

/**
 * Fonction pour ouvrir la vue détaillée d'un jour spécifique
 * @param {string} date - La date au format YYYY-MM-DD
 */
function openDayView(date) {
  window.location.href = 'agenda_jour.php?date=' + date;
}

/**
 * Fonction pour ouvrir la vue détaillée d'un événement
 * @param {number} eventId - L'identifiant de l'événement
 * @param {Event} e - L'événement du DOM
 */
function openEventDetails(eventId, e) {
  if (e) {
    e.stopPropagation(); // Empêcher la propagation au jour du calendrier
  }
  window.location.href = 'details_evenement.php?id=' + eventId;
}

/**
 * Fonction pour synchroniser les événements en temps réel (à implémenter avec AJAX)
 */
function syncEvents() {
  // Cette fonction pourrait être appelée périodiquement pour mettre à jour le calendrier
  // sans rechargement complet de la page, en utilisant AJAX pour récupérer les nouveaux événements
  
  // Exemple de code pour une requête AJAX
  /*
  fetch('get_events.php?month=' + currentMonth + '&year=' + currentYear)
    .then(response => response.json())
    .then(data => {
      // Mettre à jour les événements dans le calendrier
      updateCalendarEvents(data);
    })
    .catch(error => {
      console.error('Erreur lors de la synchronisation des événements:', error);
    });
  */
}

/**
 * Gestion des filtres de type d'événement
 */
function setupFilters() {
  document.querySelectorAll('.filter-checkbox').forEach(checkbox => {
    checkbox.addEventListener('change', applyFilters);
  });
}

/**
 * Fonction pour mettre à jour les événements dans le calendrier (appelée par syncEvents)
 * @param {Array} events - Liste des événements à afficher
 */
function updateCalendarEvents(events) {
  // Cette fonction mettrait à jour le DOM avec les nouveaux événements
  
  // Supprimer les événements existants
  document.querySelectorAll('.calendar-day-events').forEach(container => {
    container.innerHTML = '';
  });
  
  // Ajouter les nouveaux événements
  events.forEach(event => {
    const eventDate = new Date(event.date_debut);
    const day = eventDate.getDate();
    const month = eventDate.getMonth() + 1;
    const year = eventDate.getFullYear();
    
    // Trouver le conteneur du jour correspondant
    const dayContainer = document.querySelector(`.calendar-day[data-date="${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}"] .calendar-day-events`);
    
    if (dayContainer) {
      // Créer l'élément d'événement
      const eventElement = document.createElement('div');
      eventElement.className = `calendar-event event-${event.type_evenement}`;
      eventElement.setAttribute('data-event-id', event.id);
      
      if (event.statut === 'annulé') {
        eventElement.classList.add('event-cancelled');
      } else if (event.statut === 'reporté') {
        eventElement.classList.add('event-postponed');
      }
      
      // Ajouter l'heure et le titre
      const eventTime = document.createElement('span');
      eventTime.className = 'event-time';
      eventTime.textContent = eventDate.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
      
      const eventTitle = document.createTextNode(' ' + event.titre);
      
      // Assembler l'élément
      eventElement.appendChild(eventTime);
      eventElement.appendChild(eventTitle);
      
      // Ajouter l'écouteur d'événement
      eventElement.addEventListener('click', function(e) {
        openEventDetails(event.id, e);
      });
      
      // Ajouter au conteneur
      dayContainer.appendChild(eventElement);
    }
  });
}

// Exporter les fonctions pour les utiliser ailleurs si nécessaire
window.calendarFunctions = {
  openDayView,
  openEventDetails,
  syncEvents
};