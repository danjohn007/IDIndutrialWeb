const http = require('http');
const fs = require('fs');
const path = require('path');

const port = Number(process.env.PORT || 4173);
const webRoot = path.resolve(__dirname, '..', 'web');
let alertCare = {
  atendida: 0,
  estado_atencion: 'NUEVA',
  responsable: null,
  comentario: null,
  gestion_fecha: null
};
let mockSessionActive = false;
const mockCsrfToken = 'mock-csrf-token';
let mq2Calibration = {
  fecha: null,
  adc: null
};

function json(response, payload, status = 200, extraHeaders = {}) {
  response.writeHead(status, {
    'Content-Type': 'application/json; charset=utf-8',
    'Cache-Control': 'no-store',
    ...extraHeaders
  });
  response.end(JSON.stringify(payload));
}

function sampleSeries() {
  return Array.from({ length: 12 }, (_, index) => {
    const date = new Date(Date.now() - (11 - index) * 5 * 60 * 1000);
    return {
      periodo: date.toISOString().slice(0, 19).replace('T', ' '),
      temperatura: 24 + index * 0.18,
      humedad: 44 - index * 0.22,
      gas_raw: index === 9 ? 1750 : 53 + index * 7,
      flama_detectada: index === 9 ? 1 : 0,
      wifi_rssi: -55 - (index % 3),
      alarmas: index === 9 ? 1 : 0,
      alertas: 0,
      revisiones_dht: 0,
      revisiones_mq2: index < 2 ? 1 : 0,
      revisiones_flama: 0
    };
  });
}

const server = http.createServer((request, response) => {
  const requestUrl = new URL(request.url, `http://${request.headers.host}`);

  if (requestUrl.pathname === '/api/auth/me.php') {
    if (!mockSessionActive) {
      json(response, { ok: false, error: 'Sesion requerida' }, 401);
      return;
    }
    json(response, {
      ok: true,
      data: {
        usuario: {
          id: 1,
          nombre: 'Administrador local',
          email: 'admin@idindustrial.local',
          rol: 'ADMIN'
        },
        csrf_token: mockCsrfToken
      }
    });
    return;
  }

  if (
    ['/api/auth/login.php', '/api/auth/crear_admin_inicial.php'].includes(requestUrl.pathname)
    && request.method === 'POST'
  ) {
    mockSessionActive = true;
    json(response, {
      ok: true,
      data: {
        usuario: {
          id: 1,
          nombre: 'Administrador local',
          email: 'admin@idindustrial.local',
          rol: 'ADMIN'
        },
        csrf_token: mockCsrfToken
      }
    });
    return;
  }

  if (requestUrl.pathname === '/api/auth/logout.php' && request.method === 'POST') {
    mockSessionActive = false;
    json(response, { ok: true });
    return;
  }

  if (requestUrl.pathname === '/api/auth/cambiar_password.php' && request.method === 'POST') {
    if (!mockSessionActive) {
      json(response, { ok: false, error: 'Sesion requerida' }, 401);
      return;
    }
    mockSessionActive = false;
    json(response, { ok: true, data: { requiere_login: true } });
    return;
  }

  if (requestUrl.pathname === '/api/web_get.php') {
    if (!mockSessionActive) {
      json(response, { ok: false, error: 'Sesion requerida' }, 401);
      return;
    }
    json(response, {
      ok: true,
      data: {
        resumen: {
          dispositivos_total: 1,
          dispositivos_activos: 1,
          sensores_incendio_activos: 2,
          sensores_incendio_total: 2,
          alertas_mes: 1,
          criticas_abiertas: 1
        },
        dispositivos: [{
          id: 'ESP32_001',
          ubicacion: 'Area de pruebas',
          estado_conexion: 'ONLINE',
          temperatura: 24.6,
          humedad: 44,
          indice_calor: 24.4,
          humo: 53,
          gas_porcentaje: 1.3,
          gas_detectado: 0,
          gas_umbral: 1600,
          mq2_calentamiento_total_s: 120,
          mq2_calentamiento_restante_s: 0,
          mq2_ultima_calibracion: mq2Calibration.fecha,
          mq2_adc_aire_limpio: mq2Calibration.adc,
          mq2_muestras_diagnostico: 10,
          mq2_gas_minimo: 53,
          mq2_gas_maximo: 60,
          mq2_lectura_atascada: 0,
          flama: 0,
          estado_general: 'NORMAL',
          salud_dht: 'OK',
          salud_mq2: 'OK',
          salud_flama: 'OK',
          wifi_rssi: -56,
          ultima_lectura: new Date().toISOString().slice(0, 19).replace('T', ' ')
        }],
        alertas: [{
          id: 1,
          dispositivo_id: 'ESP32_001',
          ubicacion: 'Area de pruebas',
          tipo_alerta: 'Flama',
          valor_sensor: 1,
          severidad: 'CRITICO',
          ...alertCare,
          fecha_hora: new Date(Date.now() - 3600000).toISOString().slice(0, 19).replace('T', ' ')
        }]
      }
    });
    return;
  }

  if (requestUrl.pathname === '/api/salud_sistema.php') {
    if (!mockSessionActive) {
      json(response, { ok: false, error: 'Sesion requerida' }, 401);
      return;
    }
    const now = new Date().toISOString().slice(0, 19).replace('T', ' ');
    json(response, {
      ok: true,
      data: {
        generado_en: now,
        umbral_calibracion_dias: 90,
        resumen: {
          dispositivos_total: 2,
          dispositivos_online: 1,
          dispositivos_offline: 1,
          sensores_revisar: 1,
          mq2_calibracion_requerida: 1,
          mq2_lectura_atascada: 0
        },
        dispositivos: [
          {
            id: 'ESP32_001', ubicacion: 'Area de pruebas', conexion: 'ONLINE',
            salud_dht: 'OK', salud_mq2: 'OK', salud_flama: 'OK', gas_raw: 53,
            mq2_umbral_adc: 1600, ultima_lectura: now,
            ultima_calibracion: mq2Calibration.fecha,
            mq2_calibracion_requerida: mq2Calibration.fecha ? 0 : 1,
            mq2_lectura_atascada: 0
          },
          {
            id: 'ESP32_002', ubicacion: 'Almacen', conexion: 'OFFLINE',
            salud_dht: 'REVISAR', salud_mq2: 'DESCONOCIDO', salud_flama: 'OK', gas_raw: null,
            mq2_umbral_adc: 1600, ultima_lectura: '2026-07-28 10:10:00',
            ultima_calibracion: null, mq2_calibracion_requerida: 1, mq2_lectura_atascada: 0
          }
        ]
      }
    });
    return;
  }

  if (requestUrl.pathname === '/api/reporte_pdf.php') {
    if (!mockSessionActive) {
      json(response, { ok: false, error: 'Sesion requerida' }, 401);
      return;
    }
    response.writeHead(200, {
      'Content-Type': 'application/pdf',
      'Content-Disposition': 'attachment; filename="reporte_id_industrial_demo.pdf"'
    });
    response.end('%PDF-1.4\n% Demo ID Industrial\n%%EOF');
    return;
  }

  if (requestUrl.pathname === '/api/calibrar_mq2.php' && request.method === 'POST') {
    if (!mockSessionActive) {
      json(response, { ok: false, error: 'Sesion requerida' }, 401);
      return;
    }
    let body = '';
    request.on('data', (chunk) => {
      body += chunk;
    });
    request.on('end', () => {
      const payload = JSON.parse(body || '{}');
      mq2Calibration = {
        fecha: new Date().toISOString().slice(0, 19).replace('T', ' '),
        adc: 53
      };
      json(response, {
        ok: true,
        data: {
          dispositivo_id: payload.dispositivo_id,
          adc_aire_limpio: mq2Calibration.adc,
          ultima_calibracion: mq2Calibration.fecha
        }
      }, 201);
    });
    return;
  }

  if (requestUrl.pathname === '/api/atender_alerta.php' && request.method === 'POST') {
    if (!mockSessionActive) {
      json(response, { ok: false, error: 'Sesion requerida' }, 401);
      return;
    }
    let body = '';
    request.on('data', (chunk) => {
      body += chunk;
    });
    request.on('end', () => {
      try {
        const payload = JSON.parse(body);
        const resolved = payload.accion === 'RESOLVER';
        alertCare = {
          atendida: resolved ? 1 : 0,
          estado_atencion: resolved ? 'RESUELTA' : 'RECONOCIDA',
          responsable: payload.responsable,
          comentario: payload.comentario || null,
          gestion_fecha: new Date().toISOString().slice(0, 19).replace('T', ' ')
        };
        json(response, {
          ok: true,
          data: {
            alerta_id: payload.alerta_id,
            estado_atencion: alertCare.estado_atencion,
            responsable: alertCare.responsable
          }
        });
      } catch (error) {
        response.writeHead(400, { 'Content-Type': 'application/json; charset=utf-8' });
        response.end(JSON.stringify({ ok: false, error: 'JSON invalido' }));
      }
    });
    return;
  }

  if (requestUrl.pathname === '/api/resumen.php') {
    if (!mockSessionActive) {
      json(response, { ok: false, error: 'Sesion requerida' }, 401);
      return;
    }
    json(response, {
      ok: true,
      data: {
        periodo_horas: 24,
        gas_umbral: 1600,
        modo_historial: 'muestras_por_minuto',
        resumen: { lecturas: 1 },
        serie: sampleSeries()
      }
    });
    return;
  }

  if (requestUrl.pathname === '/api/incidente.php') {
    if (!mockSessionActive) {
      json(response, { ok: false, error: 'Sesion requerida' }, 401);
      return;
    }
    const alertTime = new Date(Date.now() - 5 * 60 * 1000);
    const series = Array.from({ length: 16 }, (_, index) => {
      const date = new Date(alertTime.getTime() + (index - 10) * 60 * 1000);
      return {
        periodo: date.toISOString().slice(0, 19).replace('T', ' '),
        temperatura: 24 + index * 0.16,
        humedad: 45 - index * 0.12,
        gas_raw: index >= 9 && index <= 11 ? 1750 + index * 20 : 60 + index * 4,
        flama_detectada: index === 10 ? 1 : 0
      };
    });
    json(response, {
      ok: true,
      data: {
        alerta: {
          id: 1,
          dispositivo_id: 'ESP32_001',
          ubicacion: 'Area de pruebas',
          tipo_alerta: 'Flama',
          valor_sensor: 1,
          severidad: 'CRITICO',
          atendida: 0,
          estado_atencion: 'NUEVA',
          responsable: null,
          fecha_hora: alertTime.toISOString().slice(0, 19).replace('T', ' ')
        },
        ventana: { minutos_antes: 15, minutos_despues: 15 },
        gas_umbral: 1600,
        serie: series
      }
    });
    return;
  }

  if (requestUrl.pathname === '/api/alertas.php') {
    if (!mockSessionActive) {
      json(response, { ok: false, error: 'Sesion requerida' }, 401);
      return;
    }
    const page = Math.max(1, Number(requestUrl.searchParams.get('pagina')) || 1);
    const perPage = Math.max(10, Number(requestUrl.searchParams.get('por_pagina')) || 25);
    const total = 37;
    const start = (page - 1) * perPage;
    const alerts = Array.from({ length: Math.max(0, Math.min(perPage, total - start)) }, (_, index) => {
      const id = total - start - index;
      const type = id % 3 === 0 ? 'Humo/Gas' : (id % 3 === 1 ? 'Flama' : 'Temperatura alta');
      return {
        id,
        dispositivo_id: 'ESP32_001',
        ubicacion: 'Area de pruebas',
        tipo_alerta: type,
        valor_sensor: type === 'Flama' ? 1 : (type === 'Humo/Gas' ? 1750 + id : 31.5),
        severidad: id % 4 === 0 ? 'PRECAUCION' : 'CRITICO',
        atendida: id % 5 === 0 ? 1 : 0,
        estado_atencion: id % 5 === 0 ? 'RESUELTA' : (id % 4 === 0 ? 'RECONOCIDA' : 'NUEVA'),
        responsable: id % 4 === 0 ? 'Administrador local' : null,
        fecha_hora: new Date(Date.now() - id * 20 * 60 * 1000).toISOString().slice(0, 19).replace('T', ' ')
      };
    });
    json(response, {
      ok: true,
      data: {
        alertas: alerts,
        dispositivos: [{ id: 'ESP32_001', ubicacion: 'Area de pruebas' }]
      },
      meta: {
        pagina: page,
        por_pagina: perPage,
        total,
        paginas: Math.ceil(total / perPage),
        filtros: {}
      }
    });
    return;
  }

  if (requestUrl.pathname === '/api/exportar_alertas_csv.php') {
    if (!mockSessionActive) {
      json(response, { ok: false, error: 'Sesion requerida' }, 401);
      return;
    }
    response.writeHead(200, { 'Content-Type': 'text/csv; charset=utf-8' });
    response.end('fecha_hora_utc,dispositivo_id,tipo_alerta\n2026-07-27 12:00:00,ESP32_001,Flama\n');
    return;
  }

  if (requestUrl.pathname === '/api/exportar_csv.php') {
    if (!mockSessionActive) {
      json(response, { ok: false, error: 'Sesion requerida' }, 401);
      return;
    }
    response.writeHead(200, { 'Content-Type': 'text/csv; charset=utf-8' });
    response.end('estado,dispositivo\nALARMA,ESP32_001\n');
    return;
  }

  const relativePath = requestUrl.pathname === '/'
    ? 'index.html'
    : decodeURIComponent(requestUrl.pathname).replace(/^[/\\]+/, '');
  const filePath = path.resolve(webRoot, relativePath);

  if (!filePath.startsWith(webRoot + path.sep) && filePath !== path.join(webRoot, 'index.html')) {
    response.writeHead(403);
    response.end('Forbidden');
    return;
  }

  fs.readFile(filePath, (error, content) => {
    if (error) {
      response.writeHead(error.code === 'ENOENT' ? 404 : 500);
      response.end('Not found');
      return;
    }

    const extension = path.extname(filePath);
    if (extension === '.html' && requestUrl.searchParams.has('chart-test')) {
      const testScript = `
        <script>
          window.addEventListener('load', async () => {
            const shell = document.querySelector('.app-shell');
            const canvas = document.querySelector('#fireChart');
            const samples = [];
            for (const width of [900, 1200, 760, 1200, 900, 1200]) {
              shell.style.width = width + 'px';
              window.dispatchEvent(new Event('resize'));
              await new Promise((resolve) => setTimeout(resolve, 220));
              samples.push({
                shell: shell.getBoundingClientRect().width,
                containerWidth: canvas.parentElement.clientWidth,
                containerHeight: canvas.parentElement.clientHeight,
                canvasWidth: canvas.getBoundingClientRect().width,
                canvasHeight: canvas.getBoundingClientRect().height
              });
            }
            document.body.dataset.chartTest = JSON.stringify(samples);
          });
        </script>
      `;
      content = Buffer.from(
        content.toString('utf8').replace('</body>', testScript + '</body>'),
        'utf8'
      );
    }
    if (extension === '.html' && requestUrl.searchParams.has('layout-test')) {
      const layoutTestScript = `
        <script>
          window.addEventListener('load', () => {
            setTimeout(() => {
              const rect = (selector) => {
                const node = document.querySelector(selector);
                if (!node) return null;
                const box = node.getBoundingClientRect();
                return { left: box.left, right: box.right, width: box.width, scrollWidth: node.scrollWidth };
              };
              document.body.dataset.layoutTest = JSON.stringify({
                innerWidth: window.innerWidth,
                documentWidth: document.documentElement.scrollWidth,
                bodyWidth: document.body.scrollWidth,
                app: rect('.app-shell'),
                topbar: rect('.topbar'),
                status: rect('.status-panel'),
                metrics: rect('.metric-grid'),
                charts: rect('.charts-panel'),
                events: rect('.events-panel')
              });
            }, 2600);
          });
        </script>
      `;
      content = Buffer.from(
        content.toString('utf8').replace('</body>', layoutTestScript + '</body>'),
        'utf8'
      );
    }
    if (extension === '.html' && requestUrl.searchParams.has('incident-test')) {
      const incidentTestScript = `
        <script>
          window.addEventListener('load', () => {
            setTimeout(() => document.querySelector('[data-alert-id]')?.click(), 2200);
          });
        </script>
      `;
      content = Buffer.from(
        content.toString('utf8').replace('</body>', incidentTestScript + '</body>'),
        'utf8'
      );
    }
    if (extension === '.html' && requestUrl.searchParams.has('mq2-test')) {
      const mq2TestScript = `
        <script>
          window.addEventListener('load', () => {
            setTimeout(() => document.querySelector('[data-mq2-calibrate]')?.click(), 2200);
          });
        </script>
      `;
      content = Buffer.from(
        content.toString('utf8').replace('</body>', mq2TestScript + '</body>'),
        'utf8'
      );
    }
    const contentType = extension === '.css'
      ? 'text/css'
      : (extension === '.js' ? 'text/javascript' : 'text/html');
    response.writeHead(200, { 'Content-Type': `${contentType}; charset=utf-8` });
    response.end(content);
  });
});

server.listen(port, '127.0.0.1', () => {
  process.stdout.write(`Preview: http://127.0.0.1:${port}\n`);
});
