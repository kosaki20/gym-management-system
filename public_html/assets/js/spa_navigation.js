/**
 * Boiyets Gym Management System — Smooth SPA Navigation Loader
 * Intercepts internal link clicks and dynamically updates page contents
 * without full browser reloads for a seamless user experience.
 */

document.addEventListener('DOMContentLoaded', function() {
    // Inject Top Progress Bar Element
    if (!document.getElementById('gym-top-loader')) {
        const loader = document.createElement('div');
        loader.id = 'gym-top-loader';
        loader.style.cssText = 'position:fixed; top:0; left:0; width:0%; height:3px; background: linear-gradient(90deg, #e8a012, #f59e0b, #e8a012); z-index:99999; transition: width 0.25s ease, opacity 0.3s ease; opacity:0; box-shadow: 0 0 10px rgba(232, 160, 18, 0.7);';
        document.body.appendChild(loader);
    }

    // Intercept clicks on links
    document.addEventListener('click', function(e) {
        const link = e.target.closest('a');
        if (!link) return;

        const href = link.getAttribute('href');
        if (!href) return;

        // Skip non-PHP links, external links, anchor targets, logout, and chat (needs full load)
        if (
            href.startsWith('#') ||
            href.startsWith('javascript:') ||
            href.startsWith('mailto:') ||
            href.startsWith('tel:') ||
            href.includes('logout.php') ||
            href.includes('chat.php') ||
            link.target === '_blank' ||
            link.hasAttribute('data-no-ajax') ||
            !href.endsWith('.php') && !href.includes('.php?')
        ) {
            return;
        }

        // Check if same origin
        const url = new URL(href, window.location.href);
        if (url.origin !== window.location.origin) return;

        // Prevent full page reload
        e.preventDefault();
        
        // If clicking the current page URL, do nothing
        if (url.href === window.location.href) return;

        loadPageAjax(url.href, true);
    });

    // Handle Browser Back & Forward buttons
    window.addEventListener('popstate', function() {
        loadPageAjax(window.location.href, false);
    });
});

function loadPageAjax(targetUrl, pushToHistory) {
    const loader = document.getElementById('gym-top-loader');
    const mainContainer = document.querySelector('.gym-main-container');

    if (loader) {
        loader.style.opacity = '1';
        loader.style.width = '40%';
    }

    if (mainContainer) {
        mainContainer.style.transition = 'opacity 0.15s ease';
        mainContainer.style.opacity = '0.5';
    }

    fetch(targetUrl, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(response => {
        if (loader) loader.style.width = '80%';
        if (!response.ok) throw new Error('Network response error');
        return response.text();
    })
    .then(html => {
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');

        // Extract new page content
        const newMainContent = doc.querySelector('.gym-main-container');
        const newTitle = doc.querySelector('title');

        if (newMainContent && mainContainer) {
            mainContainer.innerHTML = newMainContent.innerHTML;
            
            if (newTitle) {
                document.title = newTitle.textContent;
            }

            if (pushToHistory) {
                window.history.pushState({}, '', targetUrl);
            }

            // Update active state in sidebar
            updateActiveSidebarLink(targetUrl);

            // Re-execute scripts embedded inside the new content
            const scripts = mainContainer.querySelectorAll('script');
            scripts.forEach(oldScript => {
                const newScript = document.createElement('script');
                Array.from(oldScript.attributes).forEach(attr => newScript.setAttribute(attr.name, attr.value));
                newScript.appendChild(document.createTextNode(oldScript.innerHTML));
                oldScript.parentNode.replaceChild(newScript, oldScript);
            });

            // Re-initialize Lucide Icons
            if (typeof lucide !== 'undefined' && typeof lucide.createIcons === 'function') {
                lucide.createIcons();
            }

            // Scroll to top smoothly
            window.scrollTo({ top: 0, behavior: 'smooth' });
        } else {
            // Fallback if main container was not found
            window.location.href = targetUrl;
        }
    })
    .catch(err => {
        console.error('SPA Navigation error, falling back to page reload:', err);
        window.location.href = targetUrl;
    })
    .finally(() => {
        if (loader) {
            loader.style.width = '100%';
            setTimeout(() => {
                loader.style.opacity = '0';
                loader.style.width = '0%';
            }, 250);
        }
        if (mainContainer) {
            mainContainer.style.opacity = '1';
        }
        // Close mobile sidebar if open
        if (typeof closeMobileSidebar === 'function') {
            closeMobileSidebar();
        }
    });
}

function updateActiveSidebarLink(targetUrl) {
    const currentFilename = targetUrl.split('/').pop().split('?')[0];
    const sidebarLinks = document.querySelectorAll('.sidebar-link');

    sidebarLinks.forEach(link => {
        const linkHref = link.getAttribute('href');
        if (!linkHref) return;

        const linkFilename = linkHref.split('/').pop().split('?')[0];
        if (linkFilename === currentFilename) {
            link.classList.add('active');
        } else {
            link.classList.remove('active');
        }
    });
}
