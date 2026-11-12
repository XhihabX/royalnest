import { ref, computed } from 'vue';

// Bengali number mapping
const bnDigitMap = {
  '০': '0', '১': '1', '২': '2', '৩': '3', '৪': '4',
  '৫': '5', '৬': '6', '৭': '7', '৮': '8', '৯': '9',
};

const bnWordNumberMap = {
  'এক': 1, 'দেড়': 1.5, 'দুই': 2, 'আড়াই': 2.5, 'তিন': 3, 'চার': 4, 'পাঁচ': 5,
  'ছয়': 6, 'ছয়': 6, 'সাত': 7, 'আট': 8, 'নয়': 9, 'নয়': 9, 'দশ': 10,
  'এগারো': 11, 'বারো': 12, 'তেরো': 13, 'চৌদ্দ': 14, 'পনেরো': 15, 'ষোল': 16, 'সতেরো': 17, 'আঠারো': 18, 'উনিশ': 19, 'বিশ': 20,
  'পঁচিশ': 25, 'ত্রিশ': 30, 'পঁয়ত্রিশ': 35, 'চল্লিশ': 40, 'পঁয়তাল্লিশ': 45, 'পঞ্চাশ': 50,
  'ষাট': 60, 'সত্তর': 70, 'আশি': 80, 'নব্বই': 90,
  'একশো': 100, 'দুশো': 200, 'তিনশো': 300, 'চারশো': 400, 'পাঁচশো': 500,
  'হাজার': 1000, 'লাখ': 100000,
};

export function useVoiceCostParser() {
  const isListening = ref(false);
  const transcript = ref('');
  const interimTranscript = ref('');
  const voiceLanguage = ref('bn-BD'); // 'bn-BD' or 'en-US'
  const errorMessage = ref(null);
  const isSupported = ref(false);

  let recognition = null;

  // Check browser support
  if (typeof window !== 'undefined') {
    const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    if (SpeechRecognition) {
      isSupported.value = true;
      recognition = new SpeechRecognition();
      recognition.continuous = false;
      recognition.interimResults = true;

      recognition.onstart = () => {
        isListening.value = true;
        errorMessage.value = null;
      };

      recognition.onresult = (event) => {
        let interim = '';
        let final = '';

        for (let i = event.resultIndex; i < event.results.length; ++i) {
          if (event.results[i].isFinal) {
            final += event.results[i][0].transcript;
          } else {
            interim += event.results[i][0].transcript;
          }
        }

        if (final) {
          transcript.value = final;
        }
        interimTranscript.value = interim;
      };

      recognition.onerror = (event) => {
        isListening.value = false;
        if (event.error === 'not-allowed') {
          errorMessage.value = 'Microphone permission was denied. Please allow microphone access in your browser settings.';
        } else if (event.error === 'no-speech') {
          errorMessage.value = 'No speech detected. Please press the mic and speak clearly.';
        } else if (event.error === 'network') {
          errorMessage.value = 'Network error during speech recognition. Check your connection or use manual input.';
        } else {
          errorMessage.value = `Voice error: ${event.error}`;
        }
      };

      recognition.onend = () => {
        isListening.value = false;
        interimTranscript.value = '';
      };
    }
  }

  const startListening = () => {
    if (!recognition) {
      errorMessage.value = 'Speech recognition is not supported in this browser. Please use Chrome, Edge, or Safari.';
      return;
    }

    transcript.value = '';
    interimTranscript.value = '';
    errorMessage.value = null;
    recognition.lang = voiceLanguage.value;

    try {
      recognition.start();
    } catch (e) {
      console.warn('Speech recognition already started or failed', e);
    }
  };

  const stopListening = () => {
    if (recognition && isListening.value) {
      recognition.stop();
      isListening.value = false;
    }
  };

  const clearVoiceInput = () => {
    stopListening();
    transcript.value = '';
    interimTranscript.value = '';
    errorMessage.value = null;
  };

  // Convert Bengali digits & words to standard numbers
  const parseNumber = (text) => {
    if (!text) return null;
    let clean = text.trim();

    // Replace Bengali digits
    for (const [bn, en] of Object.entries(bnDigitMap)) {
      clean = clean.replaceAll(bn, en);
    }

    // Direct numeric regex
    const numMatch = clean.match(/(\d+(?:\.\d+)?)/);
    if (numMatch) {
      return parseFloat(numMatch[1]);
    }

    // Parse Bengali word numbers (e.g. "পাঁচ", "চল্লিশ", "দুই হাজার")
    let total = 0;
    let currentMultiplier = 0;
    const words = clean.split(/\s+/);

    for (let i = 0; i < words.length; i++) {
      const w = words[i];
      if (bnWordNumberMap[w] !== undefined) {
        const val = bnWordNumberMap[w];
        if (val === 1000 || val === 100000) {
          total += (currentMultiplier || 1) * val;
          currentMultiplier = 0;
        } else if (val >= 100) {
          total += val;
        } else {
          currentMultiplier = (currentMultiplier || 0) + val;
        }
      }
    }

    total += currentMultiplier;
    return total > 0 ? total : null;
  };

  /**
   * Intelligently parse raw spoken phrases into structured ERP transaction fields.
   */
  const parseCostSpeech = (spokenText) => {
    if (!spokenText) return null;
    const text = spokenText.toLowerCase();

    const result = {
      raw_text: spokenText,
      transaction_type: 'purchase',
      name: '',
      quantity: null,
      unit: 'kg',
      unit_price: null,
      amount: null,
      expense_category: 'misc',
      payment_method: 'cash',
      notes: spokenText,
    };

    // 1. Detect Payment Method
    if (text.includes('বিকাশ') || text.includes('bkash')) {
      result.payment_method = 'bkash';
    } else if (text.includes('নগদ') || text.includes('nagad')) {
      result.payment_method = 'nagad';
    } else if (text.includes('ব্যাংক') || text.includes('কার্ড') || text.includes('bank') || text.includes('card')) {
      result.payment_method = 'bank';
    } else {
      result.payment_method = 'cash';
    }

    // 2. Detect Transaction Intent
    if (text.includes('বেতন') || text.includes('salary')) {
      result.transaction_type = 'salary_payment';
      // Look for amount: e.g. "দুই হাজার টাকা"
      const amtMatch = text.match(/(?:বেতন বাবদ|বেতন)\s*([^\s]+(?:\s+[^\s]+)?)\s*টাকা/) || text.match(/([^\s]+(?:\s+[^\s]+)?)\s*টাকা/);
      if (amtMatch) {
        result.amount = parseNumber(amtMatch[1]);
      }
      return result;
    }

    if (text.includes('অগ্রিম') || text.includes('advance') || text.includes('দাদন')) {
      result.transaction_type = 'salary_advance';
      const amtMatch = text.match(/([^\s]+(?:\s+[^\s]+)?)\s*টাকা/);
      if (amtMatch) {
        result.amount = parseNumber(amtMatch[1]);
      }
      return result;
    }

    if (text.includes('বকেয়া') || text.includes('বকেয়া') || text.includes('due') || text.includes('ডিলার বিল')) {
      result.transaction_type = 'supplier_payment';
      const amtMatch = text.match(/([^\s]+(?:\s+[^\s]+)?)\s*টাকা/);
      if (amtMatch) {
        result.amount = parseNumber(amtMatch[1]);
      }
      return result;
    }

    if (text.includes('বিল') || text.includes('ভাড়া') || text.includes('ভাড়া') || text.includes('বিদ্যুৎ') || text.includes('গ্যাস') || text.includes('খরচ')) {
      result.transaction_type = 'expense';
      if (text.includes('বিদ্যুৎ') || text.includes('electric')) result.expense_category = 'electricity';
      else if (text.includes('গ্যাস') || text.includes('gas')) result.expense_category = 'gas';
      else if (text.includes('ভাড়া') || text.includes('ভাড়া') || text.includes('rent')) result.expense_category = 'rent';
      else if (text.includes('পরিচ্ছন্ন') || text.includes('cleaning')) result.expense_category = 'cleaning';
      else if (text.includes('গাড়ি') || text.includes('যাতায়াত') || text.includes('transport')) result.expense_category = 'transport';

      const amtMatch = text.match(/([^\s]+(?:\s+[^\s]+)?)\s*টাকা/);
      if (amtMatch) {
        result.amount = parseNumber(amtMatch[1]);
      }
      return result;
    }

    // 3. Purchase Intent (e.g. "পাঁচ কেজি শসা কিনেছি, প্রতি কেজি চল্লিশ টাকা")
    result.transaction_type = 'purchase';

    // Detect unit: কেজি, গ্রাম, লিটার, পিস, বস্তা, প্যাকেট
    if (text.includes('কেজি') || text.includes('kg') || text.includes('kilo')) {
      result.unit = 'kg';
    } else if (text.includes('গ্রাম') || text.includes('gram') || text.includes('gm')) {
      result.unit = 'g';
    } else if (text.includes('লিটার') || text.includes('liter') || text.includes('litre')) {
      result.unit = 'L';
    } else if (text.includes('পিস') || text.includes('টা') || text.includes('টি') || text.includes('pcs')) {
      result.unit = 'pcs';
    } else if (text.includes('বস্তা') || text.includes('sack')) {
      result.unit = 'sack';
    } else if (text.includes('প্যাকেট') || text.includes('packet')) {
      result.unit = 'box';
    }

    // Extract quantity: words before unit
    const qtyRegex = /(?:আজকে|মোট|প্রায়)?\s*([^\s]+(?:\s+[^\s]+)?)\s*(?:কেজি|গ্রাম|লিটার|পিস|টি|টা|বস্তা|প্যাকেট|kg|liter|pcs)/i;
    const qtyMatch = text.match(qtyRegex);
    if (qtyMatch) {
      result.quantity = parseNumber(qtyMatch[1]);
    }

    // Extract item name: text between unit and "কিনেছি" or "নিয়েছি", or before quantity
    const itemMatch = text.match(/(?:কেজি|গ্রাম|লিটার|পিস|টি|টা|বস্তা|প্যাকেট|kg|liter|pcs)\s+([^\s,]+)/i);
    if (itemMatch) {
      result.name = itemMatch[1].replace(/(?:কিনেছি|নিয়েছি|এনেছি|টাকা|দর)/g, '').trim();
    } else {
      const itemBeforeMatch = text.match(/(?:আজকে\s+)?([^\s,]+)\s+(?:পাঁচ|দুই|তিন|চার|\d+)/i);
      if (itemBeforeMatch) {
        result.name = itemBeforeMatch[1].trim();
      }
    }

    // Extract unit price: "প্রতি কেজি চল্লিশ টাকা" or "দর ৪০ টাকা"
    const priceRegex = /(?:প্রতি\s*(?:কেজি|গ্রাম|লিটার|পিস|টি|টা)?\s*|দর\s*|রেট\s*)([^\s]+(?:\s+[^\s]+)?)\s*টাকা/i;
    const priceMatch = text.match(priceRegex);
    if (priceMatch) {
      result.unit_price = parseNumber(priceMatch[1]);
    } else {
      // Fallback: look for second number followed by টাকা
      const allNumbers = [...text.matchAll(/([^\s]+(?:\s+[^\s]+)?)\s*টাকা/g)];
      if (allNumbers.length > 0) {
        const lastVal = parseNumber(allNumbers[allNumbers.length - 1][1]);
        if (lastVal) {
          if (result.quantity && !result.unit_price) {
            result.unit_price = lastVal;
          } else {
            result.amount = lastVal;
          }
        }
      }
    }

    if (result.quantity && result.unit_price) {
      result.amount = round(result.quantity * result.unit_price, 2);
    }

    return result;
  };

  const round = (val, dec = 2) => {
    return Number(Math.round(val + 'e' + dec) + 'e-' + dec);
  };

  return {
    isListening,
    transcript,
    interimTranscript,
    voiceLanguage,
    errorMessage,
    isSupported,
    startListening,
    stopListening,
    clearVoiceInput,
    parseCostSpeech,
  };
}

