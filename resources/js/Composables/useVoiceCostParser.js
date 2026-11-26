import { ref, computed } from 'vue';

// Bengali number mapping
const bnDigitMap = {
  '০': '0', '১': '1', '২': '2', '৩': '3', '৪': '4',
  '৫': '5', '৬': '6', '৭': '7', '৮': '8', '৯': '9',
};

const bnWordNumberMap = {
  'এক': 1, 'দেড়': 1.5, 'দেড়': 1.5, 'দুই': 2, 'আড়াই': 2.5, 'আড়াই': 2.5, 'তিন': 3, 'চার': 4, 'পাঁচ': 5,
  'ছয়': 6, 'ছয়': 6, 'সাত': 7, 'আট': 8, 'নয়': 9, 'নয়': 9, 'দশ': 10,
  'এগারো': 11, 'বারো': 12, 'তেরো': 13, 'চৌদ্দ': 14, 'পনেরো': 15, 'ষোল': 16, 'সতেরো': 17, 'আঠারো': 18, 'উনিশ': 19, 'বিশ': 20,
  'পঁচিশ': 25, 'ত্রিশ': 30, 'পঁয়ত্রিশ': 35, 'পঁয়ত্রিশ': 35, 'চল্লিশ': 40, 'পঁয়তাল্লিশ': 45, 'পঁয়তাল্লিশ': 45, 'পঞ্চাশ': 50,
  'ষাট': 60, 'সত্তর': 70, 'আশি': 80, 'নব্বই': 90,
  'একশো': 100, 'দুশো': 200, 'তিনশো': 300, 'চারশো': 400, 'পাঁচশো': 500, 'ছয়শো': 600, 'ছয়শো': 600, 'সাতশো': 700, 'আটশো': 800, 'নয়শো': 900, 'নয়শো': 900,
  'হাজার': 1000, 'লাখ': 100000, 'কোটি': 10000000,
};

const enWordNumberMap = {
  'zero': 0, 'one': 1, 'two': 2, 'three': 3, 'four': 4, 'five': 5, 'six': 6, 'seven': 7, 'eight': 8, 'nine': 9, 'ten': 10,
  'eleven': 11, 'twelve': 12, 'thirteen': 13, 'fourteen': 14, 'fifteen': 15, 'sixteen': 16, 'seventeen': 17, 'eighteen': 18, 'nineteen': 19,
  'twenty': 20, 'thirty': 30, 'forty': 40, 'fifty': 50, 'sixty': 60, 'seventy': 70, 'eighty': 80, 'ninety': 90,
  'hundred': 100, 'thousand': 1000, 'lakh': 100000, 'million': 1000000,
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

  const setLanguage = (lang) => {
    voiceLanguage.value = lang;
    if (recognition) {
      recognition.lang = lang;
    }
  };

  // Convert Bengali/English digits & words to standard numbers
  const parseNumber = (text) => {
    if (!text) return null;
    let clean = String(text).trim();

    // Replace Bengali digits
    for (const [bn, en] of Object.entries(bnDigitMap)) {
      clean = clean.replaceAll(bn, en);
    }

    // Direct numeric regex (including decimals)
    // Check for patterns like "25 হাজার" -> 25000, "2.5 লাখ" -> 250000
    const multiplierMatch = clean.match(/^(\d+(?:\.\d+)?)\s*(হাজার|লাখ|কোটি|thousand|lakh|million)$/i);
    if (multiplierMatch) {
      const base = parseFloat(multiplierMatch[1]);
      const unit = multiplierMatch[2].toLowerCase();
      if (unit === 'হাজার' || unit === 'thousand') return base * 1000;
      if (unit === 'লাখ' || unit === 'lakh') return base * 100000;
      if (unit === 'কোটি') return base * 10000000;
      if (unit === 'million') return base * 1000000;
    }

    const numMatch = clean.match(/(\d+(?:\.\d+)?)/);
    if (numMatch && !clean.match(/(হাজার|লাখ|কোটি|thousand|lakh|million)/i)) {
      return parseFloat(numMatch[1]);
    }

    // Parse Bengali & English word numbers
    let total = 0;
    let currentMultiplier = 0;
    const words = clean.split(/\s+/);

    for (let i = 0; i < words.length; i++) {
      const w = words[i].toLowerCase();

      // Check numeric word maps
      const val = bnWordNumberMap[w] !== undefined ? bnWordNumberMap[w] : enWordNumberMap[w];
      if (val !== undefined) {
        if (val === 1000 || val === 100000 || val === 10000000 || val === 1000000) {
          total += (currentMultiplier || 1) * val;
          currentMultiplier = 0;
        } else if (val >= 100) {
          total += (currentMultiplier || 1) * val;
          currentMultiplier = 0;
        } else {
          currentMultiplier = (currentMultiplier || 0) + val;
        }
      } else {
        // Check if word contains a digit
        const digitVal = parseFloat(w);
        if (!isNaN(digitVal)) {
          currentMultiplier = (currentMultiplier || 0) + digitVal;
        }
      }
    }

    total += currentMultiplier;
    return total > 0 ? total : null;
  };

  /**
   * Extract unit from spoken text
   */
  const extractUnit = (text) => {
    if (!text) return 'kg';
    const lower = text.toLowerCase();
    if (lower.includes('কেজি') || lower.includes('kg') || lower.includes('kilo')) return 'kg';
    if (lower.includes('গ্রাম') || lower.includes('gram') || lower.includes('gm')) return 'g';
    if (lower.includes('লিটার') || lower.includes('liter') || lower.includes('litre')) return 'L';
    if (lower.includes('মিলি') || lower.includes('ml')) return 'ml';
    if (lower.includes('পিস') || lower.includes('টা') || lower.includes('টি') || lower.includes('pcs') || lower.includes('piece')) return 'pcs';
    if (lower.includes('বস্তা') || lower.includes('sack') || lower.includes('bag')) return 'sack';
    if (lower.includes('প্যাকেট') || lower.includes('packet') || lower.includes('box') || lower.includes('বক্স')) return 'box';
    if (lower.includes('ডজন') || lower.includes('dozen')) return 'dozen';
    return 'kg';
  };

  /**
   * Intelligently parse raw spoken phrases into structured ERP transaction fields
   * tailored to the specific context type.
   */
  const parseCostSpeech = (spokenText, context = 'costs') => {
    if (!spokenText) return null;
    const text = spokenText.toLowerCase();

    // Default return template
    const result = {
      raw_text: spokenText,
      context: context,
      transaction_type: 'purchase',
      item_name: '',
      name: '',
      quantity: null,
      unit: 'kg',
      unit_price: null,
      amount: null,
      total_sales: null,
      cash_sales: null,
      bkash_sales: null,
      nagad_sales: null,
      card_sales: null,
      other_sales: null,
      shift: 'morning',
      attendance_status: 'present',
      expense_category: 'misc',
      payment_method: 'cash',
      reason: 'spoiled',
      counted_closing_cash: null,
      items: [],
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

    // -------------------------------------------------------------
    // CONTEXT: SALES (Daily Sales Entry)
    // -------------------------------------------------------------
    if (context === 'sales' || context === 'daily_sales') {
      result.transaction_type = 'sales';

      // Look for individual breakdown channels with boundary lookahead
      // Cash
      const cashMatch = text.match(/(?:ক্যাশ|নগদ ক্যাশ|cash)\s*([^,]+?)(?=(?:বিকাশ|নগদ|কার্ড|ব্যাংক|অন্যান্য|টাকা|$))/i);
      if (cashMatch) {
        result.cash_sales = parseNumber(cashMatch[1].trim());
      }

      // bKash
      const bkashMatch = text.match(/(?:বিকাশ|bkash)\s*([^,]+?)(?=(?:ক্যাশ|নগদ|কার্ড|ব্যাংক|অন্যান্য|টাকা|$))/i);
      if (bkashMatch) {
        result.bkash_sales = parseNumber(bkashMatch[1].trim());
      }

      // Nagad
      const nagadMatch = text.match(/(?:নগদ|nagad)\s*([^,]+?)(?=(?:ক্যাশ|বিকাশ|কার্ড|ব্যাংক|অন্যান্য|টাকা|$))/i);
      if (nagadMatch && !text.includes('নগদ ক্যাশ')) {
        result.nagad_sales = parseNumber(nagadMatch[1].trim());
      }

      // Card / Bank
      const cardMatch = text.match(/(?:কার্ড|ব্যাংক|card|bank)\s*([^,]+?)(?=(?:ক্যাশ|বিকাশ|নগদ|অন্যান্য|টাকা|$))/i);
      if (cardMatch) {
        result.card_sales = parseNumber(cardMatch[1].trim());
      }

      // Other
      const otherMatch = text.match(/(?:অন্যান্য|other)\s*([^,]+?)(?=(?:ক্যাশ|বিকাশ|নগদ|কার্ড|টাকা|$))/i);
      if (otherMatch) {
        result.other_sales = parseNumber(otherMatch[1].trim());
      }

      // Total Sales
      const totalMatch = text.match(/(?:মোট\s*বিক্রি|টোটাল\s*বিক্রি|মোট\s*সেলস|টোটাল|total\s*sales|total)\s*([^,]+?)(?=(?:ক্যাশ|বিকাশ|নগদ|কার্ড|ব্যাংক|অন্যান্য|টাকা|$))/i);
      if (totalMatch) {
        result.total_sales = parseNumber(totalMatch[1].trim());
      } else {
        // If single number mentioned like "আজকের বিক্রি ২৫০০০ টাকা" or "বিক্রি ২৫০০০"
        const singleMatch = text.match(/(?:বিক্রি|সেলস|sales)?\s*([^\s,]+(?:\s+[^\s,]+)?)\s*(?:টাকা)?$/i);
        if (singleMatch) {
          const val = parseNumber(singleMatch[1]);
          if (val) {
            result.total_sales = val;
            if (!result.cash_sales && !result.bkash_sales && !result.nagad_sales && !result.card_sales) {
              result.cash_sales = val;
            }
          }
        }
      }

      // Fallback calculation for total if individual components provided
      const sum = (result.cash_sales || 0) + (result.bkash_sales || 0) + (result.nagad_sales || 0) + (result.card_sales || 0) + (result.other_sales || 0);
      if (!result.total_sales && sum > 0) {
        result.total_sales = sum;
      }
      result.amount = result.total_sales || sum || null;
      return result;
    }

    // -------------------------------------------------------------
    // CONTEXT: CALCULATOR or CONSUMPTION (Material Usage / Profit)
    // -------------------------------------------------------------
    if (context === 'calculator' || context === 'consumption') {
      result.transaction_type = 'consumption';

      // Check if sales total is also mentioned
      const salesMatch = text.match(/(?:বিক্রি|সেলস|sales)\s*([^\s,]+(?:\s+[^\s,]+)?)\s*(?:টাকা)?/i);
      if (salesMatch) {
        result.total_sales = parseNumber(salesMatch[1]);
      }

      // Normalize digits in text for regex
      let normalizedText = text;
      for (const [bn, en] of Object.entries(bnDigitMap)) {
        normalizedText = normalizedText.replaceAll(bn, en);
      }

      const parsedItems = [];

      // 1. First try continuous regex: [item] [qty] [unit]
      const continuousRegex = /([^\d\s,]+)\s*(\d+(?:\.\d+)?)\s*(কেজি|গ্রাম|লিটার|পিস|টি|টা|বস্তা|প্যাকেট|kg|liter|pcs)/gi;
      let cMatch;
      while ((cMatch = continuousRegex.exec(normalizedText)) !== null) {
        const rawName = cMatch[1]
          .replace(/(?:আজকে|মোট|খরচ|লেগেছে|ব্যবহার|হয়েছে|হয়ে|কিনেছি|দর)/gi, '')
          .trim();
        if (rawName && !rawName.includes('বিক্রি') && !rawName.includes('সেলস')) {
          parsedItems.push({
            item_name: rawName,
            quantity: parseFloat(cMatch[2]),
            unit: extractUnit(cMatch[3]),
            notes: cMatch[0],
          });
        }
      }

      // 2. If continuous regex didn't find items, fallback to segment splitting
      if (parsedItems.length === 0) {
        const segments = text.split(/[,،\n]|এবং|ও|আর/);
        for (const seg of segments) {
          const s = seg.trim();
          if (!s || s.includes('বিক্রি') || s.includes('সেলস')) continue;

          const unit = extractUnit(s);
          const qtyMatch = s.match(/([^\s]+(?:\s+[^\s]+)?)\s*(?:কেজি|গ্রাম|লিটার|পিস|টি|টা|বস্তা|প্যাকেট|kg|liter|pcs)/i);
          const qty = qtyMatch ? parseNumber(qtyMatch[1]) : null;

          let itemName = s
            .replace(/(?:কেজি|গ্রাম|লিটার|পিস|টি|টা|বস্তা|প্যাকেট|kg|liter|pcs)/gi, '')
            .replace(/(\d+(?:\.\d+)?)/g, '')
            .replace(/(?:আজকে|মোট|খরচ|লেগেছে|ব্যবহার|হয়েছে|হয়ে)/gi, '')
            .trim();

          if (itemName && qty) {
            parsedItems.push({
              item_name: itemName,
              quantity: qty,
              unit: unit,
              notes: s,
            });
          }
        }
      }

      if (parsedItems.length > 0) {
        result.items = parsedItems;
        result.item_name = parsedItems[0].item_name;
        result.name = parsedItems[0].item_name;
        result.quantity = parsedItems[0].quantity;
        result.unit = parsedItems[0].unit;
      } else {
        // Fallback single item extraction
        result.unit = extractUnit(text);
        const qtyMatch = text.match(/([^\s]+(?:\s+[^\s]+)?)\s*(?:কেজি|গ্রাম|লিটার|পিস|টি|টা|বস্তা|প্যাকেট|kg|liter|pcs)/i);
        if (qtyMatch) result.quantity = parseNumber(qtyMatch[1]);
        result.item_name = text.split(/\s+/)[0];
        result.name = result.item_name;
      }

      return result;
    }

    // -------------------------------------------------------------
    // CONTEXT: ATTENDANCE & EMPLOYEES
    // -------------------------------------------------------------
    if (context === 'attendance' || context === 'employees') {
      result.transaction_type = 'attendance';

      // Detect shift
      if (/সকাল|মর্নিং|morning|day/i.test(text)) {
        result.shift = 'morning';
      } else if (/বিকাল|সন্ধ্যা|ইভনিং|রাত|নাইট|evening|night/i.test(text)) {
        result.shift = 'evening';
      }

      // Detect attendance status
      if (/অনুপস্থিত|এবসেন্ট|absent/i.test(text)) {
        result.attendance_status = 'absent';
      } else if (/ছুটি|leave/i.test(text)) {
        result.attendance_status = 'leave';
      } else if (/হাফ|অর্ধেক|half/i.test(text)) {
        result.attendance_status = 'half_day';
      } else {
        result.attendance_status = 'present';
      }

      // Extract worker name: words before shift or status
      const nameParts = text.replace(/(?:সকাল|মর্নিং|morning|day|বিকাল|সন্ধ্যা|ইভনিং|রাত|নাইট|evening|night|উপস্থিত|হাজির|present|অনুপস্থিত|এবসেন্ট|absent|ছুটি|leave|হাফ|অর্ধেক|half|শিফট|শিফটে)/gi, '').trim();
      if (nameParts) {
        result.item_name = nameParts.split(/\s+/)[0];
        result.name = result.item_name;
      }

      // Extract optional salary/advance amount if mentioned
      const amtMatch = text.match(/([^\s]+(?:\s+[^\s]+)?)\s*টাকা/);
      if (amtMatch) {
        result.amount = parseNumber(amtMatch[1]);
      }

      return result;
    }

    // -------------------------------------------------------------
    // CONTEXT: RECONCILIATION (Cash Drawer Count)
    // -------------------------------------------------------------
    if (context === 'reconciliation' || context === 'cash') {
      result.transaction_type = 'reconciliation';

      const cashMatch = text.match(/(?:ক্যাশ|ড্রয়ার|ড্রয়ার|কাউন্ট|গুনে|হাতে|closing|counted)?\s*([^\s]+(?:\s+[^\s]+)?)\s*টাকা/i)
        || text.match(/([^\s]+(?:\s+[^\s]+)?)\s*টাকা/i);
      if (cashMatch) {
        const val = parseNumber(cashMatch[1]);
        result.counted_closing_cash = val;
        result.amount = val;
      }

      return result;
    }

    // -------------------------------------------------------------
    // CONTEXT: WASTAGE & SPOILAGE
    // -------------------------------------------------------------
    if (context === 'wastage') {
      result.transaction_type = 'wastage';
      result.unit = extractUnit(text);

      // Detect reason
      if (/পচে|পচা|নষ্ট|গলে|গন্ধ|spoil/i.test(text)) {
        result.reason = 'spoiled';
      } else if (/মেয়াদ|মেয়াদে|ডেট|তারিখ|expire/i.test(text)) {
        result.reason = 'expired';
      } else if (/পোড়া|পোড়া|পুড়ে|পুড়েছে|burnt/i.test(text)) {
        result.reason = 'burnt';
      } else if (/ভাঙা|ভেঙে|নষ্ট|damage/i.test(text)) {
        result.reason = 'damaged';
      }

      // Quantity
      const qtyMatch = text.match(/([^\s]+(?:\s+[^\s]+)?)\s*(?:কেজি|গ্রাম|লিটার|পিস|টি|টা|বস্তা|প্যাকেট|kg|liter|pcs)/i);
      if (qtyMatch) {
        result.quantity = parseNumber(qtyMatch[1]);
      }

      // Item Name
      const cleaned = text
        .replace(/(?:নষ্ট|হয়েছে|হয়ে|গেছে|পচে|পচা|মেয়াদ|উত্তীর্ণ|পোড়া|ভেঙে|কেজি|গ্রাম|লিটার|পিস|টি|টা|kg|pcs)/gi, '')
        .replace(/(\d+(?:\.\d+)?)/g, '')
        .trim();
      if (cleaned) {
        result.item_name = cleaned.split(/\s+/)[0];
        result.name = result.item_name;
      }

      return result;
    }

    // -------------------------------------------------------------
    // CONTEXT: PURCHASES (Supplier Raw Material Purchases)
    // -------------------------------------------------------------
    if (context === 'purchases') {
      result.transaction_type = 'purchase';
      result.unit = extractUnit(text);

      // Quantity
      const qtyMatch = text.match(/(?:আজকে|মোট|প্রায়)?\s*([^\s]+(?:\s+[^\s]+)?)\s*(?:কেজি|গ্রাম|লিটার|পিস|টি|টা|বস্তা|প্যাকেট|kg|liter|pcs)/i);
      if (qtyMatch) {
        result.quantity = parseNumber(qtyMatch[1]);
      }

      // Rate / Unit price (দর ৪০ টাকা)
      const priceRegex = /(?:প্রতি\s*(?:কেজি|গ্রাম|লিটার|পিস|টি|টা)?\s*|দর\s*|রেট\s*)([^\s]+(?:\s+[^\s]+)?)\s*টাকা/i;
      const priceMatch = text.match(priceRegex);
      if (priceMatch) {
        result.unit_price = parseNumber(priceMatch[1]);
      }

      // Total or fallback amount
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

      if (result.quantity && result.unit_price && !result.amount) {
        result.amount = Number((result.quantity * result.unit_price).toFixed(2));
      }

      // Item Name
      const itemMatch = text.match(/(?:কেজি|গ্রাম|লিটার|পিস|টি|টা|বস্তা|প্যাকেট|kg|liter|pcs)\s+([^\s,]+)/i);
      if (itemMatch) {
        result.item_name = itemMatch[1].replace(/(?:কিনেছি|নিয়েছি|এনেছি|টাকা|দর)/g, '').trim();
      } else {
        const itemBeforeMatch = text.match(/(?:থেকে\s+)?([^\s,]+)\s+(?:পাঁচ|দুই|তিন|চার|\d+)/i);
        if (itemBeforeMatch) {
          result.item_name = itemBeforeMatch[1].trim();
        }
      }
      result.name = result.item_name;

      return result;
    }

    // -------------------------------------------------------------
    // CONTEXT: EXPENSES (Operating Expenses)
    // -------------------------------------------------------------
    if (context === 'expenses') {
      result.transaction_type = 'expense';

      if (text.includes('বিদ্যুৎ') || text.includes('কারেন্ট') || text.includes('electric')) result.expense_category = 'electricity';
      else if (text.includes('গ্যাস') || text.includes('gas')) result.expense_category = 'gas';
      else if (text.includes('ভাড়া') || text.includes('ভাড়া') || text.includes('rent')) result.expense_category = 'rent';
      else if (text.includes('পরিচ্ছন্ন') || text.includes('ঝাড়ু') || text.includes('cleaning')) result.expense_category = 'cleaning';
      else if (text.includes('গাড়ি') || text.includes('যাতায়াত') || text.includes('ভাড়া') || text.includes('transport')) result.expense_category = 'transport';
      else if (text.includes('মেরামত') || text.includes('repair')) result.expense_category = 'repairs';
      else if (text.includes('ইন্টারনেট') || text.includes('ওয়াইফাই') || text.includes('internet')) result.expense_category = 'internet';

      const amtMatch = text.match(/([^\s]+(?:\s+[^\s]+)?)\s*টাকা/);
      if (amtMatch) {
        result.amount = parseNumber(amtMatch[1]);
      }

      // Payee detection
      const payeeMatch = text.match(/(?:বাবদ|বিল|খরচ)\s*([^\s,]+(?:\s+[^\s,]+)?)/i);
      if (payeeMatch) {
        result.item_name = payeeMatch[1].trim();
      }

      return result;
    }

    // -------------------------------------------------------------
    // CONTEXT: GENERAL COSTS (7-in-1 Daily Costs)
    // -------------------------------------------------------------
    // 1. Salary Payment
    if (text.includes('বেতন') || text.includes('salary')) {
      result.transaction_type = 'salary_payment';
      const amtMatch = text.match(/(?:বেতন বাবদ|বেতন)\s*([^\s]+(?:\s+[^\s]+)?)\s*টাকা/) || text.match(/([^\s]+(?:\s+[^\s]+)?)\s*টাকা/);
      if (amtMatch) {
        result.amount = parseNumber(amtMatch[1]);
      }
      const nameMatch = text.match(/([^\s,]+)(?:\s*কে|\s*এর)?\s*(?:বেতন|salary)/i);
      if (nameMatch) {
        result.item_name = nameMatch[1].trim();
        result.name = result.item_name;
      }
      return result;
    }

    // 2. Salary Advance
    if (text.includes('অগ্রিম') || text.includes('advance') || text.includes('দাদন')) {
      result.transaction_type = 'salary_advance';
      const amtMatch = text.match(/([^\s]+(?:\s+[^\s]+)?)\s*টাকা/);
      if (amtMatch) {
        result.amount = parseNumber(amtMatch[1]);
      }
      const nameMatch = text.match(/([^\s,]+)(?:\s*কে)?\s*(?:অগ্রিম|advance|দাদন)/i);
      if (nameMatch) {
        result.item_name = nameMatch[1].trim();
        result.name = result.item_name;
      }
      return result;
    }

    // 3. Supplier Payment
    if (text.includes('বকেয়া') || text.includes('বকেয়া') || text.includes('due') || text.includes('ডিলার বিল') || text.includes('মহাজন')) {
      result.transaction_type = 'supplier_payment';
      const amtMatch = text.match(/([^\s]+(?:\s+[^\s]+)?)\s*টাকা/);
      if (amtMatch) {
        result.amount = parseNumber(amtMatch[1]);
      }
      const supMatch = text.match(/([^\s,]+)(?:\s*কে|\s*এর)?\s*(?:বকেয়া|বকেয়া|বিল)/i);
      if (supMatch) {
        result.item_name = supMatch[1].trim();
        result.name = result.item_name;
      }
      return result;
    }

    // 4. Operating Expense
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

    // 5. Default: Raw Material Purchase
    result.transaction_type = 'purchase';
    result.unit = extractUnit(text);

    // Quantity
    const qtyRegex = /(?:আজকে|মোট|প্রায়)?\s*([^\s]+(?:\s+[^\s]+)?)\s*(?:কেজি|গ্রাম|লিটার|পিস|টি|টা|বস্তা|প্যাকেট|kg|liter|pcs)/i;
    const qtyMatch = text.match(qtyRegex);
    if (qtyMatch) {
      result.quantity = parseNumber(qtyMatch[1]);
    }

    // Item Name
    const itemMatch = text.match(/(?:কেজি|গ্রাম|লিটার|পিস|টি|টা|বস্তা|প্যাকেট|kg|liter|pcs)\s+([^\s,]+)/i);
    if (itemMatch) {
      result.item_name = itemMatch[1].replace(/(?:কিনেছি|নিয়েছি|এনেছি|টাকা|দর)/g, '').trim();
    } else {
      const itemBeforeMatch = text.match(/(?:আজকে\s+)?([^\s,]+)\s+(?:পাঁচ|দুই|তিন|চার|\d+)/i);
      if (itemBeforeMatch) {
        result.item_name = itemBeforeMatch[1].trim();
      }
    }
    result.name = result.item_name;

    // Unit Price / Amount
    const priceRegex = /(?:প্রতি\s*(?:কেজি|গ্রাম|লিটার|পিস|টি|টা)?\s*|দর\s*|রেট\s*)([^\s]+(?:\s+[^\s]+)?)\s*টাকা/i;
    const priceMatch = text.match(priceRegex);
    if (priceMatch) {
      result.unit_price = parseNumber(priceMatch[1]);
    } else {
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
      result.amount = Number((result.quantity * result.unit_price).toFixed(2));
    }

    return result;
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
    resetTranscript: clearVoiceInput,
    setLanguage,
    parseCostSpeech,
    parseSpokenCost: parseCostSpeech,
    parseNumber,
  };
}
