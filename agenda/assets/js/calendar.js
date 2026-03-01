/**
 * Agenda — Module JS unifié
 * Remplace : inline scripts de agenda.php, footer.php, ajouter_evenement.php
 * Fournit : navigation, filtrage, interactions calendrier, mini-calendrier
 */
const Agenda = (function () {
  'use strict';

  /* ── Helpers URL ── */
  function getFilterParams() {
    const params = new URLSearchParams(window.location.search);
    const keep = [];
    params.getAll('types[]').forEach(t => keep.push('types[]=' + encodeURIComponent(t)));
    if (params.get('date'))  keep.push('date='  + params.get('date'));
    if (params.get('month')) keep.push('month=' + params.get('month'));
    if (params.get('year'))  keep.push('year='  + params.get('year'));
    return keep.length ? '&' + keep.join('&') : '';
  }

  function currentParams() {
    return new URLSearchParams(window.location.search);
  }

  /* ── Navigation mois/semaine/jour ── */
  function navigate(direction) {
    const p = currentParams();
    const view = p.get('view') || 'month';
    let url = 'agenda.php?view=' + view;

    if (view === 'month') {
      let m = parseInt(p.get('month')) || new Date().getMonth() + 1;
      let y = parseInt(p.get('year'))  || new Date().getFullYear();
      if (direction === 'prev') { m--; if (m < 1)  { m = 12; y--; } }
      if (direction === 'next') { m++; if (m > 12) { m = 1;  y++; } }
      if (direction === 'today') { const d = new Date(); m = d.getMonth() + 1; y = d.getFullYear(); }
      url += '&month=' + m + '&year=' + y;
    } else if (view === 'week') {
      const curDate = p.get('date') ? new Date(p.get('date')) : new Date();
      if (direction === 'prev')  curDate.setDate(curDate.getDate() - 7);
      if (direction === 'next')  curDate.setDate(curDate.getDate() + 7);
      if (direction === 'today') { const t = new Date(); curDate.setTime(t.getTime()); }
      url += '&date=' + curDate.toISOString().slice(0, 10);
    } else if (view === 'day') {
      const curDate = p.get('date') ? new Date(p.get('date')) : new Date();
      if (direction === 'prev')  curDate.setDate(curDate.getDate() - 1);
      if (direction === 'next')  curDate.setDate(curDate.getDate() + 1);
      if (direction === 'today') { const t = new Date(); curDate.setTime(t.getTime()); }
      url += '&date=' + curDate.toISOString().slice(0, 10);
    } else {
      // list view — navigate par mois
      let m = parseInt(p.get('month')) || new Date().getMonth() + 1;
      let y = parseInt(p.get('year'))  || new Date().getFullYear();
      if (direction === 'prev') { m--; if (m < 1)  { m = 12; y--; } }
      if (direction === 'next') { m++; if (m > 12) { m = 1;  y++; } }
      if (direction === 'today') { const d = new Date(); m = d.getMonth() + 1; y = d.getFullYear(); }
      url += '&month=' + m + '&year=' + y;
    }

    url += getFilterParams();
    window.location.href = url;
  }

  function openDay(date) {
    window.location.href = 'agenda.php?view=day&date=' + date + getFilterParams();
  }

  function openEvent(eventId, e) {
    if (e) e.stopPropagation();
    window.location.href = 'details_evenement.php?id=' + eventId;
  }

  /* ── Filtres types (sidebar checkboxes) ── */
  function applyFilters() {
    const p = currentParams();
    const url = new URL(window.location.href.split('?')[0], window.location.origin);
    url.searchParams.set('view', p.get('view') || 'month');
    if (p.get('month')) url.searchParams.set('month', p.get('month'));
    if (p.get('year'))  url.searchParams.set('year', p.get('year'));
    if (p.get('date'))  url.searchParams.set('date', p.get('date'));

    document.querySelectorAll('.filter-checkbox:checked').forEach(cb => {
      url.searchParams.append('types[]', cb.value);
    });

    window.location.href = url.toString();
  }

  /* ── Mini-calendrier ── */
  function setupMiniCalendar() {
    document.querySelectorAll('.mini-calendar-day:not(.other-month)').forEach(day => {
      day.addEventListener('click', function () {
        const date = this.getAttribute('data-date');
        if (date) openDay(date);
      });
      day.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault();
          this.click();
        }
      });
    });

    document.querySelectorAll('.mini-calendar-nav-btn').forEach(btn => {
      btn.addEventListener('click', function () {
        const m = this.getAttribute('data-month');
        const y = this.getAttribute('data-year');
        if (m && y) {
          const p = currentParams();
          const view = p.get('view') || 'month';
          window.location.href = 'agenda.php?view=' + view + '&month=' + m + '&year=' + y + getFilterParams();
        }
      });
    });
  }

  /* ── Grid calendar interactions ── */
  function setupCalendarGrid() {
    document.querySelectorAll('.calendar-day:not(.other-month)').forEach(day => {
      day.addEventListener('click', function () {
        const date = this.getAttribute('data-date');
        if (date) openDay(date);
      });
    });

    document.querySelectorAll('.calendar-event').forEach(ev => {
      ev.addEventListener('click', function (e) {
        e.stopPropagation();
        const id = this.getAttribute('data-event-id');
        if (id) openEvent(id, e);
      });
    });

    // Auto-adjust day heights to fit events
    const days = document.querySelectorAll('.calendar-day:not(.other-month)');
    let maxEv = 0;
    days.forEach(d => { maxEv = Math.max(maxEv, d.querySelectorAll('.calendar-event').length); });
    if (maxEv > 0) {
      const h = 100 + maxEv * 26;
      days.forEach(d => { d.style.minHeight = h + 'px'; });
    }
  }

  /* ── Navigation buttons ── */
  function setupNavButtons() {
    const prev  = document.querySelector('[data-nav="prev"]');
    const next  = document.querySelector('[data-nav="next"]');
    const today = document.querySelector('[data-nav="today"]');
    if (prev)  prev.addEventListener('click',  () => navigate('prev'));
    if (next)  next.addEventListener('click',  () => navigate('next'));
    if (today) today.addEventListener('click', () => navigate('today'));
  }

  /* ── Sidebar filter checkboxes ── */
  function setupFilters() {
    document.querySelectorAll('.filter-checkbox').forEach(cb => {
      cb.addEventListener('change', applyFilters);
    });
  }

  /* ── Delete confirmation modal (details page) ── */
  function setupDeleteModal() {
    const deleteBtn = document.getElementById('delete-event-btn');
    const modal     = document.getElementById('delete-modal');
    const cancelBtn = document.getElementById('cancel-delete');
    if (!deleteBtn || !modal) return;

    deleteBtn.addEventListener('click', () => {
      modal.style.display = 'flex';
      const confirmBtn = modal.querySelector('.btn-danger, [type="submit"]');
      if (confirmBtn) confirmBtn.focus();
    });
    if (cancelBtn) cancelBtn.addEventListener('click', () => { modal.style.display = 'none'; });
    modal.addEventListener('click', (e) => { if (e.target === modal) modal.style.display = 'none'; });
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && modal.style.display === 'flex') modal.style.display = 'none';
    });
  }

  /* ── Keyboard shortcuts ── */
  function setupKeyboard() {
    document.addEventListener('keydown', (e) => {
      if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.tagName === 'SELECT') return;
      if (e.key === 'ArrowLeft')  navigate('prev');
      if (e.key === 'ArrowRight') navigate('next');
      if (e.key === 't' || e.key === 'T') navigate('today');
    });
  }

  /* ── Init ── */
  function init() {
    setupMiniCalendar();
    setupCalendarGrid();
    setupNavButtons();
    setupFilters();
    setupDeleteModal();
    setupKeyboard();
  }

  document.addEventListener('DOMContentLoaded', init);

  return { navigate, openDay, openEvent, applyFilters, init };
})();

// Backward compat
window.calendarFunctions = Agenda;