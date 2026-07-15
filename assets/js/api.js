/**
 * Couche d'accès à l'API REST.
 * Gère le token CSRF et l'envoi/réception JSON.
 */
const Api = (() => {
    // On cible directement le fichier PHP réel et on passe la route en paramètre.
    // Cela fonctionne sur n'importe quelle configuration Nginx/PHP-FPM,
    // sans dépendre d'une règle de réécriture d'URL.
    const BASE = 'api/index.php';
    let csrf = '';

    function setCsrf(token) { csrf = token || ''; }

    async function request(method, path, body) {
        const headers = { 'Content-Type': 'application/json' };
        if (method !== 'GET' && csrf) {
            headers['X-CSRF-Token'] = csrf;
        }
        const url = `${BASE}?r=${encodeURIComponent(path)}`;
        const res = await fetch(url, {
            method,
            headers,
            credentials: 'same-origin',
            body: body ? JSON.stringify(body) : undefined,
        });

        let data = null;
        const text = await res.text();
        if (text) {
            try { data = JSON.parse(text); } catch { data = { error: text }; }
        }
        if (!res.ok) {
            throw new Error((data && data.error) || `Erreur ${res.status}`);
        }
        return data;
    }

    return {
        setCsrf,
        me: () => request('GET', '/auth/me'),
        adminLogin: (username, password) => request('POST', '/auth/admin/login', { username, password }),
        participantLogin: (nom, prenom) => request('POST', '/auth/participant/login', { nom, prenom }),
        logout: () => request('POST', '/auth/logout'),

        listParticipants: () => request('GET', '/participants'),
        createParticipant: (p) => request('POST', '/participants', p),
        updateParticipant: (id, p) => request('PUT', `/participants/${id}`, p),
        deleteParticipant: (id) => request('DELETE', `/participants/${id}`),

        listMatches: () => request('GET', '/matches'),
        createMatch: (m) => request('POST', '/matches', m),
        updateMatch: (id, m) => request('PUT', `/matches/${id}`, m),
        deleteMatch: (id) => request('DELETE', `/matches/${id}`),
        reorderMatches: (ids) => request('PUT', '/matches/reorder', { order: ids }),
        saveResult: (id, payload) => request('PUT', `/matches/${id}/result`, payload),

        myMatches: () => request('GET', '/me/matches'),
        stats: () => request('GET', '/stats'),
    };
})();
