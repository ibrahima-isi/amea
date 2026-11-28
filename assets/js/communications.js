(function () {
    'use strict';

    function escapeHtml(text) {
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    async function confirmAction(options) {
        if (typeof Swal === 'undefined') {
            return window.confirm(options.fallbackText || options.title || 'Confirmer ?');
        }
        const res = await Swal.fire({
            title: options.title,
            html: options.html,
            text: options.text,
            icon: options.icon || 'question',
            showCancelButton: true,
            confirmButtonText: options.confirmButtonText || 'Confirmer',
            cancelButtonText: options.cancelButtonText || 'Annuler'
        });
        return Boolean(res.isConfirmed);
    }

    window.ameaConfirmAction = confirmAction;

    // ─── Audience count preview & confirmation modal ──────────────────────────
    const form = document.getElementById('bulkCommunicationForm');
    const previewBtn = document.getElementById('previewCount');
    const countSpan = document.getElementById('recipientCount');
    const submitBtn = document.getElementById('btnSubmitBulk');

    function currentFilterParams() {
        const params = new URLSearchParams();
        ['status', 'institution', 'consent', 'kyc_status'].forEach(function (name) {
            const field = document.getElementById(name);
            if (field) {
                params.set(name, field.value);
            }
        });
        return params;
    }

    let countRevision = 0;
    async function refreshRecipientCount() {
        if (!form || !countSpan) {
            return null;
        }
        const revision = ++countRevision;
        const filters = currentFilterParams().toString();
        const countUrl = form.dataset.countUrl || 'communications-count.php';
        countSpan.textContent = 'Calcul du nombre de destinataires…';
        try {
            const response = await fetch(countUrl + '?' + filters, {
                headers: { Accept: 'application/json' }
            });
            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }
            const data = await response.json();
            if (revision !== countRevision || currentFilterParams().toString() !== filters) return null;
            const count = data.count;
            if (!Number.isSafeInteger(count) || count < 0) throw new Error('Invalid count');
            countSpan.dataset.count = String(count);
            countSpan.innerHTML = '<strong>' + count + '</strong> destinataire(s) joignable(s) avec ces filtres.';
            return count;
        } catch (err) {
            if (revision !== countRevision) return null;
            countSpan.textContent = 'Impossible de charger le nombre de destinataires.';
            return null;
        }
    }

    if (previewBtn) {
        previewBtn.addEventListener('click', refreshRecipientCount);
    }

    if (form) {
        ['status', 'institution', 'consent', 'kyc_status'].forEach(function (name) {
            const field = document.getElementById(name);
            if (field) {
                field.addEventListener('change', refreshRecipientCount);
            }
        });

        let confirmedSubmit = false;
        form.addEventListener('submit', async function (event) {
            event.preventDefault();
            if (confirmedSubmit) return;
            const subject = (document.getElementById('subject')?.value || '').trim();
            const body = (document.getElementById('body')?.value || '').trim();
            if (!subject || !body) return;
            const filters = currentFilterParams().toString();
            const recipientCount = await refreshRecipientCount();
            if (recipientCount === null) return;

            if (recipientCount === 0) {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        title: 'Aucun destinataire',
                        text: 'Aucun adhérent joignable avec une adresse email valide ne correspond aux filtres sélectionnés.',
                        icon: 'warning'
                    });
                }
                return;
            }

            const confirmedRevision = countRevision;
            const ok = await confirmAction({
                title: 'Confirmer l’envoi',
                html: 'Le message <strong>« ' + escapeHtml(subject) + ' »</strong> sera envoyé à <strong>' + recipientCount + '</strong> destinataire(s). Continuer ?',
                fallbackText: 'Envoyer "' + subject + '" à ' + recipientCount + ' destinataire(s) ?',
                confirmButtonText: 'Oui, lancer l’envoi'
            });
            if (ok && !confirmedSubmit && confirmedRevision === countRevision
                && currentFilterParams().toString() === filters
                && document.getElementById('subject').value.trim() === subject
                && document.getElementById('body').value.trim() === body) {
                confirmedSubmit = true;
                form.elements.namedItem('confirmed_recipient_count').value = String(recipientCount);
                if (submitBtn) {
                    submitBtn.disabled = true;
                }
                form.submit();
            }
        });
    }

    // ─── Live chunked dispatch loop ───────────────────────────────────────────
    const panel = document.getElementById('liveDispatchPanel');
    if (panel) {
        let statusLabels = {};
        let statusBadges = {};
        try {
            statusLabels = JSON.parse(panel.dataset.statusLabels || '{}');
            statusBadges = JSON.parse(panel.dataset.statusBadges || '{}');
        } catch (e) {
            statusLabels = {};
            statusBadges = {};
        }

        const badge = document.getElementById('dispatchStatusBadge');
        const bar = document.getElementById('dispatchProgressBar');
        const sentEl = document.getElementById('dispatchSentCount');
        const totalEl = document.getElementById('dispatchTotalCount');
        const pendingEl = document.getElementById('dispatchPendingCount');
        const failedEl = document.getElementById('dispatchFailedCount');
        const msgEl = document.getElementById('dispatchMessage');
        const quotaEl = document.getElementById('dailyQuotaValue');

        let stopped = false;

        function updateBadge(status) {
            if (!badge) {
                return;
            }
            badge.className = 'badge ' + (statusBadges[status] || 'bg-secondary');
            badge.textContent = statusLabels[status] || status;
        }

        updateBadge(panel.dataset.status || 'QUEUED');

        async function runNextChunk() {
            if (stopped) {
                return;
            }
            const commId = panel.dataset.communicationId;
            const dispatchUrl = panel.dataset.dispatchUrl || 'communications-dispatch.php';
            const csrfToken = panel.dataset.csrfToken || '';

            const body = new URLSearchParams();
            body.set('csrf_token', csrfToken);
            body.set('communication_id', commId || '');

            try {
                const response = await fetch(dispatchUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                        Accept: 'application/json'
                    },
                    body: body.toString()
                });
                const contentType = response.headers.get('content-type') || '';
                if (response.redirected || !contentType.includes('application/json')) {
                    throw new Error('Session expirée. Veuillez vous reconnecter puis recharger la page.');
                }
                const data = await response.json();
                if (!response.ok) {
                    throw new Error(data.error || 'Erreur de traitement du lot.');
                }

                const comm = data.communication;
                if (quotaEl && typeof data.daily_sent === 'number' && typeof data.daily_limit === 'number') {
                    quotaEl.textContent = data.daily_sent + ' / ' + data.daily_limit;
                }

                if (comm) {
                    const total = Number(comm.total_recipients || 0);
                    const sent = Number(comm.sent_count || 0);
                    const failed = Number(comm.failed_count || 0);
                    const cancelled = Number(comm.cancelled_count || 0);
                    const pending = Number(comm.pending_count || 0);
                    const completed = sent + failed + cancelled;
                    const pct = total > 0 ? Math.min(100, Math.round((completed * 100) / total)) : 100;

                    if (sentEl) sentEl.textContent = String(sent);
                    if (totalEl) totalEl.textContent = String(total);
                    if (pendingEl) pendingEl.textContent = String(pending);
                    if (failedEl) failedEl.textContent = String(failed);
                    if (bar) {
                        bar.style.width = pct + '%';
                        bar.textContent = pct + '%';
                        bar.parentElement?.setAttribute('aria-valuenow', String(pct));
                    }
                    updateBadge(comm.status);

                    if (comm.status === 'CANCELLED') {
                        bar?.classList.remove('progress-bar-animated');
                        panel.querySelectorAll('[data-cancel-form="1"]').forEach(function (el) {
                            el.classList.add('d-none');
                        });
                        if (msgEl) msgEl.textContent = 'Communication annulée (' + sent + ' email(s) envoyé(s)).';
                        return;
                    }

                    if (data.daily_limit_reached) {
                        bar?.classList.remove('progress-bar-animated');
                        if (msgEl) {
                            msgEl.textContent = 'Quota quotidien atteint (' + data.daily_sent + ' / ' + data.daily_limit + '). Les destinataires restants reprendront automatiquement dès la réouverture du quota.';
                        }
                        return;
                    }

                    if (data.done) {
                        bar?.classList.remove('progress-bar-animated');
                        panel.querySelectorAll('[data-cancel-form="1"]').forEach(function (el) {
                            el.classList.add('d-none');
                        });
                        if (msgEl) {
                            msgEl.textContent = failed > 0
                                ? 'Envoi terminé : ' + sent + ' envoyé(s), ' + failed + ' échec(s).'
                                : 'Envoi terminé avec succès (' + sent + ' email(s) envoyé(s)).';
                        }
                        return;
                    }

                    if (!data.has_more_due && pending > 0) {
                        bar?.classList.remove('progress-bar-animated');
                        if (msgEl) {
                            msgEl.textContent = pending + ' destinataire(s) en attente de nouvelle tentative automatique (backoff).';
                        }
                        return;
                    }
                }

                if (data.has_more_due) {
                    window.setTimeout(runNextChunk, 150);
                }
            } catch (err) {
                bar?.classList.remove('progress-bar-animated');
                if (msgEl) {
                    msgEl.textContent = 'Erreur temporaire lors de l’envoi du lot : ' + err.message;
                }
            }
        }

        if (panel.dataset.autoStart === '1') {
            runNextChunk();
        }

        document.querySelectorAll('[data-cancel-form="1"]').forEach(function (cancelForm) {
            cancelForm.addEventListener('submit', function () {
                stopped = true;
            });
        });
    }

    // ─── One-click self-test preview send ─────────────────────────────────────
    const selfTestBtn = document.getElementById('btnSelfTest');
    const selfTestFeedback = document.getElementById('selfTestFeedback');
    if (selfTestBtn && form) {
        selfTestBtn.addEventListener('click', async function () {
            const subject = (document.getElementById('subject')?.value || '').trim();
            const body = (document.getElementById('body')?.value || '').trim();
            const csrfToken = form.querySelector('input[name="csrf_token"]')?.value || '';

            if (!subject || !body) {
                if (selfTestFeedback) {
                    selfTestFeedback.className = 'small mb-3 text-danger';
                    selfTestFeedback.textContent = 'Veuillez renseigner un sujet et un message avant d’envoyer un test.';
                }
                return;
            }

            selfTestBtn.disabled = true;
            if (selfTestFeedback) {
                selfTestFeedback.className = 'small mb-3 text-muted';
                selfTestFeedback.textContent = 'Envoi de l’aperçu de test en cours…';
            }

            try {
                const payload = new URLSearchParams();
                payload.set('csrf_token', csrfToken);
                payload.set('action', 'self_test');
                payload.set('subject', subject);
                payload.set('body', body);

                const response = await fetch(form.getAttribute('action') || 'communications.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                        Accept: 'application/json',
                        'X-Requested-With': 'fetch'
                    },
                    body: payload.toString()
                });
                const contentType = response.headers.get('content-type') || '';
                if (response.redirected || !contentType.includes('application/json')) {
                    throw new Error('Session expirée. Veuillez recharger la page.');
                }
                const data = await response.json();
                if (selfTestFeedback) {
                    selfTestFeedback.className = 'small mb-3 ' + (data.ok ? 'text-success' : 'text-danger');
                    selfTestFeedback.textContent = data.message || data.error || 'Résultat inconnu.';
                }
            } catch (err) {
                if (selfTestFeedback) {
                    selfTestFeedback.className = 'small mb-3 text-danger';
                    selfTestFeedback.textContent = err.message || 'Erreur réseau lors de l’envoi du test.';
                }
            } finally {
                selfTestBtn.disabled = false;
            }
        });
    }
})();
