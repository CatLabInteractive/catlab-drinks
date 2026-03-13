import { createI18n } from 'vue-i18n';
import en from './en';
import nl from './nl';
import fr from './fr';
import de from './de';
import es from './es';

const SUPPORTED_LOCALES = ['en', 'nl', 'fr', 'de', 'es'];
const STORAGE_KEY = 'catlab_drinks_locale';

function detectLocale() {
    // 1. Check query parameters: 'lang' or 'language'
    if (typeof window !== 'undefined' && window.location) {
        const params = new URLSearchParams(window.location.search);
        const langParam = params.get('lang') || params.get('language');
        if (langParam) {
            const normalized = langParam.toLowerCase().split('-')[0];
            if (SUPPORTED_LOCALES.includes(normalized)) {
                localStorage.setItem(STORAGE_KEY, normalized);
                return normalized;
            }
        }
    }

    // 2. Check localStorage for previously saved preference
    if (typeof localStorage !== 'undefined') {
        const stored = localStorage.getItem(STORAGE_KEY);
        if (stored && SUPPORTED_LOCALES.includes(stored)) {
            return stored;
        }
    }

    // 3. Fallback to navigator language
    if (typeof navigator !== 'undefined' && navigator.language) {
        const navLang = navigator.language.toLowerCase().split('-')[0];
        if (SUPPORTED_LOCALES.includes(navLang)) {
            return navLang;
        }
    }

    // 4. Default to English
    return 'en';
}

export function setLocale(locale) {
    if (SUPPORTED_LOCALES.includes(locale)) {
        i18n.global.locale.value = locale;
        localStorage.setItem(STORAGE_KEY, locale);
        document.documentElement.setAttribute('lang', locale);
    }
}

export function getLocale() {
    return i18n.global.locale.value;
}

export function getSupportedLocales() {
    return SUPPORTED_LOCALES;
}

const i18n = createI18n({
    legacy: false,
    locale: detectLocale(),
    fallbackLocale: 'en',
    missingWarn: false,
    fallbackWarn: false,
    messages: {
        en,
        nl,
        fr,
        de,
        es,
    },
});

export default i18n;
