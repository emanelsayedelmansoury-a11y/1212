/* Shared site color setting. The selected palette is stored locally for this site. */
(function () {
    const palettes = {
        blue:    { label: 'أزرق محيطي', primary: '#0284c7', dark: '#0369a1', accent: '#38bdf8', light: '#e0f2fe', hover: '#0ea5e9', border: '#bae6fd' },
        red:     { label: 'أحمر روبي', primary: '#dc2626', dark: '#991b1b', accent: '#f87171', light: '#fee2e2', hover: '#ef4444', border: '#fecaca' },
        orange:  { label: 'برتقالي دافئ', primary: '#ea580c', dark: '#c2410c', accent: '#fb923c', light: '#ffedd5', hover: '#f97316', border: '#fed7aa' },
        yellow:  { label: 'أصفر ذهبي', primary: '#ca8a04', dark: '#a16207', accent: '#facc15', light: '#fef9c3', hover: '#eab308', border: '#fde68a' },
        green:   { label: 'أخضر طبيعي', primary: '#16a34a', dark: '#15803d', accent: '#4ade80', light: '#dcfce7', hover: '#22c55e', border: '#bbf7d0' },
        emerald: { label: 'زمردي فاخر', primary: '#059669', dark: '#047857', accent: '#34d399', light: '#d1fae5', hover: '#10b981', border: '#a7f3d0' },
        teal:    { label: 'تركوازي هادئ', primary: '#0d9488', dark: '#0f766e', accent: '#2dd4bf', light: '#ccfbf1', hover: '#14b8a6', border: '#99f6e4' },
        cyan:    { label: 'سماوي عصري', primary: '#0891b2', dark: '#0e7490', accent: '#22d3ee', light: '#cffafe', hover: '#06b6d4', border: '#a5f3fc' },
        indigo:  { label: 'نيلي احترافي', primary: '#4f46e5', dark: '#3730a3', accent: '#818cf8', light: '#e0e7ff', hover: '#6366f1', border: '#c7d2fe' },
        purple:  { label: 'بنفسجي ملكي', primary: '#7c3aed', dark: '#6d28d9', accent: '#a78bfa', light: '#ede9fe', hover: '#8b5cf6', border: '#ddd6fe' },
        violet:  { label: 'بنفسجي ليلي', primary: '#9333ea', dark: '#7e22ce', accent: '#c084fc', light: '#f3e8ff', hover: '#a855f7', border: '#e9d5ff' },
        pink:    { label: 'وردي إبداعي', primary: '#db2777', dark: '#be185d', accent: '#f472b6', light: '#fce7f3', hover: '#ec4899', border: '#fbcfe8' },
        rose:    { label: 'وردي ناعم', primary: '#e11d48', dark: '#be123c', accent: '#fb7185', light: '#ffe4e6', hover: '#f43f5e', border: '#fecdd3' },
        slate:   { label: 'رمادي أنيق', primary: '#475569', dark: '#334155', accent: '#94a3b8', light: '#f1f5f9', hover: '#64748b', border: '#cbd5e1' },
        gold:    { label: 'أسود وذهبي', primary: '#b7791f', dark: '#975a16', accent: '#d69e2e', light: '#fef3c7', hover: '#d97706', border: '#fcd34d' }
    };

    function getSelectedPalette() {
        const name = localStorage.getItem('site_color_theme') || 'blue';
        return { name: palettes[name] ? name : 'blue', colors: palettes[name] || palettes.blue };
    }

    function applySitePalette() {
        const { colors } = getSelectedPalette();
        const targets = [document.documentElement, document.body].filter(Boolean);
        targets.forEach((target) => {
            target.style.setProperty('--primary', colors.primary);
            target.style.setProperty('--primary-dark', colors.dark);
            target.style.setProperty('--primary-accent', colors.accent);
            target.style.setProperty('--primary-light', colors.light);
            target.style.setProperty('--primary-hover', colors.hover);
            target.style.setProperty('--border-focus', colors.accent);
            target.style.setProperty('--border-light', colors.border);
            target.style.setProperty('--plyr-color-main', colors.primary);
            target.style.setProperty('--site-primary-gradient', `linear-gradient(135deg, ${colors.accent}, ${colors.primary})`);
        });

        // بعض العناصر القديمة في الصفحات تستخدم ألواناً ثابتة؛ هذه الطبقة
        // تجعلها تتبع اللون المختار أيضاً بدون الحاجة لتعديل كل عنصر يدوياً.
        if (!document.getElementById('site-theme-overrides')) {
            const style = document.createElement('style');
            style.id = 'site-theme-overrides';
            style.textContent = `
                .logo-icon, .logo-box, .brand-badge, .btn-submit-auth,
                .btn-lock-submit, .btn-export, .nav-btn, .pay-main-btn,
                .pricing-badge, .pricing-btn, .checkout-submit-btn,
                .btn-confirm-now, .modal-pay-btn, .btn-load-drive,
                .btn-unlock-all {
                    background: var(--site-primary-gradient) !important;
                }
                .hero-badge, .banner-badge, .float-icon, .auth-tabs,
                .wallet-card.selected, .episode-card.active,
                .drive-loader-card, .modal-offer-box, .instructions-box {
                    background-color: var(--primary-light) !important;
                    border-color: var(--border-light) !important;
                }
                .hero-badge, .banner-badge, .file-id-badge,
                .offer-row, .coupon-header, .section-tag {
                    color: var(--primary-dark) !important;
                }
                .hero-title span, .new-price, .pricing-features i,
                .pricing-badge, .btn-next-lesson, .playlist-count,
                .lesson-tag, .card-title i, .api-key-toggle,
                .coupon-header, .coupon-box, .coupon-box .coupon-header {
                    color: var(--primary) !important;
                }
                .browse-course-btn, .btn-next-lesson {
                    border-color: var(--border-light) !important;
                }
                .site-color-option.active {
                    border-color: var(--primary) !important;
                    background-color: var(--primary-light) !important;
                    color: var(--primary-dark) !important;
                }
                .plyr--video .plyr__control--overlaid {
                    background: var(--primary) !important;
                }
                .plyr--video .plyr__control:hover,
                .plyr--video .plyr__control[aria-expanded="true"] {
                    background: var(--primary-light) !important;
                    color: var(--primary) !important;
                }
            `;
            document.head.appendChild(style);
        }
    }

    window.SITE_COLOR_PALETTES = palettes;
    window.SITE_COLOR_PALETTE_ORDER = Object.keys(palettes);
    window.applySitePalette = applySitePalette;
    window.getSiteColorTheme = () => getSelectedPalette().name;

    if (document.body) applySitePalette();
    else document.addEventListener('DOMContentLoaded', applySitePalette, { once: true });
    document.addEventListener('DOMContentLoaded', () => {
        applySitePalette();
        new MutationObserver(applySitePalette).observe(document.body, { attributes: true, attributeFilter: ['class'] });
    }, { once: true });

    window.addEventListener('storage', (event) => {
        if (event.key === 'site_color_theme') applySitePalette();
    });
})();
