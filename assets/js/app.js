/**
 * Logique applicative : connexion, vues admin/pratiquant, gestion des données.
 */
(() => {
    'use strict';

    // ------------------------------------------------------------------ //
    // Utilitaires
    // ------------------------------------------------------------------ //
    const $ = (sel, ctx = document) => ctx.querySelector(sel);
    const $$ = (sel, ctx = document) => Array.from(ctx.querySelectorAll(sel));

    /** Échappement HTML anti-XSS. */
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    }[c]));

    const state = { session: null, participants: [], matches: [], matchFilter: 'ALL' };

    function showView(id) {
        $$('.view').forEach((v) => v.classList.add('hidden'));
        $(`#${id}`).classList.remove('hidden');
    }

    function showSessionInfo() {
        const info = $('#session-info');
        if (state.session && state.session.role) {
            info.classList.remove('hidden');
            $('#session-name').textContent = state.session.name || '';
        } else {
            info.classList.add('hidden');
        }
    }

    // ------------------------------------------------------------------ //
    // Démarrage
    // ------------------------------------------------------------------ //
    async function init() {
        try {
            const me = await Api.me();
            Api.setCsrf(me.csrf);
            state.session = me;
            route();
        } catch (e) {
            showView('view-login');
        }
        bindGlobalEvents();
    }

    function route() {
        stopScoresPolling();
        showSessionInfo();
        if (!state.session || !state.session.role) {
            showView('view-login');
        } else if (state.session.role === 'admin') {
            showView('view-admin');
            loadAdmin();
        } else {
            showView('view-participant');
            loadParticipantView();
        }
    }

    // ------------------------------------------------------------------ //
    // Événements globaux
    // ------------------------------------------------------------------ //
    function bindGlobalEvents() {
        $('#logout-btn').addEventListener('click', async () => {
            await Api.logout();
            state.session = null;
            route();
        });

        $('#nav-scores').addEventListener('click', openScores);
        $('#scores-back').addEventListener('click', route);

        $('#admin-login-form').addEventListener('submit', onAdminLogin);
        $('#participant-login-form').addEventListener('submit', onParticipantLogin);

        // Onglets admin
        $$('.tab').forEach((tab) => tab.addEventListener('click', () => {
            $$('.tab').forEach((t) => t.classList.remove('active'));
            tab.classList.add('active');
            $$('.tab-panel').forEach((p) => p.classList.add('hidden'));
            $(`#tab-${tab.dataset.tab}`).classList.remove('hidden');
            if (tab.dataset.tab === 'scores') {
                startScoresPolling($('#scores-admin'));
            } else {
                stopScoresPolling();
            }
        }));

        $('#participant-form').addEventListener('submit', onSaveParticipant);
        $('#participant-cancel').addEventListener('click', resetParticipantForm);
        $('#match-form-bad').addEventListener('submit', onCreateMatch);
        $('#match-form-mma').addEventListener('submit', onCreateMatch);

        // Filtres de discipline
        $$('.chip').forEach((c) => c.addEventListener('click', () => {
            $$('.chip').forEach((x) => x.classList.remove('active'));
            c.classList.add('active');
            state.matchFilter = c.dataset.filter;
            renderMatches(state.matches, $('#matches-list'), true);
        }));

        // Modale résultat
        $('#result-close').addEventListener('click', closeResultModal);
        $('#score-mma').addEventListener('input', refreshBadWinnerHint);
        $('#score-bad').addEventListener('input', refreshBadWinnerHint);
        $('#mma-soumission').addEventListener('change', refreshMmaWinnerHint);
        $('#result-save').addEventListener('click', onSaveResult);

        // Boutons +/- et raccourcis de score (badminton)
        $$('#block-bad .step').forEach((b) => b.addEventListener('click', () => {
            const input = $('#' + b.dataset.target);
            const v = parseInt(input.value, 10) || 0;
            input.value = Math.max(0, v + Number(b.dataset.delta));
            refreshBadWinnerHint();
        }));
        $$('#block-bad .quick-btn').forEach((b) => b.addEventListener('click', () => {
            $('#' + b.dataset.target).value = b.dataset.set;
            refreshBadWinnerHint();
        }));

        bindTimer();
    }

    function openScores() {
        showView('view-scores');
        startScoresPolling($('#scores-public'));
    }

    // ------------------------------------------------------------------ //
    // Connexion
    // ------------------------------------------------------------------ //
    async function onAdminLogin(e) {
        e.preventDefault();
        const f = e.target;
        try {
            const me = await Api.adminLogin(f.username.value, f.password.value);
            Api.setCsrf(me.csrf);
            state.session = me;
            f.reset();
            route();
        } catch (err) { loginError(err.message); }
    }

    async function onParticipantLogin(e) {
        e.preventDefault();
        const f = e.target;
        try {
            const me = await Api.participantLogin(f.nom.value, f.prenom.value);
            Api.setCsrf(me.csrf);
            state.session = me;
            f.reset();
            route();
        } catch (err) { loginError(err.message); }
    }

    function loginError(msg) {
        const el = $('#login-error');
        el.textContent = msg;
        el.classList.remove('hidden');
    }

    // ------------------------------------------------------------------ //
    // Espace admin
    // ------------------------------------------------------------------ //
    async function loadAdmin() {
        await loadParticipants();
        await loadMatches();
    }

    async function loadParticipants() {
        state.participants = await Api.listParticipants();
        renderParticipantsTable();
        fillMatchSelects();
    }

    function renderParticipantsTable() {
        const tbody = $('#participants-table tbody');
        tbody.innerHTML = state.participants.map((p) => `
            <tr>
                <td>${esc(p.nom)}</td>
                <td>${esc(p.prenom)}</td>
                <td><span class="badge ${esc(p.categorie)}">${esc(p.categorie)}</span></td>
                <td>
                    <button class="btn btn-ghost btn-sm" data-edit="${p.id}">Modifier</button>
                    <button class="btn btn-danger btn-sm" data-del="${p.id}">Suppr.</button>
                </td>
            </tr>`).join('');

        $$('[data-edit]', tbody).forEach((b) =>
            b.addEventListener('click', () => editParticipant(Number(b.dataset.edit))));
        $$('[data-del]', tbody).forEach((b) =>
            b.addEventListener('click', () => removeParticipant(Number(b.dataset.del))));
    }

    async function onSaveParticipant(e) {
        e.preventDefault();
        const f = e.target;
        const payload = {
            nom: f.nom.value.trim(),
            prenom: f.prenom.value.trim(),
            categorie: f.categorie.value,
        };
        try {
            if (f.id.value) {
                await Api.updateParticipant(Number(f.id.value), payload);
            } else {
                await Api.createParticipant(payload);
            }
            resetParticipantForm();
            await loadParticipants();
        } catch (err) { alert(err.message); }
    }

    function editParticipant(id) {
        const p = state.participants.find((x) => x.id === id);
        if (!p) return;
        const f = $('#participant-form');
        f.id.value = p.id;
        f.nom.value = p.nom;
        f.prenom.value = p.prenom;
        f.categorie.value = p.categorie;
        $('#participant-cancel').classList.remove('hidden');
    }

    function resetParticipantForm() {
        const f = $('#participant-form');
        f.reset();
        f.id.value = '';
        $('#participant-cancel').classList.add('hidden');
    }

    async function removeParticipant(id) {
        if (!confirm('Supprimer ce pratiquant ? Ses affrontements seront aussi supprimés.')) return;
        try {
            await Api.deleteParticipant(id);
            await loadParticipants();
            await loadMatches();
        } catch (err) { alert(err.message); }
    }

    function fillMatchSelects() {
        const mma = state.participants.filter((p) => p.categorie === 'MMA');
        const bad = state.participants.filter((p) => p.categorie === 'BADMINTON');
        const opt = (p) => `<option value="${p.id}">${esc(p.prenom)} ${esc(p.nom)}</option>`;
        $$('select[name="participant_mma_id"]').forEach((s) => { s.innerHTML = mma.map(opt).join(''); });
        $$('select[name="participant_bad_id"]').forEach((s) => { s.innerHTML = bad.map(opt).join(''); });
    }

    async function onCreateMatch(e) {
        e.preventDefault();
        const f = e.target;
        try {
            await Api.createMatch({
                discipline: f.dataset.discipline,
                participant_mma_id: Number(f.participant_mma_id.value),
                participant_bad_id: Number(f.participant_bad_id.value),
                ordre: Number(f.ordre.value) || 0,
            });
            await loadMatches();
        } catch (err) { alert(err.message); }
    }

    async function loadMatches() {
        state.matches = await Api.listMatches();
        renderMatches(state.matches, $('#matches-list'), true);
    }

    // ------------------------------------------------------------------ //
    // Rendu des affrontements
    // ------------------------------------------------------------------ //
    function renderMatches(matches, container, isAdmin) {
        const filter = isAdmin ? state.matchFilter : 'ALL';
        const list = (matches || []).filter((m) => filter === 'ALL' || m.discipline === filter);
        if (!list.length) {
            container.innerHTML = '<p class="muted">Aucun affrontement.</p>';
            return;
        }
        const canDrag = isAdmin && filter === 'ALL';
        container.innerHTML =
            (isAdmin ? `<p class="muted drag-hint">${canDrag
                ? '↕ Glissez-déposez les affrontements pour changer leur ordre.'
                : 'Passez le filtre sur « Tous » pour réordonner par glisser-déposer.'}</p>` : '')
            + list.map((m) => matchCard(m, isAdmin, canDrag)).join('');

        if (isAdmin) {
            $$('[data-result]', container).forEach((b) =>
                b.addEventListener('click', () => openResultModal(Number(b.dataset.result))));
            $$('[data-delmatch]', container).forEach((b) =>
                b.addEventListener('click', () => removeMatch(Number(b.dataset.delmatch))));
        }
        if (canDrag) {
            enableDragAndDrop(container);
        }
    }

    function matchCard(m, isAdmin, canDrag) {
        const winMma = m.vainqueur_id && Number(m.vainqueur_id) === Number(m.participant_mma_id);
        const winBad = m.vainqueur_id && Number(m.vainqueur_id) === Number(m.participant_bad_id);
        const statutLabel = { a_venir: 'À venir', en_cours: 'En cours', termine: 'Terminé' }[m.statut];
        const isBad = m.discipline === 'BADMINTON';

        let resume;
        if (isBad) {
            const played = m.score_mma !== null && m.score_mma !== undefined && m.score_mma !== '';
            resume = played
                ? `Score — MMA ${esc(m.score_mma)} / Badminton ${esc(m.score_bad)}`
                : 'Résultat non saisi';
        } else {
            const s = m.soumission;
            resume = (s === null || s === undefined || s === '')
                ? 'Résultat non saisi'
                : (String(s) === '1'
                    ? `Soumission en ${esc(m.duree_secondes ?? '?')} s (MMA gagne)`
                    : 'Non soumis en 60 s (Badminton gagne)');
        }

        return `
            <div class="match ${isBad ? 'is-bad' : 'is-mma'}" data-id="${m.id}" ${canDrag ? 'draggable="true"' : ''}>
                <div class="match-head">
                    <div class="fighters">
                        ${canDrag ? '<span class="drag-handle" title="Glisser pour réordonner">⠿</span>' : ''}
                        <span class="badge MMA">MMA</span>
                        <span class="fighter ${winMma ? 'win' : ''}">${esc(m.mma_nom)}</span>
                        <span class="vs">VS</span>
                        <span class="fighter ${winBad ? 'win' : ''}">${esc(m.bad_nom)}</span>
                        <span class="badge BADMINTON">BAD</span>
                    </div>
                    <span class="disc-tag ${isBad ? 'BADMINTON' : 'MMA'}">${isBad ? '🏸 Badminton' : '🥊 MMA'}</span>
                </div>
                <div class="match-rounds">
                    <div>${resume}</div>
                    <span class="status ${esc(m.statut)}">${statutLabel}</span>
                </div>
                ${isAdmin ? `
                <div class="match-actions">
                    <button class="btn btn-primary btn-sm" data-result="${m.id}">Saisir / modifier le résultat</button>
                    <button class="btn btn-danger btn-sm" data-delmatch="${m.id}">Supprimer</button>
                </div>` : ''}
            </div>`;
    }

    // Glisser-déposer pour réordonner les affrontements.
    let dragged = null;

    function enableDragAndDrop(container) {
        $$('.match[draggable="true"]', container).forEach((card) => {
            card.addEventListener('dragstart', () => {
                dragged = card;
                card.classList.add('dragging');
            });
            card.addEventListener('dragend', async () => {
                card.classList.remove('dragging');
                dragged = null;
                await persistOrder(container);
            });
        });

        container.addEventListener('dragover', (e) => {
            e.preventDefault();
            if (!dragged) return;
            const after = getDragAfterElement(container, e.clientY);
            if (after == null) {
                container.appendChild(dragged);
            } else {
                container.insertBefore(dragged, after);
            }
        });
    }

    function getDragAfterElement(container, y) {
        const cards = $$('.match[draggable="true"]:not(.dragging)', container);
        let closest = { offset: Number.NEGATIVE_INFINITY, element: null };
        cards.forEach((card) => {
            const box = card.getBoundingClientRect();
            const offset = y - box.top - box.height / 2;
            if (offset < 0 && offset > closest.offset) {
                closest = { offset, element: card };
            }
        });
        return closest.element;
    }

    async function persistOrder(container) {
        const ids = $$('.match[data-id]', container).map((c) => Number(c.dataset.id));
        if (!ids.length) return;
        try {
            await Api.reorderMatches(ids);
            // Réaligne le cache local sur le nouvel ordre.
            state.matches.sort((a, b) => ids.indexOf(a.id) - ids.indexOf(b.id));
        } catch (err) {
            alert(err.message);
            await loadMatches();
        }
    }

    async function removeMatch(id) {
        if (!confirm('Supprimer cet affrontement ?')) return;
        await Api.deleteMatch(id);
        await loadMatches();
    }

    // ------------------------------------------------------------------ //
    // Modale de saisie des résultats
    // ------------------------------------------------------------------ //
    let currentMatch = null;

    function openResultModal(id) {
        currentMatch = state.matches.find((m) => Number(m.id) === id);
        if (!currentMatch) return;
        const isBad = currentMatch.discipline === 'BADMINTON';

        $('#result-title').textContent = `${currentMatch.mma_nom} (MMA) vs ${currentMatch.bad_nom} (Bad)`;
        $('#result-rule').textContent = isBad
            ? '🏸 Badminton — pas de smash • MMA gagne à 11 • Badminton gagne à 21'
            : "🥊 MMA — le badminton gagne s'il n'est pas soumis en 60 s ; sinon le MMA gagne par soumission";
        $('#result-error').classList.add('hidden');

        $('#block-bad').classList.toggle('hidden', !isBad);
        $('#block-mma').classList.toggle('hidden', isBad);

        if (isBad) {
            $('#score-mma').value = currentMatch.score_mma ?? '';
            $('#score-bad').value = currentMatch.score_bad ?? '';
        } else {
            const s = currentMatch.soumission;
            $('#mma-soumission').value = (s === null || s === undefined) ? '' : String(s);
            $('#mma-duree').value = currentMatch.duree_secondes ?? '';
        }

        resetTimer();
        refreshBadWinnerHint();
        refreshMmaWinnerHint();
        $('#result-modal').classList.remove('hidden');
    }

    function closeResultModal() {
        $('#result-modal').classList.add('hidden');
        stopTimer();
        currentMatch = null;
    }

    function computeBadWinner() {
        const sm = parseInt($('#score-mma').value, 10);
        const sb = parseInt($('#score-bad').value, 10);
        if (Number.isNaN(sm) || Number.isNaN(sb)) return null;
        if (sb >= 21 && sb > sm) return 'BAD';
        if (sm >= 11 && sm > sb) return 'MMA';
        return null;
    }

    function computeMmaWinner() {
        const v = $('#mma-soumission').value;
        if (v === '') return null;
        return v === '1' ? 'MMA' : 'BAD';
    }

    function refreshBadWinnerHint() {
        if (!currentMatch) return;
        const w = computeBadWinner();
        $('#bad-winner').textContent = w
            ? `Vainqueur : ${w === 'MMA' ? currentMatch.mma_nom : currentMatch.bad_nom}`
            : '';
    }

    function refreshMmaWinnerHint() {
        if (!currentMatch) return;
        const w = computeMmaWinner();
        $('#mma-winner').textContent = w
            ? `Vainqueur : ${w === 'MMA' ? currentMatch.mma_nom : currentMatch.bad_nom}`
            : '';
    }

    async function onSaveResult() {
        if (!currentMatch) return;
        const isBad = currentMatch.discipline === 'BADMINTON';
        const payload = {};

        if (isBad) {
            const sm = $('#score-mma').value;
            const sb = $('#score-bad').value;
            payload.score_mma = sm === '' ? null : Number(sm);
            payload.score_bad = sb === '' ? null : Number(sb);
        } else {
            const soum = $('#mma-soumission').value;
            payload.soumission = soum === '' ? null : Number(soum);
            payload.duree_secondes = $('#mma-duree').value === '' ? null : Number($('#mma-duree').value);
        }

        try {
            await Api.saveResult(currentMatch.id, payload);
            closeResultModal();
            await loadMatches();
        } catch (err) { resultError(err.message); }
    }

    function resultError(msg) {
        const el = $('#result-error');
        el.textContent = msg;
        el.classList.remove('hidden');
    }

    // ------------------------------------------------------------------ //
    // Minuteur 60 s (manche MMA)
    // ------------------------------------------------------------------ //
    let timerId = null;
    let remaining = 60;

    function bindTimer() {
        $('#timer-start').addEventListener('click', toggleTimer);
        $('#timer-reset').addEventListener('click', resetTimer);
    }

    function renderTimer() {
        const mm = String(Math.floor(remaining / 60)).padStart(2, '0');
        const ss = String(remaining % 60).padStart(2, '0');
        const el = $('#timer-display');
        el.textContent = `${mm}:${ss}`;
        el.classList.toggle('warn', remaining <= 10);
    }

    function toggleTimer() {
        if (timerId) { stopTimer(); return; }
        $('#timer-start').textContent = 'Pause';
        timerId = setInterval(() => {
            remaining -= 1;
            renderTimer();
            if (remaining <= 0) {
                stopTimer();
                // Temps écoulé sans soumission => le badminton gagne.
                if ($('#mma-soumission').value === '') {
                    $('#mma-soumission').value = '0';
                    $('#mma-duree').value = 60;
                    refreshMmaWinnerHint();
                }
            }
        }, 1000);
    }

    function stopTimer() {
        if (timerId) { clearInterval(timerId); timerId = null; }
        $('#timer-start').textContent = 'Démarrer';
    }

    function resetTimer() {
        stopTimer();
        remaining = 60;
        renderTimer();
    }

    // ------------------------------------------------------------------ //
    // Espace pratiquant
    // ------------------------------------------------------------------ //
    async function loadParticipantView() {
        const matches = await Api.myMatches();
        renderMatches(matches, $('#my-matches'), false);
    }

    // ------------------------------------------------------------------ //
    // Scores globaux (live)
    // ------------------------------------------------------------------ //
    let scoresTimer = null;

    function startScoresPolling(container) {
        stopScoresPolling();
        const tick = async () => {
            try {
                const s = await Api.stats();
                renderScores(s, container);
            } catch (err) {
                container.innerHTML = `<p class="error">${esc(err.message)}</p>`;
            }
        };
        tick();
        scoresTimer = setInterval(tick, 5000);
    }

    function stopScoresPolling() {
        if (scoresTimer) { clearInterval(scoresTimer); scoresTimer = null; }
    }

    function renderScores(s, container) {
        const bad = s.victoires.badminton;
        const mma = s.victoires.mma;
        const total = bad + mma;
        const badPct = total ? Math.round((bad / total) * 100) : 50;
        const mmaPct = 100 - badPct;

        container.innerHTML = `
            <div class="scoreboard">
                <div class="score-camp bad">
                    <div class="score-emoji">🏸</div>
                    <div class="score-num">${bad}</div>
                    <div class="score-label">Victoires Badminton</div>
                </div>
                <div class="score-vs">VS</div>
                <div class="score-camp mma">
                    <div class="score-emoji">🥊</div>
                    <div class="score-num">${mma}</div>
                    <div class="score-label">Victoires MMA</div>
                </div>
            </div>
            <div class="score-bar">
                <div class="score-bar-bad" style="width:${badPct}%"></div>
                <div class="score-bar-mma" style="width:${mmaPct}%"></div>
            </div>
            <div class="score-details">
                <div class="card mini">
                    <h4>🏸 Discipline Badminton</h4>
                    <p>${s.disciplines.BADMINTON.termine} terminé(s) / ${s.disciplines.BADMINTON.total} match(s)</p>
                </div>
                <div class="card mini">
                    <h4>🥊 Discipline MMA</h4>
                    <p>${s.disciplines.MMA.termine} terminé(s) / ${s.disciplines.MMA.total} match(s)</p>
                </div>
            </div>
            <p class="muted center">Mise à jour automatique toutes les 5 s • ${s.total_matches} affrontement(s) au total</p>`;
    }

    init();
})();
