(function () {
    const body = document.body;
    const sidebar = document.getElementById('appSidebar');
    const backdrop = document.querySelector('[data-sidebar-backdrop]');
    const toggles = document.querySelectorAll('[data-sidebar-toggle]');
    const closes = document.querySelectorAll('[data-sidebar-close]');
    const desktopQuery = window.matchMedia('(min-width: 1025px)');
    const closableOverlaySelector = [
        '.modal',
        '.modal-ajuda',
        '.modal-ia',
        '#overlay-territorio',
        '#drawer-territorio',
        '#overlay-compdec',
        '#drawer-compdec'
    ].join(', ');
    const sessionAuthenticated = window.APP_SESSION_IS_AUTHENTICATED === true;
    const sessionTimeoutSeconds = Number(window.APP_SESSION_TIMEOUT || 0);
    const sessionWarningSeconds = Number(window.APP_SESSION_WARNING || 120);
    let inactivityTimeoutId = null;
    let inactivityWarningId = null;
    let inactivityCountdownId = null;
    let inactivityWarningNode = null;
    let inactivityDeadline = 0;
    const scrollTopButtonId = 'scrollTopButton';
    const scrollTopThreshold = 280;
    const doubleSubmitOptOutAttribute = 'data-allow-resubmit';
    const requestFrame = window.requestAnimationFrame || function (callback) {
        return window.setTimeout(callback, 16);
    };
    let scrollTopButton = null;
    let scrollTopTicking = false;

    function syncExpandedState(isOpen) {
        toggles.forEach(function (toggle) {
            toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });
    }

    function openSidebar() {
        if (!body || !backdrop) {
            return;
        }

        body.classList.add('sidebar-open');
        backdrop.hidden = false;
        syncExpandedState(true);
    }

    function closeSidebar() {
        if (!body || !backdrop) {
            return;
        }

        body.classList.remove('sidebar-open');
        backdrop.hidden = true;
        syncExpandedState(false);
    }

    function isElementVisible(element) {
        if (!element || element.hidden) {
            return false;
        }

        if (element.classList.contains('ativo') || element.classList.contains('aberto')) {
            return true;
        }

        const computed = window.getComputedStyle(element);

        if (computed.display === 'none' || computed.visibility === 'hidden' || computed.opacity === '0') {
            return false;
        }

        return element.offsetWidth > 0 ||
            element.offsetHeight > 0 ||
            computed.position === 'fixed';
    }

    function closeOverlayElement(element) {
        if (!element) {
            return false;
        }

        const id = element.id || '';

        if ((id === 'overlay-territorio' || id === 'drawer-territorio') && typeof window.fecharTerritorio === 'function') {
            window.fecharTerritorio();
            return true;
        }

        if ((id === 'overlay-compdec' || id === 'drawer-compdec') && typeof window.fecharDrawerCompdec === 'function') {
            window.fecharDrawerCompdec();
            return true;
        }

        if (id === 'modalAjuda' && typeof window.fecharModalAjuda === 'function') {
            window.fecharModalAjuda();
            return true;
        }

        if (id === 'modalIRP' && typeof window.fecharModalIRP === 'function') {
            window.fecharModalIRP();
            return true;
        }

        if (id === 'modalIA' && typeof window.fecharIA === 'function') {
            window.fecharIA();
            return true;
        }

        if ((id === 'modalInfo' || id === 'modalRelatorio') && typeof window.fecharModal === 'function') {
            window.fecharModal();
            return true;
        }

        if (element.classList.contains('modal-ajuda')) {
            element.classList.remove('ativo');
            element.style.display = 'none';
            return true;
        }

        if (element.classList.contains('modal') || element.classList.contains('modal-ia')) {
            element.style.display = 'none';
            return true;
        }

        return false;
    }

    function closeTopOverlay() {
        const overlays = Array.from(document.querySelectorAll(closableOverlaySelector))
            .filter(isElementVisible);

        if (!overlays.length) {
            return false;
        }

        return closeOverlayElement(overlays[overlays.length - 1]);
    }

    function ensureInactivityWarning() {
        if (inactivityWarningNode) {
            return inactivityWarningNode;
        }

        const wrapper = document.createElement('div');
        wrapper.className = 'session-warning';
        wrapper.hidden = true;
        wrapper.innerHTML = [
            '<div class="session-warning-card" role="status" aria-live="polite">',
            '<span class="session-warning-kicker">Sessao protegida</span>',
            '<strong>Voce esta prestes a ser desconectado por inatividade.</strong>',
            '<p id="sessionWarningText">Interaja com a pagina para continuar conectado.</p>',
            '<div class="session-warning-actions">',
            '<button type="button" class="btn btn-secondary" data-session-logout>Sair agora</button>',
            '<button type="button" class="btn btn-primary" data-session-continue>Continuar conectado</button>',
            '</div>',
            '</div>'
        ].join('');

        wrapper.querySelector('[data-session-logout]')?.addEventListener('click', function () {
            window.location.href = '/logout.php';
        });

        wrapper.querySelector('[data-session-continue]')?.addEventListener('click', function () {
            resetInactivityTimers();
        });

        document.body.appendChild(wrapper);
        inactivityWarningNode = wrapper;
        return wrapper;
    }

    function hideInactivityWarning() {
        const warning = ensureInactivityWarning();
        warning.hidden = true;

        if (inactivityCountdownId) {
            window.clearInterval(inactivityCountdownId);
            inactivityCountdownId = null;
        }
    }

    function renderInactivityCountdown() {
        const warning = ensureInactivityWarning();
        const text = warning.querySelector('#sessionWarningText');

        if (!text) {
            return;
        }

        const remainingSeconds = Math.max(0, Math.ceil((inactivityDeadline - Date.now()) / 1000));
        text.textContent = remainingSeconds > 0
            ? `Voce sera desconectado em ${remainingSeconds} segundo${remainingSeconds === 1 ? '' : 's'} se permanecer sem atividade.`
            : 'Desconectando por inatividade...';
    }

    function showInactivityWarning() {
        const warning = ensureInactivityWarning();
        warning.hidden = false;
        renderInactivityCountdown();

        if (inactivityCountdownId) {
            window.clearInterval(inactivityCountdownId);
        }

        inactivityCountdownId = window.setInterval(renderInactivityCountdown, 1000);
    }

    function redirectForInactivity() {
        window.location.href = '/logout.php?motivo=inatividade';
    }

    function resetInactivityTimers() {
        if (!sessionAuthenticated || sessionTimeoutSeconds <= 0) {
            return;
        }

        if (inactivityTimeoutId) {
            window.clearTimeout(inactivityTimeoutId);
        }

        if (inactivityWarningId) {
            window.clearTimeout(inactivityWarningId);
        }

        hideInactivityWarning();

        inactivityDeadline = Date.now() + (sessionTimeoutSeconds * 1000);
        inactivityTimeoutId = window.setTimeout(redirectForInactivity, sessionTimeoutSeconds * 1000);

        const warningDelay = Math.max(1000, (sessionTimeoutSeconds - Math.min(sessionWarningSeconds, sessionTimeoutSeconds - 1)) * 1000);
        inactivityWarningId = window.setTimeout(showInactivityWarning, warningDelay);
    }

    function updateScrollTopVisibility() {
        if (!scrollTopButton) {
            return;
        }

        const currentScroll = window.scrollY || window.pageYOffset || document.documentElement.scrollTop || 0;
        scrollTopButton.classList.toggle('is-visible', currentScroll > scrollTopThreshold);
    }

    function requestScrollTopUpdate() {
        if (scrollTopTicking) {
            return;
        }

        scrollTopTicking = true;
        requestFrame(function () {
            scrollTopTicking = false;
            updateScrollTopVisibility();
        });
    }

    function ensureScrollTopButton() {
        if (!document.body) {
            return;
        }

        scrollTopButton = document.getElementById(scrollTopButtonId);

        if (!scrollTopButton) {
            scrollTopButton = document.createElement('button');
            scrollTopButton.type = 'button';
            scrollTopButton.id = scrollTopButtonId;
            scrollTopButton.className = 'scroll-top-button';
            scrollTopButton.setAttribute('aria-label', 'Voltar ao topo');
            scrollTopButton.setAttribute('title', 'Voltar ao topo');
            scrollTopButton.innerHTML = '<span aria-hidden="true">&uarr;</span>';
            scrollTopButton.addEventListener('click', function () {
                const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                window.scrollTo({ top: 0, behavior: reduceMotion ? 'auto' : 'smooth' });
            });

            document.body.appendChild(scrollTopButton);
        }

        updateScrollTopVisibility();
    }

    function shouldGuardFormSubmission(form) {
        if (!(form instanceof HTMLFormElement)) {
            return false;
        }

        const method = String(form.getAttribute('method') || 'GET').toUpperCase();

        if (method !== 'POST') {
            return false;
        }

        if (form.getAttribute(doubleSubmitOptOutAttribute) === 'true') {
            return false;
        }

        return true;
    }

    function isSubmitButton(element) {
        if (!(element instanceof HTMLElement)) {
            return false;
        }

        if (element instanceof HTMLButtonElement) {
            const type = String(element.getAttribute('type') || 'submit').toLowerCase();
            return type === 'submit';
        }

        if (element instanceof HTMLInputElement) {
            const type = String(element.getAttribute('type') || 'submit').toLowerCase();
            return type === 'submit';
        }

        return false;
    }

    function formSubmitButtons(form) {
        return Array.from(form.querySelectorAll('button[type="submit"], input[type="submit"], button:not([type])'))
            .filter(isSubmitButton);
    }

    function setLoadingStateOnButton(button) {
        if (!isSubmitButton(button)) {
            return;
        }

        if (button instanceof HTMLInputElement) {
            if (typeof button.dataset.originalLabel !== 'string') {
                button.dataset.originalLabel = button.value;
            }

            button.value = 'Processando...';
        } else {
            if (typeof button.dataset.originalLabel !== 'string') {
                button.dataset.originalLabel = button.innerHTML;
            }

            button.innerHTML = '<span class="btn-loading-spinner" aria-hidden="true"></span><span>Processando...</span>';
        }

        button.classList.add('is-loading');
        button.setAttribute('aria-busy', 'true');
        button.disabled = true;
    }

    function restoreButtonState(button) {
        if (!isSubmitButton(button)) {
            return;
        }

        const originalLabel = button.dataset.originalLabel;
        button.classList.remove('is-loading');
        button.removeAttribute('aria-busy');
        button.disabled = false;

        if (typeof originalLabel !== 'string') {
            return;
        }

        if (button instanceof HTMLInputElement) {
            button.value = originalLabel;
        } else {
            button.innerHTML = originalLabel;
        }

        delete button.dataset.originalLabel;
    }

    function guardDoubleSubmit() {
        document.addEventListener('submit', function (event) {
            const form = event.target;

            if (!shouldGuardFormSubmission(form)) {
                return;
            }

            if (form.dataset.submitting === '1') {
                event.preventDefault();
                return;
            }

            form.dataset.submitting = '1';

            const buttons = formSubmitButtons(form);
            buttons.forEach(function (button) {
                if (button.disabled) {
                    return;
                }

                button.disabled = true;
            });

            const submitter = event.submitter instanceof HTMLElement && isSubmitButton(event.submitter)
                ? event.submitter
                : (buttons[0] || null);

            if (submitter) {
                setLoadingStateOnButton(submitter);
            }
        });

        // Evita botoes travados ao voltar para a pagina via historico (bfcache).
        window.addEventListener('pageshow', function () {
            document.querySelectorAll('form[data-submitting="1"]').forEach(function (formNode) {
                if (!(formNode instanceof HTMLFormElement)) {
                    return;
                }

                formSubmitButtons(formNode).forEach(restoreButtonState);
                delete formNode.dataset.submitting;
            });
        });
    }

    if (body && sidebar && backdrop) {
        toggles.forEach(function (toggle) {
            toggle.addEventListener('click', function () {
                if (body.classList.contains('sidebar-open')) {
                    closeSidebar();
                    return;
                }

                openSidebar();
            });
        });

        closes.forEach(function (button) {
            button.addEventListener('click', closeSidebar);
        });

        backdrop.addEventListener('click', closeSidebar);

        desktopQuery.addEventListener('change', function (event) {
            if (event.matches) {
                closeSidebar();
            }
        });
    }

    document.addEventListener('click', function (event) {
        const target = event.target;

        if (!(target instanceof HTMLElement)) {
            return;
        }

        if (!target.matches(closableOverlaySelector) || !isElementVisible(target)) {
            return;
        }

        closeOverlayElement(target);
    });

    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape') {
            return;
        }

        if (closeTopOverlay()) {
            return;
        }

        if (body && body.classList.contains('sidebar-open')) {
            closeSidebar();
        }
    });

    ensureScrollTopButton();
    guardDoubleSubmit();

    window.addEventListener('scroll', requestScrollTopUpdate, { passive: true });
    window.addEventListener('resize', requestScrollTopUpdate);

    if (sessionAuthenticated && sessionTimeoutSeconds > 0) {
        ['click', 'keydown', 'mousemove', 'scroll', 'touchstart'].forEach(function (eventName) {
            document.addEventListener(eventName, function () {
                resetInactivityTimers();
            }, { passive: true });
        });

        document.addEventListener('visibilitychange', function () {
            if (document.visibilityState === 'visible') {
                resetInactivityTimers();
            }
        });

        resetInactivityTimers();
    }
})();
