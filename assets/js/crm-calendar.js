(() => {
  'use strict';

  const ready = (callback) => {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', callback, { once: true });
    } else {
      callback();
    }
  };

  ready(() => {
    const root = document.querySelector('[data-calendar-root]');
    const source = document.getElementById('crm-calendar-data');
    if (!root || !source) return;

    let payload;
    try {
      payload = JSON.parse(source.textContent || '{}');
    } catch (error) {
      root.innerHTML = '<p class="crm-calendar-notice" role="alert">No fue posible leer las fechas del calendario.</p>';
      return;
    }

    const events = Array.isArray(payload.events) ? payload.events : [];
    const eventById = new Map(events.map((event) => [String(event.id), event]));
    const filterButtons = Array.from(document.querySelectorAll('[data-calendar-filter]'));
    const agendaItems = Array.from(document.querySelectorAll('[data-calendar-event]'));
    const visibleCount = document.querySelector('[data-calendar-visible-count]');
    const dialog = document.getElementById('crm-calendar-detail');
    let activeFilter = 'all';
    let lastTrigger = null;
    let calendar = null;

    const setText = (selector, value) => {
      const target = dialog?.querySelector(selector);
      if (target) target.textContent = value || '—';
    };

    const openDetail = (event, trigger) => {
      if (!(dialog instanceof HTMLDialogElement) || !event) return false;
      const props = event.extendedProps || {};
      lastTrigger = trigger || null;
      setText('[data-calendar-detail-kind]', `${props.recordLabel || 'Registro'} · ${props.dateMeaning || 'Fecha programada'}`);
      setText('[data-calendar-detail-title]', event.title || props.reference || 'Evento del calendario');
      setText('[data-calendar-detail-date]', props.dateLabel);
      setText('[data-calendar-detail-meaning]', props.dateMeaning);
      setText('[data-calendar-detail-company]', props.company);
      setText('[data-calendar-detail-service]', props.service);
      setText('[data-calendar-detail-status]', props.status);
      const state = dialog.querySelector('[data-calendar-detail-state]');
      if (state) {
        state.textContent = props.stateLabel || 'En seguimiento';
        state.className = `crm-calendar-detail__status crm-calendar-detail__status--${props.state || 'followup'}`;
      }
      const link = dialog.querySelector('[data-calendar-detail-url]');
      if (link) link.href = props.url || event.url || '#';
      if (!dialog.open) dialog.showModal();
      return true;
    };

    dialog?.querySelector('[data-calendar-detail-close]')?.addEventListener('click', () => dialog.close());
    dialog?.addEventListener('click', (event) => {
      if (event.target === dialog) dialog.close();
    });
    dialog?.addEventListener('close', () => lastTrigger?.focus());

    agendaItems.forEach((item) => {
      item.addEventListener('click', (event) => {
        const calendarEvent = eventById.get(String(item.dataset.calendarEvent || ''));
        if (openDetail(calendarEvent, item)) event.preventDefault();
      });
    });

    const filteredEvents = () => events.filter((event) => {
      if (activeFilter === 'all') return true;
      return event.extendedProps?.recordType === activeFilter;
    });

    const updateFilterUi = () => {
      filterButtons.forEach((button) => {
        const selected = button.dataset.calendarFilter === activeFilter;
        button.classList.toggle('is-active', selected);
        button.setAttribute('aria-pressed', selected ? 'true' : 'false');
      });
      agendaItems.forEach((item) => {
        const event = eventById.get(String(item.dataset.calendarEvent || ''));
        item.hidden = activeFilter !== 'all' && event?.extendedProps?.recordType !== activeFilter;
      });
      const count = filteredEvents().length;
      if (visibleCount) visibleCount.textContent = `${count} ${count === 1 ? 'fecha' : 'fechas'}`;
    };

    const applyFilter = (filter) => {
      activeFilter = ['all', 'quote', 'project'].includes(filter) ? filter : 'all';
      updateFilterUi();
      if (calendar) {
        calendar.batchRendering(() => {
          calendar.removeAllEvents();
          calendar.addEventSource(filteredEvents());
        });
      }
    };

    filterButtons.forEach((button) => {
      button.addEventListener('click', () => applyFilter(button.dataset.calendarFilter || 'all'));
    });

    updateFilterUi();

    if (!window.FullCalendar?.Calendar) {
      root.innerHTML = '<p class="crm-calendar-notice" role="status"><strong>La vista mensual no pudo cargar.</strong> Usa la agenda inmediata para abrir cada registro.</p>';
      return;
    }

    const compactMedia = window.matchMedia('(max-width: 720px)');
    const toolbar = (compact) => compact
      ? { left: 'prev,next', center: 'title', right: 'today' }
      : { left: 'prev,next today', center: 'title', right: 'dayGridMonth,listMonth' };

    calendar = new window.FullCalendar.Calendar(root, {
      locale: 'es',
      firstDay: 1,
      initialDate: payload.initialDate || undefined,
      initialView: compactMedia.matches ? 'listMonth' : 'dayGridMonth',
      headerToolbar: toolbar(compactMedia.matches),
      buttonText: { today: 'Hoy', month: 'Mes', list: 'Agenda' },
      height: 'auto',
      expandRows: true,
      fixedWeekCount: false,
      dayMaxEvents: 3,
      moreLinkText: (count) => `+${count} mas`,
      noEventsText: 'No hay fechas registradas en este periodo.',
      events,
      eventClick: (info) => {
        info.jsEvent.preventDefault();
        openDetail({
          title: info.event.title,
          url: info.event.url,
          extendedProps: info.event.extendedProps,
        }, info.el);
      },
      eventDidMount: (info) => {
        const props = info.event.extendedProps;
        const label = `${props.stateLabel || 'Evento'}. ${info.event.title}. ${props.dateMeaning || ''}.`;
        info.el.setAttribute('aria-label', label);
        info.el.setAttribute('title', label);
      },
    });

    try {
      calendar.render();
      root.classList.add('is-ready');
      root.querySelector('[data-calendar-loading]')?.remove();
    } catch (error) {
      calendar = null;
      root.innerHTML = '<p class="crm-calendar-notice" role="alert"><strong>No se pudo dibujar el calendario.</strong> La agenda inmediata sigue disponible.</p>';
      return;
    }

    const changeResponsiveView = (event) => {
      if (!calendar) return;
      calendar.setOption('headerToolbar', toolbar(event.matches));
      calendar.changeView(event.matches ? 'listMonth' : 'dayGridMonth');
    };
    if (typeof compactMedia.addEventListener === 'function') {
      compactMedia.addEventListener('change', changeResponsiveView);
    } else {
      compactMedia.addListener(changeResponsiveView);
    }
  });
})();