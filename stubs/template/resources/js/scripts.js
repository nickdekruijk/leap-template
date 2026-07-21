const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

// Navigation + search state (Alpine)
document.addEventListener('alpine:init', function () {
    const mobile = window.matchMedia('(max-width: 768px)');

    Alpine.data('navigation', function () {
        return {
            navExpanded: false,
            scrolling: false,
            searchOpen: false,

            // Submenus fold open and shut on desktop, but inside the hamburger panel
            // they are simply listed under their parent. Alpine sets display:none
            // inline on a hidden submenu, which CSS cannot override, so the panel has
            // to know it is on a phone rather than fight it with !important.
            // Kept in sync with $bp-mobile in template.scss.
            isMobile: mobile.matches,

            init() {
                mobile.addEventListener('change', (e) => (this.isMobile = e.matches));

                this.$watch('searchOpen', (value) => {
                    if (value) {
                        this.$nextTick(() => document.getElementById('search-input')?.focus());
                    }
                });

                window.addEventListener('keydown', (e) => {
                    if (e.key === 'Escape') {
                        this.navExpanded = false;
                        this.searchOpen = false;
                        return;
                    }
                    if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
                        e.preventDefault();
                        this.searchOpen = !this.searchOpen;
                        return;
                    }
                    const tag = document.activeElement?.tagName;
                    if (e.key === '/' && !['INPUT', 'TEXTAREA', 'SELECT'].includes(tag) && !document.activeElement?.isContentEditable) {
                        e.preventDefault();
                        this.searchOpen = true;
                    }
                });
            },
        };
    });

    // The tag filter above a content overview. Every card is already in the page (the
    // overview has no paging), so filtering is a local show/hide rather than a fetch:
    // instant, and x-transition fades the cards that come and go. The chosen tag lives
    // in the URL (?tag=slug) via pushState, so a filtered view is shareable and the
    // back button steps back through the filters; popstate keeps the chips in sync.
    Alpine.data('tagFilter', function () {
        const tagFromUrl = () => new URLSearchParams(location.search).get('tag') || '';

        return {
            selected: tagFromUrl(),

            init() {
                window.addEventListener('popstate', () => (this.selected = tagFromUrl()));
            },

            pick(tag) {
                // Clicking the active tag again clears the filter.
                this.selected = this.selected === tag ? '' : tag;
                history.pushState({}, '', this.selected ? '?tag=' + this.selected : location.pathname);
            },

            visible(el) {
                return !this.selected || (el.dataset.tags || '').split(' ').includes(this.selected);
            },
        };
    });

    // The "back" link in front of a breadcrumb. It is a plain link to the parent page, so
    // it works without JavaScript and a crawler follows it — but a visitor who arrived
    // from that very page is better served by the browser's own back: it restores their
    // scroll position and the tag filter they had picked, and costs no request. Only in
    // that case is the click taken over, and never when a modifier says "new tab".
    Alpine.data('breadcrumbBack', function () {
        return {
            back(event) {
                if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
                    return;
                }

                if (!document.referrer || history.length < 2) {
                    return;
                }

                let from;
                try {
                    from = new URL(document.referrer);
                } catch (e) {
                    return;
                }

                // Path only: the referrer may carry a ?tag= filter, and stepping back to
                // exactly that is the point.
                if (from.origin === location.origin && from.pathname === this.$el.pathname) {
                    event.preventDefault();
                    history.back();
                }
            },
        };
    });
});

// Carousel(s)
document.querySelectorAll('.slider').forEach(function (slider) {
    new Slider({
        selector: '#' + slider.id,
        slideSelector: '.slide',
        interval: reduceMotion ? 0 : 5000,
    });
});

// Video sections: swap the poster for the player when it is clicked.
//
// The iframe is built here, inside the click handler, rather than by Alpine. A browser
// only lets a video start with sound if it can tie the play to a user gesture, and an
// iframe conjured up a tick later — as x-if does — no longer counts. Vimeo quietly falls
// back to starting muted, so it looked fine; YouTube just sat on its first frame.
document.querySelectorAll('.video-poster').forEach(function (poster) {
    const section = poster.closest('.video');
    const notice = section.querySelector('.video-consent');

    const play = function () {
        const iframe = document.createElement('iframe');

        iframe.src = poster.dataset.video;
        iframe.title = poster.dataset.videoTitle || '';
        iframe.allow = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture';
        iframe.allowFullscreen = true;

        if (notice) {
            notice.hidden = true;
        }

        poster.replaceWith(iframe);
    };

    poster.addEventListener('click', function () {
        // Loading the player sends the visitor's data to Google or Vimeo, so it waits for
        // permission. With consent switched off, has() answers with the configured
        // default and this is simply never in the way.
        if (!notice || window.consent.has('embeds')) {
            play();

            return;
        }

        notice.hidden = false;
    });

    if (!notice) {
        return;
    }

    // Consent for this one video: an informed click on a button that says what it does.
    // Refusing embeds in general should not mean never watching anything.
    notice.querySelector('.video-consent-once')?.addEventListener('click', play);

    notice.querySelector('.video-consent-always')?.addEventListener('click', function () {
        window.consent.grant('embeds');
        play();
    });
});

// Horizontal-scroll card sections. The scroller turns CSS scroll-snap off while
// dragging and back on for button navigation itself (disableSnapOnDrag, default).
new HorizontalScroller({
    selector: '.items-horizontal .items-container',
    buttonRight: true,
    buttonLeft: true,
    draggable: true,
});

// Accordion panels, for browsers without interpolate-size.
//
// Chrome animates an accordion open on its own: interpolate-size lets the panel
// transition to `auto`, so it lands on its own height with no help. Safari and Firefox
// do not have it, and there is nothing to load that would give it to them — a length is
// the only thing they will animate to. So the height is measured here and handed to CSS
// as --panel-height, which the @supports-not block in template.scss animates towards.
// Where the CSS route works this code never runs at all.
if (!CSS.supports('interpolate-size', 'allow-keywords')) {
    const panels = document.querySelectorAll('.article details');

    const measure = function (details) {
        const summary = details.querySelector('summary');

        if (!summary) {
            return;
        }

        // Reading the height means opening the panel for a moment, which would animate
        // and would be seen. The class switches the transition off for that moment, and
        // lifting the cap first keeps a panel taller than the fallback 100vh from being
        // measured at the clipped height.
        const wasOpen = details.open;

        details.classList.add('measuring');
        details.style.setProperty('--panel-height', 'none');
        details.open = true;

        // scrollHeight covers the whole open element, so the summary and the padding the
        // details itself carries have to come off to leave the panel on its own.
        const padding = getComputedStyle(details);
        const height = details.scrollHeight - summary.offsetHeight - parseFloat(padding.paddingTop) - parseFloat(padding.paddingBottom);

        details.dataset.panelHeight = height + 'px';
        details.style.setProperty('--panel-height', details.dataset.panelHeight);

        details.open = wasOpen;
        details.offsetHeight; // Forces the reflow, so the transition is back before anything else changes it
        details.classList.remove('measuring');
    };

    // Closing has to be taken over. A details element drops its content the instant the
    // open attribute goes, so the panel is gone before any transition can run — opening
    // animates, closing snaps. content-visibility with allow-discrete is supposed to hold
    // the content in place for the duration; Safari does not honour it here. So the click
    // is caught, the element is kept open while the panel travels back to zero, and only
    // then is it really closed.
    const collapse = function (details, summary) {
        summary.addEventListener('click', function (event) {
            // Opening needs no help — the browser gets that right on its own.
            if (!details.open || reduceMotion) {
                return;
            }

            event.preventDefault();

            // The chevron hangs off [open], which is held on until the panel has landed,
            // so it needs telling that the close has started or it turns back late.
            details.classList.add('closing');
            details.style.setProperty('--panel-height', '0px');

            const done = function () {
                details.open = false;
                details.classList.remove('closing');
                details.style.setProperty('--panel-height', details.dataset.panelHeight || 'none');
            };

            // transitionend is the honest signal, but it never arrives if the panel was
            // already at zero height or the transition is cancelled, so the duration read
            // back from the stylesheet closes it regardless.
            const duration = parseFloat(getComputedStyle(details, '::details-content').transitionDuration) * 1000 || 200;
            const guard = setTimeout(done, duration + 50);

            details.addEventListener('transitionend', function () {
                clearTimeout(guard);
                done();
            }, { once: true });
        });
    };

    const measureAll = function () {
        panels.forEach(measure);
    };

    measureAll();

    panels.forEach(function (details) {
        const summary = details.querySelector('summary');

        if (summary) {
            collapse(details, summary);
        }
    });

    // A measurement is only true for the width it was taken at: narrower means more
    // lines means a taller panel. Images settling after load move it as well.
    window.addEventListener('load', measureAll);

    let pending;
    window.addEventListener('resize', function () {
        clearTimeout(pending);
        pending = setTimeout(measureAll, 150);
    });
}

// Light parallax on slider media (skipped when the user prefers reduced motion)
if (!reduceMotion) {
    let pending = false;

    const update = function () {
        pending = false;
        document.querySelectorAll('.slider .slide img, .slider .slide video').forEach(function (media) {
            const rect = media.getBoundingClientRect();
            const offset = rect.top + rect.height / 2 - window.innerHeight / 2;
            media.style.objectPosition = 'center calc(50% + ' + offset * -0.05 + 'px)';
        });
    };

    window.addEventListener('scroll', function () {
        if (!pending) {
            pending = true;
            requestAnimationFrame(update);
        }
    }, { passive: true });

    document.addEventListener('DOMContentLoaded', update);
}
