<template>
    <b-nav-item-dropdown :text="currentLabel" right>
        <b-dropdown-item
            v-for="locale in locales"
            :key="locale.code"
            @click="switchLocale(locale.code)"
            :active="locale.code === currentLocale"
        >
            {{ locale.label }}
        </b-dropdown-item>
    </b-nav-item-dropdown>
</template>

<script>
import { setLocale, getLocale, getSupportedLocales } from '../i18n/index';

const LOCALE_LABELS = {
    en: 'English',
    nl: 'Nederlands',
    fr: 'Français',
    de: 'Deutsch',
    es: 'Español',
};

export default {
    data() {
        return {
            currentLocale: getLocale(),
        };
    },

    computed: {
        locales() {
            return getSupportedLocales().map(code => ({
                code,
                label: LOCALE_LABELS[code] || code,
            }));
        },
        currentLabel() {
            return LOCALE_LABELS[this.currentLocale] || this.currentLocale;
        },
    },

    methods: {
        switchLocale(code) {
            setLocale(code);
            this.currentLocale = code;
        },
    },
};
</script>
