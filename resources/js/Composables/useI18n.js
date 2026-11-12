import { ref, computed } from 'vue';
import en from '../i18n/en.json';
import bn from '../i18n/bn.json';

const dictionaries = { en, bn };

// Default to Bengali ('bn') as requested, fallback to English
const savedLocale = typeof window !== 'undefined' ? (localStorage.getItem('rn_locale') || 'bn') : 'bn';
const currentLocale = ref(savedLocale);

export function useI18n() {
  const setLocale = (locale) => {
    if (dictionaries[locale]) {
      currentLocale.value = locale;
      if (typeof window !== 'undefined') {
        localStorage.setItem('rn_locale', locale);
      }
    }
  };

  const toggleLocale = () => {
    const next = currentLocale.value === 'bn' ? 'en' : 'bn';
    setLocale(next);
  };

  const t = (key, fallback = '') => {
    const dict = dictionaries[currentLocale.value] || dictionaries.en;
    if (dict && dict[key]) {
      return dict[key];
    }
    // Fallback to English dictionary if missing in Bengali
    if (dictionaries.en && dictionaries.en[key]) {
      return dictionaries.en[key];
    }
    return fallback || key;
  };

  const isBengali = computed(() => currentLocale.value === 'bn');

  return {
    currentLocale,
    setLocale,
    toggleLocale,
    t,
    isBengali,
  };
}

