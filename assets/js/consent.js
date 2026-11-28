(function () {
    'use strict';

    const confirmAction = window.ameaConfirmAction || (async function (options) {
        return window.confirm(options.fallbackText || options.title || 'Confirmer ?');
    });

    // ─── Manual member selector (AJAX search & pagination) ────────────────────
    const form = document.getElementById('cguReminderForm');
    const manualPanel = document.getElementById('manualSelectionPanel');
    const searchInput = document.getElementById('manualMemberSearch');
    const resultsBox = document.getElementById('manualMemberResults');
    const selectedInputs = document.getElementById('manualSelectedInputs');
    const selectedCountEl = document.getElementById('selectedMembersCount');
    const prevBtn = document.getElementById('manualPrevPage');
    const nextBtn = document.getElementById('manualNextPage');
    const pageInfo = document.getElementById('manualPageInfo');

    const selectedIds = new Set();
    let currentPage = 1;
    let totalPages = 1;
    let loadedOnce = false;
    let searchTimer = null;

    function syncSelectedHiddenInputs() {
        if (!selectedInputs || !selectedCountEl) {
            return;
        }
        selectedInputs.innerHTML = '';
        selectedIds.forEach(function (id) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'selected_ids[]';
            input.value = String(id);
            selectedInputs.appendChild(input);
        });
        selectedCountEl.textContent = String(selectedIds.size);
    }

    async function loadSelectableMembers(page) {
        if (!form || !resultsBox) {
            return;
        }
        const url = form.dataset.membersUrl || 'consent-members.php';
        const q = (searchInput?.value || '').trim();
        const params = new URLSearchParams({ scope: 'all', q: q, page: String(page) });

        resultsBox.innerHTML = '<div class="p-3 text-muted small">Chargement…</div>';
        try {
            const response = await fetch(url + '?' + params.toString(), {
                headers: { Accept: 'application/json' }
            });
            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }
            const data = await response.json();
            currentPage = Number(data.page || 1);
            totalPages = Number(data.total_pages || 1);

            if (pageInfo) {
                pageInfo.textContent = 'Page ' + currentPage + ' / ' + totalPages + ' (' + Number(data.total || 0) + ' adhérent(s))';
            }
            if (prevBtn) prevBtn.disabled = currentPage <= 1;
            if (nextBtn) nextBtn.disabled = currentPage >= totalPages;

            const items = Array.isArray(data.items) ? data.items : [];
            if (items.length === 0) {
                resultsBox.innerHTML = '<div class="p-3 text-muted small fst-italic">Aucun adhérent joignable trouvé.</div>';
                return;
            }

            resultsBox.innerHTML = '';
            items.forEach(function (member) {
                const id = Number(member.id);
                const label = document.createElement('label');
                label.className = 'list-group-item d-flex align-items-center gap-2 mb-0';
                label.style.cursor = 'pointer';

                const cb = document.createElement('input');
                cb.type = 'checkbox';
                cb.className = 'form-check-input mt-0 flex-shrink-0';
                cb.checked = selectedIds.has(id);
                cb.addEventListener('change', function () {
                    if (cb.checked) {
                        selectedIds.add(id);
                    } else {
                        selectedIds.delete(id);
                    }
                    syncSelectedHiddenInputs();
                });

                const info = document.createElement('div');
                info.className = 'flex-grow-1 small';
                const nameEl = document.createElement('div');
                nameEl.className = 'fw-semibold';
                nameEl.textContent = (member.first_name || '') + ' ' + (member.last_name || '');

                const badge = document.createElement('span');
                badge.className = Number(member.consent_privacy) === 1
                    ? 'badge bg-success ms-2'
                    : 'badge bg-warning text-dark ms-2';
                badge.textContent = Number(member.consent_privacy) === 1 ? 'CGU acceptées' : 'En attente';
                nameEl.appendChild(badge);

                const emailEl = document.createElement('div');
                emailEl.className = 'text-muted';
                emailEl.textContent = member.email || '';

                info.appendChild(nameEl);
                info.appendChild(emailEl);
                label.appendChild(cb);
                label.appendChild(info);
                resultsBox.appendChild(label);
            });
        } catch (err) {
            resultsBox.innerHTML = '<div class="p-3 text-danger small">Impossible de charger les adhérents.</div>';
        }
    }

    if (form && manualPanel) {
        form.querySelectorAll('input[name="cgu_scope"]').forEach(function (radio) {
            radio.addEventListener('change', function () {
                const isSelected = radio.value === 'selected' && radio.checked;
                manualPanel.classList.toggle('d-none', !isSelected);
                if (isSelected && !loadedOnce) {
                    loadedOnce = true;
                    loadSelectableMembers(1);
                }
            });
        });

        searchInput?.addEventListener('input', function () {
            window.clearTimeout(searchTimer);
            searchTimer = window.setTimeout(function () {
                loadSelectableMembers(1);
            }, 250);
        });

        prevBtn?.addEventListener('click', function () {
            if (currentPage > 1) loadSelectableMembers(currentPage - 1);
        });
        nextBtn?.addEventListener('click', function () {
            if (currentPage < totalPages) loadSelectableMembers(currentPage + 1);
        });

        let confirmed = false;
        form.addEventListener('submit', async function (event) {
            if (confirmed) {
                return;
            }
            const scope = form.querySelector('input[name="cgu_scope"]:checked')?.value || 'not_consented';
            if (scope === 'selected' && selectedIds.size === 0) {
                event.preventDefault();
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        title: 'Aucun adhérent sélectionné',
                        text: 'Veuillez sélectionner au moins un adhérent dans la liste.',
                        icon: 'warning'
                    });
                }
                return;
            }

            event.preventDefault();
            const message = scope === 'all'
                ? 'Un email de mise à jour des CGU sera envoyé à <strong>tous les adhérents actifs</strong> joignables. Continuer ?'
                : scope === 'selected'
                    ? 'Un rappel CGU sera envoyé aux <strong>' + selectedIds.size + ' adhérent(s) sélectionné(s)</strong>. Continuer ?'
                    : 'Un rappel CGU sera envoyé aux adhérents <strong>en attente de consentement</strong> uniquement. Continuer ?';

            const ok = await confirmAction({
                title: 'Confirmer le rappel CGU',
                html: message,
                fallbackText: 'Lancer l’envoi du rappel CGU ?',
                confirmButtonText: 'Oui, lancer'
            });
            if (ok) {
                confirmed = true;
                form.submit();
            }
        });
    }

    // ─── Pending-consent table (live AJAX search & pagination) ────────────────
    const pendingCard = document.getElementById('pendingConsentCard');
    const pendingSearchForm = document.getElementById('pendingSearchForm');
    const pendingSearchInput = document.getElementById('pendingSearch');
    const pendingTbody = document.getElementById('pendingConsentTbody');
    const pendingTotalEl = document.getElementById('pendingConsentTotal');
    const pendingPaginationNav = document.getElementById('pendingConsentPaginationNav');
    const pendingPagination = document.getElementById('pendingConsentPagination');
    let pendingTimer = null;

    async function loadPendingConsent(page) {
        if (!pendingCard || !pendingTbody) {
            return;
        }
        const url = pendingCard.dataset.membersUrl || 'consent-members.php';
        const q = (pendingSearchInput?.value || '').trim();
        const params = new URLSearchParams({ scope: 'pending', q: q, page: String(page) });

        try {
            const response = await fetch(url + '?' + params.toString(), {
                headers: { Accept: 'application/json' }
            });
            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }
            const data = await response.json();
            const items = Array.isArray(data.items) ? data.items : [];
            const total = Number(data.total || 0);
            const current = Number(data.page || 1);
            const pages = Number(data.total_pages || 1);

            if (pendingTotalEl) {
                pendingTotalEl.textContent = String(total);
            }

            pendingTbody.innerHTML = '';
            if (items.length === 0) {
                const tr = document.createElement('tr');
                const td = document.createElement('td');
                td.colSpan = 4;
                td.className = 'text-muted fst-italic text-center py-4';
                td.textContent = 'Aucun adhérent en attente de consentement pour ce filtre.';
                tr.appendChild(td);
                pendingTbody.appendChild(tr);
            } else {
                items.forEach(function (member) {
                    const tr = document.createElement('tr');

                    const tdName = document.createElement('td');
                    const link = document.createElement('a');
                    link.href = 'student-details.php?id=' + Number(member.id);
                    link.className = 'fw-semibold text-decoration-none';
                    link.textContent = (member.first_name || '') + ' ' + (member.last_name || '');
                    tdName.appendChild(link);

                    const tdEmail = document.createElement('td');
                    tdEmail.className = 'small';
                    tdEmail.textContent = member.email || '—';

                    const tdReg = document.createElement('td');
                    tdReg.className = 'small text-nowrap';
                    tdReg.textContent = member.registration_date || '';

                    const tdStatus = document.createElement('td');
                    const badge = document.createElement('span');
                    if (member.consent_refused_at) {
                        badge.className = 'badge bg-danger';
                        badge.textContent = 'Refusé (' + member.consent_refused_at + ')';
                    } else if (member.cgu_reminder_sent_at) {
                        badge.className = 'badge bg-warning text-dark';
                        badge.textContent = 'Rappel envoyé (' + member.cgu_reminder_sent_at + ')';
                    } else {
                        badge.className = 'badge bg-secondary';
                        badge.textContent = 'Pas encore contacté';
                    }
                    tdStatus.appendChild(badge);

                    tr.appendChild(tdName);
                    tr.appendChild(tdEmail);
                    tr.appendChild(tdReg);
                    tr.appendChild(tdStatus);
                    pendingTbody.appendChild(tr);
                });
            }

            if (pendingPaginationNav && pendingPagination) {
                pendingPagination.innerHTML = '';
                pendingPaginationNav.classList.toggle('d-none', pages <= 1);
                if (pages > 1) {
                    for (let p = 1; p <= pages; p++) {
                        const li = document.createElement('li');
                        li.className = 'page-item' + (p === current ? ' active' : '');
                        const a = document.createElement('a');
                        a.className = 'page-link';
                        a.dataset.page = String(p);
                        a.href = 'consent.php?tab=cgu&page=' + p + (q ? '&q=' + encodeURIComponent(q) : '');
                        a.textContent = String(p);
                        li.appendChild(a);
                        pendingPagination.appendChild(li);
                    }
                }
            }
        } catch (err) {
            // Keep existing table rows visible on transient network failure.
        }
    }

    if (pendingCard && pendingSearchForm && pendingSearchInput) {
        pendingSearchForm.addEventListener('submit', function (event) {
            event.preventDefault();
            loadPendingConsent(1);
        });
        pendingSearchInput.addEventListener('input', function () {
            window.clearTimeout(pendingTimer);
            pendingTimer = window.setTimeout(function () {
                loadPendingConsent(1);
            }, 250);
        });
        pendingPagination?.addEventListener('click', function (event) {
            const link = event.target.closest('a[data-page]');
            if (!link) {
                return;
            }
            event.preventDefault();
            loadPendingConsent(Number(link.dataset.page || 1));
        });
    }

    // ─── Confirm moving a deletion-requested member to trash ──────────────────
    document.querySelectorAll('form[data-trash-form="1"]').forEach(function (trashForm) {
        let confirmed = false;
        trashForm.addEventListener('submit', async function (event) {
            if (confirmed) {
                return;
            }
            event.preventDefault();
            const name = trashForm.dataset.memberName || 'cet adhérent';
            const ok = await confirmAction({
                title: 'Déplacer vers la corbeille ?',
                text: 'Le dossier de ' + name + ' sera déplacé vers la corbeille selon la politique de rétention.',
                fallbackText: 'Déplacer ' + name + ' vers la corbeille ?',
                icon: 'warning',
                confirmButtonText: 'Déplacer vers la corbeille'
            });
            if (ok) {
                confirmed = true;
                trashForm.submit();
            }
        });
    });
})();
