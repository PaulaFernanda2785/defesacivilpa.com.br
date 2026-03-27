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
