const HANDLER_URL = process.env.IDIND_ALEXA_HANDLER_URL;
const BRIDGE_TOKEN = process.env.IDIND_ALEXA_BRIDGE_TOKEN;

function alexaError(event, type, message) {
  const directive = event?.directive ?? {};
  const requestHeader = directive.header ?? {};
  const header = {
    namespace: 'Alexa',
    name: 'ErrorResponse',
    messageId: crypto.randomUUID(),
    payloadVersion: '3',
  };

  if (requestHeader.correlationToken) {
    header.correlationToken = requestHeader.correlationToken;
  }

  const response = {
    event: {
      header,
      payload: { type, message },
    },
  };

  if (directive.endpoint?.endpointId) {
    response.event.endpoint = { endpointId: directive.endpoint.endpointId };
  }

  return response;
}

export const handler = async (event) => {
  if (!HANDLER_URL || !BRIDGE_TOKEN || BRIDGE_TOKEN.length < 32) {
    return alexaError(event, 'INTERNAL_ERROR', 'La integracion ID Industrial no esta configurada');
  }

  const controller = new AbortController();
  const timeout = setTimeout(() => controller.abort(), 7000);

  try {
    const response = await fetch(HANDLER_URL, {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-ALEXA-BRIDGE-TOKEN': BRIDGE_TOKEN,
      },
      body: JSON.stringify(event),
      signal: controller.signal,
    });
    const payload = await response.json().catch(() => null);

    if (!response.ok || !payload?.event) {
      console.error('ID Industrial Alexa bridge error', response.status);
      return alexaError(event, 'BRIDGE_UNREACHABLE', 'ID Industrial no respondio correctamente');
    }

    return payload;
  } catch (error) {
    console.error('ID Industrial Alexa bridge unavailable', error?.name ?? 'Error');
    return alexaError(event, 'BRIDGE_UNREACHABLE', 'No fue posible conectar con ID Industrial');
  } finally {
    clearTimeout(timeout);
  }
};
