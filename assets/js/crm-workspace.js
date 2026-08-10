(() => {
  'use strict';

  const ready = (callback) => {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', callback, { once: true });
    } else {
      callback();
    }
  };

  const initModals = () => {
    const openers = document.querySelectorAll('[data-modal-open]');
    const dialogs = document.querySelectorAll('.crm-modal');

    const closeDialog = (dialog, force = false) => {
      const form = dialog.querySelector('[data-modal-form]');
      if (!force && form?.dataset.dirty === 'true') {
        const discard = window.confirm('Hay datos sin guardar. ¿Quieres cerrar el formulario?');
        if (!discard) return;
      }
      if (form) form.dataset.dirty = 'false';
      dialog.close();
    };

    openers.forEach((opener) => {
      opener.addEventListener('click', () => {
        const dialog = document.getElementById(opener.dataset.modalOpen || '');
        if (!(dialog instanceof HTMLDialogElement) || opener.disabled) return;
        dialog._crmTrigger = opener;
        dialog.showModal();
        requestAnimationFrame(() => dialog.querySelector('input:not([type="hidden"]), select, textarea')?.focus());
      });
    });

    dialogs.forEach((dialog) => {
      const form = dialog.querySelector('[data-modal-form]');
      form?.addEventListener('input', () => { form.dataset.dirty = 'true'; });
      form?.addEventListener('change', () => { form.dataset.dirty = 'true'; });
      form?.addEventListener('submit', (event) => {
        if (!form.checkValidity()) {
          event.preventDefault();
          form.querySelector(':invalid')?.focus();
          return;
        }
        form.dataset.dirty = 'false';
        const submit = form.querySelector('button[type="submit"]');
        if (submit) {
          submit.disabled = true;
          submit.dataset.originalLabel = submit.textContent || '';
          submit.textContent = 'Guardando...';
        }
      });

      dialog.querySelectorAll('[data-modal-close]').forEach((button) => {
        button.addEventListener('click', () => closeDialog(dialog));
      });
      dialog.addEventListener('cancel', (event) => {
        event.preventDefault();
        closeDialog(dialog);
      });
      dialog.addEventListener('click', (event) => {
        if (event.target === dialog) closeDialog(dialog);
      });
      dialog.addEventListener('close', () => {
        dialog._crmTrigger?.focus();
      });
    });
  };

  const initConfirmations = () => {
    document.querySelectorAll('form[data-confirm-message]').forEach((form) => {
      form.addEventListener('submit', (event) => {
        const message = form.dataset.confirmMessage || '¿Confirmas esta accion?';
        if (!window.confirm(message)) event.preventDefault();
      });
    });
  };

  const chartEmptyState = (canvas, message) => {
    canvas.hidden = true;
    const empty = document.createElement('p');
    empty.className = 'crm-chart-empty';
    empty.textContent = message;
    canvas.parentElement?.appendChild(empty);
  };

  const initCharts = () => {
    const dataNode = document.getElementById('crm-dashboard-chart-data');
    if (!dataNode) return;

    let data;
    try {
      data = JSON.parse(dataNode.textContent || '{}');
    } catch (error) {
      return;
    }

    const trendCanvas = document.getElementById('crm-opportunity-trend');
    const pipelineCanvas = document.getElementById('crm-pipeline-distribution');
    if (!trendCanvas || !pipelineCanvas) return;
    if (typeof window.Chart === 'undefined') {
      chartEmptyState(trendCanvas, 'La grafica no pudo cargarse. Consulta la tabla de datos.');
      chartEmptyState(pipelineCanvas, 'La grafica no pudo cargarse. Consulta la tabla de datos.');
      return;
    }

    const monthly = Array.isArray(data.monthly) ? data.monthly : [];
    const pipeline = Array.isArray(data.pipeline) ? data.pipeline : [];
    if (!monthly.some((item) => Number(item.value) > 0)) {
      chartEmptyState(trendCanvas, 'Aun no hay oportunidades suficientes para mostrar una tendencia.');
    }
    if (!pipeline.some((item) => Number(item.value) > 0)) {
      chartEmptyState(pipelineCanvas, 'Aun no hay etapas comerciales con datos.');
    }

    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const colors = ['#1B5F7A', '#D4A321', '#315F52', '#7A4B84', '#A6573B', '#40566D', '#806007', '#5D6B3C'];
    const common = {
      responsive: true,
      maintainAspectRatio: false,
      animation: reducedMotion ? false : { duration: 320 },
      interaction: { intersect: false, mode: 'index' },
      plugins: {
        legend: { display: false },
        tooltip: {
          backgroundColor: '#101318',
          padding: 12,
          titleFont: { weight: '700' },
          callbacks: { label: (context) => ` ${context.parsed.y ?? context.parsed.x} oportunidades` },
        },
      },
      scales: {
        x: { grid: { display: false }, ticks: { color: '#64686F', font: { size: 11 } } },
        y: { beginAtZero: true, grid: { color: '#E8E2D7' }, ticks: { color: '#64686F', precision: 0, font: { size: 11 } } },
      },
    };

    if (!trendCanvas.hidden) {
      new window.Chart(trendCanvas, {
        type: 'line',
        data: {
          labels: monthly.map((item) => item.label),
          datasets: [{
            label: 'Oportunidades',
            data: monthly.map((item) => Number(item.value) || 0),
            borderColor: '#1B5F7A',
            backgroundColor: 'rgba(27, 95, 122, 0.12)',
            borderWidth: 3,
            fill: true,
            pointBackgroundColor: '#FFFFFF',
            pointBorderColor: '#1B5F7A',
            pointBorderWidth: 2,
            pointRadius: 4,
            pointHoverRadius: 6,
            tension: 0.28,
          }],
        },
        options: common,
      });
    }

    if (!pipelineCanvas.hidden) {
      new window.Chart(pipelineCanvas, {
        type: 'bar',
        data: {
          labels: pipeline.map((item) => item.label),
          datasets: [{
            label: 'Oportunidades',
            data: pipeline.map((item) => Number(item.value) || 0),
            backgroundColor: pipeline.map((_, index) => colors[index % colors.length]),
            borderRadius: 6,
            borderSkipped: false,
          }],
        },
        options: {
          ...common,
          indexAxis: 'y',
          interaction: { intersect: false, mode: 'nearest' },
          scales: {
            x: { beginAtZero: true, grid: { color: '#E8E2D7' }, ticks: { color: '#64686F', precision: 0, font: { size: 11 } } },
            y: { grid: { display: false }, ticks: { color: '#30343A', autoSkip: false, font: { size: 11, weight: '600' } } },
          },
          plugins: {
            ...common.plugins,
            tooltip: {
              ...common.plugins.tooltip,
              callbacks: { label: (context) => ` ${context.parsed.x} oportunidades` },
            },
          },
        },
      });
    }
  };

  ready(() => {
    initModals();
    initConfirmations();
    initCharts();
  });
})();