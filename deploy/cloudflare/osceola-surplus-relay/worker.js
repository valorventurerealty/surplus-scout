const SOURCE_URL = 'https://courts.osceolaclerk.com/reports/TaxDeedsSurplusFundsAvailableWeb.pdf';
const MAX_FILE_BYTES = 15 * 1024 * 1024;

export default {
  async fetch(request, env) {
    if (request.method !== 'GET') {
      return new Response('Method not allowed', { status: 405, headers: { Allow: 'GET' } });
    }

    const suppliedToken = request.headers.get('X-VVR-Relay-Token') || '';
    if (!env.RELAY_TOKEN || suppliedToken !== env.RELAY_TOKEN) {
      return new Response('Unauthorized', { status: 401 });
    }

    try {
      const source = await fetch(SOURCE_URL, {
        headers: {
          Accept: 'application/pdf',
          'User-Agent': 'VVR-Surplus-Research-Relay/1.0',
        },
        redirect: 'follow',
        cf: { cacheEverything: false, cacheTtl: 0 },
      });

      if (!source.ok) return new Response('Clerk source unavailable', { status: 502 });
      const declaredLength = Number(source.headers.get('Content-Length') || 0);
      if (declaredLength > MAX_FILE_BYTES) return new Response('Clerk file exceeds allowed size', { status: 502 });

      const contents = await source.arrayBuffer();
      if (contents.byteLength < 100 || contents.byteLength > MAX_FILE_BYTES) {
        return new Response('Invalid Clerk file size', { status: 502 });
      }
      const signature = new Uint8Array(contents.slice(0, 5));
      if (String.fromCharCode(...signature) !== '%PDF-') {
        return new Response('Clerk source did not return a PDF', { status: 502 });
      }

      return new Response(contents, {
        status: 200,
        headers: {
          'Content-Type': 'application/pdf',
          'Content-Disposition': 'inline; filename="TaxDeedsSurplusFundsAvailableWeb.pdf"',
          'Cache-Control': 'no-store, max-age=0',
          'X-Content-Type-Options': 'nosniff',
          'X-VVR-Authoritative-Source': SOURCE_URL,
        },
      });
    } catch {
      return new Response('Clerk source request failed', { status: 502 });
    }
  },
};
